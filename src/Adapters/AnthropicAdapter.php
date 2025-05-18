<?php

namespace AiAgent\Adapters;

use GuzzleHttp\Exception\GuzzleException;

class AnthropicAdapter extends BaseAdapter
{
  /**
   * API Base URL.
   */
  protected $apiBaseUrl = 'https://api.anthropic.com/v1';

  /**
   * Create a new Anthropic adapter instance.
   */
  public function __construct(array $config)
  {
    parent::__construct($config);

    $this->validateConfig(['api_key']);

    if (isset($config['api_base_url'])) {
      $this->apiBaseUrl = $config['api_base_url'];
    }
  }

  /**
   * Generate content with Anthropic Claude.
   */
  public function generate(string $prompt, array $options = []): string
  {
    $messages = [
      [
        'role' => 'user',
        'content' => $prompt
      ]
    ];

    $response = $this->chat($messages, $options);

    return $response['message']['content'] ?? '';
  }

  /**
   * Generate chat completion with Anthropic Claude.
   */
  public function chat(array $messages, array $options = []): array
  {
    $model = $options['model'] ?? $this->getConfig('model', 'claude-3-opus-20240229');
    $maxTokens = $options['max_tokens'] ?? $this->getConfig('max_tokens', 1000);
    $temperature = $options['temperature'] ?? $this->getConfig('temperature', 0.7);

    // Convert messages to Anthropic format if needed
    $anthropicMessages = $this->formatMessages($messages);

    $data = [
      'model' => $model,
      'messages' => $anthropicMessages,
      'max_tokens' => $maxTokens,
      'temperature' => $temperature,
    ];

    if (isset($options['system'])) {
      $data['system'] = $options['system'];
    } elseif ($this->hasConfig('system_prompt')) {
      $data['system'] = $this->getConfig('system_prompt');
    }

    $response = $this->makeRequest('messages', $data);

    return [
      'message' => [
        'role' => $response['content'][0]['type'] === 'text' ? 'assistant' : 'tool',
        'content' => $response['content'][0]['text'] ?? '',
      ],
      'usage' => [
        'prompt_tokens' => $response['usage']['input_tokens'] ?? 0,
        'completion_tokens' => $response['usage']['output_tokens'] ?? 0,
        'total_tokens' => ($response['usage']['input_tokens'] ?? 0) + ($response['usage']['output_tokens'] ?? 0),
      ],
      'id' => $response['id'] ?? null,
    ];
  }

  /**
   * Generate embeddings for a text with Anthropic.
   * Note: Anthropic may not support embeddings directly, so this is a placeholder.
   */
  public function embeddings($input, array $options = []): array
  {
    throw new \RuntimeException('Anthropic does not natively support embeddings. Please use a different provider for embeddings.');
  }

  /**
   * Format messages for Anthropic API.
   */
  protected function formatMessages(array $messages): array
  {
    $result = [];

    foreach ($messages as $message) {
      if (!isset($message['role']) || !isset($message['content'])) {
        continue;
      }

      // Map OpenAI message format to Anthropic
      $role = $message['role'];

      if ($role === 'system') {
        // Handle system messages separately as they're passed differently in Anthropic
        continue;
      }

      if ($role === 'assistant') {
        $role = 'assistant';
      } elseif ($role === 'user') {
        $role = 'user';
      }

      $result[] = [
        'role' => $role,
        'content' => $message['content'],
      ];
    }

    return $result;
  }

  /**
   * Make a request to the Anthropic API.
   */
  protected function makeRequest(string $endpoint, array $data): array
  {
    try {
      $response = $this->client->post("{$this->apiBaseUrl}/{$endpoint}", [
        'headers' => [
          'x-api-key' => $this->getConfig('api_key'),
          'anthropic-version' => '2023-06-01',
          'Content-Type' => 'application/json',
        ],
        'json' => $data,
      ]);

      return json_decode($response->getBody()->getContents(), true);
    } catch (GuzzleException $e) {
      $message = $e->getMessage();
      $responseBody = '';

      if ($e->hasResponse()) {
        $responseBody = $e->getResponse()->getBody()->getContents();
        $decodedBody = json_decode($responseBody, true);
        $message = $decodedBody['error']['message'] ?? $message;
      }

      throw new \RuntimeException("Anthropic API error: {$message}", 0, $e);
    }
  }
}
