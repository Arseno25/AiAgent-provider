<?php

namespace AiAgent\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for validating AI embeddings generation requests.
 *
 * This class defines the validation rules for the data expected when
 * a client requests to generate embeddings via the AI agent.
 */
class AiEmbeddingsRequest extends FormRequest
{
    /****
     * Allows all users to make this request.
     *
     * Always returns true to permit any user to submit an AI embeddings generation request. Authorization should be enforced elsewhere if needed.
     *
     * @return bool Always true.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Returns the validation rules for AI embeddings generation requests.
     *
     * Ensures that the 'input' field is provided as a non-empty string or a non-empty array, while 'provider' and 'options' are optional fields with appropriate types if present.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string> Validation rules for the request data.
     */
    public function rules(): array
    {
        return [
            // The input for embeddings: can be a single string or an array of strings.
            // The 'array' rule checks if it's an array, 'string' checks if it's a string.
            // Laravel's validation will pass if it's EITHER a string OR an array.
            'input' => ['required', function ($attribute, $value, $fail) {
                if (!is_string($value) && !is_array($value)) {
                    $fail(ucfirst($attribute) . ' must be a string or an array.');
                }
                if (is_array($value) && empty($value)) {
                    $fail(ucfirst($attribute) . ' array cannot be empty.');
                }
                if (is_string($value) && trim($value) === '') {
                     $fail(ucfirst($attribute) . ' string cannot be empty.');
                }
            }],
            'provider' => 'nullable|string',   // Optional: specify an AI provider.
            'options' => 'nullable|array',     // Optional: provider-specific options.
        ];
    }
}
