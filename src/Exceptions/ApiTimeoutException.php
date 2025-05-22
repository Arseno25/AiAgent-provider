<?php

namespace AiAgent\Exceptions;

/**
 * Represents an error due to a request timeout during an API interaction.
 *
 * This exception is typically thrown when a connection to the AI provider's
 * server cannot be established or when the request takes too long to complete,
 * often resulting from a `GuzzleHttp\Exception\ConnectException`.
 */
class ApiTimeoutException extends ApiException
{
    // No specific logic needed in this class for now,
    // it inherits behavior from ApiException and provides a distinct type.
}
