<?php

namespace AiAgent\Providers;

use AiAgent\AiAgent;
use AiAgent\Console\Commands\AiProvidersCommand;
use AiAgent\Services\AiLoggerService;
use AiAgent\Services\AiService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AiAgentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/ai-agent.php',
            'ai-agent'
        );

        // Register the main class
        $this->app->singleton('ai-agent', function ($app) {
            return new AiAgent($app);
        });

        // Register the AI service
        $this->app->singleton(AiService::class, function ($app) {
            return new AiService($app);
        });

        // Register the logger service
        $this->app->singleton(AiLoggerService::class, function ($app) {
            return new AiLoggerService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../../config/ai-agent.php' => config_path('ai-agent.php'),
        ], 'ai-agent-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../../database/migrations/' => database_path('migrations'),
        ], 'ai-agent-migrations');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                AiProvidersCommand::class,
            ]);
        }

        // Register blade directives
        $this->registerBladeDirectives();
    }

    /**
     * Register blade directives for AI generation.
     */
    protected function registerBladeDirectives(): void
    {
        // Generate AI content directive
        Blade::directive('ai', function ($expression) {
            return "<?php echo app('ai-agent')->generate({$expression}); ?>";
        });

        // Chat with AI directive
        Blade::directive('aichat', function ($expression) {
            return "<?php echo app('ai-agent')->chat({$expression})['message']['content'] ?? ''; ?>";
        });
    }
}
