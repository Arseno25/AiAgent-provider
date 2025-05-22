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
    /**
     * Determine if the user is authorized to make this request.
     *
     * Authorization is typically handled at a higher level (e.g., route middleware)
     * in the consuming application. Defaults to true for package flexibility.
     *
     * @return bool True if the request is authorized, false otherwise.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the embeddings generation request.
     *
     * Defines rules for:
     * - 'input': Required, can be a single string or an array of strings/items to embed.
     * - 'provider': Optional string, the name of the AI provider.
     * - 'options': Optional array, additional provider-specific options.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
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
