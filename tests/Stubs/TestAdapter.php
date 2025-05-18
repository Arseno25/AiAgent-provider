<?php

namespace AiAgent\Tests\Stubs;

use AiAgent\Adapters\BaseAdapter;

class TestAdapter extends BaseAdapter
{
  /**
   * Generate content with test adapter.
   */
  public function generate(string $prompt, array $options = []): string
  {
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
