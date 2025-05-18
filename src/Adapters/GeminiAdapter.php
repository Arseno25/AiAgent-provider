<?php

namespace AiAgent\Adapters;

use GuzzleHttp\Exception\GuzzleException;

class GeminiAdapter extends BaseAdapter
{
  /**
   * API Base URL.
   */
  protected $apiBaseUrl = 'https://generativelanguage.googleapis.com/v1';

  /**
   * Create a new Gemini adapter instance.
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
   * Generate content with Gemini.
   */
  public function generate(string $prompt, array $options = []): string
  {
    $model = $options['model'] ?? $this->getConfig('model', 'gemini-1.5-pro');
    $temperature = $options['temperature'] ?? $this->getConfig('temperature', 0.7);
    $maxTokens = $options['max_tokens'] ?? $this->getConfig('max_tokens', 1000);

    $response = $this->makeRequest("models/{$model}:generateContent", [
      'contents' => [
        [
          'parts' => [
            ['text' => $prompt]
          ]
        ]
      ],
      'generationConfig' => [
        'temperature' => $temperature,
        'maxOutputTokens' => $maxTokens,
      ],
    ]);

    return $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
  }

  /**
   * Generate chat completion with Gemini.
   */
  public function chat(array $messages, array $options = []): array
  {
    $model = $options['model'] ?? $this->getConfig('model', 'gemini-1.5-pro');
    $temperature = $options['temperature'] ?? $this->getConfig('temperature', 0.7);
    $maxTokens = $options['max_tokens'] ?? $this->getConfig('max_tokens', 1000);

    // Convert messages to Gemini format
    $contents = $this->formatMessages($messages);

    $response = $this->makeRequest("models/{$model}:generateContent", [
      'contents' => $contents,
      'generationConfig' => [
        'temperature' => $temperature,
        'maxOutputTokens' => $maxTokens,
      ],
    ]);

    $content = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';

    return [
      'message' => [
        'role' => 'assistant',
        'content' => $content,
      ],
      'usage' => [
        'prompt_tokens' => $response['usageMetadata']['promptTokenCount'] ?? 0,
        'completion_tokens' => $response['usageMetadata']['candidatesTokenCount'] ?? 0,
        'total_tokens' => ($response['usageMetadata']['promptTokenCount'] ?? 0) +
          ($response['usageMetadata']['candidatesTokenCount'] ?? 0),
      ],
      'id' => $response['candidates'][0]['index'] ?? null,
    ];
  }

  /**
   * Generate embeddings for a text with Gemini.
   */
  public function embeddings($input, array $options = []): array
  {
    $model = $options['model'] ?? $this->getConfig('embedding_model', 'embedding-001');

    // Convert to array if single string
    $texts = is_array($input) ? $input : [$input];
    $embeddings = [];

    foreach ($texts as $index => $text) {
      $response = $this->makeRequest("models/{$model}:embedContent", [
        'content' => [
          'parts' => [
            ['text' => $text]
          ]
        ],
      ]);

      $embeddings[] = [
        'embedding' => $response['embedding']['values'] ?? [],
        'index' => $index,
        'object' => 'embedding',
      ];
    }

    return $embeddings;
  }

  /**
   * Format messages for Gemini API.
   */
  protected function formatMessages(array $messages): array
  {
    $contents = [];
    $currentContent = [];

    foreach ($messages as $message) {
      if (!isset($message['role']) || !isset($message['content'])) {
        continue;
      }

      $role = $message['role'];
      $content = $message['content'];

      if ($role === 'system') {
        // System message gets added as a user message at the beginning
        $currentContent[] = [
          'role' => 'user',
          'parts' => [
            ['text' => $content]
          ]
        ];
      } elseif ($role === 'user') {
        $currentContent[] = [
          'role' => 'user',
          'parts' => [
            ['text' => $content]
          ]
        ];
      } elseif ($role === 'assistant') {
        $currentContent[] = [
          'role' => 'model',
          'parts' => [
            ['text' => $content]
          ]
        ];
      }
    }

    return $currentContent;
  }

  /**
   * Make a request to the Gemini API.
   */
  protected function makeRequest(string $endpoint, array $data): array
  {
    try {
      $url = "{$this->apiBaseUrl}/{$endpoint}?key=" . $this->getConfig('api_key');

      $response = $this->client->post($url, [
        'headers' => [
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

      throw new \RuntimeException("Gemini API error: {$message}", 0, $e);
    }
  }
}
