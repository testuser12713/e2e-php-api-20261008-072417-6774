<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\ApiException;
use App\Http\Request;
use App\Http\Response;
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
        throw new ApiException('not_implemented', 'POST /bookmarks is not implemented yet.', 501);
    }
}
