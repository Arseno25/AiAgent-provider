<?php

namespace AiAgent\Exceptions;

/**
 * Base class for exceptions related to AI provider API interactions.
 *
 * This exception is intended to be extended by more specific API error types
 * (e.g., authentication, request validation, rate limiting, server errors)
 * or used as a generic fallback when a more specific error category cannot be determined.
 */
class ApiException extends \RuntimeException
{
    // No specific logic needed in the base class for now,
    // but it provides a common type for catching API-related errors.
}
