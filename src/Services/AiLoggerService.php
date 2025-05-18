<?php

namespace AiAgent\Services;

use AiAgent\Models\AiInteraction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AiLoggerService
{
  /**
   * Whether logging is enabled.
   */
  protected bool $enabled;

  /**
   * The logging channel to use.
   */
  protected string $channel;

  /**
   * Create a new logger service instance.
   */
  public function __construct()
  {
    $this->enabled = config('ai-agent.logging.enabled', false);
    $this->channel = config('ai-agent.logging.channel', 'stack');
  }

  /**
   * Log an AI interaction.
   *
   * @param string $provider The AI provider
   * @param string $type The interaction type (generate, chat, embeddings)
   * @param mixed $input The input to the AI
   * @param mixed $output The output from the AI
   * @param array $options Additional options used
   * @param int $tokensUsed Number of tokens used
   * @param float $duration Duration of the request in seconds
   * @param bool $success Whether the interaction was successful
   * @param string|null $error Error message if the interaction failed
   * @return void
   */
  public function log(
    string $provider,
    string $type,
    $input,
    $output = null,
    array $options = [],
    int $tokensUsed = 0,
    float $duration = 0,
    bool $success = true,
    ?string $error = null
  ): void {
    if (!$this->enabled) {
      return;
    }

    try {
      // Log to database
      AiInteraction::record(
        $provider,
        $type,
        $input,
        $output,
        $options,
        $tokensUsed,
        $duration,
        $success,
        $error,
        Auth::id()
      );

      // Log to file
      $context = [
        'provider' => $provider,
        'type' => $type,
        'input' => $input,
        'options' => $options,
        'tokens_used' => $tokensUsed,
        'duration' => $duration,
        'user_id' => Auth::id(),
      ];

      if (!$success) {
        Log::channel($this->channel)->error("AI error ({$provider}): {$error}", $context);
      } else {
        Log::channel($this->channel)->info("AI request ({$provider}/{$type})", $context);
      }
    } catch (\Exception $e) {
      Log::channel($this->channel)->error("Failed to log AI interaction: {$e->getMessage()}", [
        'exception' => $e,
      ]);
    }
  }

  /**
   * Check if logging is enabled.
   */
  public function isEnabled(): bool
  {
    return $this->enabled;
  }

  /**
   * Enable or disable logging.
   */
  public function setEnabled(bool $enabled): self
  {
    $this->enabled = $enabled;
    return $this;
  }
}
