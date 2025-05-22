<?php

namespace AiAgent\Exceptions;

/**
 * Represents an error due to exceeding the API rate limits.
 *
 * This exception is typically thrown when the API provider indicates
 * that the client has sent too many requests in a given amount of time.
 * Corresponds to HTTP status code 429 (Too Many Requests).
 */
class ApiRateLimitException extends ApiException
{
    // No specific logic needed in this class for now,
    // it inherits behavior from ApiException and provides a distinct type.
}
