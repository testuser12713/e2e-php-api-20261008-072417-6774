<?php

declare(strict_types=1);

namespace Tests;

use App\Http\Request;
use App\Http\Response;
use App\Routing\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private function router(): Router
    {
        $router = new Router();
        $router->add('GET', '/health', static fn (Request $request, array $params): Response => Response::json(['status' => 'ok']));
        $router->add('GET', '/bookmarks', static fn (Request $request, array $params): Response => Response::json(['bookmarks' => []]));
        $router->add('POST', '/bookmarks', static fn (Request $request, array $params): Response => Response::json([], 201));
        $router->add('PUT', '/bookmarks/{id}', static fn (Request $request, array $params): Response => Response::json($params));
        $router->add('DELETE', '/bookmarks/{id}', static fn (Request $request, array $params): Response => Response::noContent());

        return $router;
    }

    public function testUnknownPathAnswers404NotFound(): void
    {
        $response = $this->router()->dispatch(new Request('GET', '/does-not-exist'));

        self::assertSame(404, $response->status());
        self::assertSame('not_found', $response->data()['error']['code']);
    }

    public function testKnownPathWithWrongMethodAnswers405WithAllowHeader(): void
    {
        $response = $this->router()->dispatch(new Request('POST', '/health'));

        self::assertSame(405, $response->status());
        self::assertSame('method_not_allowed', $response->data()['error']['code']);
        self::assertSame('GET', $response->header('Allow'));
    }

    public function testAllowHeaderListsEverySupportedMethod(): void
    {
        $response = $this->router()->dispatch(new Request('PATCH', '/bookmarks'));

        self::assertSame(405, $response->status());
        $allow = $response->header('Allow');
        self::assertIsString($allow);
        self::assertStringContainsString('GET', $allow);
        self::assertStringContainsString('POST', $allow);
    }

    public function testPathParametersArePassedToTheHandler(): void
    {
        $response = $this->router()->dispatch(new Request('PUT', '/bookmarks/42'));

        self::assertSame(200, $response->status());
        self::assertSame(['id' => '42'], $response->data());
    }

    public function testRouteDoesNotMatchTrailingSlashVariant(): void
    {
        $response = $this->router()->dispatch(new Request('GET', '/bookmarks/'));

        self::assertSame(404, $response->status());
    }
}
