<?php

namespace AiAgent\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for validating AI content generation requests.
 *
 * This class defines the validation rules for the data expected when
 * a client requests to generate content via the AI agent.
 */
class AiGenerateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * In this package, we assume that authorization is handled by middleware
     * or the consuming application's policies, so this defaults to true.
     *
     * @return bool True if the request is authorized, false otherwise.
     */
    public function authorize(): bool
    {
        // Authorization logic can be implemented here if needed,
        // for example, checking user permissions or roles.
        // For a general package, defaulting to true is common,
        // relying on application-level authorization.
        return true;
    }

    /**
     * Get the validation rules that apply to the content generation request.
     *
     * Defines rules for:
     * - 'prompt': Required string, the input text for the AI.
     * - 'provider': Optional string, the name of the AI provider to use.
     * - 'options': Optional array, additional provider-specific options.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'prompt' => 'required|string', // The main text prompt for generation.
            'provider' => 'nullable|string', // Optional: specify an AI provider.
            'options' => 'nullable|array',   // Optional: provider-specific options.
        ];
    }
}
