<?php

use AiAgent\Facades\AiAgent;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Mockery as m;

beforeEach(function () {
  AiAgent::shouldReceive('setProvider')->andReturnSelf();
});

test('ai directive renders content from AI provider', function () {
  $prompt = 'Generate a greeting';
  $cacheKey = 'ai_directive_' . md5($prompt . '_openai');

  // Test with cache miss
  Cache::shouldReceive('has')
    ->once()
    ->with($cacheKey)
    ->andReturn(false);

  AiAgent::shouldReceive('generate')
    ->once()
    ->with($prompt)
    ->andReturn('Hello, world!');

  Cache::shouldReceive('put')
    ->once()
    ->with($cacheKey, 'Hello, world!', m::type('int'));

  $renderedContent = Blade::render('@ai("' . $prompt . '")');
  expect($renderedContent)->toBe('Hello, world!');

  // Test with cache hit
  Cache::shouldReceive('has')
    ->once()
    ->with($cacheKey)
    ->andReturn(true);

  Cache::shouldReceive('get')
    ->once()
    ->with($cacheKey)
    ->andReturn('Hello, world!');

  AiAgent::shouldNotReceive('generate');

  $renderedContent = Blade::render('@ai("' . $prompt . '")');
  expect($renderedContent)->toBe('Hello, world!');
});

test('ai directive uses specified provider', function () {
  $prompt = 'Generate a greeting';
  $provider = 'gemini';
  $cacheKey = 'ai_directive_' . md5($prompt . '_' . $provider);

  Cache::shouldReceive('has')
    ->once()
    ->with($cacheKey)
    ->andReturn(false);

  AiAgent::shouldReceive('provider')
    ->once()
    ->with($provider)
    ->andReturnSelf();

  AiAgent::shouldReceive('generate')
    ->once()
    ->with($prompt)
    ->andReturn('Hello from Gemini!');

  Cache::shouldReceive('put')
    ->once()
    ->with($cacheKey, 'Hello from Gemini!', m::type('int'));

  $renderedContent = Blade::render('@ai("' . $prompt . '", "' . $provider . '")');
  expect($renderedContent)->toBe('Hello from Gemini!');
});

test('ai directive with refresh true bypasses cache', function () {
  $prompt = 'Generate a greeting';
  $cacheKey = 'ai_directive_' . md5($prompt . '_openai');

  Cache::shouldNotReceive('has');

  AiAgent::shouldReceive('generate')
    ->once()
    ->with($prompt)
    ->andReturn('Fresh greeting!');

  Cache::shouldReceive('put')
    ->once()
    ->with($cacheKey, 'Fresh greeting!', m::type('int'));

  $renderedContent = Blade::render('@ai("' . $prompt . '", null, true)');
  expect($renderedContent)->toBe('Fresh greeting!');
});

test('aichat directive renders chat response from AI provider', function () {
  $messages = [
    ['role' => 'user', 'content' => 'Hello'],
    ['role' => 'assistant', 'content' => 'Hi there'],
    ['role' => 'user', 'content' => 'How are you?']
  ];

  $messagesJson = json_encode($messages);
  $cacheKey = 'ai_chat_directive_' . md5($messagesJson . '_openai');

  // Test with cache miss
  Cache::shouldReceive('has')
    ->once()
    ->with($cacheKey)
    ->andReturn(false);

  AiAgent::shouldReceive('chat')
    ->once()
    ->with($messages)
    ->andReturn('I am doing well, thanks for asking!');

  Cache::shouldReceive('put')
    ->once()
    ->with($cacheKey, 'I am doing well, thanks for asking!', m::type('int'));

  $renderedContent = Blade::render('@aichat(' . $messagesJson . ')');
  expect($renderedContent)->toBe('I am doing well, thanks for asking!');

  // Test with cache hit
  Cache::shouldReceive('has')
    ->once()
    ->with($cacheKey)
    ->andReturn(true);

  Cache::shouldReceive('get')
    ->once()
    ->with($cacheKey)
    ->andReturn('I am doing well, thanks for asking!');

  AiAgent::shouldNotReceive('chat');

  $renderedContent = Blade::render('@aichat(' . $messagesJson . ')');
  expect($renderedContent)->toBe('I am doing well, thanks for asking!');
});

test('aichat directive uses specified provider', function () {
  $messages = [
    ['role' => 'user', 'content' => 'Tell me a joke']
  ];

  $messagesJson = json_encode($messages);
  $provider = 'gemini';
  $cacheKey = 'ai_chat_directive_' . md5($messagesJson . '_' . $provider);

  Cache::shouldReceive('has')
    ->once()
    ->with($cacheKey)
    ->andReturn(false);

  AiAgent::shouldReceive('provider')
    ->once()
    ->with($provider)
    ->andReturnSelf();

  AiAgent::shouldReceive('chat')
    ->once()
    ->with($messages)
    ->andReturn('Why did the chicken cross the road?');

  Cache::shouldReceive('put')
    ->once()
    ->with($cacheKey, 'Why did the chicken cross the road?', m::type('int'));

  $renderedContent = Blade::render('@aichat(' . $messagesJson . ', "' . $provider . '")');
  expect($renderedContent)->toBe('Why did the chicken cross the road?');
});

test('aichat directive with refresh true bypasses cache', function () {
  $messages = [
    ['role' => 'user', 'content' => 'Tell me something new']
  ];

  $messagesJson = json_encode($messages);
  $cacheKey = 'ai_chat_directive_' . md5($messagesJson . '_openai');

  Cache::shouldNotReceive('has');

  AiAgent::shouldReceive('chat')
    ->once()
    ->with($messages)
    ->andReturn('Here is something brand new!');

  Cache::shouldReceive('put')
    ->once()
    ->with($cacheKey, 'Here is something brand new!', m::type('int'));

  $renderedContent = Blade::render('@aichat(' . $messagesJson . ', null, true)');
  expect($renderedContent)->toBe('Here is something brand new!');
});
