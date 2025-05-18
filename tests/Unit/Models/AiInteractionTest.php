<?php

use AiAgent\Models\AiInteraction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Mockery as m;

beforeEach(function () {
  $this->interaction = new AiInteraction();
});

test('model uses the correct table', function () {
  expect($this->interaction->getTable())->toBe('ai_interactions');
});

test('model has the correct fillable attributes', function () {
  $fillable = [
    'user_id',
    'provider',
    'type',
    'input',
    'output',
    'options',
    'tokens_used',
    'duration',
    'success',
    'error',
  ];

  expect($this->interaction->getFillable())->toBe($fillable);
});

test('model has the correct casts', function () {
  $casts = [
    'options' => 'json',
    'tokens_used' => 'integer',
    'duration' => 'float',
    'success' => 'boolean',
  ];

  expect($this->interaction->getCasts())->toBe($casts);
});

test('model uses HasFactory trait', function () {
  expect(in_array(HasFactory::class, class_uses_recursive($this->interaction)))->toBeTrue();
});

test('model has user relationship', function () {
  $relation = $this->interaction->user();

  expect($relation)->toBeInstanceOf(BelongsTo::class);
  expect($relation->getQualifiedForeignKeyName())->toContain('user_id');
});

test('record method creates new interaction', function () {
  $mock = m::mock('alias:' . AiInteraction::class);

  $mockInteraction = m::mock(AiInteraction::class);

  $mock->shouldReceive('create')
    ->once()
    ->with([
      'user_id' => 123,
      'provider' => 'openai',
      'type' => 'generate',
      'input' => 'Test prompt',
      'output' => 'Test output',
      'options' => ['temperature' => 0.7],
      'tokens_used' => 100,
      'duration' => 0.5,
      'success' => true,
      'error' => null,
    ])
    ->andReturn($mockInteraction);

  $result = AiInteraction::record(
    'openai',
    'generate',
    'Test prompt',
    'Test output',
    ['temperature' => 0.7],
    100,
    0.5,
    true,
    null,
    123
  );

  expect($result)->toBe($mockInteraction);
});

test('record method handles array input and output', function () {
  $arrayInput = ['message' => 'Hello'];
  $arrayOutput = ['response' => 'Hi'];

  $mock = m::mock('alias:' . AiInteraction::class);

  $mockInteraction = m::mock(AiInteraction::class);

  $mock->shouldReceive('create')
    ->once()
    ->with([
      'user_id' => null,
      'provider' => 'openai',
      'type' => 'chat',
      'input' => json_encode($arrayInput),
      'output' => json_encode($arrayOutput),
      'options' => [],
      'tokens_used' => 0,
      'duration' => 0,
      'success' => true,
      'error' => null,
    ])
    ->andReturn($mockInteraction);

  $result = AiInteraction::record(
    'openai',
    'chat',
    $arrayInput,
    $arrayOutput
  );

  expect($result)->toBe($mockInteraction);
});
