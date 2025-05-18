<?php

use AiAgent\Console\Commands\AiProvidersCommand;
use AiAgent\Facades\AiAgent;
use Mockery as m;
use Symfony\Component\Console\Command\Command;

beforeEach(function () {
    $this->providers = [
        'openai' => [
            'name' => 'OpenAI',
            'class' => 'AiAgent\\Adapters\\OpenAiAdapter',
            'features' => ['generate', 'chat', 'embeddings'],
        ],
        'gemini' => [
            'name' => 'Gemini',
            'class' => 'AiAgent\\Adapters\\GeminiAdapter',
            'features' => ['generate', 'chat', 'embeddings'],
        ],
        'test' => [
            'name' => 'Test',
            'class' => 'AiAgent\\Stubs\\TestAdapter',
            'features' => ['generate', 'chat', 'embeddings'],
        ],
    ];

    $this->command = new AiProvidersCommand();
});

test('returns success exit code', function () {
    AiAgent::shouldReceive('getProviderNames')
        ->once()
        ->andReturn(['openai', 'gemini', 'test']);

    AiAgent::shouldReceive('provider')
        ->times(3)
        ->andReturnUsing(function ($provider) {
            $mockAdapter = m::mock();
            $mockAdapter->shouldReceive('info')
                ->once()
                ->andReturn($this->providers[$provider]);
            return $mockAdapter;
        });

    $result = $this->artisan('ai:providers');

    $result->assertExitCode(Command::SUCCESS);
});

test('displays provider information in table format', function () {
    AiAgent::shouldReceive('getProviderNames')
        ->once()
        ->andReturn(['openai', 'gemini', 'test']);

    AiAgent::shouldReceive('provider')
        ->times(3)
        ->andReturnUsing(function ($provider) {
            $mockAdapter = m::mock();
            $mockAdapter->shouldReceive('info')
                ->once()
                ->andReturn($this->providers[$provider]);
            return $mockAdapter;
        });

    $result = $this->artisan('ai:providers');

    $result->expectsTable(
        ['Provider', 'Class', 'Features'],
        [
            ['OpenAI', 'AiAgent\\Adapters\\OpenAiAdapter', 'generate, chat, embeddings'],
            ['Gemini', 'AiAgent\\Adapters\\GeminiAdapter', 'generate, chat, embeddings'],
            ['Test', 'AiAgent\\Stubs\\TestAdapter', 'generate, chat, embeddings'],
        ]
    );
});

test('displays error message when no providers found', function () {
    AiAgent::shouldReceive('getProviderNames')
        ->once()
        ->andReturn([]);

    $result = $this->artisan('ai:providers');

    $result->expectsOutput('No AI providers found.');
    $result->assertExitCode(Command::FAILURE);
});
