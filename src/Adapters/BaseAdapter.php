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
   * Constructs the adapter and initializes the HTTP client with configurable timeouts.
   *
   * @param array $config Adapter configuration options.
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
 * Returns provider-specific Guzzle request options for an API call.
 *
 * Concrete adapters must implement this method to supply authentication, headers, and payload formatting required by the provider.
 *
 * @param string $method HTTP method for the request.
 * @param string $endpoint API endpoint path.
 * @param array $data Optional request payload or parameters.
 * @param array $customHeaders Optional custom headers to include.
 * @return array Associative array of Guzzle request options.
 */
  protected abstract function getRequestOptions(string $method, string $endpoint, array $data = [], array $customHeaders = []): array;

  /**
   * Executes an HTTP request to the AI provider and returns the decoded JSON response.
   *
   * Constructs the full request URL using the configured API base URL and endpoint, applies provider-specific request options, and handles errors by mapping Guzzle exceptions to domain-specific exceptions. Throws a LogicException if the API base URL is not set.
   *
   * @param string $method HTTP method to use (e.g., 'POST', 'GET').
   * @param string $endpoint API endpoint path.
   * @param array $data Optional request payload.
   * @param array $customHeaders Optional additional headers for the request.
   * @return array Decoded JSON response as an associative array.
   * @throws \LogicException If the API base URL is not set in the adapter.
   * @throws ApiException If the request fails due to network, HTTP, or provider-side errors.
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
   * Decodes the JSON body of an HTTP response into an associative array.
   *
   * Returns an empty array if the response body is empty or contains invalid JSON.
   *
   * @param ResponseInterface $response The HTTP response to parse.
   * @return array Associative array representation of the JSON response, or an empty array on failure.
   */
  protected function parseResponse(ResponseInterface $response): array
  {
    $responseBody = $response->getBody()->getContents();
    // Ensure that we return an array even if the response body is empty or not valid JSON
    return json_decode($responseBody, true) ?? [];
  }

  /****
   * Converts Guzzle exceptions into domain-specific API exceptions based on error type and HTTP status code.
   *
   * Maps connection timeouts, client errors, authentication failures, rate limiting, and server errors to custom exceptions, extracting detailed error messages from the API response when available.
   *
   * @param GuzzleException $e The exception thrown during an API request.
   * @throws ApiTimeoutException If a connection timeout occurs.
   * @throws ApiRequestException For client-side errors (400, 422).
   * @throws ApiAuthenticationException For authentication failures (401, 403).
   * @throws ApiRateLimitException For rate limiting errors (429).
   * @throws ApiServerException For server-side errors (500, 502, 503, 504).
   * @throws ApiException For all other communication or HTTP errors.
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
   * Returns the configured name of the AI provider, or the adapter's class name if not set.
   *
   * @return string Provider name or adapter class name.
   */
  public function getName(): string
  {
    return $this->config['name'] ?? class_basename(static::class);
  }

  /**
   * Returns metadata about the provider, including its name, class, and supported features.
   *
   * @return array Associative array with keys 'name', 'class', and 'features'.
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
   * Returns an associative array indicating which features are supported by the adapter.
   *
   * The features `generate`, `chat`, and `embeddings` are marked as supported if the corresponding methods exist in the adapter.
   *
   * @return array Associative array with feature names as keys and boolean values indicating support.
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
   * Retrieves a configuration value by key, returning a default if the key is not set.
   *
   * @param string $key Configuration key to retrieve.
   * @param mixed|null $default Value to return if the key does not exist.
   * @return mixed The configuration value or the provided default.
   */
  protected function getConfig(string $key, $default = null)
  {
    return $this->config[$key] ?? $default;
  }

  /**
   * Determines whether a given configuration key is set for the adapter.
   *
   * @param string $key Configuration key to check.
   * @return bool True if the configuration key exists; otherwise, false.
   */
  protected function hasConfig(string $key): bool
  {
    return isset($this->config[$key]);
  }

  /**
   * Ensures all specified configuration keys are present in the adapter's configuration.
   *
   * @param array $required List of required configuration key names.
   * @throws \InvalidArgumentException If any required configuration key is missing.
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
