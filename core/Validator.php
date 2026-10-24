<?php

namespace Core;

class Validator
{
    private array $errors = [];

    public function __construct(private array $data)
    {
    }

    public function required(string $field, ?string $message = null): self
    {
        if (trim((string) ($this->data[$field] ?? '')) === '') {
            $this->errors[$field][] = $message ?? ucfirst(str_replace('_', ' ', $field)) . ' is required.';
        }
        return $this;
    }

    public function email(string $field, ?string $message = null): self
    {
        $value = $this->data[$field] ?? '';
        if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = $message ?? 'Enter a valid email address.';
        }
        return $this;
    }

    public function minLength(string $field, int $length, ?string $message = null): self
    {
        $value = $this->data[$field] ?? '';
        if ($value !== '' && strlen($value) < $length) {
            $this->errors[$field][] = $message ?? ucfirst(str_replace('_', ' ', $field)) . " must be at least {$length} characters.";
        }
        return $this;
    }

    public function matches(string $field, string $otherField, ?string $message = null): self
    {
        if (($this->data[$field] ?? null) !== ($this->data[$otherField] ?? null)) {
            $this->errors[$field][] = $message ?? ucfirst(str_replace('_', ' ', $field)) . ' does not match.';
        }
        return $this;
    }

    public function integer(string $field, ?string $message = null): self
    {
        $value = $this->data[$field] ?? '';
        if ($value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false) {
            $this->errors[$field][] = $message ?? ucfirst(str_replace('_', ' ', $field)) . ' must be a number.';
        }
        return $this;
    }

    public function numeric(string $field, ?string $message = null): self
    {
        $value = $this->data[$field] ?? '';
        if ($value !== '' && !is_numeric($value)) {
            $this->errors[$field][] = $message ?? ucfirst(str_replace('_', ' ', $field)) . ' must be a number.';
        }
        return $this;
    }

    public function unique(string $field, string $table, string $column, ?int $exceptId = null, ?string $message = null): self
    {
        $value = $this->data[$field] ?? '';
        if ($value === '') {
            return $this;
        }
        $sql = "SELECT id FROM {$table} WHERE {$column} = ?";
        $params = [$value];
        if ($exceptId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $exceptId;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetch()) {
            $this->errors[$field][] = $message ?? ucfirst(str_replace('_', ' ', $field)) . ' is already taken.';
        }
        return $this;
    }

    public function fails(): bool
    {
        return count($this->errors) > 0;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstErrors(): array
    {
        return array_map(fn($messages) => $messages[0], $this->errors);
    }
}
