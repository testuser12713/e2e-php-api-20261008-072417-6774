<?php

declare(strict_types=1);

namespace App\Http;

final class Response
{
    /**
     * @param array<string, string> $headers
     */
    private function __construct(
        private readonly mixed $data,
        private readonly int $status,
        private readonly array $headers = []
    ) {
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self($data, $status);
    }

    public static function noContent(): self
    {
        return new self(null, 204);
    }

    public function withHeader(string $name, string $value): self
    {
        $headers = $this->headers;
        $headers[$name] = $value;

        return new self($this->data, $this->status, $headers);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function data(): mixed
    {
        return $this->data;
    }

    public function header(string $name): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }

        return null;
    }

    public function body(): string
    {
        if ($this->status === 204) {
            return '';
        }

        return (string) json_encode($this->data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value, true);
        }

        if ($this->status !== 204) {
            header('Content-Type: application/json; charset=utf-8', true);
            echo $this->body();
        }
    }
}
