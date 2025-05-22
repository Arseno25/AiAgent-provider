<?php

namespace AiAgent\Tests\Unit\Http\Requests;

use AiAgent\Http\Requests\AiEmbeddingsRequest;
use Illuminate\Support\Facades\Validator;

uses(\AiAgent\Tests\TestCase::class);

beforeEach(function () {
    $this->request = new AiEmbeddingsRequest();
});

test('authorize method returns true for embeddings request', function () {
    expect($this->request->authorize())->toBeTrue();
});

describe('validation rules for embeddings request', function () {
    it('passes with valid string input', function () {
        $data = [
            'input' => 'This is a test string.',
            'provider' => 'test_provider',
            'options' => ['model' => 'text-embedding-ada-002'],
        ];
        $validator = Validator::make($data, $this->request->rules());
        expect($validator->passes())->toBeTrue()->والفشل($validator->errors()->all());
    });

    it('passes with valid array input', function () {
        $data = [
            'input' => ['String 1', 'String 2'],
            'provider' => 'test_provider',
        ];
        $validator = Validator::make($data, $this->request->rules());
        expect($validator->passes())->toBeTrue()->والفشل($validator->errors()->all());
    });
    
    it('passes with only required string input', function () {
        $data = ['input' => 'A single string.'];
        $validator = Validator::make($data, $this->request->rules());
        expect($validator->passes())->toBeTrue()->والفشل($validator->errors()->all());
    });

    it('passes with only required array input', function () {
        $data = ['input' => ['An array with one string.']];
        $validator = Validator::make($data, $this->request->rules());
        expect($validator->passes())->toBeTrue()->والفشل($validator->errors()->all());
    });

    it('fails if input is missing', function () {
        $data = ['provider' => 'test_provider'];
        $validator = Validator::make($data, $this->request->rules());
        expect($validator->fails())->toBeTrue();
        expect($validator->errors())->toHaveKey('input');
    });

    it('fails if input is not a string or an array', function () {
        $data = ['input' => 12345]; // Not a string or array
        $validator = Validator::make($data, $this->request->rules());
        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('input'))->toBe('Input must be a string or an array.');
    });

    it('fails if input string is empty', function () {
        $data = ['input' => ''];
        $validator = Validator::make($data, $this->request->rules());
        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('input'))->toBe('Input string cannot be empty.');
    });
    
    it('fails if input string is only whitespace', function () {
        $data = ['input' => '   '];
        $validator = Validator::make($data, $this->request->rules());
        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('input'))->toBe('Input string cannot be empty.');
    });

    it('fails if input array is empty', function () {
        $data = ['input' => []];
        $validator = Validator::make($data, $this->request->rules());
        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('input'))->toBe('Input array cannot be empty.');
    });
    
    // Example of how to test if array elements are strings (if that rule was added)
    // it('fails if input array contains non-string elements', function () {
    //     $data = ['input' => ['A string', 123, 'Another string']];
    //     // This would require adding 'input.*' => 'string' to the rules
    //     // and potentially adjusting the custom closure or adding another one.
    //     // For now, the custom closure only checks if it IS an array or IS a string.
    //     $validator = Validator::make($data, $this->request->rules());
    //     // Adjust expectation based on actual rules.
    //     // If 'input.*' => 'string' is added, this should fail.
    //     // As is, this will pass the custom closure because $value is_array() is true.
    //     // To make it fail, you'd need 'input.*' => 'string'.
    //     // For now, let's assume the current rules only validate the top-level input type.
    //     expect($validator->passes())->toBeTrue(); // This will pass with current rules
    // });


    it('fails if provider is not a string', function () {
        $data = ['input' => 'A string', 'provider' => 123];
        $validator = Validator::make($data, $this->request->rules());
        expect($validator->fails())->toBeTrue();
        expect($validator->errors())->toHaveKey('provider');
    });

    it('fails if options is not an array', function () {
        $data = ['input' => 'A string', 'options' => 'not-an-array'];
        $validator = Validator::make($data, $this->request->rules());
        expect($validator->fails())->toBeTrue();
        expect($validator->errors())->toHaveKey('options');
    });
});
