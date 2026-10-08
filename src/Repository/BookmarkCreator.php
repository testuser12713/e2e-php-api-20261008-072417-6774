<?php

declare(strict_types=1);

namespace App\Repository;

use App\Http\ApiException;
use PDO;

final class BookmarkCreator
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param list<string> $tags
     *
     * @return array<string, mixed>
     */
    public function insert(string $url, string $title, array $tags): array
    {
        throw new ApiException('not_implemented', 'Bookmark creation is not implemented yet.', 501);
    }
}
