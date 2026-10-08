<?php

declare(strict_types=1);

namespace App\Http;

final class Request
{
    /**
     * @param array<string, mixed> $query
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query = [],
        private readonly ?string $rawBody = null
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = '/';
        }

        $queryString = parse_url($uri, PHP_URL_QUERY);
        $query = [];
        if (is_string($queryString) && $queryString !== '') {
            parse_str($queryString, $query);
        }

        $body = file_get_contents('php://input');

        return new self($method, $path, $query, $body === false ? null : $body);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(string $key): ?string
    {
        $value = $this->query[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function json(): array
    {
        if ($this->rawBody === null || trim($this->rawBody) === '') {
            throw new ApiException('invalid_json', 'The request body is not valid JSON.', 400);
        }

        try {
            $decoded = json_decode($this->rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ApiException('invalid_json', 'The request body is not valid JSON.', 400);
        }

        if (!is_array($decoded)) {
            throw new ApiException('invalid_json', 'The request body is not valid JSON.', 400);
        }

        return $decoded;
    }
}
