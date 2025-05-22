<?php

namespace AiAgent\Exceptions;

/**
 * Represents an error that occurred on the AI provider's server.
 *
 * This exception is typically thrown when the API provider returns
 * a 5xx HTTP status code (e.g., 500 Internal Server Error, 502 Bad Gateway,
 * 503 Service Unavailable, 504 Gateway Timeout), indicating a problem
 * on the provider's end.
 */
class ApiServerException extends ApiException
{
    // No specific logic needed in this class for now,
    // it inherits behavior from ApiException and provides a distinct type.
}
