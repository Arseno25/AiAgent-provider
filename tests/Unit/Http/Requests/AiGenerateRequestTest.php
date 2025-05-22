<?php

namespace AiAgent\Tests\Unit\Http\Requests;

use AiAgent\Http\Requests\AiGenerateRequest;
use Illuminate\Support\Facades\Validator;

uses(\AiAgent\Tests\TestCase::class); // If you have a base TestCase with helpers

beforeEach(function () {
    $this->request = new AiGenerateRequest();
});

test('authorize method returns true', function () {
    expect($this->request->authorize())->toBeTrue();
});

describe('validation rules for generate request', function () {
    it('passes with valid data', function () {
        $data = [
            'prompt' => 'This is a test prompt.',
            'provider' => 'test_provider',
            'options' => ['temperature' => 0.7],
        ];
        $validator = Validator::make($data, $this->request->rules());
        expect($validator->passes())->toBeTrue();
    });

    it('passes with only required prompt', function () {
        $data = ['prompt' => 'Just a prompt.'];
        $validator = Validator::make($data, $this->request->rules());
        expect($validator->passes())->toBeTrue();
    });

    it('fails if prompt is missing', function () {
        $data = ['provider' => 'test_provider'];
        $validator = Validator::make($data, $this->request->rules());
        expect($validator->fails())->toBeTrue();
        expect($validator->errors())->toHaveKey('prompt');
    });

    it('fails if prompt is not a string', function () {
        $data = ['prompt' => 12345];
        $validator = Validator::make($data, $this->request->rules());
        expect($validator->fails())->toBeTrue();
        expect($validator->errors())->toHaveKey('prompt');
    });

    it('fails if provider is not a string', function () {
        $data = ['prompt' => 'A prompt', 'provider' => 123];
        $validator = Validator::make($data, $this->request->rules());
        expect($validator->fails())->toBeTrue();
        expect($validator->errors())->toHaveKey('provider');
    });

    it('fails if options is not an array', function () {
        $data = ['prompt' => 'A prompt', 'options' => 'not-an-array'];
        $validator = Validator::make($data, $this->request->rules());
        expect($validator->fails())->toBeTrue();
        expect($validator->errors())->toHaveKey('options');
    });
});
