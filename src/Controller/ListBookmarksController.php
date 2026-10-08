<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Repository\BookmarkLister;
use PDO;

final class ListBookmarksController
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function handle(Request $request, array $params): Response
    {
        $lister = new BookmarkLister($this->pdo);

        return Response::json([
            'bookmarks' => $lister->all($request->query('tag')),
        ]);
    }
}
