<?php

namespace AiAgent\Exceptions;

/**
 * Represents an error due to an invalid or malformed API request.
 *
 * This exception is typically thrown when the API provider indicates
 * that the request itself is problematic (e.g., missing required parameters,
 * invalid data format).
 * Corresponds to HTTP status codes like 400 (Bad Request) or 422 (Unprocessable Entity).
 */
class ApiRequestException extends ApiException
{
    // No specific logic needed in this class for now,
    // it inherits behavior from ApiException and provides a distinct type.
}
