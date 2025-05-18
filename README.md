# AI Agent

A Laravel package for integrating with various AI providers including OpenAI, Anthropic and Google's Gemini.

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-10.x-red.svg" alt="Laravel">
  <img src="https://img.shields.io/badge/License-MIT-blue.svg" alt="License">
  <img src="https://img.shields.io/badge/PHP-8.1+-purple.svg" alt="PHP">
</p>

## Features

- 🤖 Simple, unified API for different AI providers
- 🔄 Support for text generation, chat completions, and embeddings
- 📊 Built-in logging of AI interactions
- 🚦 Rate limiting for API requests
- 🔌 Easy to extend with new AI providers
- 🔧 Blade directives for simple template integration
- 🚀 HTTP API endpoints for integration with frontend frameworks
- 🧠 Memory caching with configurable TTL
- 📝 Comprehensive logging for auditing and debugging

## Table of Contents

- [AI Agent](#ai-agent)
  - [Features](#features)
  - [Table of Contents](#table-of-contents)
  - [Installation](#installation)
  - [Configuration](#configuration)
  - [Basic Usage](#basic-usage)
    - [Text Generation](#text-generation)
    - [Chat Completions](#chat-completions)
    - [Embeddings](#embeddings)
    - [Blade Directives](#blade-directives)
  - [Advanced Usage](#advanced-usage)
    - [Custom Options](#custom-options)
    - [Error Handling](#error-handling)
    - [Logging](#logging)
    - [Rate Limiting](#rate-limiting)
    - [Caching Responses](#caching-responses)
  - [HTTP API Endpoints](#http-api-endpoints)
    - [Get all providers](#get-all-providers)
    - [Generate text](#generate-text)
    - [Chat completion](#chat-completion)
    - [Generate embeddings](#generate-embeddings)
  - [Extending with Custom Providers](#extending-with-custom-providers)
  - [Artisan Commands](#artisan-commands)
  - [Troubleshooting](#troubleshooting)
    - [Common Issues](#common-issues)
    - [Debugging](#debugging)
  - [API Reference](#api-reference)
    - [AiAgent Facade](#aiagent-facade)
    - [AiLoggerService](#ailoggerservice)
  - [Testing](#testing)
  - [License](#license)
  - [Author](#author)

## Installation

You can install the package via composer:

```bash
composer require ai-agent/ai-agent
```

Publish the configuration file:

```bash
php artisan vendor:publish --provider="AiAgent\Providers\AiAgentServiceProvider" --tag="config"
```

Publish migrations (optional):

```bash
php artisan vendor:publish --provider="AiAgent\Providers\AiAgentServiceProvider" --tag="migrations"
```

Run migrations to create the AI interactions table:

```bash
php artisan migrate
```

## Configuration

Configure your AI providers in the `config/ai-agent.php` file:

```php
return [
    'default_provider' => env('AI_DEFAULT_PROVIDER', 'openai'),
    
    'providers' => [
        'openai' => [
            'enabled' => true,
            'adapter' => \AiAgent\Adapters\OpenAiAdapter::class,
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4-turbo-preview'),
            'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
        ],
        'anthropic' => [
            'enabled' => true,
            'adapter' => \AiAgent\Adapters\AnthropicAdapter::class,
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-3-opus-20240229'),
        ],
        'gemini' => [
            'enabled' => true,
            'adapter' => \AiAgent\Adapters\GeminiAdapter::class,
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-pro'),
            'embedding_model' => env('GEMINI_EMBEDDING_MODEL', 'embedding-001'),
        ],
    ],
    
    'logging' => [
        'enabled' => env('AI_LOGGING_ENABLED', true),
        'channel' => env('AI_LOGGING_CHANNEL', 'stack'),
    ],
    
    'rate_limiting' => [
        'enabled' => env('AI_RATE_LIMITING_ENABLED', true),
        'max_requests' => env('AI_RATE_LIMITING_MAX_REQUESTS', 60),
        'decay_minutes' => env('AI_RATE_LIMITING_DECAY_MINUTES', 1),
    ],
    
    'cache' => [
        'enabled' => env('AI_CACHE_ENABLED', true),
        'ttl' => env('AI_CACHE_TTL', 3600), // seconds
        'prefix' => env('AI_CACHE_PREFIX', 'ai_agent_'),
    ],
    
    'routes' => [
        'enabled' => env('AI_ROUTES_ENABLED', true),
        'prefix' => env('AI_ROUTES_PREFIX', 'api/ai'),
        'middleware' => ['api', 'throttle:60,1'],
    ],
];
```

## Basic Usage

### Text Generation

```php
use AiAgent\Facades\AiAgent;

// Using the default provider
$response = AiAgent::generate('Write a haiku about programming');

// Using a specific provider
$response = AiAgent::provider('anthropic')->generate('Write a haiku about programming');

// Using with options
$response = AiAgent::generate('Explain quantum computing', [
    'temperature' => 0.7,
    'max_tokens' => 500
]);
```

### Chat Completions

```php
$messages = [
    ['role' => 'user', 'content' => 'Hello, who are you?'],
    ['role' => 'assistant', 'content' => 'I am an AI assistant. How can I help you today?'],
    ['role' => 'user', 'content' => 'Tell me a joke about programming.'],
];

$response = AiAgent::chat($messages);

// Using a specific provider with options
$response = AiAgent::provider('gemini')->chat($messages, [
    'temperature' => 0.9,
]);
```

### Embeddings

```php
// Single text embedding
$embedding = AiAgent::embeddings('Convert this text to a vector representation');

// Multiple text embeddings
$embeddings = AiAgent::embeddings([
    'This is the first text to embed',
    'This is the second text to embed'
]);
```

### Blade Directives

The package provides two Blade directives for easy template integration:

```php
// Text generation
@ai('Generate a tagline for a tech company')

// Chat completion
@aichat([
    ['role' => 'user', 'content' => 'Write a short bio for a software developer']
])

// With specific provider and refresh option
@ai('Generate a tagline for a tech company', 'anthropic', true)
```

## Advanced Usage

### Custom Options

Each AI provider supports different options that can be passed to customize the request:

```php
// OpenAI options
$options = [
    'temperature' => 0.7,           // Controls randomness (0.0 to 1.0)
    'max_tokens' => 500,            // Maximum length of the response
    'top_p' => 0.9,                 // Controls diversity via nucleus sampling
    'frequency_penalty' => 0.5,     // Reduces repetition of token sequences
    'presence_penalty' => 0.5,      // Encourages discussing new topics
];

$response = AiAgent::provider('openai')->generate('Write a story', $options);
```

### Error Handling

Handle potential errors when working with AI providers:

```php
use AiAgent\Exceptions\ProviderNotFoundException;
use AiAgent\Exceptions\AdapterNotFoundException;

try {
    $response = AiAgent::provider('unknown')->generate('Test prompt');
} catch (ProviderNotFoundException $e) {
    // Handle provider not found error
    report($e);
    $response = 'Sorry, the AI provider is not available.';
} catch (\Exception $e) {
    // Handle general errors
    report($e);
    $response = 'Sorry, there was an error processing your request.';
}
```

### Logging

Enable or disable logging at runtime:

```php
use AiAgent\Facades\AiAgent;

// Get the logger service
$logger = app(\AiAgent\Services\AiLoggerService::class);

// Disable logging for a specific operation
$logger->setEnabled(false);
$response = AiAgent::generate('This won\'t be logged');
$logger->setEnabled(true);

// Check if logging is enabled
if ($logger->isEnabled()) {
    // Do something
}
```

### Rate Limiting

The package includes built-in rate limiting to prevent excessive API usage. You can configure this in the `config/ai-agent.php` file:

```php
'rate_limiting' => [
    'enabled' => true,
    'max_requests' => 60,    // Maximum number of requests
    'decay_minutes' => 1,    // Time window in minutes
],
```

### Caching Responses

Cache AI responses to reduce API costs and improve performance:

```php
use Illuminate\Support\Facades\Cache;

// With custom caching
$cacheKey = 'ai_response_' . md5('My prompt');

if (Cache::has($cacheKey)) {
    $response = Cache::get($cacheKey);
} else {
    $response = AiAgent::generate('My prompt');
    Cache::put($cacheKey, $response, now()->addHours(24));
}

// Using built-in caching with Blade directives
// The third parameter (true) forces a refresh, bypassing the cache
@ai('Generate a quote', 'openai', false)  // Uses cache if available
```

## HTTP API Endpoints

The package provides API endpoints for integration with frontend frameworks:

### Get all providers
```
GET /api/ai/providers
```

Response:
```json
{
    "providers": ["openai", "anthropic", "gemini"],
    "default": "openai"
}
```

### Generate text
```
POST /api/ai/generate
```

Request:
```json
{
    "prompt": "Explain artificial intelligence",
    "provider": "openai",  // optional
    "options": {
        "temperature": 0.7,
        "max_tokens": 500
    }
}
```

Response:
```json
{
    "result": "Artificial intelligence (AI) refers to..."
}
```

### Chat completion
```
POST /api/ai/chat
```

Request:
```json
{
    "messages": [
        {"role": "user", "content": "What is Laravel?"}
    ],
    "provider": "anthropic",  // optional
    "options": {
        "temperature": 0.7
    }
}
```

### Generate embeddings
```
POST /api/ai/embeddings
```

Request:
```json
{
    "input": "Convert this text to a vector",
    "provider": "openai",  // optional
    "options": {
        "model": "text-embedding-3-small"  // optional
    }
}
```

## Extending with Custom Providers

You can create your own adapters to integrate additional AI providers:

1. Create a new adapter class that extends the BaseAdapter:

```php
namespace App\Adapters;

use AiAgent\Adapters\BaseAdapter;

class CustomAdapter extends BaseAdapter
{
    public function generate(string $prompt, array $options = []): string
    {
        // Implement your custom logic to generate text
        // Example:
        $api_key = $this->getConfig('api_key');
        $model = $this->getConfig('model', 'default-model');
        
        // Make API request...
        
        return $response;
    }
    
    public function chat(array $messages, array $options = []): array
    {
        // Implement chat functionality
    }
    
    public function embeddings($input, array $options = []): array
    {
        // Implement embeddings functionality
    }
}
```

2. Register your custom adapter in the config file:

```php
'providers' => [
    // Other providers...
    
    'custom' => [
        'enabled' => true,
        'adapter' => \App\Adapters\CustomAdapter::class,
        'api_key' => env('CUSTOM_API_KEY'),
        'model' => env('CUSTOM_MODEL', 'default-model'),
        // Add any other configuration your adapter needs
    ],
],
```

3. Use your custom provider:

```php
$response = AiAgent::provider('custom')->generate('Hello, custom AI!');
```

## Artisan Commands

The package provides helpful Artisan commands:

```bash
# List all available AI providers with their features
php artisan ai:providers
```

## Troubleshooting

### Common Issues

1. **API Key Not Found**

   Make sure you've set the appropriate API keys in your `.env` file:
   
   ```
   OPENAI_API_KEY=your-openai-key
   ANTHROPIC_API_KEY=your-anthropic-key
   GEMINI_API_KEY=your-gemini-key
   ```

2. **Rate Limiting Errors**

   If you're hitting rate limits, consider:
   
   - Increasing the rate limit in the configuration
   - Implementing better caching strategies
   - Optimizing your prompt to reduce token usage

3. **Provider Not Found Error**

   Ensure the provider is correctly configured in `config/ai-agent.php` and that the `enabled` flag is set to `true`.

### Debugging

Enable more detailed logging to troubleshoot issues:

```php
// In your .env file
AI_LOGGING_ENABLED=true
AI_LOGGING_CHANNEL=stack
```

## API Reference

The package provides the following core API:

### AiAgent Facade

- `AiAgent::provider(string $name)`: Get a specific AI provider
- `AiAgent::generate(string $prompt, array $options = [], string $provider = null)`: Generate text
- `AiAgent::chat(array $messages, array $options = [], string $provider = null)`: Get chat completion
- `AiAgent::embeddings(string|array $input, array $options = [], string $provider = null)`: Generate embeddings
- `AiAgent::getProviderNames()`: Get all available provider names

### AiLoggerService

- `log(string $provider, string $type, $input, $output, array $options = [], int $tokensUsed = 0, float $duration = 0, bool $success = true, string $error = null, ?int $userId = null)`: Log an AI interaction
- `isEnabled()`: Check if logging is enabled
- `setEnabled(bool $enabled)`: Enable or disable logging

## Testing

```bash
composer test
```

## License

This package is open-sourced software licensed under the MIT license.

## Author

Created by [Arseno25](https://github.com/Arseno25)
