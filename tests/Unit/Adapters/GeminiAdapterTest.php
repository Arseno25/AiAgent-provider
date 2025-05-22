<?php

use AiAgent\Adapters\GeminiAdapter;
use AiAgent\Exceptions\ApiAuthenticationException;
use AiAgent\Exceptions\ApiRequestException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException; // Keep for constructor test
use Mockery as m;
// Removed RuntimeException as we use specific exceptions now

beforeEach(function () {
    $this->historyContainer = [];
    $this->mockHandler = new MockHandler();
    $historyMiddleware = Middleware::history($this->historyContainer);

    $stack = HandlerStack::create($this->mockHandler);
    $stack->push($historyMiddleware);

    $this->httpClient = new Client(['handler' => $stack]);

    $this->config = [
        'api_key' => 'test-gemini-key',
        'model' => 'gemini-1.0-pro-test', // Test specific default model
        'embedding_model' => 'text-embedding-004-test', // Test specific default embedding model
        'max_tokens' => 1024, // Default for generate/chat
        'temperature' => 0.75,
    ];

    $this->adapter = new GeminiAdapter($this->config);

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
    $this->expectExceptionMessage('The [api_key] configuration is required for GeminiAdapter.');
    new GeminiAdapter(['model' => 'gemini-pro']);
});

test('constructor sets api base url from config', function () {
    $adapter = new GeminiAdapter([
        'api_key' => 'test-key',
        'api_base_url' => 'https://custom-gemini-url.googleapis.com/v1beta',
        'model' => 'gemini-pro'
    ]);
    $reflection = new ReflectionClass($adapter);
    $property = $reflection->getProperty('apiBaseUrl');
    $property->setAccessible(true);
    expect($property->getValue($adapter))->toBe('https://custom-gemini-url.googleapis.com/v1beta');
});

test('generate method sends correct request and parses response', function () {
    $this->mockHandler->append(new Response(200, [], json_encode([
        'candidates' => [['content' => ['parts' => [['text' => 'Generated text']]], 'finishReason' => 'STOP']],
        'usageMetadata' => ['promptTokenCount' => 5, 'candidatesTokenCount' => 10, 'totalTokenCount' => 15]
    ])));

    $prompt = 'Test generate prompt';
    $options = ['temperature' => 0.9, 'max_tokens' => 500]; // Override defaults
    $result = $this->adapter->generate($prompt, $options);

    expect($result)->toBe('Generated text');
    expect($this->historyContainer)->toHaveCount(1);

    $request = $this->historyContainer[0]['request'];
    $uri = $request->getUri();
    $body = json_decode((string) $request->getBody(), true);

    expect($request->getMethod())->toBe('POST');
    expect((string) $uri)->toContain("models/gemini-1.0-pro-test:generateContent");
    expect((string) $uri)->toContain("key=test-gemini-key"); // API key in query
    expect($body['contents'][0]['parts'][0]['text'])->toBe($prompt);
    expect($body['generationConfig']['temperature'])->toBe(0.9);
    expect($body['generationConfig']['maxOutputTokens'])->toBe(500);
});

test('chat method sends correct request and parses response structure', function () {
    $apiResponse = [
        'candidates' => [['content' => ['parts' => [['text' => 'Gemini chat response']]], 'index' => 0]],
        'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 20]
    ];
    $this->mockHandler->append(new Response(200, [], json_encode($apiResponse)));

    $messages = [['role' => 'user', 'content' => 'Hello Gemini']];
    $result = $this->adapter->chat($messages, ['model' => 'gemini-1.5-pro-latest']); // Override model

    expect($result)->toBeArray()->toHaveKeys(['message', 'usage', 'id']);
    expect($result['message'])->toEqual(['role' => 'assistant', 'content' => 'Gemini chat response']);
    expect($result['usage'])->toEqual([
        'prompt_tokens' => 10,
        'completion_tokens' => 20,
        'total_tokens' => 30
    ]);
    expect($result['id'])->toBe(0); // Candidate index

    expect($this->historyContainer)->toHaveCount(1);
    $request = $this->historyContainer[0]['request'];
    $uri = $request->getUri();
    $body = json_decode((string) $request->getBody(), true);

    expect((string) $uri)->toContain("models/gemini-1.5-pro-latest:generateContent");
    expect($body['contents'][0]['parts'][0]['text'])->toBe('Hello Gemini'); // Basic check, formatMessages is tested separately
});

describe('formatMessages for Gemini', function () {
    it('formats basic user and assistant messages', function () {
        $messages = [
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi'],
        ];
        $method = new ReflectionMethod(GeminiAdapter::class, 'formatMessages');
        $method->setAccessible(true);
        $formatted = $method->invoke($this->adapter, $messages);
        expect($formatted)->toEqual([
            ['role' => 'user', 'parts' => [['text' => 'Hello']]],
            ['role' => 'model', 'parts' => [['text' => 'Hi']]],
        ]);
    });

    it('prepends system prompt to first user message', function () {
        $messages = [
            ['role' => 'system', 'content' => 'Be helpful.'],
            ['role' => 'user', 'content' => 'How are you?'],
        ];
        $method = new ReflectionMethod(GeminiAdapter::class, 'formatMessages');
        $method->setAccessible(true);
        $formatted = $method->invoke($this->adapter, $messages);
        expect($formatted[0]['role'])->toBe('user');
        expect($formatted[0]['parts'][0]['text'])->toBe("Be helpful.\n\nHow are you?");
    });

    it('concatenates multiple system prompts', function () {
        $messages = [
            ['role' => 'system', 'content' => 'Be concise.'],
            ['role' => 'system', 'content' => 'Be polite.'],
            ['role' => 'user', 'content' => 'Question.'],
        ];
        $method = new ReflectionMethod(GeminiAdapter::class, 'formatMessages');
        $method->setAccessible(true);
        $formatted = $method->invoke($this->adapter, $messages);
        expect($formatted[0]['parts'][0]['text'])->toBe("Be concise.\nBe polite.\n\nQuestion.");
    });
});


test('embeddings method uses batch endpoint and formats request/response correctly', function () {
    $apiResponse = [
        'embeddings' => [
            ['values' => [0.1, 0.2]],
            ['values' => [0.3, 0.4]],
        ]
    ];
    $this->mockHandler->append(new Response(200, [], json_encode($apiResponse)));

    $inputTexts = ['First text', 'Second text'];
    // Use the default embedding model from config
    $result = $this->adapter->embeddings($inputTexts);

    expect($result)->toBe([
        ['embedding' => [0.1, 0.2], 'index' => 0, 'object' => 'embedding'],
        ['embedding' => [0.3, 0.4], 'index' => 1, 'object' => 'embedding'],
    ]);

    expect($this->historyContainer)->toHaveCount(1);
    $request = $this->historyContainer[0]['request'];
    $uri = $request->getUri();
    $body = json_decode((string) $request->getBody(), true);

    expect((string) $uri)->toContain("models/text-embedding-004-test:batchEmbedContents");
    expect((string) $uri)->toContain("key=test-gemini-key");
    expect($body['requests'])->toHaveCount(2);
    expect($body['requests'][0]['model'])->toBe("models/text-embedding-004-test");
    expect($body['requests'][0]['content']['parts'][0]['text'])->toBe('First text');
    expect($body['requests'][1]['model'])->toBe("models/text-embedding-004-test");
    expect($body['requests'][1]['content']['parts'][0]['text'])->toBe('Second text');
});

test('embeddings with single string input', function () {
    $this->mockHandler->append(new Response(200, [], json_encode([
        'embeddings' => [['values' => [0.5, 0.6]]]
    ])));
    $result = $this->adapter->embeddings('Single input');
    expect($result)->toBe([['embedding' => [0.5, 0.6], 'index' => 0, 'object' => 'embedding']]);
    $body = json_decode((string) $this->historyContainer[0]['request']->getBody(), true);
    expect($body['requests'])->toHaveCount(1);
    expect($body['requests'][0]['content']['parts'][0]['text'])->toBe('Single input');
});


test('throws ApiRequestException for 400 error from API', function () {
    $errorMessage = 'User location is not supported for the API use.';
    $this->mockHandler->append(new Response(400, [], json_encode([
        'error' => ['code' => 400, 'message' => $errorMessage, 'status' => 'FAILED_PRECONDITION']
    ])));
    $this->expectException(ApiRequestException::class);
    $this->expectExceptionMessageMatches("/API Error \(Status: 400\): {$errorMessage}/");
    $this->adapter->generate('Test prompt');
});

test('throws ApiAuthenticationException for 403 error (permission denied) from API', function () {
    $errorMessage = 'The caller does not have permission.';
    $this->mockHandler->append(new Response(403, [], json_encode([
        'error' => ['code' => 403, 'message' => $errorMessage, 'status' => 'PERMISSION_DENIED']
    ])));
    $this->expectException(ApiAuthenticationException::class);
    $this->expectExceptionMessageMatches("/API Error \(Status: 403\): {$errorMessage}/");
    $this->adapter->chat([['role' => 'user', 'content' => 'test']]);
});
