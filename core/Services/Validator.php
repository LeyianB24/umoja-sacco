<?php
declare(strict_types=1);

namespace USMS\Services;

/**
 * USMS\Services\Validator
 * Standardized input validation and sanitization.
 */
class Validator {

    private array $data;
    private array $errors = [];

    public function __construct(array $data) {
        $this->data = $data;
    }

    /**
     * Validate a field exists and is not empty.
     */
    public function required(string $field, string $message = null): self {
        if (empty($this->data[$field])) {
            $this->errors[$field][] = $message ?: ucfirst($field) . " is required.";
        }
        return $this;
    }

    /**
     * Validate email format.
     */
    public function email(string $field, string $message = null): self {
        if (!empty($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = $message ?: "Invalid email format.";
        }
        return $this;
    }

    /**
     * Validate minimum length.
     */
    public function min(string $field, int $min, string $message = null): self {
        if (!empty($this->data[$field]) && strlen((string)$this->data[$field]) < $min) {
            $this->errors[$field][] = $message ?: ucfirst($field) . " must be at least $min characters.";
        }
        return $this;
    }

    /**
     * Validate numeric fields.
     */
    public function numeric(string $field, string $message = null): self {
        if (!empty($this->data[$field]) && !is_numeric($this->data[$field])) {
            $this->errors[$field][] = $message ?: ucfirst($field) . " must be a number.";
        }
        return $this;
    }

    /**
     * Check if validation passed.
     */
    public function passes(): bool {
        return empty($this->errors);
    }

    /**
     * Get all validation errors.
     */
    public function getErrors(): array {
        return $this->errors;
    }

    /**
     * Get first error for each field.
     */
    public function getFirstErrors(): array {
        $first = [];
        foreach ($this->errors as $field => $msgs) {
            $first[$field] = $msgs[0];
        }
        return $first;
    }
}
