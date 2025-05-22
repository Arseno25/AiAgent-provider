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
   * Create a new Gemini adapter instance.
   *
   * Validates that an 'api_key' is provided in the configuration.
   * Allows overriding `apiBaseUrl` via configuration.
   *
   * @param array $config The configuration array for this adapter.
   * @throws \InvalidArgumentException If the 'api_key' is not found in the config.
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
   * {@inheritdoc}
   * Prepares Guzzle request options specific to the Gemini API.
   *
   * This includes setting the 'Content-Type' header and adding the API key
   * as a query parameter to the request URL.
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
   * Generate content with a Gemini model.
   *
   * @param string $prompt The prompt to send to the model.
   * @param array $options Additional options for the request (e.g., 'model', 'temperature', 'maxOutputTokens').
   * @return string The generated text content.
   * @throws ApiException If the API request fails.
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

  /**
   * Generate chat completion with a Gemini model.
   *
   * @param array $messages An array of message objects, formatted for Gemini.
   * @param array $options Additional options for the request (e.g., 'model', 'temperature').
   * @return array The chat completion response, including the message, usage, and ID.
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
   * Generate embeddings for a text or array of texts with a Gemini embedding model.
   *
   * Uses the `batchEmbedContents` endpoint for efficiency.
   *
   * @param string|array $input The text string or an array of text strings to embed.
   * @param array $options Additional options, primarily 'model' to specify the embedding model.
   * @return array An array of embedding objects, each containing the embedding vector, index, and object type.
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
   * Format an array of messages into the 'contents' structure required by the Gemini API.
   *
   * Gemini API expects a specific format for conversational history, where roles are
   * 'user' and 'model' (for assistant). System prompts are typically prepended
   * to the first user message's content.
   *
   * @param array $messages The array of message objects to format.
   *                        Standard roles ('system', 'user', 'assistant') are mapped.
   * @return array The formatted 'contents' array suitable for the Gemini API.
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
