<?php

use AiAgent\Models\AiInteraction;
use AiAgent\Services\AiLoggerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Mockery as m;

beforeEach(function () {
  $this->logger = new AiLoggerService();
});

afterEach(function () {
  m::close();
});

test('construct sets enabled from config', function () {
  Config::shouldReceive('get')
    ->with('ai-agent.logging.enabled', false)
    ->once()
    ->andReturn(true);

  Config::shouldReceive('get')
    ->with('ai-agent.logging.channel', 'stack')
    ->once()
    ->andReturn('custom-channel');

  $logger = new AiLoggerService();

  $reflection = new ReflectionProperty(AiLoggerService::class, 'enabled');
  $reflection->setAccessible(true);
  expect($reflection->getValue($logger))->toBeTrue();

  $channelReflection = new ReflectionProperty(AiLoggerService::class, 'channel');
  $channelReflection->setAccessible(true);
  expect($channelReflection->getValue($logger))->toBe('custom-channel');
});

test('log does nothing when logging disabled', function () {
  // Set logger to disabled
  $reflection = new ReflectionProperty(AiLoggerService::class, 'enabled');
  $reflection->setAccessible(true);
  $reflection->setValue($this->logger, false);

  // Mock should not be called
  $mock = m::mock('overload:' . AiInteraction::class);
  $mock->shouldNotReceive('record');

  Log::shouldNotReceive('channel');

  $this->logger->log('openai', 'generate', 'Test prompt', 'Test response');
});

test('log creates database record and logs to file when enabled', function () {
  // Set logger to enabled
  $reflection = new ReflectionProperty(AiLoggerService::class, 'enabled');
  $reflection->setAccessible(true);
  $reflection->setValue($this->logger, true);

  // Channel reflection
  $channelReflection = new ReflectionProperty(AiLoggerService::class, 'channel');
  $channelReflection->setAccessible(true);
  $channelReflection->setValue($this->logger, 'test-channel');

  // User ID
  Auth::shouldReceive('id')
    ->twice()
    ->andReturn(123);

  // Mock AiInteraction
  $interaction = m::mock(AiInteraction::class);
  $mock = m::mock('overload:' . AiInteraction::class);
  $mock->shouldReceive('record')
    ->once()
    ->with(
      'openai',
      'generate',
      'Test prompt',
      'Test response',
      ['temperature' => 0.7],
      100,
      0.5,
      true,
      null,
      123
    )
    ->andReturn($interaction);

  // Mock Log
  $logChannel = m::mock('log-channel');
  Log::shouldReceive('channel')
    ->once()
    ->with('test-channel')
    ->andReturn($logChannel);

  $logChannel->shouldReceive('info')
    ->once()
    ->with('AI request (openai/generate)', m::type('array'));

  $this->logger->log(
    'openai',
    'generate',
    'Test prompt',
    'Test response',
    ['temperature' => 0.7],
    100,
    0.5
  );
});

test('log creates database record and logs error when failure', function () {
  // Set logger to enabled
  $reflection = new ReflectionProperty(AiLoggerService::class, 'enabled');
  $reflection->setAccessible(true);
  $reflection->setValue($this->logger, true);

  // Channel reflection
  $channelReflection = new ReflectionProperty(AiLoggerService::class, 'channel');
  $channelReflection->setAccessible(true);
  $channelReflection->setValue($this->logger, 'test-channel');

  // User ID
  Auth::shouldReceive('id')
    ->twice()
    ->andReturn(123);

  // Mock AiInteraction
  $interaction = m::mock(AiInteraction::class);
  $mock = m::mock('overload:' . AiInteraction::class);
  $mock->shouldReceive('record')
    ->once()
    ->with(
      'openai',
      'generate',
      'Test prompt',
      null,
      [],
      0,
      0.5,
      false,
      'Test error',
      123
    )
    ->andReturn($interaction);

  // Mock Log
  $logChannel = m::mock('log-channel');
  Log::shouldReceive('channel')
    ->once()
    ->with('test-channel')
    ->andReturn($logChannel);

  $logChannel->shouldReceive('error')
    ->once()
    ->with('AI error (openai): Test error', m::type('array'));

  $this->logger->log(
    'openai',
    'generate',
    'Test prompt',
    null,
    [],
    0,
    0.5,
    false,
    'Test error'
  );
});

test('log handles exceptions gracefully', function () {
  // Set logger to enabled
  $reflection = new ReflectionProperty(AiLoggerService::class, 'enabled');
  $reflection->setAccessible(true);
  $reflection->setValue($this->logger, true);

  // Channel reflection
  $channelReflection = new ReflectionProperty(AiLoggerService::class, 'channel');
  $channelReflection->setAccessible(true);
  $channelReflection->setValue($this->logger, 'test-channel');

  // User ID
  Auth::shouldReceive('id')
    ->once()
    ->andReturn(123);

  // Mock AiInteraction to throw an exception
  $mock = m::mock('overload:' . AiInteraction::class);
  $mock->shouldReceive('record')
    ->once()
    ->andThrow(new \Exception('Database error'));

  // Mock Log to record the exception
  $logChannel = m::mock('log-channel');
  Log::shouldReceive('channel')
    ->once()
    ->with('test-channel')
    ->andReturn($logChannel);

  $logChannel->shouldReceive('error')
    ->once()
    ->with('Failed to log AI interaction: Database error', m::type('array'));

  // Should not throw an exception
  $this->logger->log('openai', 'generate', 'Test prompt', 'Test response');
});

test('can check if logging is enabled', function () {
  $reflection = new ReflectionProperty(AiLoggerService::class, 'enabled');
  $reflection->setAccessible(true);
  $reflection->setValue($this->logger, true);

  expect($this->logger->isEnabled())->toBeTrue();

  $reflection->setValue($this->logger, false);

  expect($this->logger->isEnabled())->toBeFalse();
});

test('can enable or disable logging', function () {
  $reflection = new ReflectionProperty(AiLoggerService::class, 'enabled');
  $reflection->setAccessible(true);

  $this->logger->setEnabled(true);
  expect($reflection->getValue($this->logger))->toBeTrue();

  $this->logger->setEnabled(false);
  expect($reflection->getValue($this->logger))->toBeFalse();

  // Method should return $this for chaining
  expect($this->logger->setEnabled(true))->toBe($this->logger);
});
