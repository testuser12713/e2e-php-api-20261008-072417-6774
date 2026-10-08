<?php

declare(strict_types=1);

namespace Tests;

use App\Http\Request;
use App\Routing\Router;
use PHPUnit\Framework\TestCase;

final class HealthRouteTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'health_');
        self::assertIsString($path);
        putenv('DB_PATH=' . $path);

        $this->router = require __DIR__ . '/../public/index.php';
    }

    public function testHealthRouteAnswersOk(): void
    {
        $response = $this->router->dispatch(new Request('GET', '/health'));

        self::assertSame(200, $response->status());
        self::assertSame(['status' => 'ok'], $response->data());
        self::assertSame('{"status":"ok"}', $response->body());
    }

    public function testUnknownPathAnswersNotFound(): void
    {
        $response = $this->router->dispatch(new Request('GET', '/unknown'));

        self::assertSame(404, $response->status());
        self::assertSame('not_found', $response->data()['error']['code']);
    }

    public function testWrongMethodOnHealthAnswersMethodNotAllowed(): void
    {
        $response = $this->router->dispatch(new Request('POST', '/health'));

        self::assertSame(405, $response->status());
        self::assertSame('method_not_allowed', $response->data()['error']['code']);
        self::assertSame('GET', $response->header('Allow'));
    }
}
