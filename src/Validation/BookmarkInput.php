<?php

declare(strict_types=1);

namespace App\Validation;

use App\Http\ApiException;

final class BookmarkInput
{
    /**
     * @param array<string, mixed> $payload
     *
     * @return array{url: string, title: string, tags: list<string>}
     */
    public static function validate(array $payload): array
    {
        $url = $payload['url'] ?? null;
        if (!is_string($url) || trim($url) === '') {
            throw new ApiException('validation_error', 'The "url" field is required.', 400);
        }
        $url = trim($url);

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!is_string($scheme) || !in_array(strtolower($scheme), ['http', 'https'], true)) {
            throw new ApiException(
                'validation_error',
                'The "url" field must be a valid http or https URL.',
                400
            );
        }
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new ApiException(
                'validation_error',
                'The "url" field must be a valid http or https URL.',
                400
            );
        }

        $title = $payload['title'] ?? null;
        if (!is_string($title) || trim($title) === '') {
            throw new ApiException('validation_error', 'The "title" field is required.', 400);
        }
        $title = trim($title);

        $tags = $payload['tags'] ?? [];
        if (!is_array($tags)) {
            throw new ApiException(
                'validation_error',
                'The "tags" field must be an array of strings.',
                400
            );
        }

        $normalizedTags = [];
        foreach ($tags as $tag) {
            if (!is_string($tag)) {
                throw new ApiException('validation_error', 'Each tag must be a string.', 400);
            }

            $tag = strtolower(trim($tag));
            if ($tag === '' || in_array($tag, $normalizedTags, true)) {
                continue;
            }

            $normalizedTags[] = $tag;
        }

        return [
            'url' => $url,
            'title' => $title,
            'tags' => array_values($normalizedTags),
        ];
    }
}
