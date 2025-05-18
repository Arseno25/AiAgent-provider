<?php

namespace AiAgent\Adapters;

use AiAgent\Contracts\AiProviderInterface;
use GuzzleHttp\Client;

abstract class BaseAdapter implements AiProviderInterface
{
  /**
   * The configuration array.
   */
  protected $config;

  /**
   * The HTTP client.
   */
  protected $client;

  /**
   * Create a new adapter instance.
   */
  public function __construct(array $config)
  {
    $this->config = $config;
    $this->client = new Client([
      'timeout' => $config['timeout'] ?? 30,
      'connect_timeout' => $config['connect_timeout'] ?? 10,
    ]);
  }

  /**
   * Get the provider name.
   */
  public function getName(): string
  {
    return $this->config['name'] ?? class_basename($this);
  }

  /**
   * Get information about the provider.
   */
  public function info(): array
  {
    return [
      'name' => $this->getName(),
      'class' => get_class($this),
      'features' => $this->getSupportedFeatures(),
    ];
  }

  /**
   * Get supported features of this adapter.
   */
  protected function getSupportedFeatures(): array
  {
    return [
      'generate' => method_exists($this, 'generate'),
      'chat' => method_exists($this, 'chat'),
      'embeddings' => method_exists($this, 'embeddings'),
    ];
  }

  /**
   * Get the configuration value.
   */
  protected function getConfig(string $key, $default = null)
  {
    return $this->config[$key] ?? $default;
  }

  /**
   * Check if a configuration value exists.
   */
  protected function hasConfig(string $key): bool
  {
    return isset($this->config[$key]);
  }

  /**
   * Validate the required configuration.
   */
  protected function validateConfig(array $required): void
  {
    foreach ($required as $key) {
      if (!isset($this->config[$key])) {
        throw new \InvalidArgumentException("The [{$key}] configuration is required.");
      }
    }
  }
}
