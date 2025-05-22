<?php

namespace AiAgent\Adapters;

use AiAgent\Exceptions\ApiException; // For @throws tag

/**
 * Adapter for interacting with the Google Gemini API.
 *
 * This class implements the AiProviderInterface and provides methods
 * for text generation, chat completions, and embeddings using Gemini models.
 */
class GeminiAdapter extends BaseAdapter
{
  /**
   * The base URL for the Google Gemini API.
   * Can be overridden by 'api_base_url' in the adapter's configuration.
   * @var string
   */
  protected $apiBaseUrl = 'https://generativelanguage.googleapis.com/v1';

  /**
   * Initializes the Gemini adapter with the provided configuration.
   *
   * Requires an 'api_key' in the configuration array and optionally allows overriding the default API base URL.
   *
   * @param array $config Adapter configuration, must include 'api_key'.
   * @throws \InvalidArgumentException If 'api_key' is missing from the configuration.
   */
  public function __construct(array $config)
  {
    parent::__construct($config);
    // Ensure API key is present.
    $this->validateConfig(['api_key']);

    // Allow overriding the API base URL if specified in config.
    if (isset($config['api_base_url'])) {
      $this->apiBaseUrl = $config['api_base_url'];
    }
  }

  /**
   * Builds HTTP request options for Gemini API calls, including headers and API key query parameter.
   *
   * Merges default and custom headers, sets the 'Content-Type' to 'application/json', and attaches the API key as a query parameter. Includes a JSON payload if data is provided.
   *
   * @param string $method HTTP method (e.g., 'POST', 'GET').
   * @param string $endpoint API endpoint path.
   * @param array $data Optional request payload.
   * @param array $customHeaders Optional additional headers.
   * @return array Prepared options array for Guzzle HTTP requests.
   */
  protected function getRequestOptions(string $method, string $endpoint, array $data = [], array $customHeaders = []): array
  {
    // Standard headers for Gemini API calls.
    $defaultHeaders = [
      'Content-Type' => 'application/json',
    ];
    
    $headers = array_merge($defaultHeaders, $customHeaders);

    $options = [
      'headers' => $headers,
      // Gemini API expects the API key as a query parameter.
      'query' => ['key' => $this->getConfig('api_key')],
    ];

    // Add JSON payload if data is provided (typically for POST requests).
    if (!empty($data)) {
      $options['json'] = $data;
    }
    return $options;
  }

  /**
   * Generates text using a Gemini model based on the provided prompt.
   *
   * Sends the prompt to the specified Gemini model and returns the generated text from the first candidate response.
   * Model parameters such as temperature and maximum output tokens can be customized via the $options array or configuration defaults.
   *
   * @param string $prompt The input prompt for text generation.
   * @param array $options Optional settings including 'model', 'temperature', and 'max_tokens'.
   * @return string The generated text, or an empty string if no output is available.
   * @throws ApiException If the Gemini API request fails.
   */
  public function generate(string $prompt, array $options = []): string
  {
    // Determine model, temperature, and maxOutputTokens from options or configuration defaults.
    $model = $options['model'] ?? $this->getConfig('model', 'gemini-1.5-pro');
    $temperature = $options['temperature'] ?? $this->getConfig('temperature', 0.7);
    $maxTokens = $options['max_tokens'] ?? $this->getConfig('max_tokens', 1000); // Maps to 'maxOutputTokens' for Gemini

    // Construct the payload for Gemini's generateContent endpoint.
    $payload = [
      'contents' => [ // Gemini uses 'contents' array for prompts
        [
          'parts' => [
            ['text' => $prompt]
          ]
        ]
      ],
      'generationConfig' => [ // Configuration for the generation process
        'temperature' => $temperature,
        'maxOutputTokens' => $maxTokens,
        // Note: Other Gemini-specific generationConfig options can be added here from $options
        // e.g., 'topP', 'topK', 'candidateCount', 'stopSequences'
      ],
    ];
    
    $endpoint = "models/{$model}:generateContent";
    $response = $this->makeRequest('POST', $endpoint, $payload);

    // Extract and return the text from the first candidate's content.
    return $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
  }

  /****
   * Generates a chat completion using a Gemini model based on the provided messages.
   *
   * Formats input messages for the Gemini API, sends a request to generate a chat response, and returns the assistant's reply along with usage statistics and a candidate ID.
   *
   * @param array $messages Chat messages to be processed, using standard roles.
   * @param array $options Optional parameters such as 'model', 'temperature', and 'max_tokens'.
   * @return array An array containing the assistant's message, usage metadata, and a candidate ID.
   * @throws ApiException If the API request fails.
   */
  public function chat(array $messages, array $options = []): array
  {
    $model = $options['model'] ?? $this->getConfig('model', 'gemini-1.5-pro');
    $temperature = $options['temperature'] ?? $this->getConfig('temperature', 0.7);
    $maxTokens = $options['max_tokens'] ?? $this->getConfig('max_tokens', 1000); // Maps to 'maxOutputTokens'

    // Format messages into Gemini's 'contents' structure.
    $contents = $this->formatMessages($messages);

    $payload = [
      'contents' => $contents,
      'generationConfig' => [
        'temperature' => $temperature,
        'maxOutputTokens' => $maxTokens,
      ],
    ];

    $endpoint = "models/{$model}:generateContent"; // Same endpoint for chat-like interactions
    $response = $this->makeRequest('POST', $endpoint, $payload);
    
    // Extract the response content.
    $content = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';

    // Standardized response structure.
    return [
      'message' => [
        'role' => 'assistant', // Gemini API uses 'model' for assistant, mapping to 'assistant'
        'content' => $content,
      ],
      'usage' => [ // Map Gemini's usageMetadata to standard usage fields
        'prompt_tokens' => $response['usageMetadata']['promptTokenCount'] ?? 0,
        'completion_tokens' => $response['usageMetadata']['candidatesTokenCount'] ?? 0, // Sum of tokens for all candidates
        'total_tokens' => ($response['usageMetadata']['promptTokenCount'] ?? 0) +
                          ($response['usageMetadata']['candidatesTokenCount'] ?? 0),
      ],
      // Gemini's response structure might not have a direct 'id' equivalent to OpenAI's response ID.
      // Using the index of the candidate as a placeholder or a generated hash if needed.
      'id' => $response['candidates'][0]['index'] ?? null, 
    ];
  }

  /**
   * Generates embeddings for one or more texts using a Gemini embedding model.
   *
   * Sends a batch request to the Gemini API's `batchEmbedContents` endpoint and returns an array of embedding objects, each containing the embedding vector, input index, and object type.
   *
   * @param string|array $input The text or array of texts to embed.
   * @param array $options Optional settings, such as 'model' to specify the embedding model.
   * @return array Array of embedding objects with keys: 'embedding', 'index', and 'object'.
   * @throws ApiException If the API request fails.
   */
  public function embeddings($input, array $options = []): array
  {
    // Determine the embedding model name.
    $modelName = $options['model'] ?? $this->getConfig('embedding_model', 'embedding-001');
    // Ensure input is an array of texts.
    $texts = is_array($input) ? $input : [$input];
    
    // Return early if there are no texts to embed.
    if (empty($texts)) {
        return [];
    }

    $requests = [];
    // Gemini requires the model path (e.g., "models/embedding-001") in each part of a batch request.
    $modelPath = "models/{$modelName}"; 

    // Prepare individual requests for the batch.
    foreach ($texts as $text) {
      $requests[] = [
        'model' => $modelPath, // Model path for this specific text embedding request
        'content' => [ // Gemini's structure for content
          'parts' => [['text' => $text]]
        ]
      ];
    }

    // Construct the payload for the batch embedding endpoint.
    $payload = ['requests' => $requests];
    $endpoint = "models/{$modelName}:batchEmbedContents"; // Target the batch embedding endpoint.

    $response = $this->makeRequest('POST', $endpoint, $payload);

    $resultEmbeddings = [];
    // Process the response, which contains an array of embeddings.
    if (isset($response['embeddings']) && is_array($response['embeddings'])) {
      foreach ($response['embeddings'] as $index => $embeddingData) {
        $resultEmbeddings[] = [
          'embedding' => $embeddingData['values'] ?? [], // The embedding vector
          'index' => $index, // Index corresponding to the order of input texts
          'object' => 'embedding', // Standardized object type
        ];
      }
    }
    return $resultEmbeddings;
  }

  /**
   * Converts an array of chat messages into the Gemini API's required 'contents' format.
   *
   * Maps standard roles ('system', 'user', 'assistant') to Gemini roles, concatenates all system prompts, and prepends them to the first user message. Skips malformed messages.
   *
   * @param array $messages Chat messages with roles and content.
   * @return array Formatted contents array for Gemini API requests.
   */
  protected function formatMessages(array $messages): array
  {
    $formattedContents = [];
    $systemPromptText = null;
    $userMessages = []; // To hold non-system messages for processing

    // First pass: extract system prompt and separate user/assistant messages.
    // Multiple system prompts are concatenated.
    foreach ($messages as $message) {
        if ($message['role'] === 'system' && isset($message['content'])) {
            $systemPromptText = ($systemPromptText ? $systemPromptText . "\n" : '') . $message['content'];
        } else {
            $userMessages[] = $message; // Collect user and assistant messages
        }
    }
    
    $isFirstUserMessage = true;
    // Second pass: format user and assistant messages, prepending system prompt if available.
    foreach ($userMessages as $message) {
        if (!isset($message['role']) || !isset($message['content'])) {
            continue; // Skip malformed messages
        }

        // Map 'assistant' role to 'model' for Gemini API. 'user' role remains 'user'.
        $role = ($message['role'] === 'assistant') ? 'model' : 'user';
        $text = $message['content'];

        // Prepend the accumulated system prompt to the content of the first user message.
        if ($role === 'user' && $isFirstUserMessage && !empty($systemPromptText)) {
            $text = $systemPromptText . "\n\n" . $text; // Add separation for clarity
            $isFirstUserMessage = false; // Ensure system prompt is added only once
        }
        
        $formattedContents[] = [
            'role' => $role,
            'parts' => [['text' => $text]] // Gemini's structure for message parts
        ];
    }
    return $formattedContents;
  }
}
