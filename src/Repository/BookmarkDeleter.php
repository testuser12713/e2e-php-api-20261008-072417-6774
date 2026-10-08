<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class BookmarkDeleter
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function delete(int $id): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM bookmarks WHERE id = :id');
        $statement->execute([':id' => $id]);

        return $statement->rowCount() > 0;
    }
}
