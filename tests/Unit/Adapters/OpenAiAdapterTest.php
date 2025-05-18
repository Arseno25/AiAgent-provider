<?php

use AiAgent\Adapters\OpenAiAdapter;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Mockery as m;

beforeEach(function () {
  $this->mockHandler = new MockHandler();
  $this->historyContainer = [];
  $this->history = Middleware::history($this->historyContainer);
  $this->handlerStack = HandlerStack::create($this->mockHandler);
  $this->handlerStack->push($this->history);

  $this->client = new Client(['handler' => $this->handlerStack]);

  $this->config = [
    'api_key' => 'test-openai-key',
    'api_base_url' => 'https://test-api.openai.com/v1',
    'model' => 'gpt-4-test',
    'embedding_model' => 'embedding-test',
    'max_tokens' => 100,
    'temperature' => 0.5,
  ];

  $this->adapter = new OpenAiAdapter($this->config);

  // Replace the HTTP client with our mock
  $reflection = new ReflectionProperty(OpenAiAdapter::class, 'client');
  $reflection->setAccessible(true);
  $reflection->setValue($this->adapter, $this->client);
});

afterEach(function () {
  m::close();
});

test('constructor validates api_key', function () {
  new OpenAiAdapter([]);
})->throws(InvalidArgumentException::class, 'The [api_key] configuration is required.');

test('constructor sets api base url from config', function () {
  $reflection = new ReflectionProperty(OpenAiAdapter::class, 'apiBaseUrl');
  $reflection->setAccessible(true);

  expect($reflection->getValue($this->adapter))->toBe('https://test-api.openai.com/v1');
});

test('can generate content', function () {
  $this->mockHandler->append(new Response(200, [], json_encode([
    'choices' => [
      ['text' => 'Generated text response']
    ]
  ])));

  $result = $this->adapter->generate('Test prompt', [
    'temperature' => 0.8,
    'max_tokens' => 50,
  ]);

  expect($result)->toBe('Generated text response');
  expect($this->historyContainer)->toHaveCount(1);

  $request = $this->historyContainer[0]['request'];
  $body = json_decode($request->getBody()->getContents(), true);

  expect($request->getMethod())->toBe('POST');
  expect((string) $request->getUri())->toBe('https://test-api.openai.com/v1/completions');
  expect($body)->toHaveKey('prompt', 'Test prompt');
  expect($body)->toHaveKey('temperature', 0.8);
  expect($body)->toHaveKey('max_tokens', 50);
  expect($body)->toHaveKey('model', 'gpt-4-test');
});

test('can get chat completion', function () {
  $this->mockHandler->append(new Response(200, [], json_encode([
    'choices' => [
      ['message' => ['role' => 'assistant', 'content' => 'Chat response']]
    ],
    'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20, 'total_tokens' => 30],
    'id' => 'chat-test-id',
  ])));

  $messages = [
    ['role' => 'user', 'content' => 'Hello']
  ];

  $result = $this->adapter->chat($messages, [
    'temperature' => 0.8,
  ]);

  expect($result)->toBeArray();
  expect($result)->toHaveKeys(['message', 'usage', 'id']);
  expect($result['message'])->toBeArray();
  expect($result['message'])->toHaveKeys(['role', 'content']);
  expect($result['message']['content'])->toBe('Chat response');

  expect($this->historyContainer)->toHaveCount(1);

  $request = $this->historyContainer[0]['request'];
  $body = json_decode($request->getBody()->getContents(), true);

  expect($request->getMethod())->toBe('POST');
  expect((string) $request->getUri())->toBe('https://test-api.openai.com/v1/chat/completions');
  expect($body)->toHaveKey('messages', $messages);
  expect($body)->toHaveKey('temperature', 0.8);
  expect($body)->toHaveKey('model', 'gpt-4-test');
});

test('can generate embeddings', function () {
  $this->mockHandler->append(new Response(200, [], json_encode([
    'data' => [
      ['embedding' => [0.1, 0.2, 0.3]],
    ]
  ])));

  $result = $this->adapter->embeddings('Test text', [
    'model' => 'custom-embedding-model',
  ]);

  expect($result)->toBeArray();
  expect($result[0])->toHaveKey('embedding');
  expect($result[0]['embedding'])->toBeArray();

  expect($this->historyContainer)->toHaveCount(1);

  $request = $this->historyContainer[0]['request'];
  $body = json_decode($request->getBody()->getContents(), true);

  expect($request->getMethod())->toBe('POST');
  expect((string) $request->getUri())->toBe('https://test-api.openai.com/v1/embeddings');
  expect($body)->toHaveKey('input', 'Test text');
  expect($body)->toHaveKey('model', 'custom-embedding-model');
});

test('throws exception when api returns error', function () {
  $this->mockHandler->append(new Response(400, [], json_encode([
    'error' => [
      'message' => 'Test API error'
    ]
  ])));

  $this->adapter->generate('Test prompt');
})->throws(RuntimeException::class, 'OpenAI API error: Test API error');
