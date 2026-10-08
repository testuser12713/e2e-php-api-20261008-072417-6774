<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Repository\BookmarkCreator;
use App\Validation\BookmarkInput;
use PDO;

final class CreateBookmarkController
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function handle(Request $request, array $params): Response
    {
        $payload = $request->json();
        $input = BookmarkInput::validate($payload);

        $creator = new BookmarkCreator($this->pdo);
        $bookmark = $creator->insert($input['url'], $input['title'], $input['tags']);

        return Response::json($bookmark, 201);
    }
}
