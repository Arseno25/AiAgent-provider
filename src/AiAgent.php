<?php

namespace AiAgent;

use AiAgent\Contracts\AiProviderInterface;
use AiAgent\Exceptions\ProviderNotFoundException;
use AiAgent\Services\AiLoggerService;
use AiAgent\Services\AiService;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Cache;

class AiAgent
{
    /**
     * The Laravel application instance.
     */
    protected $app;

    /**
     * The AI service instance.
     */
    protected $service;

    /**
     * The logger service instance.
     */
    protected $logger;

    /**
     * Available AI providers.
     */
    protected $providers = [];

    /**
     * Create a new AiAgent instance.
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->service = $app->make(AiService::class);
        $this->logger = $app->make(AiLoggerService::class);
        $this->loadProviders();
    }

    /**
     * Load all registered AI providers from configuration.
     */
    protected function loadProviders(): void
    {
        $providers = config('ai-agent.providers', []);

        foreach ($providers as $name => $config) {
            if (isset($config['adapter']) && $config['enabled'] ?? true) {
                $this->providers[$name] = [
                    'adapter' => $config['adapter'],
                    'config' => $config,
                ];
            }
        }
    }

    /**
     * Get an AI provider instance.
     *
     * @param string|null $provider The provider name, or null to use the default provider
     * @return AiProviderInterface
     * @throws ProviderNotFoundException
     */
    public function provider(?string $provider = null): AiProviderInterface
    {
        $provider = $provider ?? config('ai-agent.default_provider');

        if (!isset($this->providers[$provider])) {
            throw new ProviderNotFoundException("AI provider [{$provider}] not found.");
        }

        return $this->service->resolveProvider(
            $this->providers[$provider]['adapter'],
            $this->providers[$provider]['config']
        );
    }

    /**
     * Generate content with AI.
     *
     * @param string $prompt The prompt to send to the AI
     * @param array $options Additional options for the provider
     * @param string|null $provider The provider name, or null to use the default provider
     * @return string The generated content
     */
    public function generate(string $prompt, array $options = [], ?string $provider = null): string
    {
        $providerName = $provider ?? config('ai-agent.default_provider');
        $cacheKey = $this->getCacheKey('generate', $prompt, $options, $providerName);

        // Check cache if enabled
        if ($this->isCacheEnabled() && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $startTime = microtime(true);

        try {
            $result = $this->provider($providerName)->generate($prompt, $options);

            $duration = microtime(true) - $startTime;

            // Log the interaction
            $this->logger->log(
                $providerName,
                'generate',
                $prompt,
                $result,
                $options,
                $this->estimateTokens($prompt, $result),
                $duration,
                true
            );

            // Cache the result if enabled
            if ($this->isCacheEnabled()) {
                Cache::put($cacheKey, $result, config('ai-agent.cache.ttl', 60 * 24));
            }

            return $result;
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;

            // Log the error
            $this->logger->log(
                $providerName,
                'generate',
                $prompt,
                null,
                $options,
                0,
                $duration,
                false,
                $e->getMessage()
            );

            throw $e;
        }
    }

    /**
     * Generate chat completion with AI.
     *
     * @param array $messages The messages to send to the AI
     * @param array $options Additional options for the provider
     * @param string|null $provider The provider name, or null to use the default provider
     * @return array The chat completion response
     */
    public function chat(array $messages, array $options = [], ?string $provider = null): array
    {
        $providerName = $provider ?? config('ai-agent.default_provider');
        $cacheKey = $this->getCacheKey('chat', $messages, $options, $providerName);

        // Check cache if enabled
        if ($this->isCacheEnabled() && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $startTime = microtime(true);

        try {
            $result = $this->provider($providerName)->chat($messages, $options);

            $duration = microtime(true) - $startTime;

            // Log the interaction
            $this->logger->log(
                $providerName,
                'chat',
                $messages,
                $result,
                $options,
                $result['usage']['total_tokens'] ?? $this->estimateTokens(json_encode($messages), json_encode($result)),
                $duration,
                true
            );

            // Cache the result if enabled
            if ($this->isCacheEnabled()) {
                Cache::put($cacheKey, $result, config('ai-agent.cache.ttl', 60 * 24));
            }

            return $result;
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;

            // Log the error
            $this->logger->log(
                $providerName,
                'chat',
                $messages,
                null,
                $options,
                0,
                $duration,
                false,
                $e->getMessage()
            );

            throw $e;
        }
    }

    /**
     * Generate embeddings for a text.
     *
     * @param string|array $input The text or array of texts to embed
     * @param array $options Additional options for the provider
     * @param string|null $provider The provider name, or null to use the default provider
     * @return array The embeddings
     */
    public function embeddings($input, array $options = [], ?string $provider = null): array
    {
        $providerName = $provider ?? config('ai-agent.default_provider');
        $cacheKey = $this->getCacheKey('embeddings', $input, $options, $providerName);

        // Check cache if enabled
        if ($this->isCacheEnabled() && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $startTime = microtime(true);

        try {
            $result = $this->provider($providerName)->embeddings($input, $options);

            $duration = microtime(true) - $startTime;

            // Log the interaction
            $this->logger->log(
                $providerName,
                'embeddings',
                $input,
                $result,
                $options,
                $this->estimateTokens(is_array($input) ? implode(' ', $input) : $input, ''),
                $duration,
                true
            );

            // Cache the result if enabled
            if ($this->isCacheEnabled()) {
                Cache::put($cacheKey, $result, config('ai-agent.cache.ttl', 60 * 24));
            }

            return $result;
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;

            // Log the error
            $this->logger->log(
                $providerName,
                'embeddings',
                $input,
                null,
                $options,
                0,
                $duration,
                false,
                $e->getMessage()
            );

            throw $e;
        }
    }

    /**
     * Get the currently supported provider names.
     *
     * @return array
     */
    public function getProviderNames(): array
    {
        return array_keys($this->providers);
    }

    /**
     * Check if caching is enabled.
     *
     * @return bool
     */
    protected function isCacheEnabled(): bool
    {
        return config('ai-agent.cache.enabled', false);
    }

    /**
     * Generate a cache key for an AI request.
     *
     * @param string $type The request type
     * @param mixed $input The input data
     * @param array $options The request options
     * @param string $provider The provider name
     * @return string
     */
    protected function getCacheKey(string $type, $input, array $options, string $provider): string
    {
        $prefix = config('ai-agent.cache.prefix', 'ai_agent_');
        $inputHash = md5(is_array($input) ? json_encode($input) : $input);
        $optionsHash = md5(json_encode($options));

        return "{$prefix}{$type}_{$provider}_{$inputHash}_{$optionsHash}";
    }

    /**
     * Estimate the number of tokens in a text (rough approximation).
     *
     * @param string $input The input text
     * @param string $output The output text
     * @return int
     */
    protected function estimateTokens(string $input, string $output): int
    {
        // A very rough approximation: 1 token ~= 4 characters
        $inputTokens = (int) (strlen($input) / 4);
        $outputTokens = (int) (strlen($output) / 4);

        return $inputTokens + $outputTokens;
    }
}
