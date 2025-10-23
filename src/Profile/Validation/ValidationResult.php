<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Profile\Validation;

/**
 * Result of profile validation.
 *
 * Contains all errors and warnings found during validation.
 * Validation is considered successful if there are no errors (warnings are acceptable).
 */
final class ValidationResult
{
    /** @var list<ValidationError> */
    private array $errors = [];

    /** @var list<ValidationError> */
    private array $warnings = [];

    /**
     * Check if validation passed (no errors).
     *
     * Warnings do not cause validation to fail.
     */
    public function isValid(): bool
    {
        return !$this->hasErrors();
    }

    /**
     * Check if there are any errors.
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Check if there are any warnings.
     */
    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    /**
     * Get all errors.
     *
     * @return list<ValidationError>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get all warnings.
     *
     * @return list<ValidationError>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Get all issues (errors and warnings combined).
     *
     * @return list<ValidationError>
     */
    public function getAllIssues(): array
    {
        return array_merge($this->errors, $this->warnings);
    }

    /**
     * Add an error to the result.
     */
    public function addError(ValidationError $error): void
    {
        if (!$error->isError()) {
            throw new \InvalidArgumentException('Only errors can be added via addError()');
        }

        $this->errors[] = $error;
    }

    /**
     * Add a warning to the result.
     */
    public function addWarning(ValidationError $warning): void
    {
        if (!$warning->isWarning()) {
            throw new \InvalidArgumentException('Only warnings can be added via addWarning()');
        }

        $this->warnings[] = $warning;
    }

    /**
     * Add a validation issue (error or warning).
     *
     * Automatically routes to addError() or addWarning() based on severity.
     */
    public function addIssue(ValidationError $issue): void
    {
        if ($issue->isError()) {
            $this->addError($issue);
        } else {
            $this->addWarning($issue);
        }
    }

    /**
     * Get count of errors.
     */
    public function getErrorCount(): int
    {
        return count($this->errors);
    }

    /**
     * Get count of warnings.
     */
    public function getWarningCount(): int
    {
        return count($this->warnings);
    }

    /**
     * Get total count of all issues.
     */
    public function getTotalIssueCount(): int
    {
        return $this->getErrorCount() + $this->getWarningCount();
    }

    /**
     * Format all errors for display.
     *
     * @return list<string>
     */
    public function formatErrors(): array
    {
        return array_map(
            static fn (ValidationError $error) => $error->format(),
            $this->errors
        );
    }

    /**
     * Format all warnings for display.
     *
     * @return list<string>
     */
    public function formatWarnings(): array
    {
        return array_map(
            static fn (ValidationError $warning) => $warning->format(),
            $this->warnings
        );
    }

    /**
     * Format all issues for display.
     *
     * @return list<string>
     */
    public function formatAllIssues(): array
    {
        return array_merge($this->formatErrors(), $this->formatWarnings());
    }

    /**
     * Get a summary of the validation result.
     */
    public function getSummary(): string
    {
        if ($this->isValid() && !$this->hasWarnings()) {
            return '✓ All profiles are valid!';
        }

        $parts = [];

        if ($this->hasErrors()) {
            $parts[] = sprintf('%d error(s)', $this->getErrorCount());
        }

        if ($this->hasWarnings()) {
            $parts[] = sprintf('%d warning(s)', $this->getWarningCount());
        }

        return 'Profile validation: ' . implode(', ', $parts);
    }
}
