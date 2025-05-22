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

    /****
     * Initializes the AiAgent with the given Laravel application instance.
     *
     * Sets up the AI service, logging service, and loads enabled AI providers from configuration.
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

    /****
     * Loads enabled AI providers and their configurations from application settings.
     *
     * Populates the `$providers` array with provider names as keys and their adapter classes and configurations as values, including only those marked as enabled.
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
     * Returns an AI provider instance by name or the default provider if none is specified.
     *
     * @param string|null $provider The provider name to resolve, or null to use the default.
     * @return AiProviderInterface The resolved AI provider instance.
     * @throws ProviderNotFoundException If the specified or default provider is not configured.
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

    /****
     * Generates content from the specified or default AI provider using a given prompt and options.
     *
     * @param string $prompt The input prompt to send to the AI provider.
     * @param array $options Optional settings such as model or temperature.
     * @param string|null $provider The provider name to use; defaults to the configured provider if null.
     * @return string The AI-generated content.
     * @throws \AiAgent\Exceptions\ApiException If the AI provider API call fails.
     * @throws \Exception For other unexpected errors.
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
     * Generates a chat completion response from the specified or default AI provider.
     *
     * Accepts an array of message objects and optional parameters to customize the provider's behavior. Returns the provider's response, which typically includes the generated message and usage statistics.
     *
     * @param array $messages Array of message objects for the chat context.
     * @param array $options Optional parameters for the provider (e.g., model, max_tokens).
     * @param string|null $provider Name of the AI provider to use, or null to use the default.
     * @return array Provider's chat completion response, including generated message and usage data.
     * @throws \AiAgent\Exceptions\ApiException If the AI provider API call fails.
     * @throws \Exception For other errors during processing.
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
     * Generates embeddings for a text string or array of strings using the specified or default AI provider.
     *
     * @param string|array $input The text or texts to generate embeddings for.
     * @param array $options Optional settings for the provider, such as model selection.
     * @param string|null $provider The provider name to use, or default if null.
     * @return array Embedding vectors corresponding to the input.
     * @throws \AiAgent\Exceptions\ApiException If the AI provider API call fails.
     * @throws \Exception For other unexpected errors.
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

    /****
     * Executes an AI provider operation with unified caching, logging, and token estimation.
     *
     * This private method centralizes the workflow for invoking an AI provider's method, including cache retrieval and storage, execution timing, token estimation, and logging of both successful and failed interactions. It accepts closures for the specific API call and token estimation logic, ensuring consistent handling across different AI operations.
     *
     * @param string $methodType The type of AI operation (e.g., 'generate', 'chat', 'embeddings').
     * @param string $providerName The name of the AI provider to use.
     * @param mixed $input The primary input for the AI call (such as a prompt string or messages array).
     * @param array $options Additional options for the AI call.
     * @param \Closure $apiCallClosure Closure that executes the AI provider's method and returns the result.
     * @param \Closure $tokenEstimationClosure Closure that estimates the number of tokens used, given the input and result.
     * @return mixed The result returned by the AI provider.
     * @throws \AiAgent\Exceptions\ApiException If the provider throws an API exception.
     * @throws \Exception If any other error occurs during the process.
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
     * Returns the names of all loaded and enabled AI providers.
     *
     * @return array List of enabled provider names.
     */
    public function getProviderNames(): array
    {
        return array_keys($this->providers);
    }

    /**
     * Determines whether AI response caching is enabled in the application configuration.
     *
     * @return bool True if caching is enabled; otherwise, false.
     */
    protected function isCacheEnabled(): bool
    {
        return (bool) config('ai-agent.cache.enabled', false);
    }

    /**
     * Generates a unique cache key for an AI operation based on type, provider, input, and options.
     *
     * Ensures that each distinct combination of operation type, provider name, input data, and options produces a unique cache key for caching AI responses.
     *
     * @param string $type The AI operation type (e.g., 'generate', 'chat', 'embeddings').
     * @param mixed $input The input data for the AI operation; arrays and objects are JSON-encoded for hashing.
     * @param array $options Additional options for the AI request.
     * @param string $provider The AI provider's name.
     * @return string The unique cache key.
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
     * Estimates the total number of tokens in the input and output text using a simple character-based heuristic.
     *
     * Uses the approximation that one token is roughly four characters in English text. The result is the sum of estimated tokens for both input and output.
     *
     * @param string $input Input text.
     * @param string $output Output text.
     * @return int Estimated total token count.
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
