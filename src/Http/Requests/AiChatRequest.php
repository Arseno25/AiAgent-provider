<?php

namespace AiAgent\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for validating AI chat completion requests.
 *
 * This class defines the validation rules for the data expected when
 * a client requests a chat completion via the AI agent.
 */
class AiChatRequest extends FormRequest
{
    /**
     * Always authorizes the request for AI chat completion validation.
     *
     * Returns true to allow all requests, deferring authorization to other parts of the application.
     *
     * @return bool Always true.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Returns the validation rules for an AI chat completion request.
     *
     * Ensures the request contains a required array of messages, each with a valid role and content, and optionally allows specifying a provider and additional options.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string> Validation rules for the request data.
     */
    public function rules(): array
    {
        return [
            'messages' => 'required|array', // The array of message objects.
            'messages.*.role' => 'required|string|in:system,user,assistant', // Role for each message.
            'messages.*.content' => 'required|string', // Content for each message.
            'provider' => 'nullable|string',   // Optional: specify an AI provider.
            'options' => 'nullable|array',     // Optional: provider-specific options.
        ];
    }
}
