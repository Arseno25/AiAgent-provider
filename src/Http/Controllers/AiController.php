<?php

namespace AiAgent\Http\Controllers;

use AiAgent\Facades\AiAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class AiController extends Controller
{
  /**
   * Get all available AI providers.
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
   * Generate content using an AI provider.
   *
   * @param Request $request
   * @return JsonResponse
   */
  public function generate(Request $request): JsonResponse
  {
    $validator = Validator::make($request->all(), [
      'prompt' => 'required|string',
      'provider' => 'nullable|string',
      'options' => 'nullable|array',
    ]);

    if ($validator->fails()) {
      return response()->json(['errors' => $validator->errors()], 422);
    }

    try {
      $result = AiAgent::generate(
        $request->input('prompt'),
        $request->input('options', []),
        $request->input('provider')
      );

      return response()->json(['result' => $result]);
    } catch (\Exception $e) {
      return response()->json(['error' => $e->getMessage()], 500);
    }
  }

  /**
   * Generate chat completion using an AI provider.
   *
   * @param Request $request
   * @return JsonResponse
   */
  public function chat(Request $request): JsonResponse
  {
    $validator = Validator::make($request->all(), [
      'messages' => 'required|array',
      'messages.*.role' => 'required|string|in:system,user,assistant',
      'messages.*.content' => 'required|string',
      'provider' => 'nullable|string',
      'options' => 'nullable|array',
    ]);

    if ($validator->fails()) {
      return response()->json(['errors' => $validator->errors()], 422);
    }

    try {
      $result = AiAgent::chat(
        $request->input('messages'),
        $request->input('options', []),
        $request->input('provider')
      );

      return response()->json(['result' => $result]);
    } catch (\Exception $e) {
      return response()->json(['error' => $e->getMessage()], 500);
    }
  }

  /**
   * Generate embeddings using an AI provider.
   *
   * @param Request $request
   * @return JsonResponse
   */
  public function embeddings(Request $request): JsonResponse
  {
    $validator = Validator::make($request->all(), [
      'input' => 'required',
      'provider' => 'nullable|string',
      'options' => 'nullable|array',
    ]);

    if ($validator->fails()) {
      return response()->json(['errors' => $validator->errors()], 422);
    }

    try {
      $result = AiAgent::embeddings(
        $request->input('input'),
        $request->input('options', []),
        $request->input('provider')
      );

      return response()->json(['result' => $result]);
    } catch (\Exception $e) {
      return response()->json(['error' => $e->getMessage()], 500);
    }
  }
}
