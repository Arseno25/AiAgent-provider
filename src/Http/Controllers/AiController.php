<?php

namespace AiAgent\Http\Controllers;

use AiAgent\Facades\AiAgent;
use AiAgent\Http\Requests\AiChatRequest;
use AiAgent\Http\Requests\AiEmbeddingsRequest;
use AiAgent\Http\Requests\AiGenerateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log; // For optional logging in error handler

/**
 * Controller for handling AI-related HTTP requests.
 *
 * This controller provides endpoints for interacting with the AiAgent facade,
 * including listing providers, generating content, performing chat completions,
 * and creating embeddings. It uses Form Requests for input validation.
 */
class AiController extends Controller
{
    /**
     * Returns a JSON response with the list of available AI provider names and the configured default provider.
     *
     * @return JsonResponse
     */
    public function providers(): JsonResponse
    {
        return response()->json([
            'providers' => AiAgent::getProviderNames(),
            'default' => config('ai-agent.default_provider'),
        ]);
    }

    /****
     * Executes an AI agent operation and returns a standardized JSON response.
     *
     * Runs the provided callable, returning its result in a JSON response on success. If an AiAgent API exception occurs, returns a JSON error response with the exception message and type, using the exception code as the HTTP status if appropriate. For all other exceptions, returns a generic error message with HTTP status 500.
     *
     * @param callable $agentCall Closure that performs the AI agent operation and returns its result.
     * @return JsonResponse JSON response containing either the operation result or an error message.
     */
    private function handleAiAgentRequest(callable $agentCall): JsonResponse
    {
        try {
            // Execute the provided closure which calls the AiAgent facade.
            $result = $agentCall();
            return response()->json(['result' => $result]);
        } catch (\AiAgent\Exceptions\ApiException $e) {
            // Catch specific API exceptions from the AiAgent
            Log::warning("AiAgent API Exception: " . $e->getMessage(), [
                'code' => $e->getCode(),
                'exception_type' => get_class($e)
            ]);
            return response()->json(['error' => $e->getMessage(), 'type' => class_basename($e)], $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500);
        } catch (\Exception $e) {
            // Catch any other general exceptions.
            // It's good practice to log these unexpected errors.
            Log::error("AiAgent general error: " . $e->getMessage(), [
                'exception' => $e, // Includes stack trace and more details in the log.
            ]);
            return response()->json(['error' => 'An unexpected error occurred.'], 500); // Generic message for unexpected errors.
        }
    }

    /**
     * Handles a content generation request using the specified AI provider.
     *
     * Processes validated input from the request and returns the generated content or an error response as JSON.
     *
     * @param AiGenerateRequest $request Validated request containing the prompt and optional parameters.
     * @return JsonResponse JSON response with the generation result or error details.
     */
    public function generate(AiGenerateRequest $request): JsonResponse
    {
        return $this->handleAiAgentRequest(function () use ($request) {
            // Retrieve validated data from the Form Request.
            $validatedData = $request->validated();
            // Call the AiAgent facade to generate content.
            return AiAgent::generate(
                $validatedData['prompt'],
                $validatedData['options'] ?? [], // Default to empty array if options are not provided.
                $validatedData['provider'] ?? null // Default to null if provider is not specified (AiAgent will use default).
            );
        });
    }

    /**
     * Handles chat completion requests using an AI provider.
     *
     * Processes validated chat messages and optional parameters to generate a chat completion response via the AiAgent facade.
     *
     * @param AiChatRequest $request Validated request containing chat messages, options, and provider.
     * @return JsonResponse JSON response with the chat completion result or an error message.
     */
    public function chat(AiChatRequest $request): JsonResponse
    {
        return $this->handleAiAgentRequest(function () use ($request) {
            $validatedData = $request->validated();
            // Call the AiAgent facade for chat completion.
            return AiAgent::chat(
                $validatedData['messages'],
                $validatedData['options'] ?? [],
                $validatedData['provider'] ?? null
            );
        });
    }

    /**
     * Handles a request to generate embeddings from input data using an AI provider.
     *
     * Returns a JSON response containing the embeddings result or an error message.
     *
     * @param AiEmbeddingsRequest $request Validated request with input data for embeddings.
     * @return JsonResponse
     */
    public function embeddings(AiEmbeddingsRequest $request): JsonResponse
    {
        return $this->handleAiAgentRequest(function () use ($request) {
            $validatedData = $request->validated();
            // Call the AiAgent facade to generate embeddings.
            return AiAgent::embeddings(
                $validatedData['input'],
                $validatedData['options'] ?? [],
                $validatedData['provider'] ?? null
            );
        });
    }
}
