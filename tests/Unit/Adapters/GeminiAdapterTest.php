<?php

use AiAgent\Adapters\GeminiAdapter;
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
    'model' => 'gemini-pro',
    'embedding_model' => 'embedding-model',
    'max_tokens' => 1000,
    'temperature' => 0.7,
  ];

  $this->adapter = new GeminiAdapter($this->config);

  // Replace HTTP client with our mock
  $reflection = new ReflectionClass($this->adapter);
  $property = $reflection->getProperty('client');
  $property->setAccessible(true);
  $property->setValue($this->adapter, $this->httpClient);
});

test('constructor validates api_key', function () {
  expect(fn() => new GeminiAdapter(['model' => 'gemini-pro']))
    ->toThrow(InvalidArgumentException::class, 'API key is required');
});

test('constructor sets api base url', function () {
  $adapter = new GeminiAdapter([
    'api_key' => 'test-key',
    'api_base_url' => 'https://custom-url.com',
    'model' => 'gemini-pro'
  ]);

  $reflection = new ReflectionClass($adapter);
  $property = $reflection->getProperty('apiBaseUrl');
  $property->setAccessible(true);

  expect($property->getValue($adapter))->toBe('https://custom-url.com');
});

test('can generate content', function () {
  $this->mockHandler->append(
    new Response(200, [], json_encode([
      'candidates' => [
        [
          'content' => [
            'parts' => [
              ['text' => 'Generated content']
            ]
          ],
          'finishReason' => 'STOP'
        ]
      ],
      'usageMetadata' => [
        'promptTokenCount' => 10,
        'candidatesTokenCount' => 20,
        'totalTokenCount' => 30
      ]
    ]))
  );

  $result = $this->adapter->generate('Test prompt');

  expect($result)->toBe('Generated content');

  $request = $this->historyContainer[0]['request'];
  expect($request->getMethod())->toBe('POST');
  expect((string) $request->getUri())->toContain('generateContent');

  $requestBody = json_decode((string) $request->getBody(), true);
  expect($requestBody)->toHaveKey('contents');
  expect($requestBody['contents'][0]['parts'][0]['text'])->toBe('Test prompt');
  expect($requestBody)->toHaveKey('generationConfig');
  expect($requestBody['generationConfig']['maxOutputTokens'])->toBe(1000);
  expect($requestBody['generationConfig']['temperature'])->toBe(0.7);
});

test('can get chat completion', function () {
  $this->mockHandler->append(
    new Response(200, [], json_encode([
      'candidates' => [
        [
          'content' => [
            'parts' => [
              ['text' => 'Chat response']
            ]
          ],
          'finishReason' => 'STOP'
        ]
      ],
      'usageMetadata' => [
        'promptTokenCount' => 15,
        'candidatesTokenCount' => 25,
        'totalTokenCount' => 40
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
  expect((string) $request->getUri())->toContain('generateContent');

  $requestBody = json_decode((string) $request->getBody(), true);
  expect($requestBody)->toHaveKey('contents');
  expect(count($requestBody['contents'][0]['parts']))->toBe(count($messages));
});

test('can generate embeddings', function () {
  $this->mockHandler->append(
    new Response(200, [], json_encode([
      'embedding' => [
        'values' => [0.1, 0.2, 0.3, 0.4]
      ]
    ]))
  );

  $result = $this->adapter->embeddings('Test input');

  expect($result)->toBe([0.1, 0.2, 0.3, 0.4]);

  $request = $this->historyContainer[0]['request'];
  expect($request->getMethod())->toBe('POST');
  expect((string) $request->getUri())->toContain('embeddings');

  $requestBody = json_decode((string) $request->getBody(), true);
  expect($requestBody)->toHaveKey('model');
  expect($requestBody['model'])->toBe('embedding-model');
  expect($requestBody)->toHaveKey('content');
  expect($requestBody['content']['parts'][0]['text'])->toBe('Test input');
});

test('throws exception when api returns error', function () {
  $this->mockHandler->append(
    new Response(400, [], json_encode([
      'error' => [
        'code' => 400,
        'message' => 'Invalid request',
        'status' => 'INVALID_ARGUMENT'
      ]
    ]))
  );

  expect(fn() => $this->adapter->generate('Test prompt'))
    ->toThrow(RuntimeException::class, 'Gemini API error: Invalid request');
});
