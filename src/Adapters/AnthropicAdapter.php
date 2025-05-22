<?php

namespace AiAgent\Adapters;

use AiAgent\Exceptions\ApiException; // For @throws tag

/**
 * Adapter for interacting with the Anthropic API (Claude models).
 *
 * This class implements the AiProviderInterface and provides methods for
 * text generation (via chat completions) and chat functionalities using Anthropic models.
 * Note: Anthropic does not currently offer a dedicated embeddings endpoint through this API version.
 */
class AnthropicAdapter extends BaseAdapter
{
  /**
   * The base URL for the Anthropic API.
   * Can be overridden by 'api_base_url' in the adapter's configuration.
   * @var string
   */
  protected $apiBaseUrl = 'https://api.anthropic.com/v1';

  /**
   * Default Anthropic API version header value.
   * Can be overridden by 'anthropic_version' in the adapter's configuration.
   * @var string
   */
  protected const DEFAULT_ANTHROPIC_VERSION = '2023-06-01';

  /**
   * Create a new Anthropic adapter instance.
   *
   * Validates that an 'api_key' is provided in the configuration.
   * Allows overriding `apiBaseUrl` and `anthropic_version` via configuration.
   *
   * @param array $config The configuration array for this adapter.
   * @throws \InvalidArgumentException If the 'api_key' is not found in the config.
   */
  public function __construct(array $config)
  {
    parent::__construct($config);
    // Ensure API key is present in the configuration.
    $this->validateConfig(['api_key']);

    // Allow overriding the API base URL if specified in config.
    if (isset($config['api_base_url'])) {
      $this->apiBaseUrl = $config['api_base_url'];
    }
  }

  /**
   * {@inheritdoc}
   * Prepares Guzzle request options specific to the Anthropic API.
   *
   * This includes setting the 'x-api-key', 'anthropic-version',
   * and 'Content-Type' headers.
   */
  protected function getRequestOptions(string $method, string $endpoint, array $data = [], array $customHeaders = []): array
  {
    // Use configured Anthropic API version or the default.
    $anthropicVersion = $this->getConfig('anthropic_version', self::DEFAULT_ANTHROPIC_VERSION);

    // Standard headers for Anthropic API calls.
    $defaultHeaders = [
      'x-api-key' => $this->getConfig('api_key'),
      'anthropic-version' => $anthropicVersion,
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
   * Generate content with Anthropic Claude.
   *
   * This method serves as a simplified interface for text generation,
   * internally utilizing the `chat` method by formatting the prompt as a user message.
   *
   * @param string $prompt The prompt to send to Claude.
   * @param array $options Additional options for the request (passed to the `chat` method).
   * @return string The generated text content from Claude's response.
   * @throws ApiException If the API request fails.
   */
  public function generate(string $prompt, array $options = []): string
  {
    // Construct a simple messages array with the user's prompt.
    $messages = [
      [
        'role' => 'user',
        'content' => $prompt
      ]
    ];
    // Delegate to the chat method for actual API interaction.
    $response = $this->chat($messages, $options);

    // Extract and return the content from the assistant's message.
    return $response['message']['content'] ?? '';
  }

  /**
   * Generate chat completion with Anthropic Claude.
   *
   * Handles system prompts by extracting them from messages or options,
   * then making a request to the 'messages' endpoint.
   *
   * @param array $messages An array of message objects.
   * @param array $options Additional options for the request (e.g., 'model', 'max_tokens', 'system').
   * @return array The chat completion response, including the message, usage, and ID.
   * @throws ApiException If the API request fails.
   */
  public function chat(array $messages, array $options = []): array
  {
    // Determine model, max_tokens, and temperature from options or configuration defaults.
    $model = $options['model'] ?? $this->getConfig('model', 'claude-3-opus-20240229');
    $maxTokens = $options['max_tokens'] ?? $this->getConfig('max_tokens', 1000);
    $temperature = $options['temperature'] ?? $this->getConfig('temperature', 0.7);

    $systemPromptFromMessages = null;
    $nonSystemMessages = [];
    // Separate system prompt from other messages. Only the first system prompt is used.
    foreach ($messages as $message) {
        if (isset($message['role']) && $message['role'] === 'system' && isset($message['content'])) {
            if ($systemPromptFromMessages === null) { // Capture only the first system message found in the array
                $systemPromptFromMessages = $message['content'];
            }
            // System messages are not passed to formatMessages, they are handled by the 'system' parameter in payload
        } else {
            $nonSystemMessages[] = $message; // Collect non-system messages
        }
    }

    // Determine the final system prompt based on precedence:
    // 1. Default from adapter configuration ('system_prompt').
    // 2. Overridden by the first system message found in the $messages array.
    // 3. Overridden by 'system' key in $options (highest precedence).
    $finalSystemPrompt = $this->getConfig('system_prompt'); // Fallback to config

    if (!empty($systemPromptFromMessages)) {
        $finalSystemPrompt = $systemPromptFromMessages; // Message array overrides config
    }

    if (isset($options['system']) && !empty($options['system'])) {
        $finalSystemPrompt = $options['system']; // Explicit option takes highest precedence
    }

    // Format the remaining messages (user, assistant) for the Anthropic API.
    $anthropicMessages = $this->formatMessages($nonSystemMessages);

    // Construct the main payload for the API request.
    $payload = [
      'model' => $model,
      'messages' => $anthropicMessages,
      'max_tokens' => $maxTokens,
      'temperature' => $temperature,
      // Note: Additional Anthropic-specific options from $options could be merged here
      // e.g., 'top_k', 'top_p', 'stream', etc.
    ];

    // Add the system prompt to the payload if one was determined.
    if (!empty($finalSystemPrompt)) {
      $payload['system'] = $finalSystemPrompt;
    }

    $response = $this->makeRequest('POST', 'messages', $payload);

    // Standardize the response structure.
    return [
      'message' => [
        // Anthropic response has content array; assuming first content block is the primary text.
        'role' => $response['content'][0]['type'] === 'text' ? 'assistant' : 'tool_use', // Or map more specifically
        'content' => $response['content'][0]['text'] ?? '',
      ],
      'usage' => [
        'prompt_tokens' => $response['usage']['input_tokens'] ?? 0,
        'completion_tokens' => $response['usage']['output_tokens'] ?? 0,
        'total_tokens' => ($response['usage']['input_tokens'] ?? 0) + ($response['usage']['output_tokens'] ?? 0),
      ],
      'id' => $response['id'] ?? null, // Anthropic response ID
    ];
  }

  /**
   * Generate embeddings for a text with Anthropic.
   *
   * Note: Anthropic's current Messages API (as of `DEFAULT_ANTHROPIC_VERSION`)
   * does not directly support a dedicated embeddings endpoint like OpenAI.
   * This method throws a RuntimeException to indicate this incompatibility.
   *
   * @param string|array $input The text or array of texts to embed.
   * @param array $options Additional options (not currently used).
   * @return array
   * @throws \RuntimeException Always, as this feature is not supported.
   */
  public function embeddings($input, array $options = []): array
  {
    throw new \RuntimeException('Anthropic does not natively support a dedicated embeddings endpoint via this API version. Please use a different provider or method for embeddings.');
  }

  /**
   * Format an array of messages into the structure required by the Anthropic API.
   *
   * This method filters out system messages (as they are handled separately)
   * and ensures that only 'user' and 'assistant' roles are included.
   *
   * @param array $messages The array of message objects to format.
   *                        Each message should have 'role' and 'content' keys.
   * @return array The formatted messages suitable for the Anthropic API.
   */
  protected function formatMessages(array $messages): array
  {
    $result = [];
    foreach ($messages as $message) {
      // Skip malformed messages.
      if (!isset($message['role']) || !isset($message['content'])) {
        continue;
      }

      $role = $message['role'];
      // System messages are handled at a higher level (in the `chat` method via the 'system' payload key).
      // This function should only receive user/assistant messages.
      if ($role === 'system') {
        continue; 
      }

      // Anthropic API expects 'user' or 'assistant' roles in the messages array.
      // Silently skip messages with other roles to prevent API errors.
      if (!in_array($role, ['user', 'assistant'])) {
        // Optionally, log a warning here about unsupported roles if strict checking is needed.
        continue;
      }

      $result[] = [
        'role' => $role,
        'content' => $message['content'],
      ];
    }
    return $result;
  }
}
