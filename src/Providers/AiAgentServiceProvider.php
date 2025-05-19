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
            return "<?php
                \$aiId = 'ai-' . uniqid();
                echo '<div id=\"' . \$aiId . '\"><div class=\"ai-loading\"><div class=\"ai-loading-indicator\"><div></div><div></div><div></div></div><span>AI is thinking...</span></div>';
                \$aiContent = app('ai-agent')->generate({$expression});
                echo '<div class=\"ai-content\" style=\"display: none;\">' . \$aiContent . '</div>';
                echo '<script>
                    document.addEventListener(\"DOMContentLoaded\", function() {
                        const container = document.getElementById(\"' . \$aiId . '\");
                        if (container) {
                            const loading = container.querySelector(\".ai-loading\");
                            const content = container.querySelector(\".ai-content\");
                            setTimeout(() => {
                                loading.style.display = \"none\";
                                content.style.display = \"block\";
                            }, 500);
                        }
                    });
                </script></div>';
            ?>";
        });

        // Chat with AI directive
        Blade::directive('aichat', function ($expression) {
            return "<?php
                \$aiChatId = 'aichat-' . uniqid();
                echo '<div id=\"' . \$aiChatId . '\"><div class=\"ai-loading\"><div class=\"ai-loading-indicator\"><div></div><div></div><div></div></div><span>AI is processing chat...</span></div>';
                \$aiChatContent = app('ai-agent')->chat({$expression})['message']['content'] ?? '';
                echo '<div class=\"ai-content\" style=\"display: none;\">' . \$aiChatContent . '</div>';
                echo '<script>
                    document.addEventListener(\"DOMContentLoaded\", function() {
                        const container = document.getElementById(\"' . \$aiChatId . '\");
                        if (container) {
                            const loading = container.querySelector(\".ai-loading\");
                            const content = container.querySelector(\".ai-content\");
                            setTimeout(() => {
                                loading.style.display = \"none\";
                                content.style.display = \"block\";
                            }, 500);
                        }
                    });
                </script></div>';
            ?>";
        });

        // Register CSS for loading indicators
        Blade::directive('aiStyles', function () {
            return "<?php echo '<style>
                .ai-loading {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    margin: 10px 0;
                    padding: 10px;
                    background-color: #f8f9fa;
                    border-radius: 5px;
                    font-family: sans-serif;
                }
                .ai-loading span {
                    color: #6c757d;
                    font-size: 14px;
                }
                .ai-loading-indicator {
                    display: inline-flex;
                    align-items: center;
                }
                .ai-loading-indicator > div {
                    width: 8px;
                    height: 8px;
                    margin: 0 2px;
                    background-color: #4361ee;
                    border-radius: 50%;
                    animation: ai-bounce 1.4s infinite ease-in-out both;
                }
                .ai-loading-indicator > div:nth-child(1) {
                    animation-delay: -0.32s;
                }
                .ai-loading-indicator > div:nth-child(2) {
                    animation-delay: -0.16s;
                }
                @keyframes ai-bounce {
                    0%, 80%, 100% {
                        transform: scale(0);
                    }
                    40% {
                        transform: scale(1.0);
                    }
                }
                .ai-content {
                    margin: 10px 0;
                }
            </style>'; ?>";
        });
    }
}
