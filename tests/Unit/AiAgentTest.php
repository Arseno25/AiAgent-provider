<?php

use AiAgent\AiAgent;
use AiAgent\Contracts\AiProviderInterface;
use AiAgent\Exceptions\ProviderNotFoundException;
use AiAgent\Services\AiLoggerService;
use AiAgent\Services\AiService;
use AiAgent\Tests\Stubs\TestAdapter;
use Illuminate\Cache\Repository as Cache;
use Illuminate\Foundation\Application;
use Mockery as m;

beforeEach(function () {
  $this->app = m::mock(Application::class);
  $this->aiService = m::mock(AiService::class);
  $this->loggerService = m::mock(AiLoggerService::class);
  $this->provider = m::mock(TestAdapter::class, AiProviderInterface::class);
  $this->cache = m::mock(Cache::class);

  $this->app->shouldReceive('make')
    ->with(AiService::class)
    ->andReturn($this->aiService);

  $this->app->shouldReceive('make')
    ->with(AiLoggerService::class)
    ->andReturn($this->loggerService);

  $this->app->shouldReceive('make')
    ->with('cache')
    ->andReturn($this->cache);

  $this->setUpConfigMock();

  $this->agent = new AiAgent($this->app);
});

afterEach(function () {
  m::close();
});

function setUpConfigMock()
{
  $config = [
    'ai-agent.default_provider' => 'test',
    'ai-agent.providers' => [
      'test' => [
        'adapter' => TestAdapter::class,
        'enabled' => true,
        'config' => ['api_key' => 'test-key'],
      ],
    ],
    'ai-agent.cache.enabled' => false,
    'ai-agent.cache.ttl' => 60,
    'ai-agent.cache.prefix' => 'prefix_',
  ];

  foreach ($config as $key => $value) {
    config()->set($key, $value);
  }
}

test('can get provider', function () {
  $this->aiService->shouldReceive('resolveProvider')
    ->once()
    ->with(TestAdapter::class, m::any())
    ->andReturn($this->provider);

  $provider = $this->agent->provider('test');

  expect($provider)->toBe($this->provider);
});

test('throws exception when provider not found', function () {
  $this->agent->provider('unknown');
})->throws(ProviderNotFoundException::class, 'AI provider [unknown] not found.');

test('can generate content', function () {
  $prompt = 'Test prompt';
  $response = 'Generated content';
  $options = ['temperature' => 0.7];

  $this->aiService->shouldReceive('resolveProvider')
    ->once()
    ->andReturn($this->provider);

  $this->provider->shouldReceive('generate')
    ->once()
    ->with($prompt, $options)
    ->andReturn($response);

  $this->loggerService->shouldReceive('log')
    ->once();

  $result = $this->agent->generate($prompt, $options, 'test');

  expect($result)->toBe($response);
});

test('can get chat completion', function () {
  $messages = [
    ['role' => 'user', 'content' => 'Hello'],
  ];
  $response = [
    'message' => ['role' => 'assistant', 'content' => 'Hi there'],
    'usage' => ['total_tokens' => 20],
  ];
  $options = ['temperature' => 0.7];

  $this->aiService->shouldReceive('resolveProvider')
    ->once()
    ->andReturn($this->provider);

  $this->provider->shouldReceive('chat')
    ->once()
    ->with($messages, $options)
    ->andReturn($response);

  $this->loggerService->shouldReceive('log')
    ->once();

  $result = $this->agent->chat($messages, $options, 'test');

  expect($result)->toBe($response);
});

test('can generate embeddings', function () {
  $input = 'test text';
  $response = [
    ['embedding' => [0.1, 0.2, 0.3]],
  ];
  $options = ['model' => 'embedding-model'];

  $this->aiService->shouldReceive('resolveProvider')
    ->once()
    ->andReturn($this->provider);

  $this->provider->shouldReceive('embeddings')
    ->once()
    ->with($input, $options)
    ->andReturn($response);

  $this->loggerService->shouldReceive('log')
    ->once();

  $result = $this->agent->embeddings($input, $options, 'test');

  expect($result)->toBe($response);
});

test('can get provider names', function () {
  $result = $this->agent->getProviderNames();

  expect($result)->toBeArray();
  expect($result)->toContain('test');
});
