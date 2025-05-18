<?php

use Illuminate\Support\Facades\Route;
use AiAgent\Http\Controllers\AiController;

/*
|--------------------------------------------------------------------------
| AI Agent API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for the AI Agent package.
| These routes are loaded by the package service provider.
|
*/

// Only register routes if they are enabled in config
if (config('ai-agent.routes.enabled', true)) {
  Route::prefix(config('ai-agent.routes.prefix', 'api/ai'))
    ->middleware(config('ai-agent.routes.middleware', ['api']))
    ->group(function () {
      // Get all available providers
      Route::get('/providers', [AiController::class, 'providers']);

      // Generate content
      Route::post('/generate', [AiController::class, 'generate']);

      // Chat completion
      Route::post('/chat', [AiController::class, 'chat']);

      // Generate embeddings
      Route::post('/embeddings', [AiController::class, 'embeddings']);
    });
}
