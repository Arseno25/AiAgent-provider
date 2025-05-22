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
     * Get the validation rules that apply to the chat completion request.
     *
     * Defines rules for:
     * - 'messages': Required array, representing the conversation history.
     * - 'messages.*.role': Each message must have a role (system, user, or assistant).
     * - 'messages.*.content': Each message must have content.
     * - 'provider': Optional string, the name of the AI provider.
     * - 'options': Optional array, additional provider-specific options.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
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
