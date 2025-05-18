<?php

namespace AiAgent\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AiRateLimiter
{
  /**
   * The rate limiter instance.
   */
  protected $limiter;

  /**
   * Create a new middleware instance.
   */
  public function __construct(RateLimiter $limiter)
  {
    $this->limiter = $limiter;
  }

  /**
   * Handle an incoming request.
   */
  public function handle(Request $request, Closure $next): Response
  {
    // Skip if rate limiting is disabled
    if (!config('ai-agent.rate_limiting.enabled', false)) {
      return $next($request);
    }

    $key = $this->resolveRequestSignature($request);
    $maxAttempts = config('ai-agent.rate_limiting.max_requests', 60);
    $decayMinutes = config('ai-agent.rate_limiting.decay_minutes', 1);

    if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
      return $this->buildResponse($key, $maxAttempts);
    }

    $this->limiter->hit($key, $decayMinutes * 60);

    $response = $next($request);

    return $this->addHeaders(
      $response,
      $maxAttempts,
      $this->calculateRemainingAttempts($key, $maxAttempts)
    );
  }

  /**
   * Resolve the request signature for the rate limiter.
   */
  protected function resolveRequestSignature(Request $request): string
  {
    $userId = Auth::id() ?: $request->ip();
    $provider = $request->input('provider', config('ai-agent.default_provider'));

    return 'ai-agent|' . $userId . '|' . $provider;
  }

  /**
   * Create a 'too many attempts' response.
   */
  protected function buildResponse(string $key, int $maxAttempts): Response
  {
    $retryAfter = $this->limiter->availableIn($key);

    $response = response()->json([
      'error' => 'Too many AI requests. Please try again later.',
      'retry_after' => $retryAfter,
    ], 429);

    $response->headers->add([
      'Retry-After' => $retryAfter,
      'X-RateLimit-Limit' => $maxAttempts,
      'X-RateLimit-Remaining' => 0,
    ]);

    return $response;
  }

  /**
   * Add the rate limit headers to the response.
   */
  protected function addHeaders(Response $response, int $maxAttempts, int $remainingAttempts): Response
  {
    $response->headers->add([
      'X-RateLimit-Limit' => $maxAttempts,
      'X-RateLimit-Remaining' => $remainingAttempts,
    ]);

    return $response;
  }

  /**
   * Calculate the number of remaining attempts.
   */
  protected function calculateRemainingAttempts(string $key, int $maxAttempts): int
  {
    return $maxAttempts - $this->limiter->attempts($key) + 1;
  }
}
