<?php

declare(strict_types=1);

use App\Controller\CreateBookmarkController;
use App\Controller\DeleteBookmarkController;
use App\Controller\ListBookmarksController;
use App\Controller\UpdateBookmarkController;
use App\Database\Connection;
use App\Database\Schema;
use App\Http\ApiException;
use App\Http\Request;
use App\Http\Response;
use App\Routing\Router;

require_once __DIR__ . '/../vendor/autoload.php';

$pdo = Connection::fromEnv();
Schema::migrate($pdo);

$router = new Router();
$router->add('GET', '/health', static function (Request $request, array $params): Response {
    return Response::json(['status' => 'ok']);
});
$router->add('POST', '/bookmarks', [new CreateBookmarkController($pdo), 'handle']);
$router->add('GET', '/bookmarks', [new ListBookmarksController($pdo), 'handle']);
$router->add('PUT', '/bookmarks/{id}', [new UpdateBookmarkController($pdo), 'handle']);
$router->add('DELETE', '/bookmarks/{id}', [new DeleteBookmarkController($pdo), 'handle']);

if (PHP_SAPI !== 'cli-server') {
    return $router;
}

try {
    $response = $router->dispatch(Request::fromGlobals());
} catch (ApiException $exception) {
    $response = Response::json($exception->toArray(), $exception->status());
} catch (\Throwable $exception) {
    error_log((string) $exception);
    $response = Response::json(
        [
            'error' => [
                'code' => 'internal_error',
                'message' => 'Internal Server Error',
            ],
        ],
        500
    );
}

$response->send();
