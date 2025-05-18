<?php

namespace AiAgent\Tests\Feature\Http\Controllers;

use AiAgent\Facades\AiAgent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
  Config::set('ai-agent.routes.enabled', true);
  Config::set('ai-agent.routes.prefix', 'api/ai');
  Config::set('ai-agent.routes.middleware', ['api']);
});

test('can get list of providers', function () {
  AiAgent::shouldReceive('getProviderNames')
    ->once()
    ->andReturn(['test', 'openai', 'anthropic']);

  $response = $this->getJson('/api/ai/providers');

  $response->assertStatus(200)
    ->assertJson([
      'providers' => ['test', 'openai', 'anthropic'],
      'default' => 'test'
    ]);
});

test('can generate content', function () {
  AiAgent::shouldReceive('generate')
    ->once()
    ->with('Test prompt', [], null)
    ->andReturn('Generated content');

  $response = $this->postJson('/api/ai/generate', [
    'prompt' => 'Test prompt'
  ]);

  $response->assertStatus(200)
    ->assertJson([
      'result' => 'Generated content'
    ]);
});

test('generate validates request', function () {
  $response = $this->postJson('/api/ai/generate', []);

  $response->assertStatus(422)
    ->assertJsonValidationErrors(['prompt']);
});

test('can get chat completion', function () {
  $messages = [
    ['role' => 'user', 'content' => 'Hello']
  ];

  $chatResponse = [
    'message' => ['role' => 'assistant', 'content' => 'Hi there'],
    'usage' => ['total_tokens' => 20],
  ];

  AiAgent::shouldReceive('chat')
    ->once()
    ->with($messages, [], null)
    ->andReturn($chatResponse);

  $response = $this->postJson('/api/ai/chat', [
    'messages' => $messages
  ]);

  $response->assertStatus(200)
    ->assertJson([
      'result' => $chatResponse
    ]);
});

test('chat validates request', function () {
  $response = $this->postJson('/api/ai/chat', [
    'messages' => [
      ['role' => 'invalid', 'content' => 'Hello']
    ]
  ]);

  $response->assertStatus(422)
    ->assertJsonValidationErrors(['messages.0.role']);
});

test('can generate embeddings', function () {
  $embeddingsResponse = [
    ['embedding' => [0.1, 0.2, 0.3]]
  ];

  AiAgent::shouldReceive('embeddings')
    ->once()
    ->with('Test text', [], null)
    ->andReturn($embeddingsResponse);

  $response = $this->postJson('/api/ai/embeddings', [
    'input' => 'Test text'
  ]);

  $response->assertStatus(200)
    ->assertJson([
      'result' => $embeddingsResponse
    ]);
});

test('embeddings validates request', function () {
  $response = $this->postJson('/api/ai/embeddings', []);

  $response->assertStatus(422)
    ->assertJsonValidationErrors(['input']);
});
