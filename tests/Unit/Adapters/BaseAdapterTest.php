<?php

use AiAgent\Exceptions\ApiAuthenticationException;
use AiAgent\Exceptions\ApiException;
use AiAgent\Exceptions\ApiRateLimitException;
use AiAgent\Exceptions\ApiRequestException;
use AiAgent\Exceptions\ApiServerException;
use AiAgent\Exceptions\ApiTimeoutException;
use AiAgent\Tests\Stubs\TestAdapter;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Mockery as m;

beforeEach(function () {
    $this->config = [
        'api_key' => 'test-key',
        'name' => 'custom-name',
        'timeout' => 60,
        'connect_timeout' => 20,
    ];

    // Create the TestAdapter instance
    $this->adapter = new TestAdapter($this->config);

    // Mock GuzzleHttp Client
    $this->guzzleClientMock = m::mock(GuzzleClient::class);
    $this->adapter->setClient($this->guzzleClientMock); // Use the new setter
});

afterEach(function () {
    m::close();
});


test('can get provider name from config', function () {
    expect($this->adapter->getName())->toBe('custom-name');
});

test('can get provider name from class basename when not in config', function () {
    $adapter = new TestAdapter(['api_key' => 'test-key']);
    expect($adapter->getName())->toBe('TestAdapter');
});

test('can get provider info', function () {
    $info = $this->adapter->info();
    expect($info)->toBeArray()->toHaveKeys(['name', 'class', 'features']);
    expect($info['name'])->toBe('custom-name');
    expect($info['class'])->toBe(TestAdapter::class);
    expect($info['features'])->toBeArray()->toHaveKeys(['generate', 'chat', 'embeddings']);
});

test('can get config value', function () {
    $method = new ReflectionMethod(TestAdapter::class, 'getConfig');
    $method->setAccessible(true);
    expect($method->invoke($this->adapter, 'api_key'))->toBe('test-key');
    expect($method->invoke($this->adapter, 'timeout'))->toBe(60);
    expect($method->invoke($this->adapter, 'non_existent', 'default'))->toBe('default');
});

test('can check if config exists', function () {
    $method = new ReflectionMethod(TestAdapter::class, 'hasConfig');
    $method->setAccessible(true);
    expect($method->invoke($this->adapter, 'api_key'))->toBeTrue();
    expect($method->invoke($this->adapter, 'non_existent'))->toBeFalse();
});

test('validate config throws exception when required fields are missing', function () {
    $method = new ReflectionMethod(TestAdapter::class, 'validateConfig');
    $method->setAccessible(true);
    $method->invoke($this->adapter, ['api_key']); // Should not throw
    $this->expectException(InvalidArgumentException::class);
    $method->invoke($this->adapter, ['non_existent_key']);
});

test('makeRequest throws LogicException if apiBaseUrl is not set', function () {
    $this->adapter->setApiBaseUrl(null); // Set apiBaseUrl to null
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage(TestAdapter::class . ' must set the $apiBaseUrl property in the concrete adapter.');
    $this->adapter->makeRequestWrapper('GET', '/test-endpoint'); // Use a wrapper if makeRequest is protected
});

test('makeRequest successfully makes request and parses response', function () {
    $mockResponseData = ['data' => 'success'];
    $mockPsrResponse = new GuzzleResponse(200, [], json_encode($mockResponseData));

    $this->guzzleClientMock
        ->shouldReceive('request')
        ->once()
        ->with('POST', 'http://fake-api.test/test-success', m::on(function ($options) {
            return isset($options['headers']['X-Test-Header']) && $options['headers']['X-Test-Header'] === 'TestValue' &&
                   isset($options['json']) && $options['json'] === ['param' => 'value'];
        }))
        ->andReturn($mockPsrResponse);

    $result = $this->adapter->makeRequestWrapper('POST', '/test-success', ['param' => 'value']);
    expect($result)->toBe($mockResponseData);
});

test('handleGuzzleException throws ApiTimeoutException for ConnectException', function () {
    $guzzleRequest = new GuzzleRequest('GET', 'http://fake-api.test/timeout');
    $connectException = new ConnectException("Connection timed out", $guzzleRequest);

    $this->guzzleClientMock
        ->shouldReceive('request')
        ->once()
        ->andThrow($connectException);

    $this->expectException(ApiTimeoutException::class);
    $this->expectExceptionMessageMatches('/API Connection Timeout/');
    $this->adapter->makeRequestWrapper('GET', '/timeout');
});


$statusExceptionMap = [
    400 => ApiRequestException::class,
    422 => ApiRequestException::class,
    401 => ApiAuthenticationException::class,
    403 => ApiAuthenticationException::class,
    429 => ApiRateLimitException::class,
    500 => ApiServerException::class,
    502 => ApiServerException::class,
    503 => ApiServerException::class,
    504 => ApiServerException::class,
    404 => ApiException::class, // Test a generic client error not specifically mapped
    501 => ApiException::class, // Test a generic server error not specifically mapped
];

foreach ($statusExceptionMap as $statusCode => $expectedExceptionClass) {
    test("handleGuzzleException throws {$expectedExceptionClass} for status code {$statusCode}", function () use ($statusCode, $expectedExceptionClass) {
        $guzzleRequest = new GuzzleRequest('GET', "http://fake-api.test/status-{$statusCode}");
        $errorResponseBody = json_encode(['error' => ['message' => "Error {$statusCode}"]]);
        $mockPsrResponse = new GuzzleResponse($statusCode, [], $errorResponseBody);
        $requestException = new RequestException("HTTP {$statusCode}", $guzzleRequest, $mockPsrResponse);

        $this->guzzleClientMock
            ->shouldReceive('request')
            ->once()
            ->andThrow($requestException);

        $this->expectException($expectedExceptionClass);
        $this->expectExceptionMessageMatches("/API Error \(Status: {$statusCode}\): Error {$statusCode}/");
        $this->adapter->makeRequestWrapper('GET', "/status-{$statusCode}");
    });
}

test('handleGuzzleException throws generic ApiException for non-Connect or non-Request exceptions', function () {
    $genericGuzzleException = new \GuzzleHttp\Exception\BadResponseException("Generic Guzzle problem", new GuzzleRequest('GET', 'http://fake-api.test/generic'), new GuzzleResponse(505));


    $this->guzzleClientMock
        ->shouldReceive('request')
        ->once()
        ->andThrow($genericGuzzleException);

    $this->expectException(ApiException::class);
    // The message would depend on whether it has a response or not.
    // If it's a BadResponseException, it will have a response and be handled by the RequestException block
    // This test case is a bit tricky because most Guzzle exceptions that aren't ConnectException ARE RequestExceptions
    // or extend it. Let's simulate one without a response if possible, or just ensure the default case in handleGuzzleException
    // is tested by a status code that falls to the default ApiException.
    // The 404 and 501 tests above already cover the default ApiException for RequestException.
    // This test will verify the final fallback if GuzzleException is not one of the specific types and does not have a response.
    // For a GuzzleException that is not a RequestException and not a ConnectException:
    $otherGuzzleException = new \GuzzleHttp\Exception\GuzzleException("Some other Guzzle issue");
     $this->guzzleClientMock
        ->shouldReceive('request') // Need to re-mock for this specific throw.
        ->once()
        ->andThrow($otherGuzzleException);
    
    $this->expectException(ApiException::class);
    $this->expectExceptionMessageMatches('/API Communication Error: Some other Guzzle issue/');
    $this->adapter->makeRequestWrapper('GET', '/other-guzzle-issue');

});

// Add a wrapper method in TestAdapter if makeRequest is protected, or make it public for testing
// For this example, assume TestAdapter has a public makeRequestWrapper or makeRequest is public
if (!method_exists(TestAdapter::class, 'makeRequestWrapper')) {
    TestAdapter::class::macro('makeRequestWrapper', function (string $method, string $endpoint, array $data = [], array $customHeaders = []) {
        return $this->makeRequest($method, $endpoint, $data, $customHeaders);
    });
}
