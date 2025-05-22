<?php

namespace AiAgent\Tests\Unit\Http\Requests;

use AiAgent\Http\Requests\AiChatRequest;
use Illuminate\Support\Facades\Validator;

uses(\AiAgent\Tests\TestCase::class);

beforeEach(function () {
    $this->request = new AiChatRequest();
});

test('authorize method returns true for chat request', function () {
    expect($this->request->authorize())->toBeTrue();
});

describe('validation rules for chat request', function () {
    it('passes with valid chat data', function () {
        $data = [
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                ['role' => 'user', 'content' => 'Hello, who are you?'],
                ['role' => 'assistant', 'content' => 'I am an AI. How can I help?'],
            ],
            'provider' => 'test_provider',
            'options' => ['temperature' => 0.5],
        ];
        $validator = Validator::make($data, $this->request->rules());
        expect($validator->passes())->toBeTrue()->والفشل($validator->errors()->all());
    });

    it('passes with only required messages', function () {
        $data = [
            'messages' => [['role' => 'user', 'content' => 'Just a message.']],
        ];
        $validator = Validator::make($data, $this->request->rules());
        expect($validator->passes())->toBeTrue()->والفشل($validator->errors()->all());
    });

    it('fails if messages is missing', function () {
        $data = ['provider' => 'test_provider'];
        $validator = Validator::make($data, $this->request->rules());
        expect($validator->fails())->toBeTrue();
        expect($validator->errors())->toHaveKey('messages');
    });

    it('fails if messages is not an array', function () {
        $data = ['messages' => 'not-an-array'];
        $validator = Validator::make($data, $this->request->rules());
        expect($validator->fails())->toBeTrue();
        expect($validator->errors())->toHaveKey('messages');
    });

    it('fails if a message role is missing', function () {
        $data = ['messages' => [['content' => 'Missing role']]];
        $validator = Validator::make($data, $this->request->rules());
        expect($validator->fails())->toBeTrue();
        expect($validator->errors())->toHaveKey('messages.0.role');
    });

    it('fails if a message role is invalid', function () {
        $data = ['messages' => [['role' => 'invalid_role', 'content' => 'Test']]];
        $validator = Validator::make($data, $this->request->rules());
        expect($validator->fails())->toBeTrue();
        expect($validator->errors())->toHaveKey('messages.0.role');
    });

    it('fails if a message content is missing', function () {
        $data = ['messages' => [['role' => 'user']]];
        $validator = Validator::make($data, $this->request->rules());
        expect($validator->fails())->toBeTrue();
        expect($validator->errors())->toHaveKey('messages.0.content');
    });
    
    it('fails if a message content is not a string', function () {
        $data = ['messages' => [['role' => 'user', 'content' => 123]]];
        $validator = Validator::make($data, $this->request->rules());
        expect($validator->fails())->toBeTrue();
        expect($validator->errors())->toHaveKey('messages.0.content');
    });

    it('fails if provider is not a string', function () {
        $data = ['messages' => [['role' => 'user', 'content' => 'Hi']], 'provider' => 123];
        $validator = Validator::make($data, $this->request->rules());
        expect($validator->fails())->toBeTrue();
        expect($validator->errors())->toHaveKey('provider');
    });

    it('fails if options is not an array', function () {
        $data = ['messages' => [['role' => 'user', 'content' => 'Hi']], 'options' => 'not-an-array'];
        $validator = Validator::make($data, $this->request->rules());
        expect($validator->fails())->toBeTrue();
        expect($validator->errors())->toHaveKey('options');
    });
});
