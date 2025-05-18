<?php

use AiAgent\Contracts\AiProviderInterface;
use AiAgent\Exceptions\AdapterNotFoundException;
use AiAgent\Services\AiService;
use AiAgent\Tests\Stubs\TestAdapter;
use Illuminate\Contracts\Container\Container;
use Mockery as m;

beforeEach(function () {
  $this->container = m::mock(Container::class);
  $this->service = new AiService($this->container);
});

afterEach(function () {
  m::close();
});

test('can resolve adapter from class name', function () {
  $config = ['api_key' => 'test-key'];
  $adapter = $this->service->resolveProvider(TestAdapter::class, $config);

  expect($adapter)->toBeInstanceOf(AiProviderInterface::class);
  expect($adapter)->toBeInstanceOf(TestAdapter::class);
});

test('throws exception when adapter is not found', function () {
  $config = ['api_key' => 'test-key'];

  $this->service->resolveProvider('NonExistentAdapter', $config);
})->throws(AdapterNotFoundException::class, 'AI adapter [NonExistentAdapter] not found.');

test('can register adapter', function () {
  $this->service->registerAdapter('custom', TestAdapter::class);
  $adapters = $this->service->getAdapters();

  expect($adapters)->toBeArray();
  expect($adapters)->toHaveKey('custom');
  expect($adapters['custom'])->toBe(TestAdapter::class);
});

test('can get all registered adapters', function () {
  $this->service->registerAdapter('adapter1', 'Class1');
  $this->service->registerAdapter('adapter2', 'Class2');

  $adapters = $this->service->getAdapters();

  expect($adapters)->toBeArray();
  expect($adapters)->toHaveCount(2);
  expect($adapters)->toHaveKeys(['adapter1', 'adapter2']);
});
