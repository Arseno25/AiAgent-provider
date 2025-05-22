<?php

namespace AiAgent\Exceptions;

/**
 * Represents an API authentication failure.
 *
 * This exception is typically thrown when an API request fails due to
 * invalid credentials, insufficient permissions, or other authentication/authorization issues.
 * Corresponds to HTTP status codes like 401 (Unauthorized) or 403 (Forbidden).
 */
class ApiAuthenticationException extends ApiException
{
    // No specific logic needed in this class for now,
    // it inherits behavior from ApiException and provides a distinct type.
}
