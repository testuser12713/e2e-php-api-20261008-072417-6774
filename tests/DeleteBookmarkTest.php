<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\DeleteBookmarkController;
use App\Http\ApiException;
use App\Http\Request;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\TempDatabase;

final class DeleteBookmarkTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = TempDatabase::create();
    }

    public function testDeleteAnswersNoContentWithoutBody(): void
    {
        $id = TempDatabase::seedBookmark($this->pdo, 'https://example.com', 'Example', ['php']);

        $response = (new DeleteBookmarkController($this->pdo))->handle(
            new Request('DELETE', '/bookmarks/' . $id),
            ['id' => (string) $id]
        );

        self::assertSame(204, $response->status());
        self::assertSame('', $response->body());
        self::assertNull($response->header('Content-Type'));
    }

    public function testDeleteRemovesBookmarkRowAndTagLinks(): void
    {
        $id = TempDatabase::seedBookmark($this->pdo, 'https://example.com', 'Example', ['php', 'api']);

        (new DeleteBookmarkController($this->pdo))->handle(
            new Request('DELETE', '/bookmarks/' . $id),
            ['id' => (string) $id]
        );

        $bookmark = $this->pdo
            ->query('SELECT COUNT(*) FROM bookmarks WHERE id = ' . $id)
            ->fetchColumn();
        self::assertSame(0, (int) $bookmark);

        $links = $this->pdo
            ->query('SELECT COUNT(*) FROM bookmark_tags WHERE bookmark_id = ' . $id)
            ->fetchColumn();
        self::assertSame(0, (int) $links);
    }

    public function testTagStillUsedByAnotherBookmarkSurvives(): void
    {
        $first = TempDatabase::seedBookmark($this->pdo, 'https://first.example', 'First', ['php', 'api']);
        $second = TempDatabase::seedBookmark($this->pdo, 'https://second.example', 'Second', ['php']);

        (new DeleteBookmarkController($this->pdo))->handle(
            new Request('DELETE', '/bookmarks/' . $first),
            ['id' => (string) $first]
        );

        $phpTags = $this->pdo
            ->query("SELECT COUNT(*) FROM tags WHERE name = 'php'")
            ->fetchColumn();
        self::assertSame(1, (int) $phpTags);

        $remainingLinks = $this->pdo
            ->query('SELECT tag_id FROM bookmark_tags WHERE bookmark_id = ' . $second)
            ->fetchAll(PDO::FETCH_COLUMN);
        self::assertCount(1, $remainingLinks);
    }

    public function testUnknownIdAnswersNotFound(): void
    {
        try {
            (new DeleteBookmarkController($this->pdo))->handle(
                new Request('DELETE', '/bookmarks/999999'),
                ['id' => '999999']
            );
            self::fail('Expected an ApiException for an unknown bookmark id.');
        } catch (ApiException $exception) {
            self::assertSame(404, $exception->status());
            self::assertSame('not_found', $exception->errorCode());
        }
    }
}
