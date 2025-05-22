<?php

namespace AiAgent\Adapters;

use Illuminate\Support\Arr;
use AiAgent\Exceptions\ApiException; // For @throws tag

/**
 * Adapter for interacting with the OpenAI API.
 *
 * This class implements the AiProviderInterface and provides methods
 * for text generation, chat completions, and embeddings using OpenAI models.
 */
class OpenAiAdapter extends BaseAdapter
{
  /**
   * The base URL for the OpenAI API.
   * This can be overridden by the 'api_base_url' in the adapter's configuration.
   * @var string
   */
  protected $apiBaseUrl = 'https://api.openai.com/v1';

  /**
   * Create a new OpenAI adapter instance.
   *
   * Validates that an 'api_key' is provided in the configuration.
   * Allows overriding the `apiBaseUrl` via configuration.
   *
   * @param array $config The configuration array for this adapter.
   * @throws \InvalidArgumentException If the 'api_key' is not found in the config.
   */
  public function __construct(array $config)
  {
    parent::__construct($config);
    // Ensure that the API key is provided in the configuration.
    $this->validateConfig(['api_key']);

    // Allow overriding the default API base URL via configuration.
    // Useful for testing or using proxy/custom OpenAI-compatible endpoints.
    if (isset($config['api_base_url'])) {
      $this->apiBaseUrl = $config['api_base_url'];
    }
  }

  /**
   * {@inheritdoc}
   * Prepares Guzzle request options specific to the OpenAI API.
   *
   * This includes setting the Authorization header with the API key
   * and the Content-Type header for JSON payloads.
   */
  protected function getRequestOptions(string $method, string $endpoint, array $data = [], array $customHeaders = []): array
  {
    // Standard headers for OpenAI API calls.
    $defaultHeaders = [
      'Authorization' => 'Bearer ' . $this->getConfig('api_key'),
      'Content-Type' => 'application/json',
    ];

    $headers = array_merge($defaultHeaders, $customHeaders);

    $options = [
      'headers' => $headers,
    ];

    // Add JSON payload if data is provided (typically for POST requests).
    if (!empty($data)) {
      $options['json'] = $data;
    }

    return $options;
  }

  /**
   * Generate content with OpenAI using the chat completions endpoint.
   *
   * This method adapts a single prompt to the chat completions message format.
   *
   * @param string $prompt The prompt to send to the AI.
   * @param array $options Additional options for the request (e.g., 'model', 'max_tokens', 'temperature').
   * @return string The generated text content.
   * @throws ApiException If the API request fails.
   */
  public function generate(string $prompt, array $options = []): string
  {
    // Determine model, max_tokens, and temperature from options or configuration defaults.
    $model = $options['model'] ?? $this->getConfig('model', 'gpt-4o'); // Default to gpt-4o if not specified
    $maxTokens = $options['max_tokens'] ?? $this->getConfig('max_tokens', 500);
    $temperature = $options['temperature'] ?? $this->getConfig('temperature', 0.7);

    // Construct the payload for the chat/completions endpoint.
    $payload = [
      'model' => $model,
      'messages' => [
        ['role' => 'user', 'content' => $prompt] // Convert single prompt to message format
      ],
      'max_tokens' => $maxTokens,
      'temperature' => $temperature,
      // Note: Additional OpenAI-specific options from $options could be merged here
      // e.g., 'top_p', 'frequency_penalty', 'presence_penalty', 'stop', etc.
      // Example: array_merge($payload, Arr::only($options, ['top_p', 'stop']));
    ];

    // Make the API request to the chat/completions endpoint.
    $response = $this->makeRequest('POST', 'chat/completions', $payload);

    // Extract and return the content from the first choice's message.
    return $response['choices'][0]['message']['content'] ?? '';
  }

  /**
   * Generate chat completion with OpenAI.
   *
   * @param array $messages An array of message objects (e.g., [['role' => 'user', 'content' => 'Hi']]).
   * @param array $options Additional options for the request (e.g., 'model', 'max_tokens').
   * @return array The chat completion response, including the message, usage statistics, and ID.
   * @throws ApiException If the API request fails.
   */
  public function chat(array $messages, array $options = []): array
  {
    $model = $options['model'] ?? $this->getConfig('model', 'gpt-4o');
    $temperature = $options['temperature'] ?? $this->getConfig('temperature', 0.7);
    $maxTokens = $options['max_tokens'] ?? $this->getConfig('max_tokens', 1000);

    $payload = [
      'model' => $model,
      'messages' => $messages,
      'temperature' => $temperature,
      'max_tokens' => $maxTokens,
      // Example: array_merge($payload, Arr::only($options, ['top_p', 'stream', 'tools', 'tool_choice']));
    ];

    $response = $this->makeRequest('POST', 'chat/completions', $payload);

    // Standardized response structure.
    return [
      'message' => $response['choices'][0]['message'] ?? [], // The assistant's message object
      'usage' => $response['usage'] ?? [], // Token usage information
      'id' => $response['id'] ?? null, // The response ID
    ];
  }

  /**
   * Generate embeddings for a text or array of texts with OpenAI.
   *
   * @param string|array $input The text or array of texts to embed.
   * @param array $options Additional options for the request, primarily 'model'.
   * @return array An array of embedding objects, each containing the embedding vector, index, and object type.
   * @throws ApiException If the API request fails.
   */
  public function embeddings($input, array $options = []): array
  {
    // Use specific embedding model or fallback to configured default.
    $model = $options['model'] ?? $this->getConfig('embedding_model', 'text-embedding-3-small');

    $payload = [
      'model' => $model,
      'input' => $input, // Input can be a string or an array of strings for batch embedding.
      // Example: array_merge($payload, Arr::only($options, ['encoding_format', 'dimensions']));
    ];

    $response = $this->makeRequest('POST', 'embeddings', $payload);

    // The response from OpenAI contains a 'data' array, where each element is an embedding object.
    // This structure is already compatible with the expected return if $response['data'] is an array of embeddings.
    return $response['data'] ?? [];
  }
}
