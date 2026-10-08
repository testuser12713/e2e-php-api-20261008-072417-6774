<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

final class ApiException extends RuntimeException
{
    public function __construct(
        private readonly string $errorCode,
        string $message,
        private readonly int $status
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * @return array{error: array{code: string, message: string}}
     */
    public function toArray(): array
    {
        return [
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
            ],
        ];
    }
}
