<?php

namespace AiAgent\Adapters;

use AiAgent\Contracts\AiProviderInterface;
use AiAgent\Exceptions\ApiAuthenticationException;
use AiAgent\Exceptions\ApiException;
use AiAgent\Exceptions\ApiRateLimitException;
use AiAgent\Exceptions\ApiRequestException;
use AiAgent\Exceptions\ApiServerException;
use AiAgent\Exceptions\ApiTimeoutException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

/**
 * Abstract base class for AI provider adapters.
 *
 * This class provides common functionality for AI adapters, including HTTP client setup,
 * configuration handling, and a standardized way to make API requests and handle errors.
 * Concrete adapters must implement the `getRequestOptions` method to define
 * provider-specific request parameters (like authentication headers and body).
 */
abstract class BaseAdapter implements AiProviderInterface
{
  /**
   * The configuration array for this adapter instance.
   * @var array
   */
  protected $config;

  /**
   * The Guzzle HTTP client instance used for making API requests.
   * @var Client
   */
  protected $client;

  /**
   * API Base URL for the AI provider.
   * This must be defined by concrete adapter implementations.
   * @var string
   */
  protected $apiBaseUrl;

  /**
   * Create a new adapter instance.
   *
   * Initializes the Guzzle HTTP client with common configurations like timeout.
   *
   * @param array $config The configuration array for the adapter.
   */
  public function __construct(array $config)
  {
    $this->config = $config;
    $this->client = new Client([
      'timeout' => $config['timeout'] ?? 30,
      'connect_timeout' => $config['connect_timeout'] ?? 10,
    ]);
    // Ensure apiBaseUrl is initialized by concrete classes or has a default here if applicable
  }

  /**
   * Prepares the request options for Guzzle.
   * This method must be implemented by concrete adapters to provide
   * authentication, specific headers, and body formatting.
   *
   * @param string $method HTTP method (e.g., 'POST', 'GET')
   * @param string $endpoint API endpoint
   * @param array $data Request data/payload
   * @param array $customHeaders Custom headers to merge with default ones
   * @return array Guzzle request options array (e.g., ['headers' => [...], 'json' => [...], 'query' => [...]])
   */
  protected abstract function getRequestOptions(string $method, string $endpoint, array $data = [], array $customHeaders = []): array;

  /**
   * Makes an HTTP request to the AI provider using the configured Guzzle client.
   *
   * This method centralizes the request logic, incorporating options from `getRequestOptions`,
   * constructing the full URL, and delegating error handling to `handleGuzzleException`.
   *
   * @param string $method The HTTP method (e.g., 'POST', 'GET').
   * @param string $endpoint The API endpoint (e.g., '/completions').
   * @param array $data The request data/payload, typically for POST, PUT, PATCH methods.
   * @param array $customHeaders Additional headers specific to this request, merged with defaults.
   * @return array The decoded JSON response from the API.
   * @throws \LogicException If `apiBaseUrl` is not set in the concrete adapter.
   * @throws ApiException Or a more specific subclass like ApiTimeoutException, ApiRequestException, etc.,
   *                      if the API request fails due to network issues, HTTP errors, or provider-side errors.
   */
  protected function makeRequest(string $method, string $endpoint, array $data = [], array $customHeaders = []): array
  {
    if (empty($this->apiBaseUrl)) {
        throw new \LogicException(static::class . ' must set the $apiBaseUrl property in the concrete adapter.');
    }

    // Get provider-specific request options (headers, body, query params)
    $options = $this->getRequestOptions($method, $endpoint, $data, $customHeaders);
    
    // Construct the full URL, ensuring no double slashes
    $url = rtrim($this->apiBaseUrl, '/') . '/' . ltrim($endpoint, '/');

    try {
      // Perform the HTTP request
      $response = $this->client->request($method, $url, $options);
      // Parse and return the successful response
      return $this->parseResponse($response);
    } catch (GuzzleException $e) {
      // Handle any Guzzle exceptions (network, HTTP errors)
      $this->handleGuzzleException($e);
    }
  }

  /**
   * Parses the HTTP response from the AI provider.
   *
   * Currently, it decodes the JSON response body into an associative array.
   *
   * @param ResponseInterface $response The PSR-7 response object.
   * @return array The decoded JSON response as an array.
   *               Returns null or an empty array if JSON decoding fails or the body is empty,
   *               depending on `json_decode` behavior.
   */
  protected function parseResponse(ResponseInterface $response): array
  {
    $responseBody = $response->getBody()->getContents();
    // Ensure that we return an array even if the response body is empty or not valid JSON
    return json_decode($responseBody, true) ?? [];
  }

  /**
   * Handles Guzzle exceptions and maps them to specific custom API exceptions.
   *
   * This method provides a standardized way of converting low-level Guzzle exceptions
   * into more meaningful exceptions that can be caught and handled by the application.
   *
   * @param GuzzleException $e The Guzzle exception caught during an API request.
   * @throws ApiTimeoutException If the request timed out (ConnectException).
   * @throws ApiRequestException For client-side errors like 400 Bad Request or 422 Unprocessable Entity.
   * @throws ApiAuthenticationException For authentication errors like 401 Unauthorized or 403 Forbidden.
   * @throws ApiRateLimitException For rate limiting errors like 429 Too Many Requests.
   * @throws ApiServerException For server-side errors from the provider (5xx status codes).
   * @throws ApiException For other Guzzle or HTTP errors that don't fit a more specific category.
   */
  protected function handleGuzzleException(GuzzleException $e): void
  {
    $adapterName = class_basename(static::class); // Get the short class name of the concrete adapter
    $originalGuzzleMessage = $e->getMessage();
    $statusCode = 0; // Default status code if not an HTTP error

    // Handle connection errors (e.g., DNS failure, connection timeout)
    if ($e instanceof ConnectException) {
        throw new ApiTimeoutException("{$adapterName} API Connection Timeout: " . $originalGuzzleMessage, $statusCode, $e);
    }

    // Handle HTTP errors where a response was received
    if ($e instanceof RequestException && $e->hasResponse()) {
        $response = $e->getResponse();
        $statusCode = $response->getStatusCode();
        $responseBodyContents = $response->getBody()->getContents();
        $decodedBody = json_decode($responseBodyContents, true);

        // Attempt to extract a more specific error message from the API response
        $apiErrorMessage = $originalGuzzleMessage; // Fallback to Guzzle's original message
        if (isset($decodedBody['error']['message'])) {
            $apiErrorMessage = $decodedBody['error']['message'];
        } elseif (isset($decodedBody['message'])) {
            $apiErrorMessage = $decodedBody['message'];
        } elseif (isset($decodedBody['error']) && is_string($decodedBody['error'])) {
            // Some APIs might return the error message directly as a string under the 'error' key
            $apiErrorMessage = $decodedBody['error'];
        }
        
        $exceptionMessage = "{$adapterName} API Error (Status: {$statusCode}): {$apiErrorMessage}";

        // Map HTTP status codes to specific custom exceptions
        switch ($statusCode) {
            case 400: // Bad Request
            case 422: // Unprocessable Entity (often validation errors)
                throw new ApiRequestException($exceptionMessage, $statusCode, $e);
            case 401: // Unauthorized
            case 403: // Forbidden
                throw new ApiAuthenticationException($exceptionMessage, $statusCode, $e);
            case 429: // Too Many Requests
                throw new ApiRateLimitException($exceptionMessage, $statusCode, $e);
            case 500: // Internal Server Error
            case 502: // Bad Gateway
            case 503: // Service Unavailable
            case 504: // Gateway Timeout
                throw new ApiServerException($exceptionMessage, $statusCode, $e);
            default:
                // For other HTTP errors (e.g., 404 Not Found, 405 Method Not Allowed)
                throw new ApiException($exceptionMessage, $statusCode, $e);
        }
    }

    // Fallback for GuzzleExceptions that are not ConnectException or RequestException with a response,
    // or if RequestException does not have a response (less common for typical HTTP errors).
    throw new ApiException("{$adapterName} API Communication Error: " . $originalGuzzleMessage, $statusCode, $e);
  }
  
  /**
   * Get the configured name of the AI provider.
   *
   * Falls back to the short class name of the adapter if no name is configured.
   *
   * @return string The name of the provider.
   */
  public function getName(): string
  {
    return $this->config['name'] ?? class_basename(static::class);
  }

  /**
   * Get information about the provider, including its name, class, and supported features.
   *
   * @return array An associative array with provider information.
   */
  public function info(): array
  {
    return [
      'name' => $this->getName(),
      'class' => static::class, // Use static::class for the concrete adapter class name
      'features' => $this->getSupportedFeatures(),
    ];
  }

  /**
   * Get an array indicating the supported features of this adapter.
   *
   * Features are determined by the existence of corresponding methods (generate, chat, embeddings).
   *
   * @return array Associative array where keys are feature names and values are booleans.
   */
  protected function getSupportedFeatures(): array
  {
    return [
      'generate' => method_exists($this, 'generate'),
      'chat' => method_exists($this, 'chat'),
      'embeddings' => method_exists($this, 'embeddings'),
    ];
  }

  /**
   * Get a specific configuration value for this adapter.
   *
   * @param string $key The configuration key.
   * @param mixed|null $default The default value to return if the key is not found.
   * @return mixed The configuration value or the default.
   */
  protected function getConfig(string $key, $default = null)
  {
    return $this->config[$key] ?? $default;
  }

  /**
   * Check if a specific configuration key exists for this adapter.
   *
   * @param string $key The configuration key to check.
   * @return bool True if the key exists, false otherwise.
   */
  protected function hasConfig(string $key): bool
  {
    return isset($this->config[$key]);
  }

  /**
   * Validate that all required configuration keys are present for this adapter.
   *
   * @param array $required An array of required configuration key names.
   * @throws \InvalidArgumentException If a required configuration key is missing.
   */
  protected function validateConfig(array $required): void
  {
    foreach ($required as $key) {
      if (!isset($this->config[$key])) {
        throw new \InvalidArgumentException("The [{$key}] configuration is required for " . class_basename(static::class) . ".");
      }
    }
  }
}
