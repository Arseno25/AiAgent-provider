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
     * Get all available AI providers and the default provider.
     *
     * Returns a JSON response containing a list of available provider names
     * and the name of the default provider as configured.
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

    /**
     * Centralized handler for executing AiAgent facade calls and formatting the JSON response.
     *
     * This method encapsulates the try-catch logic for AI agent operations,
     * returning a standardized JSON response for successes or failures.
     *
     * @param callable $agentCall A closure that contains the specific AiAgent facade call to execute.
     *                            The closure should return the result from the AiAgent method.
     * @return JsonResponse A JSON response containing either the 'result' of the AI operation
     *                      or an 'error' message if an exception occurred.
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
     * Generate content using an AI provider.
     *
     * Uses AiGenerateRequest for input validation.
     *
     * @param AiGenerateRequest $request The validated HTTP request containing 'prompt', 'options', and 'provider'.
     * @return JsonResponse The result of the content generation or an error response.
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
     * Generate chat completion using an AI provider.
     *
     * Uses AiChatRequest for input validation.
     *
     * @param AiChatRequest $request The validated HTTP request containing 'messages', 'options', and 'provider'.
     * @return JsonResponse The result of the chat completion or an error response.
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
     * Generate embeddings using an AI provider.
     *
     * Uses AiEmbeddingsRequest for input validation.
     *
     * @param AiEmbeddingsRequest $request The validated HTTP request containing 'input', 'options', and 'provider'.
     * @return JsonResponse The result of the embeddings generation or an error response.
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
