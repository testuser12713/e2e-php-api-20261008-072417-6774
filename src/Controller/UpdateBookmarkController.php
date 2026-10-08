<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\ApiException;
use App\Http\Request;
use App\Http\Response;
use App\Repository\BookmarkUpdater;
use App\Validation\BookmarkInput;
use PDO;

final class UpdateBookmarkController
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

        $payload = $request->json();
        $input = BookmarkInput::validate($payload);

        $updater = new BookmarkUpdater($this->pdo);
        $bookmark = $updater->update($id, $input['url'], $input['title'], $input['tags']);

        if ($bookmark === null) {
            throw new ApiException('not_found', 'The requested bookmark was not found.', 404);
        }

        return Response::json($bookmark);
    }
}
