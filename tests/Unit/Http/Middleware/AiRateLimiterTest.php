<?php

use AiAgent\Http\Middleware\AiRateLimiter;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Mockery as m;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
  $this->rateLimiter = m::mock(RateLimiter::class);
  $this->middleware = new AiRateLimiter($this->rateLimiter);
  $this->request = m::mock(Request::class);
  $this->next = function () {
    return new \Illuminate\Http\Response('Ok');
  };
});

afterEach(function () {
  m::close();
});

test('middleware skips when rate limiting is disabled', function () {
  Config::shouldReceive('get')
    ->with('ai-agent.rate_limiting.enabled', false)
    ->once()
    ->andReturn(false);

  $response = $this->middleware->handle($this->request, $this->next);

  expect($response->getContent())->toBe('Ok');
});

test('middleware allows request when not too many attempts', function () {
  Config::shouldReceive('get')
    ->with('ai-agent.rate_limiting.enabled', false)
    ->once()
    ->andReturn(true);

  Config::shouldReceive('get')
    ->with('ai-agent.rate_limiting.max_requests', 60)
    ->once()
    ->andReturn(30);

  Config::shouldReceive('get')
    ->with('ai-agent.rate_limiting.decay_minutes', 1)
    ->once()
    ->andReturn(2);

  Config::shouldReceive('get')
    ->with('ai-agent.default_provider')
    ->once()
    ->andReturn('test-provider');

  $this->request->shouldReceive('ip')
    ->once()
    ->andReturn('127.0.0.1');

  $this->request->shouldReceive('input')
    ->with('provider', 'test-provider')
    ->once()
    ->andReturn('test-provider');

  Auth::shouldReceive('id')
    ->once()
    ->andReturn(null);

  $this->rateLimiter->shouldReceive('tooManyAttempts')
    ->once()
    ->andReturn(false);

  $this->rateLimiter->shouldReceive('hit')
    ->once()
    ->with('ai-agent|127.0.0.1|test-provider', 120)
    ->andReturn(1);

  $this->rateLimiter->shouldReceive('attempts')
    ->once()
    ->andReturn(1);

  $response = $this->middleware->handle($this->request, $this->next);

  expect($response->getContent())->toBe('Ok');
  expect($response->headers->has('X-RateLimit-Limit'))->toBeTrue();
  expect($response->headers->has('X-RateLimit-Remaining'))->toBeTrue();
  expect($response->headers->get('X-RateLimit-Limit'))->toBe('30');
  expect($response->headers->get('X-RateLimit-Remaining'))->toBe('30');
});

test('middleware blocks request when too many attempts', function () {
  Config::shouldReceive('get')
    ->with('ai-agent.rate_limiting.enabled', false)
    ->once()
    ->andReturn(true);

  Config::shouldReceive('get')
    ->with('ai-agent.rate_limiting.max_requests', 60)
    ->once()
    ->andReturn(10);

  Config::shouldReceive('get')
    ->with('ai-agent.default_provider')
    ->once()
    ->andReturn('test-provider');

  $this->request->shouldReceive('ip')
    ->once()
    ->andReturn('127.0.0.1');

  $this->request->shouldReceive('input')
    ->with('provider', 'test-provider')
    ->once()
    ->andReturn('test-provider');

  Auth::shouldReceive('id')
    ->once()
    ->andReturn(null);

  $this->rateLimiter->shouldReceive('tooManyAttempts')
    ->once()
    ->andReturn(true);

  $this->rateLimiter->shouldReceive('availableIn')
    ->once()
    ->andReturn(60);

  $response = $this->middleware->handle($this->request, $this->next);

  expect($response->getStatusCode())->toBe(429);
  expect($response->headers->has('Retry-After'))->toBeTrue();
  expect($response->headers->has('X-RateLimit-Limit'))->toBeTrue();
  expect($response->headers->has('X-RateLimit-Remaining'))->toBeTrue();
  expect($response->headers->get('Retry-After'))->toBe('60');
  expect($response->headers->get('X-RateLimit-Limit'))->toBe('10');
  expect($response->headers->get('X-RateLimit-Remaining'))->toBe('0');
});
