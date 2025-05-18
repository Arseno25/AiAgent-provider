<?php

namespace AiAgent\Adapters;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Arr;

class OpenAiAdapter extends BaseAdapter
{
  /**
   * API Base URL.
   */
  protected $apiBaseUrl = 'https://api.openai.com/v1';

  /**
   * Create a new OpenAI adapter instance.
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
   * Generate content with OpenAI.
   */
  public function generate(string $prompt, array $options = []): string
  {
    $model = $options['model'] ?? $this->getConfig('model', 'gpt-4o');
    $maxTokens = $options['max_tokens'] ?? $this->getConfig('max_tokens', 500);
    $temperature = $options['temperature'] ?? $this->getConfig('temperature', 0.7);

    $response = $this->makeRequest('completions', [
      'model' => $model,
      'prompt' => $prompt,
      'max_tokens' => $maxTokens,
      'temperature' => $temperature,
    ]);

    return $response['choices'][0]['text'] ?? '';
  }

  /**
   * Generate chat completion with OpenAI.
   */
  public function chat(array $messages, array $options = []): array
  {
    $model = $options['model'] ?? $this->getConfig('model', 'gpt-4o');
    $temperature = $options['temperature'] ?? $this->getConfig('temperature', 0.7);
    $maxTokens = $options['max_tokens'] ?? $this->getConfig('max_tokens', 1000);

    $response = $this->makeRequest('chat/completions', [
      'model' => $model,
      'messages' => $messages,
      'temperature' => $temperature,
      'max_tokens' => $maxTokens,
    ]);

    return [
      'message' => $response['choices'][0]['message'] ?? [],
      'usage' => $response['usage'] ?? [],
      'id' => $response['id'] ?? null,
    ];
  }

  /**
   * Generate embeddings for a text with OpenAI.
   */
  public function embeddings($input, array $options = []): array
  {
    $model = $options['model'] ?? $this->getConfig('embedding_model', 'text-embedding-3-small');

    $response = $this->makeRequest('embeddings', [
      'model' => $model,
      'input' => $input,
    ]);

    return $response['data'] ?? [];
  }

  /**
   * Make a request to the OpenAI API.
   */
  protected function makeRequest(string $endpoint, array $data): array
  {
    try {
      $response = $this->client->post("{$this->apiBaseUrl}/{$endpoint}", [
        'headers' => [
          'Authorization' => 'Bearer ' . $this->getConfig('api_key'),
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

      throw new \RuntimeException("OpenAI API error: {$message}", 0, $e);
    }
  }
}
