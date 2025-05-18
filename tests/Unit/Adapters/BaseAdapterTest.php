<?php

use AiAgent\Tests\Stubs\TestAdapter;

beforeEach(function () {
  $this->config = [
    'api_key' => 'test-key',
    'name' => 'custom-name',
    'timeout' => 60,
    'connect_timeout' => 20,
  ];

  $this->adapter = new TestAdapter($this->config);
});

test('can get provider name from config', function () {
  expect($this->adapter->getName())->toBe('custom-name');
});

test('can get provider name from class basename when not in config', function () {
  $adapter = new TestAdapter(['api_key' => 'test-key']);
  expect($adapter->getName())->toBe('TestAdapter');
});

test('can get provider info', function () {
  $info = $this->adapter->info();

  expect($info)->toBeArray();
  expect($info)->toHaveKeys(['name', 'class', 'features']);
  expect($info['name'])->toBe('custom-name');
  expect($info['class'])->toBe(TestAdapter::class);
  expect($info['features'])->toBeArray();
  expect($info['features'])->toHaveKeys(['generate', 'chat', 'embeddings']);
});

test('can get config value', function () {
  $method = new ReflectionMethod(TestAdapter::class, 'getConfig');
  $method->setAccessible(true);

  expect($method->invoke($this->adapter, 'api_key'))->toBe('test-key');
  expect($method->invoke($this->adapter, 'timeout'))->toBe(60);
  expect($method->invoke($this->adapter, 'non_existent', 'default'))->toBe('default');
});

test('can check if config exists', function () {
  $method = new ReflectionMethod(TestAdapter::class, 'hasConfig');
  $method->setAccessible(true);

  expect($method->invoke($this->adapter, 'api_key'))->toBeTrue();
  expect($method->invoke($this->adapter, 'non_existent'))->toBeFalse();
});

test('validate config throws exception when required fields are missing', function () {
  $method = new ReflectionMethod(TestAdapter::class, 'validateConfig');
  $method->setAccessible(true);

  // This should not throw an exception
  $method->invoke($this->adapter, ['api_key']);

  // This should throw an exception
  $method->invoke($this->adapter, ['non_existent_key']);
})->throws(InvalidArgumentException::class);
