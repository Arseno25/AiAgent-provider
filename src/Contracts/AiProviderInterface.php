<?php

namespace AiAgent\Contracts;

interface AiProviderInterface
{
  /**
   * Generate content with AI.
   *
   * @param string $prompt The prompt to send to the AI
   * @param array $options Additional options for the provider
   * @return string The generated content
   */
  public function generate(string $prompt, array $options = []): string;

  /**
   * Generate chat completion with AI.
   *
   * @param array $messages The messages to send to the AI
   * @param array $options Additional options for the provider
   * @return array The chat completion response
   */
  public function chat(array $messages, array $options = []): array;

  /**
   * Generate embeddings for a text.
   *
   * @param string|array $input The text or array of texts to embed
   * @param array $options Additional options for the provider
   * @return array The embeddings
   */
  public function embeddings($input, array $options = []): array;

  /**
   * Get information about the provider.
   *
   * @return array
   */
  public function info(): array;

  /**
   * Get the provider name.
   *
   * @return string
   */
  public function getName(): string;
}
