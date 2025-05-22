<?php

namespace AiAgent\Tests\Unit\Http\Controllers;

use AiAgent\Facades\AiAgent;
use AiAgent\Http\Controllers\AiController;
use AiAgent\Http\Requests\AiChatRequest;
use AiAgent\Http\Requests\AiEmbeddingsRequest;
use AiAgent\Http\Requests\AiGenerateRequest;
use AiAgent\Exceptions\ApiException;
use AiAgent\Exceptions\ApiRequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Mockery as m;

uses(\AiAgent\Tests\TestCase::class); // Assuming a base TestCase

beforeEach(function () {
    $this->controller = new AiController();

    // Mock the facade
    AiAgent::partialMock(); 
});

afterEach(function () {
    m::close();
});

test('providers method returns correct data', function () {
    $providerNames = ['openai_test', 'anthropic_test'];
    $defaultProvider = 'openai_test';

    AiAgent::shouldReceive('getProviderNames')
        ->once()
        ->andReturn($providerNames);
    
    // Mock config facade if AiController uses it directly, or ensure config is set for testing
    Config::shouldReceive('get')->with('ai-agent.default_provider')->andReturn($defaultProvider);
    // If using global config() helper, it might be harder to mock per test.
    // Consider dependency injection for config if it becomes an issue.
    // For now, assuming Config facade is used or test config is pre-set.
    // If AiController uses global config(), this mock won't apply.
    // Let's assume it's okay for now or the global config is set in a bootstrap/TestCase.
    // Re-checking AiController, it uses global config().
    // So, we'll use the global config helper for setting.
    config(['ai-agent.default_provider' => $defaultProvider]);


    $response = $this->controller->providers();

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);
    $responseData = $response->getData(true);
    expect($responseData['providers'])->toEqual($providerNames);
    expect($responseData['default'])->toEqual($defaultProvider);
});

describe('generate method', function () {
    it('handles successful generation', function () {
        $mockRequest = m::mock(AiGenerateRequest::class);
        $validatedData = [
            'prompt' => 'Test prompt',
            'options' => ['temp' => 1],
            'provider' => 'test_generate_provider',
        ];
        $expectedResult = 'Generated text successfully.';

        $mockRequest->shouldReceive('validated')->once()->andReturn($validatedData);
        AiAgent::shouldReceive('generate')
            ->once()
            ->with($validatedData['prompt'], $validatedData['options'], $validatedData['provider'])
            ->andReturn($expectedResult);

        $response = $this->controller->generate($mockRequest);

        expect($response)->toBeInstanceOf(JsonResponse::class);
        expect($response->getStatusCode())->toBe(200);
        expect($response->getData(true))->toEqual(['result' => $expectedResult]);
    });

    it('handles ApiException from AiAgent generate', function () {
        $mockRequest = m::mock(AiGenerateRequest::class);
        $validatedData = ['prompt' => 'Error prompt'];
        $exceptionMessage = "API request failed for generate";
        $statusCode = 400;

        $mockRequest->shouldReceive('validated')->once()->andReturn($validatedData);
        AiAgent::shouldReceive('generate')
            ->once()
            ->andThrow(new ApiRequestException($exceptionMessage, $statusCode)); // Using a specific child

        $response = $this->controller->generate($mockRequest);
        
        expect($response)->toBeInstanceOf(JsonResponse::class);
        expect($response->getStatusCode())->toBe($statusCode); // Check if status code from exception is used
        $responseData = $response->getData(true);
        expect($responseData['error'])->toBe($exceptionMessage);
        expect($responseData['type'])->toBe('ApiRequestException');
    });
    
    it('handles generic Exception from AiAgent generate', function () {
        $mockRequest = m::mock(AiGenerateRequest::class);
        $validatedData = ['prompt' => 'Generic error prompt'];
        $exceptionMessage = "Generic failure";

        $mockRequest->shouldReceive('validated')->once()->andReturn($validatedData);
        AiAgent::shouldReceive('generate')
            ->once()
            ->andThrow(new \Exception($exceptionMessage));

        $response = $this->controller->generate($mockRequest);
        
        expect($response)->toBeInstanceOf(JsonResponse::class);
        expect($response->getStatusCode())->toBe(500);
        expect($response->getData(true)['error'])->toBe('An unexpected error occurred.');
    });
});

describe('chat method', function () {
    it('handles successful chat completion', function () {
        $mockRequest = m::mock(AiChatRequest::class);
        $validatedData = [
            'messages' => [['role' => 'user', 'content' => 'Hello']],
            'options' => ['stream' => false],
            'provider' => 'test_chat_provider',
        ];
        $expectedResult = ['message' => ['role' => 'assistant', 'content' => 'Hi!']];

        $mockRequest->shouldReceive('validated')->once()->andReturn($validatedData);
        AiAgent::shouldReceive('chat')
            ->once()
            ->with($validatedData['messages'], $validatedData['options'], $validatedData['provider'])
            ->andReturn($expectedResult);

        $response = $this->controller->chat($mockRequest);

        expect($response)->toBeInstanceOf(JsonResponse::class);
        expect($response->getStatusCode())->toBe(200);
        expect($response->getData(true))->toEqual(['result' => $expectedResult]);
    });
    
    it('handles ApiException from AiAgent chat', function () {
        $mockRequest = m::mock(AiChatRequest::class);
        $validatedData = ['messages' => [['role' => 'user', 'content' => 'Error chat']]];
        $exceptionMessage = "API request failed for chat";
        $statusCode = 401; // Example: Authentication error

        $mockRequest->shouldReceive('validated')->once()->andReturn($validatedData);
        AiAgent::shouldReceive('chat')
            ->once()
            ->andThrow(new \AiAgent\Exceptions\ApiAuthenticationException($exceptionMessage, $statusCode));

        $response = $this->controller->chat($mockRequest);
        
        expect($response)->toBeInstanceOf(JsonResponse::class);
        expect($response->getStatusCode())->toBe($statusCode);
        $responseData = $response->getData(true);
        expect($responseData['error'])->toBe($exceptionMessage);
        expect($responseData['type'])->toBe('ApiAuthenticationException');
    });
});

describe('embeddings method', function () {
    it('handles successful embeddings generation', function () {
        $mockRequest = m::mock(AiEmbeddingsRequest::class);
        $validatedData = [
            'input' => 'Test input for embeddings',
            'options' => ['model' => 'text-embed-test'],
            'provider' => 'test_embed_provider',
        ];
        $expectedResult = [[0.1, 0.2, 0.3]];

        $mockRequest->shouldReceive('validated')->once()->andReturn($validatedData);
        AiAgent::shouldReceive('embeddings')
            ->once()
            ->with($validatedData['input'], $validatedData['options'], $validatedData['provider'])
            ->andReturn($expectedResult);

        $response = $this->controller->embeddings($mockRequest);

        expect($response)->toBeInstanceOf(JsonResponse::class);
        expect($response->getStatusCode())->toBe(200);
        expect($response->getData(true))->toEqual(['result' => $expectedResult]);
    });

    it('handles ApiException from AiAgent embeddings', function () {
        $mockRequest = m::mock(AiEmbeddingsRequest::class);
        $validatedData = ['input' => 'Error input embed'];
        $exceptionMessage = "API request failed for embeddings";
        $statusCode = 500; // Example: Server error

        $mockRequest->shouldReceive('validated')->once()->andReturn($validatedData);
        AiAgent::shouldReceive('embeddings')
            ->once()
            ->andThrow(new \AiAgent\Exceptions\ApiServerException($exceptionMessage, $statusCode));

        $response = $this->controller->embeddings($mockRequest);
        
        expect($response)->toBeInstanceOf(JsonResponse::class);
        expect($response->getStatusCode())->toBe($statusCode);
        $responseData = $response->getData(true);
        expect($responseData['error'])->toBe($exceptionMessage);
        expect($responseData['type'])->toBe('ApiServerException');
    });
});
