<?php

use AiAgent\Adapters\OpenAiAdapter;
use AiAgent\Exceptions\ApiRequestException; // Added for specific exception testing
use AiAgent\Exceptions\ApiAuthenticationException; // Added
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Mockery as m;

beforeEach(function () {
    $this->mockHandler = new MockHandler();
    $this->historyContainer = [];
    $history = Middleware::history($this->historyContainer); // Corrected variable name
    $this->handlerStack = HandlerStack::create($this->mockHandler);
    $this->handlerStack->push($history);

    $this->client = new Client(['handler' => $this->handlerStack]);

    $this->config = [
        'api_key' => 'test-openai-key',
        'api_base_url' => 'https://test-api.openai.com/v1', // Ensure this is used
        'model' => 'gpt-4-test', // Default model for generate/chat
        'embedding_model' => 'embedding-test', // Default model for embeddings
        'max_tokens' => 100, // Default max_tokens for generate (if applicable)
        'temperature' => 0.5, // Default temperature
    ];

    $this->adapter = new OpenAiAdapter($this->config);

    // Replace the HTTP client with our Guzzle mock setup
    $reflection = new ReflectionProperty(OpenAiAdapter::class, 'client');
    $reflection->setAccessible(true);
    $reflection->setValue($this->adapter, $this->client);
});

afterEach(function () {
    m::close();
});

test('constructor validates api_key', function () {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('The [api_key] configuration is required for OpenAiAdapter.');
    new OpenAiAdapter([]);
});

test('constructor sets api base url from config if provided', function () {
    $adapterWithCustomBase = new OpenAiAdapter([
        'api_key' => 'test-key',
        'api_base_url' => 'https://custom.openai.com/custom',
    ]);
    $reflection = new ReflectionProperty(OpenAiAdapter::class, 'apiBaseUrl');
    $reflection->setAccessible(true);
    expect($reflection->getValue($adapterWithCustomBase))->toBe('https://custom.openai.com/custom');
});

test('constructor uses default api base url if not in config', function () {
    $adapterWithoutCustomBase = new OpenAiAdapter(['api_key' => 'test-key']);
    $reflection = new ReflectionProperty(OpenAiAdapter::class, 'apiBaseUrl');
    $reflection->setAccessible(true);
    expect($reflection->getValue($adapterWithoutCustomBase))->toBe('https://api.openai.com/v1');
});


test('generate method uses chat/completions endpoint and correct payload structure', function () {
    $this->mockHandler->append(new Response(200, [], json_encode([
        'choices' => [
            ['message' => ['role' => 'assistant', 'content' => 'Generated chat response']]
        ]
    ])));

    $prompt = 'Test prompt for generate';
    $options = ['temperature' => 0.88, 'max_tokens' => 150];
    $result = $this->adapter->generate($prompt, $options);

    expect($result)->toBe('Generated chat response');
    expect($this->historyContainer)->toHaveCount(1);

    $request = $this->historyContainer[0]['request'];
    $body = json_decode($request->getBody()->getContents(), true);

    expect($request->getMethod())->toBe('POST');
    // Verify endpoint is chat/completions
    expect((string) $request->getUri())->toBe('https://test-api.openai.com/v1/chat/completions');
    // Verify payload structure for chat/completions
    expect($body['messages'])->toBe([['role' => 'user', 'content' => $prompt]]);
    expect($body)->toHaveKey('temperature', 0.88);
    expect($body)->toHaveKey('max_tokens', 150);
    expect($body)->toHaveKey('model', $this->config['model']); // Check if default model from config is used
});

test('chat method sends correct request and parses response', function () {
    $this->mockHandler->append(new Response(200, [], json_encode([
        'choices' => [
            ['message' => ['role' => 'assistant', 'content' => 'Chat response']]
        ],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20, 'total_tokens' => 30],
        'id' => 'chat-test-id',
    ])));

    $messages = [['role' => 'user', 'content' => 'Hello']];
    $options = ['temperature' => 0.8, 'model' => 'gpt-3.5-turbo-test']; // Override default model

    $result = $this->adapter->chat($messages, $options);

    expect($result)->toBeArray()->toHaveKeys(['message', 'usage', 'id']);
    expect($result['message']['content'])->toBe('Chat response');
    expect($this->historyContainer)->toHaveCount(1);

    $request = $this->historyContainer[0]['request'];
    $body = json_decode($request->getBody()->getContents(), true);

    expect($request->getMethod())->toBe('POST');
    expect((string) $request->getUri())->toBe('https://test-api.openai.com/v1/chat/completions');
    expect($body['messages'])->toBe($messages);
    expect($body['temperature'])->toBe(0.8);
    expect($body['model'])->toBe('gpt-3.5-turbo-test'); // Check if model from options is used
});

test('embeddings method sends correct request and parses response', function () {
    $this->mockHandler->append(new Response(200, [], json_encode([
        'data' => [
            ['embedding' => [0.1, 0.2, 0.3], 'index' => 0, 'object' => 'embedding'],
        ]
    ])));

    $input = 'Test text for embedding';
    $options = ['model' => 'custom-embedding-model']; // Override default embedding model

    $result = $this->adapter->embeddings($input, $options);

    expect($result)->toBeArray();
    expect($result[0]['embedding'] ?? null)->toBe([0.1, 0.2, 0.3]);
    expect($this->historyContainer)->toHaveCount(1);

    $request = $this->historyContainer[0]['request'];
    $body = json_decode($request->getBody()->getContents(), true);

    expect($request->getMethod())->toBe('POST');
    expect((string) $request->getUri())->toBe('https://test-api.openai.com/v1/embeddings');
    expect($body['input'])->toBe($input);
    expect($body['model'])->toBe('custom-embedding-model'); // Check if model from options is used
});

test('throws ApiRequestException for 400 error from API', function () {
    $errorMessage = 'Invalid request parameters.';
    $this->mockHandler->append(new Response(400, [], json_encode([
        'error' => ['message' => $errorMessage]
    ])));

    $this->expectException(ApiRequestException::class);
    $this->expectExceptionMessageMatches("/API Error \(Status: 400\): {$errorMessage}/");
    $this->adapter->generate('Test prompt');
});

test('throws ApiAuthenticationException for 401 error from API', function () {
    $errorMessage = 'Invalid API key.';
    $this->mockHandler->append(new Response(401, [], json_encode([
        'error' => ['message' => $errorMessage]
    ])));

    $this->expectException(ApiAuthenticationException::class);
    $this->expectExceptionMessageMatches("/API Error \(Status: 401\): {$errorMessage}/");
    $this->adapter->chat([['role' => 'user', 'content' => 'test']]);
});
