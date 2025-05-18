<?php

namespace AiAgent\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiInteraction extends Model
{
  use HasFactory;

  /**
   * The attributes that are mass assignable.
   *
   * @var array<int, string>
   */
  protected $fillable = [
    'user_id',
    'provider',
    'type',
    'input',
    'output',
    'options',
    'tokens_used',
    'duration',
    'success',
    'error',
  ];

  /**
   * The attributes that should be cast.
   *
   * @var array<string, string>
   */
  protected $casts = [
    'options' => 'json',
    'tokens_used' => 'integer',
    'duration' => 'float',
    'success' => 'boolean',
  ];

  /**
   * Get the user that owns the interaction.
   */
  public function user(): BelongsTo
  {
    return $this->belongsTo(config('auth.providers.users.model'));
  }

  /**
   * Record an AI interaction.
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
   * @param int|null $userId The ID of the user who made the request
   * @return self
   */
  public static function record(
    string $provider,
    string $type,
    $input,
    $output = null,
    array $options = [],
    int $tokensUsed = 0,
    float $duration = 0,
    bool $success = true,
    ?string $error = null,
    ?int $userId = null
  ): self {
    return self::create([
      'user_id' => $userId,
      'provider' => $provider,
      'type' => $type,
      'input' => is_array($input) ? json_encode($input) : $input,
      'output' => is_array($output) ? json_encode($output) : $output,
      'options' => $options,
      'tokens_used' => $tokensUsed,
      'duration' => $duration,
      'success' => $success,
      'error' => $error,
    ]);
  }
}
