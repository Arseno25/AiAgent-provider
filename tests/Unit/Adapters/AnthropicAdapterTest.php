<?php

use AiAgent\Adapters\AnthropicAdapter;
use AiAgent\Exceptions\ApiAuthenticationException;
use AiAgent\Exceptions\ApiRequestException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException; // Already used, good.
use Mockery as m;
// Removed RuntimeException as we now expect more specific exceptions.

beforeEach(function () {
    $this->historyContainer = []; // Corrected: Initialize here
    $this->mockHandler = new MockHandler();
    $historyMiddleware = Middleware::history($this->historyContainer); // Use a different variable name

    $stack = HandlerStack::create($this->mockHandler);
    $stack->push($historyMiddleware);

    $this->httpClient = new Client(['handler' => $stack]);

    $this->config = [
        'api_key' => 'test-anthropic-key',
        'model' => 'claude-3-haiku-test', // Using a different default for testing
        'max_tokens' => 1024,
        'temperature' => 0.6,
        'anthropic_version' => '2023-06-01-test', // Test custom version
    ];

    $this->adapter = new AnthropicAdapter($this->config);

    // Replace HTTP client with our mock Guzzle setup
    $reflection = new ReflectionClass($this->adapter);
    $property = $reflection->getProperty('client');
    $property->setAccessible(true);
    $property->setValue($this->adapter, $this->httpClient);
});

afterEach(function () {
    m::close();
});

test('constructor validates api_key', function () {
    $this->expectException(InvalidArgumentException::class);
    // Updated expected message to match BaseAdapter's validateConfig more closely
    $this->expectExceptionMessage('The [api_key] configuration is required for AnthropicAdapter.');
    new AnthropicAdapter(['model' => 'claude-3-opus']);
});

test('constructor sets api base url from config', function () {
    $adapter = new AnthropicAdapter([
        'api_key' => 'test-key',
        'api_base_url' => 'https://custom-anthropic-url.com/v1',
        'model' => 'claude-3-opus'
    ]);

    $reflection = new ReflectionClass($adapter);
    $property = $reflection->getProperty('apiBaseUrl');
    $property->setAccessible(true);

    expect($property->getValue($adapter))->toBe('https://custom-anthropic-url.com/v1');
});

test('can generate content using chat endpoint', function () {
    $this->mockHandler->append(
        new Response(200, [], json_encode([
            'id' => 'gen-id-123',
            'type' => 'message',
            'role' => 'assistant',
            'content' => [['type' => 'text', 'text' => 'Generated content via generate']],
            'model' => 'claude-3-haiku-test',
            'usage' => ['input_tokens' => 10, 'output_tokens' => 20]
        ]))
    );

    $result = $this->adapter->generate('Test generate prompt');
    expect($result)->toBe('Generated content via generate');

    expect($this->historyContainer)->toHaveCount(1);
    $request = $this->historyContainer[0]['request'];
    $requestBody = json_decode((string) $request->getBody(), true);

    expect($request->getMethod())->toBe('POST');
    expect((string) $request->getUri())->toContain('/v1/messages');
    expect($request->getHeaderLine('x-api-key'))->toBe('test-anthropic-key');
    expect($request->getHeaderLine('anthropic-version'))->toBe('2023-06-01-test');
    expect($requestBody['messages'][0]['content'])->toBe('Test generate prompt');
    expect($requestBody['model'])->toBe('claude-3-haiku-test');
    expect($requestBody['max_tokens'])->toBe(1024);
});

test('can get chat completion and response structure is correct', function () {
    $apiResponse = [
        'id' => 'chat-id-456',
        'type' => 'message',
        'role' => 'assistant',
        'content' => [['type' => 'text', 'text' => 'Actual chat response']],
        'model' => 'claude-3-opus-override',
        'usage' => ['input_tokens' => 15, 'output_tokens' => 25]
    ];
    $this->mockHandler->append(new Response(200, [], json_encode($apiResponse)));

    $messages = [['role' => 'user', 'content' => 'Hello']];
    $options = ['model' => 'claude-3-opus-override']; // Override model
    $result = $this->adapter->chat($messages, $options);

    // Verify the structure of the returned array
    expect($result)->toBeArray()->toHaveKeys(['message', 'usage', 'id']);
    expect($result['message'])->toEqual(['role' => 'assistant', 'content' => 'Actual chat response']);
    expect($result['usage'])->toEqual([
        'prompt_tokens' => 15,
        'completion_tokens' => 25,
        'total_tokens' => 40 // 15 + 25
    ]);
    expect($result['id'])->toBe('chat-id-456');

    expect($this->historyContainer)->toHaveCount(1);
    $request = $this->historyContainer[0]['request'];
    $requestBody = json_decode((string) $request->getBody(), true);
    expect($requestBody['model'])->toBe('claude-3-opus-override');
});

test('chat method handles system prompt from options', function () {
    $this->mockHandler->append(new Response(200, [], json_encode([
        'content' => [['type' => 'text', 'text' => 'Response with system prompt']],
        'usage' => ['input_tokens' => 5, 'output_tokens' => 5]
    ])));
    $messages = [['role' => 'user', 'content' => 'User message']];
    $options = ['system' => 'System prompt from options'];
    $this->adapter->chat($messages, $options);

    $requestBody = json_decode((string) $this->historyContainer[0]['request']->getBody(), true);
    expect($requestBody['system'])->toBe('System prompt from options');
});

test('chat method handles system prompt from messages array', function () {
    $this->mockHandler->append(new Response(200, [], json_encode([
        'content' => [['type' => 'text', 'text' => 'Response']],
        'usage' => ['input_tokens' => 5, 'output_tokens' => 5]
    ])));
    $messages = [
        ['role' => 'system', 'content' => 'System prompt from messages'],
        ['role' => 'user', 'content' => 'User message']
    ];
    $this->adapter->chat($messages);
    $requestBody = json_decode((string) $this->historyContainer[0]['request']->getBody(), true);
    expect($requestBody['system'])->toBe('System prompt from messages');
    expect(collect($requestBody['messages'])->pluck('role')->all())->not->toContain('system');
});

test('chat method system prompt from options overrides messages array', function () {
    $this->mockHandler->append(new Response(200, [], json_encode([
        'content' => [['type' => 'text', 'text' => 'Response']],
        'usage' => ['input_tokens' => 5, 'output_tokens' => 5]
    ])));
    $messages = [['role' => 'system', 'content' => 'This should be overridden'], ['role' => 'user', 'content' => 'User message']];
    $options = ['system' => 'Overriding system prompt'];
    $this->adapter->chat($messages, $options);
    $requestBody = json_decode((string) $this->historyContainer[0]['request']->getBody(), true);
    expect($requestBody['system'])->toBe('Overriding system prompt');
});


test('embeddings method throws RuntimeException', function () {
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Anthropic does not natively support a dedicated embeddings endpoint via this API version. Please use a different provider or method for embeddings.');
    $this->adapter->embeddings('Test input');
});

test('throws ApiRequestException for 400 error from API', function () {
    $errorMessage = 'Your request was malformed.';
    $this->mockHandler->append(new Response(400, [], json_encode([
        'error' => ['type' => 'invalid_request_error', 'message' => $errorMessage]
    ])));
    $this->expectException(ApiRequestException::class);
    $this->expectExceptionMessageMatches("/API Error \(Status: 400\): {$errorMessage}/");
    $this->adapter->generate('Test prompt');
});

test('throws ApiAuthenticationException for 401 error from API', function () {
    $errorMessage = 'Invalid API Key.';
    $this->mockHandler->append(new Response(401, [], json_encode([
        'error' => ['type' => 'authentication_error', 'message' => $errorMessage]
    ])));
    $this->expectException(ApiAuthenticationException::class);
    $this->expectExceptionMessageMatches("/API Error \(Status: 401\): {$errorMessage}/");
    $this->adapter->chat([['role' => 'user', 'content' => 'test']]);
});
