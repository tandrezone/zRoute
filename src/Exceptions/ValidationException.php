<?php

declare(strict_types=1);

namespace zRoute\Exceptions;

use RuntimeException;

/**
 * Thrown when incoming request parameters fail validation.
 *
 * The $errors array maps parameter names to human-readable error messages,
 * allowing callers to return structured error responses.
 */
class ValidationException extends RuntimeException
{
    /**
     * @param string              $message Top-level summary message.
     * @param array<string,string> $errors  Per-field error messages keyed by parameter name.
     */
    public function __construct(
        string $message,
        private readonly array $errors = [],
    ) {
        parent::__construct($message);
    }

    /**
     * Return per-field validation errors.
     *
     * @return array<string,string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
