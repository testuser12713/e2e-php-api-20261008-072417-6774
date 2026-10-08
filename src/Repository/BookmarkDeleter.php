<?php

declare(strict_types=1);

namespace App\Repository;

use App\Http\ApiException;
use PDO;

final class BookmarkDeleter
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function delete(int $id): bool
    {
        throw new ApiException('not_implemented', 'Deleting a bookmark is not implemented yet.', 501);
    }
}
