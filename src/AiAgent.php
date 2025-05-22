<?php

namespace AiAgent;

use AiAgent\Contracts\AiProviderInterface;
use AiAgent\Exceptions\ProviderNotFoundException;
use AiAgent\Services\AiLoggerService;
use AiAgent\Services\AiService;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Cache;

/**
 * Main class for interacting with AI providers.
 *
 * This class provides a unified interface to generate content, chat, and create embeddings
 * using various configured AI providers, while also handling caching, logging, and token estimation.
 */
class AiAgent
{
    /**
     * The Laravel application instance.
     * @var Application
     */
    protected $app;

    /**
     * The AI service instance, responsible for resolving AI provider adapters.
     * @var AiService
     */
    protected $service;

    /**
     * The logger service instance, used for logging AI interactions.
     * @var AiLoggerService
     */
    protected $logger;

    /**
     * Array of available AI providers, loaded from configuration.
     * Each element contains the adapter class and its configuration.
     * @var array
     */
    protected $providers = [];

    /**
     * Create a new AiAgent instance.
     *
     * @param Application $app The Laravel application instance.
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->service = $app->make(AiService::class);
        $this->logger = $app->make(AiLoggerService::class);
        $this->loadProviders();
    }

    /**
     * Load all registered AI providers from the configuration file.
     *
     * This method reads the 'ai-agent.providers' configuration and populates the
     * `$providers` array with enabled adapters and their configurations.
     */
    protected function loadProviders(): void
    {
        $providers = config('ai-agent.providers', []);

        foreach ($providers as $name => $config) {
            // Add provider only if it has an adapter and is enabled (or enabled by default if not set)
            if (isset($config['adapter']) && ($config['enabled'] ?? true)) {
                $this->providers[$name] = [
                    'adapter' => $config['adapter'],
                    'config' => $config, // Store the full config for the provider
                ];
            }
        }
    }

    /**
     * Get an AI provider instance by its name.
     *
     * If no provider name is given, the default provider from the configuration is used.
     *
     * @param string|null $provider The name of the provider to resolve. Uses default if null.
     * @return AiProviderInterface An instance of the requested AI provider.
     * @throws ProviderNotFoundException If the requested provider is not found or not configured.
     */
    public function provider(?string $provider = null): AiProviderInterface
    {
        // Use the default provider if none is specified
        $providerName = $provider ?? config('ai-agent.default_provider');

        if (!isset($this->providers[$providerName])) {
            throw new ProviderNotFoundException("AI provider [{$providerName}] not found.");
        }

        // Resolve and return the provider instance using the AiService
        return $this->service->resolveProvider(
            $this->providers[$providerName]['adapter'],
            $this->providers[$providerName]['config']
        );
    }

    /**
     * Generate content using the specified AI provider.
     *
     * @param string $prompt The prompt to send to the AI.
     * @param array $options Additional options for the provider (e.g., model, temperature).
     * @param string|null $provider The name of the provider to use. Uses default if null.
     * @return string The generated content from the AI.
     * @throws \AiAgent\Exceptions\ApiException If the API call fails.
     * @throws \Exception For other underlying errors.
     */
    public function generate(string $prompt, array $options = [], ?string $provider = null): string
    {
        $providerName = $provider ?? config('ai-agent.default_provider');

        // Delegate to the common interaction handler
        return $this->_handleProviderInteraction(
            'generate', // Method type
            $providerName,
            $prompt,
            $options,
            fn() => $this->provider($providerName)->generate($prompt, $options), // API call closure
            fn($input, $result) => $this->estimateTokens((string)$input, (string)$result) // Token estimation closure
        );
    }

    /**
     * Generate a chat completion response using the specified AI provider.
     *
     * @param array $messages An array of message objects (e.g., [['role' => 'user', 'content' => 'Hello']]).
     * @param array $options Additional options for the provider (e.g., model, max_tokens).
     * @param string|null $provider The name of the provider to use. Uses default if null.
     * @return array The chat completion response, typically including the message and usage data.
     * @throws \AiAgent\Exceptions\ApiException If the API call fails.
     * @throws \Exception For other underlying errors.
     */
    public function chat(array $messages, array $options = [], ?string $provider = null): array
    {
        $providerName = $provider ?? config('ai-agent.default_provider');

        // Delegate to the common interaction handler
        return $this->_handleProviderInteraction(
            'chat', // Method type
            $providerName,
            $messages,
            $options,
            fn() => $this->provider($providerName)->chat($messages, $options), // API call closure
            // Token estimation closure: uses actual tokens from response if available, otherwise estimates
            fn($input, $result) => $result['usage']['total_tokens'] ?? $this->estimateTokens(json_encode($input), json_encode($result))
        );
    }

    /**
     * Generate embeddings for a given text or array of texts using the specified AI provider.
     *
     * @param string|array $input The text string or an array of text strings to embed.
     * @param array $options Additional options for the provider (e.g., model).
     * @param string|null $provider The name of the provider to use. Uses default if null.
     * @return array An array of embedding vectors.
     * @throws \AiAgent\Exceptions\ApiException If the API call fails.
     * @throws \Exception For other underlying errors.
     */
    public function embeddings($input, array $options = [], ?string $provider = null): array
    {
        $providerName = $provider ?? config('ai-agent.default_provider');

        // Delegate to the common interaction handler
        return $this->_handleProviderInteraction(
            'embeddings', // Method type
            $providerName,
            $input,
            $options,
            fn() => $this->provider($providerName)->embeddings($input, $options), // API call closure
            // Token estimation for embeddings: input only, as output structure varies (and might be large)
            fn($input, $result) => $this->estimateTokens(is_array($input) ? implode(' ', $input) : (string)$input, '')
        );
    }

    /**
     * Handles the common logic for AI provider interactions, including caching, logging, and execution.
     *
     * This private method abstracts the boilerplate code for making an API call,
     * handling its caching, logging the interaction (success or failure), and estimating tokens.
     *
     * @param string $methodType The type of AI operation (e.g., 'generate', 'chat', 'embeddings').
     * @param string $providerName The name of the AI provider to use.
     * @param mixed $input The primary input for the AI call (e.g., prompt string, messages array).
     * @param array $options Additional options for the AI call.
     * @param \Closure $apiCallClosure A closure that, when called, executes the specific AI provider's method.
     *                                 It should return the result from the AI provider.
     * @param \Closure $tokenEstimationClosure A closure that, when called with the input and result,
     *                                         estimates the number of tokens used.
     * @return mixed The result from the AI provider.
     * @throws \AiAgent\Exceptions\ApiException Re-throws API exceptions from the provider.
     * @throws \Exception Re-throws any other exceptions that occur during the process.
     */
    private function _handleProviderInteraction(
        string $methodType,
        string $providerName,
        $input, // Can be string (prompt), array (messages), etc.
        array $options,
        \Closure $apiCallClosure,
        \Closure $tokenEstimationClosure
    ) {
        // Generate a unique cache key for this specific request.
        $cacheKey = $this->getCacheKey($methodType, $input, $options, $providerName);

        // Return cached result if caching is enabled and the key exists.
        if ($this->isCacheEnabled() && Cache::has($cacheKey)) {
            // Optionally, log cache hit or return metadata indicating it's a cached response.
            return Cache::get($cacheKey);
        }

        $startTime = microtime(true); // Record start time for duration calculation.

        try {
            // Execute the actual API call via the provided closure.
            $result = $apiCallClosure();
            $duration = microtime(true) - $startTime; // Calculate duration of the API call.

            // Estimate tokens used for the interaction.
            $tokens = $tokenEstimationClosure($input, $result);

            // Log the successful interaction.
            $this->logger->log(
                $providerName,
                $methodType,
                $input,
                $result,
                $options,
                $tokens,
                $duration,
                true // Success status
            );

            // Cache the result if caching is enabled.
            if ($this->isCacheEnabled()) {
                Cache::put($cacheKey, $result, config('ai-agent.cache.ttl', 60 * 24)); // Use configured TTL.
            }

            return $result;
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime; // Calculate duration even in case of failure.

            // Log the failed interaction.
            $this->logger->log(
                $providerName,
                $methodType,
                $input,
                null, // No result in case of error.
                $options,
                0, // Tokens are 0 in case of an error.
                $duration,
                false, // Success status
                $e->getMessage() // Include error message in log.
            );

            throw $e; // Re-throw the exception to be handled by the caller.
        }
    }

    /**
     * Get the names of all currently loaded and enabled AI providers.
     *
     * @return array An array of provider names.
     */
    public function getProviderNames(): array
    {
        return array_keys($this->providers);
    }

    /**
     * Check if caching is enabled in the configuration.
     *
     * @return bool True if caching is enabled, false otherwise.
     */
    protected function isCacheEnabled(): bool
    {
        return (bool) config('ai-agent.cache.enabled', false);
    }

    /**
     * Generate a unique cache key for an AI request.
     *
     * The key is based on the request type, provider name, a hash of the input,
     * and a hash of the options to ensure uniqueness for distinct requests.
     *
     * @param string $type The type of AI operation (e.g., 'generate', 'chat').
     * @param mixed $input The input data for the AI operation.
     * @param array $options The request options.
     * @param string $provider The name of the AI provider.
     * @return string The generated cache key.
     */
    protected function getCacheKey(string $type, $input, array $options, string $provider): string
    {
        $prefix = config('ai-agent.cache.prefix', 'ai_agent_');
        // Create a hash of the input (serialize arrays/objects for consistent hashing).
        $inputHash = md5(is_array($input) || is_object($input) ? json_encode($input) : (string)$input);
        // Create a hash of the options array.
        $optionsHash = md5(json_encode($options));

        return "{$prefix}{$type}_{$provider}_{$inputHash}_{$optionsHash}";
    }

    /**
     * Estimate the number of tokens in a given input and output text.
     *
     * This is a very rough approximation based on the general rule that
     * 1 token is approximately 4 characters in English text.
     * This method should be overridden or improved if more accurate tokenization is needed.
     *
     * @param string $input The input text.
     * @param string $output The output text from the AI.
     * @return int The estimated total number of tokens.
     */
    protected function estimateTokens(string $input, string $output): int
    {
        // A common heuristic: 1 token is roughly equivalent to 4 characters for English text.
        // This can vary significantly based on the language and specific tokenizer used by the model.
        $inputTokens = (int) (strlen($input) / 4);
        $outputTokens = (int) (strlen($output) / 4);

        return $inputTokens + $outputTokens;
    }
}
