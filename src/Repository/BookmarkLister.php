<?php

declare(strict_types=1);

namespace App\Repository;

use App\Http\ApiException;
use PDO;

final class BookmarkLister
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(?string $tag = null): array
    {
        throw new ApiException('not_implemented', 'Listing bookmarks is not implemented yet.', 501);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        throw new ApiException('not_implemented', 'Listing bookmarks is not implemented yet.', 501);
    }
}
