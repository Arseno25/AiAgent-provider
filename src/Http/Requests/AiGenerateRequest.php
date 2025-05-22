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
     * Allows all users to make this request.
     *
     * Always returns true, assuming authorization is managed elsewhere in the application.
     *
     * @return bool
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
     * Returns the validation rules for AI content generation requests.
     *
     * Ensures the request includes a required string prompt, and optionally allows a provider name and additional options.
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
