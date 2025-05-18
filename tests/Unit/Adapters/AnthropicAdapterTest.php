<?php

use AiAgent\Adapters\AnthropicAdapter;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Mockery as m;
use RuntimeException;

beforeEach(function () {
  $this->history = [];
  $this->historyContainer = [];

  $this->mockHandler = new MockHandler();
  $history = Middleware::history($this->historyContainer);

  $stack = HandlerStack::create($this->mockHandler);
  $stack->push($history);

  $this->httpClient = new Client(['handler' => $stack]);

  $this->config = [
    'api_key' => 'test-api-key',
    'model' => 'claude-3-opus',
    'max_tokens' => 1000,
    'temperature' => 0.7,
  ];

  $this->adapter = new AnthropicAdapter($this->config);

  // Replace HTTP client with our mock
  $reflection = new ReflectionClass($this->adapter);
  $property = $reflection->getProperty('client');
  $property->setAccessible(true);
  $property->setValue($this->adapter, $this->httpClient);
});

test('constructor validates api_key', function () {
  expect(fn() => new AnthropicAdapter(['model' => 'claude-3-opus']))
    ->toThrow(InvalidArgumentException::class, 'API key is required');
});

test('constructor sets api base url from config', function () {
  $adapter = new AnthropicAdapter([
    'api_key' => 'test-key',
    'api_base_url' => 'https://custom-url.com',
    'model' => 'claude-3-opus'
  ]);

  $reflection = new ReflectionClass($adapter);
  $property = $reflection->getProperty('apiBaseUrl');
  $property->setAccessible(true);

  expect($property->getValue($adapter))->toBe('https://custom-url.com');
});

test('can generate content', function () {
  $this->mockHandler->append(
    new Response(200, [], json_encode([
      'content' => [
        [
          'type' => 'text',
          'text' => 'Generated content'
        ]
      ],
      'usage' => [
        'input_tokens' => 10,
        'output_tokens' => 20
      ]
    ]))
  );

  $result = $this->adapter->generate('Test prompt');

  expect($result)->toBe('Generated content');

  $request = $this->historyContainer[0]['request'];
  expect($request->getMethod())->toBe('POST');
  expect((string) $request->getUri())->toContain('messages');

  $requestBody = json_decode((string) $request->getBody(), true);
  expect($requestBody)->toHaveKey('messages');
  expect($requestBody['messages'][0]['content'])->toBe('Test prompt');
  expect($requestBody)->toHaveKey('model');
  expect($requestBody['model'])->toBe('claude-3-opus');
  expect($requestBody)->toHaveKey('max_tokens');
  expect($requestBody['max_tokens'])->toBe(1000);
  expect($requestBody)->toHaveKey('temperature');
  expect($requestBody['temperature'])->toBe(0.7);
});

test('can get chat completion', function () {
  $this->mockHandler->append(
    new Response(200, [], json_encode([
      'content' => [
        [
          'type' => 'text',
          'text' => 'Chat response'
        ]
      ],
      'usage' => [
        'input_tokens' => 15,
        'output_tokens' => 25
      ]
    ]))
  );

  $messages = [
    ['role' => 'user', 'content' => 'Hello'],
    ['role' => 'assistant', 'content' => 'Hi there'],
    ['role' => 'user', 'content' => 'How are you?']
  ];

  $result = $this->adapter->chat($messages);

  expect($result)->toBe('Chat response');

  $request = $this->historyContainer[0]['request'];
  expect($request->getMethod())->toBe('POST');
  expect((string) $request->getUri())->toContain('messages');

  $requestBody = json_decode((string) $request->getBody(), true);
  expect($requestBody)->toHaveKey('messages');
  expect(count($requestBody['messages']))->toBe(count($messages));
});

test('throws exception when api returns error', function () {
  $this->mockHandler->append(
    new Response(400, [], json_encode([
      'error' => [
        'type' => 'invalid_request_error',
        'message' => 'Invalid request',
      ]
    ]))
  );

  expect(fn() => $this->adapter->generate('Test prompt'))
    ->toThrow(RuntimeException::class, 'Anthropic API error: Invalid request');
});
