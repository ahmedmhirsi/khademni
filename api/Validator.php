<?php
/**
 * KHADEMNI — Input Validator
 */

class Validator {
    private array $errors = [];

    public function required(string $field, ?string $value, string $label = ''): self {
        $label = $label ?: $field;
        if ($value === null || trim($value) === '') {
            $this->errors[$field] = "$label is required.";
        }
        return $this;
    }

    public function email(string $field, ?string $value): self {
        if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = 'Please enter a valid email address.';
        }
        return $this;
    }

    public function minLength(string $field, ?string $value, int $min, string $label = ''): self {
        $label = $label ?: $field;
        if ($value && mb_strlen($value) < $min) {
            $this->errors[$field] = "$label must be at least $min characters.";
        }
        return $this;
    }

    public function maxLength(string $field, ?string $value, int $max, string $label = ''): self {
        $label = $label ?: $field;
        if ($value && mb_strlen($value) > $max) {
            $this->errors[$field] = "$label must not exceed $max characters.";
        }
        return $this;
    }

    public function enum(string $field, ?string $value, array $allowed, string $label = ''): self {
        $label = $label ?: $field;
        if ($value && !in_array($value, $allowed, true)) {
            $this->errors[$field] = "$label must be one of: " . implode(', ', $allowed) . '.';
        }
        return $this;
    }

    public function match(string $field, ?string $value1, ?string $value2, string $label = 'Passwords'): self {
        if ($value1 !== $value2) {
            $this->errors[$field] = "$label do not match.";
        }
        return $this;
    }

    public function url(string $field, ?string $value): self {
        if ($value && !filter_var($value, FILTER_VALIDATE_URL)) {
            $this->errors[$field] = 'Please enter a valid URL.';
        }
        return $this;
    }

    public function integer(string $field, $value, string $label = ''): self {
        $label = $label ?: $field;
        if ($value !== null && $value !== '' && !is_numeric($value)) {
            $this->errors[$field] = "$label must be a number.";
        }
        return $this;
    }

    public function fails(): bool {
        return !empty($this->errors);
    }

    public function errors(): array {
        return $this->errors;
    }

    public function firstError(): string {
        return reset($this->errors) ?: '';
    }
}
