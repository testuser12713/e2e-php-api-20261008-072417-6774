<?php

declare(strict_types=1);

namespace App\Repository;

use App\Http\ApiException;
use PDO;

final class BookmarkUpdater
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param list<string> $tags
     *
     * @return array<string, mixed>|null
     */
    public function update(int $id, string $url, string $title, array $tags): ?array
    {
        throw new ApiException('not_implemented', 'Updating a bookmark is not implemented yet.', 501);
    }
}
