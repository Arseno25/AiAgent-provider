<?php

namespace AiAgent\Tests;

use AiAgent\Providers\AiAgentServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
  /**
   * Setup the test environment.
   */
  protected function setUp(): void
  {
    parent::setUp();
    $this->withoutExceptionHandling();
  }

  /**
   * Define environment setup.
   *
   * @param  \Illuminate\Foundation\Application  $app
   * @return void
   */
  protected function getEnvironmentSetUp($app)
  {
    // Setup default database for testing
    $app['config']->set('database.default', 'testing');
    $app['config']->set('database.connections.testing', [
      'driver' => 'sqlite',
      'database' => ':memory:',
      'prefix' => '',
    ]);

    // Set up default AI provider configurations
    $app['config']->set('ai-agent.default_provider', 'test');
    $app['config']->set('ai-agent.providers.test', [
      'enabled' => true,
      'adapter' => \AiAgent\Tests\Stubs\TestAdapter::class,
      'api_key' => 'test-api-key',
    ]);
  }

  /**
   * Get package providers.
   *
   * @param  \Illuminate\Foundation\Application  $app
   * @return array<int, class-string>
   */
  protected function getPackageProviders($app)
  {
    return [
      AiAgentServiceProvider::class,
    ];
  }

  /**
   * Get package aliases.
   *
   * @param  \Illuminate\Foundation\Application  $app
   * @return array<string, class-string>
   */
  protected function getPackageAliases($app)
  {
    return [
      'AiAgent' => 'AiAgent\Facades\AiAgent',
    ];
  }
}
