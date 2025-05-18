<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | This option controls the default AI provider that will be used by the
    | package. You may set this to any of the providers defined in the
    | "providers" configuration array.
    |
    */
    'default_provider' => env('AI_AGENT_DEFAULT_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the AI providers used by your application.
    | Each provider can be configured with specific settings, such as API keys,
    | model preferences, and more.
    |
    */
    'providers' => [
        'openai' => [
            'enabled' => env('AI_AGENT_OPENAI_ENABLED', true),
            'adapter' => \AiAgent\Adapters\OpenAiAdapter::class,
            'api_key' => env('OPENAI_API_KEY'),
            'api_base_url' => env('OPENAI_API_BASE_URL', 'https://api.openai.com/v1'),
            'organization' => env('OPENAI_ORGANIZATION'),
            'model' => env('OPENAI_MODEL', 'gpt-4o'),
            'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
            'max_tokens' => env('OPENAI_MAX_TOKENS', 1000),
            'temperature' => env('OPENAI_TEMPERATURE', 0.7),
        ],

        'anthropic' => [
            'enabled' => env('AI_AGENT_ANTHROPIC_ENABLED', false),
            'adapter' => \AiAgent\Adapters\AnthropicAdapter::class,
            'api_key' => env('ANTHROPIC_API_KEY'),
            'api_base_url' => env('ANTHROPIC_API_BASE_URL', 'https://api.anthropic.com/v1'),
            'model' => env('ANTHROPIC_MODEL', 'claude-3-opus-20240229'),
            'max_tokens' => env('ANTHROPIC_MAX_TOKENS', 1000),
            'temperature' => env('ANTHROPIC_TEMPERATURE', 0.7),
            'system_prompt' => env('ANTHROPIC_SYSTEM_PROMPT'),
        ],

        'gemini' => [
            'enabled' => env('AI_AGENT_GEMINI_ENABLED', false),
            'adapter' => \AiAgent\Adapters\GeminiAdapter::class,
            'api_key' => env('GEMINI_API_KEY'),
            'api_base_url' => env('GEMINI_API_BASE_URL', 'https://generativelanguage.googleapis.com/v1'),
            'model' => env('GEMINI_MODEL', 'gemini-1.5-pro'),
            'embedding_model' => env('GEMINI_EMBEDDING_MODEL', 'embedding-001'),
            'max_tokens' => env('GEMINI_MAX_TOKENS', 1000),
            'temperature' => env('GEMINI_TEMPERATURE', 0.7),
        ],

        // Add more providers here
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | This option controls whether to log AI requests and responses.
    | When enabled, all AI interactions will be logged for auditing.
    |
    */
    'logging' => [
        'enabled' => env('AI_AGENT_LOGGING_ENABLED', false),
        'channel' => env('AI_AGENT_LOGGING_CHANNEL', 'stack'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for AI provider requests.
    | This helps manage API usage and costs.
    |
    */
    'rate_limiting' => [
        'enabled' => env('AI_AGENT_RATE_LIMITING_ENABLED', false),
        'max_requests' => env('AI_AGENT_RATE_LIMITING_MAX_REQUESTS', 60),
        'decay_minutes' => env('AI_AGENT_RATE_LIMITING_DECAY_MINUTES', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Configure caching for AI responses to reduce API calls.
    | Caching can significantly reduce costs for identical requests.
    |
    */
    'cache' => [
        'enabled' => env('AI_AGENT_CACHE_ENABLED', false),
        'ttl' => env('AI_AGENT_CACHE_TTL', 60 * 24), // 1 day in minutes
        'prefix' => env('AI_AGENT_CACHE_PREFIX', 'ai_agent_'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Web Routes
    |--------------------------------------------------------------------------
    |
    | Configure the package web routes for API endpoints.
    |
    */
    'routes' => [
        'enabled' => env('AI_AGENT_ROUTES_ENABLED', true),
        'prefix' => env('AI_AGENT_ROUTES_PREFIX', 'api/ai'),
        'middleware' => ['api', 'auth:sanctum'],
    ],
];
