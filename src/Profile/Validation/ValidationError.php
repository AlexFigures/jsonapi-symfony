<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Profile\Validation;

/**
 * Represents a validation error or warning for a profile requirement.
 *
 * Errors indicate critical issues that prevent the profile from working.
 * Warnings indicate potential issues that may or may not cause problems.
 */
final class ValidationError
{
    public const SEVERITY_ERROR = 'ERROR';
    public const SEVERITY_WARNING = 'WARNING';

    /**
     * @param string      $severity     Severity level: 'ERROR' or 'WARNING'
     * @param string      $profileUri   URI of the profile that failed validation
     * @param string      $resourceType JSON:API resource type
     * @param string      $message      Human-readable error message
     * @param string|null $field        Field name that caused the error (optional)
     */
    public function __construct(
        public readonly string $severity,
        public readonly string $profileUri,
        public readonly string $resourceType,
        public readonly string $message,
        public readonly ?string $field = null,
    ) {
    }

    /**
     * Create an error-level validation issue.
     */
    public static function error(
        string $profileUri,
        string $resourceType,
        string $message,
        ?string $field = null
    ): self {
        return new self(
            self::SEVERITY_ERROR,
            $profileUri,
            $resourceType,
            $message,
            $field
        );
    }

    /**
     * Create a warning-level validation issue.
     */
    public static function warning(
        string $profileUri,
        string $resourceType,
        string $message,
        ?string $field = null
    ): self {
        return new self(
            self::SEVERITY_WARNING,
            $profileUri,
            $resourceType,
            $message,
            $field
        );
    }

    /**
     * Check if this is an error.
     */
    public function isError(): bool
    {
        return $this->severity === self::SEVERITY_ERROR;
    }

    /**
     * Check if this is a warning.
     */
    public function isWarning(): bool
    {
        return $this->severity === self::SEVERITY_WARNING;
    }

    /**
     * Format the error for display in console or logs.
     *
     * Example output:
     * ✗ [urn:jsonapi:profile:soft-delete] articles: Missing required field "deletedAt"
     * ⚠ [urn:jsonapi:profile:audit-trail] comments: Field "createdAt" type mismatch
     */
    public function format(): string
    {
        $icon = $this->isError() ? '✗' : '⚠';
        $fieldInfo = $this->field !== null ? " (field: {$this->field})" : '';

        return sprintf(
            '%s [%s] %s: %s%s',
            $icon,
            $this->profileUri,
            $this->resourceType,
            $this->message,
            $fieldInfo
        );
    }

    /**
     * Get a short summary of the error.
     */
    public function getSummary(): string
    {
        return sprintf(
            '[%s] %s: %s',
            $this->resourceType,
            $this->field ?? 'general',
            $this->message
        );
    }
}
