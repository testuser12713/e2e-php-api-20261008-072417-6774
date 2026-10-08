<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\ApiException;
use App\Http\Request;
use App\Http\Response;
use App\Repository\BookmarkDeleter;
use PDO;

final class DeleteBookmarkController
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function handle(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);

        $deleter = new BookmarkDeleter($this->pdo);
        if (!$deleter->delete($id)) {
            throw new ApiException('not_found', 'The requested bookmark was not found.', 404);
        }

        return Response::noContent();
    }
}
