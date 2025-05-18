<?php

namespace AiAgent\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \AiAgent\Contracts\AiProviderInterface provider(?string $provider = null)
 * @method static string generate(string $prompt, array $options = [], ?string $provider = null)
 * @method static array chat(array $messages, array $options = [], ?string $provider = null)
 * @method static array embeddings(string|array $input, array $options = [], ?string $provider = null)
 * @method static array getProviderNames()
 *
 * @see \AiAgent\AiAgent
 */
class AiAgent extends Facade
{
  /**
   * Get the registered name of the component.
   *
   * @return string
   */
  protected static function getFacadeAccessor()
  {
    return 'ai-agent';
  }
}
