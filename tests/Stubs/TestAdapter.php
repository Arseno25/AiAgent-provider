<?php

namespace AiAgent\Tests\Stubs;

use AiAgent\Adapters\BaseAdapter;
use GuzzleHttp\Client; // Added for type hinting

class TestAdapter extends BaseAdapter
{
  // Allow public access for testing base adapter logic
  public ?string $apiBaseUrl = 'http://fake-api.test'; 

  /**
   * Optionally allow a custom Guzzle client to be set for testing.
   * @param Client $client
   */
  public function setClient(Client $client): void
  {
      $this->client = $client;
  }

  /**
   * Allow setting a custom API base URL for testing.
   * @param string|null $url
   */
  public function setApiBaseUrl(?string $url): void
  {
      $this->apiBaseUrl = $url;
  }

  /**
   * {@inheritdoc}
   * Basic implementation for testing.
   */
  protected function getRequestOptions(string $method, string $endpoint, array $data = [], array $customHeaders = []): array
  {
    $options = [
      'headers' => array_merge(['X-Test-Header' => 'TestValue'], $customHeaders)
    ];
    if (!empty($data)) {
      $options['json'] = $data;
    }
    return $options;
  }

  /**
   * Generate content with test adapter.
   */
  public function generate(string $prompt, array $options = []): string
  {
    // This method would call $this->makeRequest in a real adapter.
    // For stub purposes, we can directly return or simulate a call.
    return "Test response for: {$prompt}";
  }

  /**
   * Generate chat completion with test adapter.
   */
  public function chat(array $messages, array $options = []): array
  {
    $lastMessage = end($messages);
    $content = "Test chat response for: " . ($lastMessage['content'] ?? 'empty message');

    return [
      'message' => [
        'role' => 'assistant',
        'content' => $content,
      ],
      'usage' => [
        'prompt_tokens' => 10,
        'completion_tokens' => 20,
        'total_tokens' => 30,
      ],
      'id' => 'test-chat-id',
    ];
  }

  /**
   * Generate embeddings for a text with test adapter.
   */
  public function embeddings($input, array $options = []): array
  {
    $texts = is_array($input) ? $input : [$input];
    $result = [];

    foreach ($texts as $index => $text) {
      $result[] = [
        'embedding' => array_fill(0, 10, 0.1), // 10-dimensional vector with 0.1 values
        'index' => $index,
        'object' => 'embedding',
      ];
    }

    return $result;
  }
}
