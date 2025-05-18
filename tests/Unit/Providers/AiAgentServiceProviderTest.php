<?php

use AiAgent\AiAgent;
use AiAgent\Console\Commands\AiProvidersCommand;
use AiAgent\Facades\AiAgent as AiAgentFacade;
use AiAgent\Services\AiLoggerService;
use AiAgent\Services\AiService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;

test('service provider registers ai-agent singleton', function () {
  expect($this->app->bound('ai-agent'))->toBeTrue();

  $instance = $this->app->make('ai-agent');
  expect($instance)->toBeInstanceOf(AiAgent::class);
});

test('service provider registers ai service singleton', function () {
  expect($this->app->bound(AiService::class))->toBeTrue();

  $instance = $this->app->make(AiService::class);
  expect($instance)->toBeInstanceOf(AiService::class);
});

test('service provider registers logger service singleton', function () {
  expect($this->app->bound(AiLoggerService::class))->toBeTrue();

  $instance = $this->app->make(AiLoggerService::class);
  expect($instance)->toBeInstanceOf(AiLoggerService::class);
});

test('service provider registers commands', function () {
  $commands = Artisan::all();

  expect(array_key_exists('ai:providers', $commands))->toBeTrue();
  expect($commands['ai:providers'])->toBeInstanceOf(AiProvidersCommand::class);
});

test('service provider registers blade directives', function () {
  $compiler = Blade::getCompiler();

  expect($compiler->getCustomDirectives())->toHaveKey('ai');
  expect($compiler->getCustomDirectives())->toHaveKey('aichat');

  $aiDirective = $compiler->getCustomDirectives()['ai']('\'Test prompt\'');
  $aiChatDirective = $compiler->getCustomDirectives()['aichat']('[["role" => "user", "content" => "Hello"]]');

  expect($aiDirective)->toContain('app(\'ai-agent\')->generate');
  expect($aiChatDirective)->toContain('app(\'ai-agent\')->chat');
});

test('facade returns correct instance', function () {
  $facade = AiAgentFacade::getFacadeRoot();

  expect($facade)->toBeInstanceOf(AiAgent::class);
  expect($facade)->toBe($this->app->make('ai-agent'));
});
