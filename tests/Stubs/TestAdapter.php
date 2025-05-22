<?php

namespace AiAgent\Tests\Stubs;

use AiAgent\Adapters\BaseAdapter;
use GuzzleHttp\Client; // Added for type hinting

class TestAdapter extends BaseAdapter
{
  // Allow public access for testing base adapter logic
  public ?string $apiBaseUrl = 'http://fake-api.test'; 

  /**
   * Sets a custom Guzzle HTTP client instance for use in tests.
   *
   * Replaces the default client with the provided one to enable controlled testing scenarios.
   */
  public function setClient(Client $client): void
  {
      $this->client = $client;
  }

  /****
   * Sets the API base URL to a custom value or clears it for testing purposes.
   *
   * @param string|null $url The new API base URL, or null to unset.
   */
  public function setApiBaseUrl(?string $url): void
  {
      $this->apiBaseUrl = $url;
  }

  /****
   * Constructs HTTP request options for testing, merging a default test header with any custom headers and including JSON data if provided.
   *
   * @param string $method HTTP method for the request.
   * @param string $endpoint Target endpoint for the request.
   * @param array $data Optional data to include as JSON payload.
   * @param array $customHeaders Additional headers to merge with the default test header.
   * @return array Assembled request options suitable for test scenarios.
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

  /****
   * Simulates content generation for a given prompt, returning a fixed test response.
   *
   * @param string $prompt The input prompt to generate content for.
   * @param array $options Optional parameters for generation (ignored in this stub).
   * @return string A static test response referencing the provided prompt.
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
