<?php

namespace App\Services\Connection;

class ConnectionConstraintResult
{
    private function __construct(
        private readonly bool $isValid,
        private readonly ?string $error = null
    ) {}

    public static function success(): self
    {
        return new self(true);
    }

    public static function failure(string $error): self
    {
        return new self(false, $error);
    }

    public function isValid(): bool
    {
        return $this->isValid;
    }

    public function getError(): ?string
    {
        return $this->error;
    }
} 