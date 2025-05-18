<?php

namespace AiAgent\Services;

use AiAgent\Contracts\AiProviderInterface;
use AiAgent\Exceptions\AdapterNotFoundException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;

class AiService
{
  /**
   * The container instance.
   */
  protected $container;

  /**
   * The registered adapters.
   */
  protected $adapters = [];

  /**
   * Create a new AI service instance.
   */
  public function __construct(Container $container)
  {
    $this->container = $container;
  }

  /**
   * Resolve a provider instance.
   *
   * @param string $adapter The adapter class name
   * @param array $config The provider configuration
   * @return AiProviderInterface
   * @throws AdapterNotFoundException
   */
  public function resolveProvider(string $adapter, array $config): AiProviderInterface
  {
    if (!class_exists($adapter)) {
      // Try to resolve from the adapter namespace
      $adapter = $this->resolveAdapterClass($adapter);
    }

    if (!class_exists($adapter)) {
      throw new AdapterNotFoundException("AI adapter [{$adapter}] not found.");
    }

    return new $adapter($config);
  }

  /**
   * Resolve the full adapter class name.
   *
   * @param string $adapter The adapter name
   * @return string The full adapter class name
   */
  protected function resolveAdapterClass(string $adapter): string
  {
    // Check if it's a fully qualified class name
    if (class_exists($adapter)) {
      return $adapter;
    }

    // Try to resolve from the namespace
    $namespaced = 'AiAgent\\Adapters\\' . Str::studly($adapter) . 'Adapter';

    if (class_exists($namespaced)) {
      return $namespaced;
    }

    return $adapter;
  }

  /**
   * Register a new adapter.
   *
   * @param string $name The adapter name
   * @param string $adapter The adapter class
   * @return void
   */
  public function registerAdapter(string $name, string $adapter): void
  {
    $this->adapters[$name] = $adapter;
  }

  /**
   * Get all registered adapters.
   *
   * @return array
   */
  public function getAdapters(): array
  {
    return $this->adapters;
  }
}
