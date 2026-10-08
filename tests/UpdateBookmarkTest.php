<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\UpdateBookmarkController;
use App\Http\ApiException;
use App\Http\Request;
use App\Repository\BookmarkUpdater;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\TempDatabase;

final class UpdateBookmarkTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = TempDatabase::create();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function putRequest(int $id, array $payload): Request
    {
        return new Request('PUT', '/bookmarks/' . $id, [], json_encode($payload));
    }

    /**
     * @return list<string>
     */
    private function tagsOf(int $id): array
    {
        $statement = $this->pdo->prepare(
            'SELECT tags.name FROM tags
             JOIN bookmark_tags ON bookmark_tags.tag_id = tags.id
             WHERE bookmark_tags.bookmark_id = :id
             ORDER BY bookmark_tags.position'
        );
        $statement->execute([':id' => $id]);

        return array_values($statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * @return array<string, mixed>
     */
    private function rowOf(int $id): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM bookmarks WHERE id = :id');
        $statement->execute([':id' => $id]);

        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    public function testUpdateReplacesUrlTitleAndTagsCompletely(): void
    {
        $id = TempDatabase::seedBookmark($this->pdo, 'https://old.example.com', 'Old title', ['old', 'shared']);

        $response = (new UpdateBookmarkController($this->pdo))->handle(
            $this->putRequest($id, [
                'url' => 'https://new.example.com',
                'title' => 'New title',
                'tags' => ['Fresh', 'SHARED', 'fresh'],
            ]),
            ['id' => (string) $id]
        );

        self::assertSame(200, $response->status());
        $data = $response->data();
        self::assertSame($id, $data['id']);
        self::assertSame('https://new.example.com', $data['url']);
        self::assertSame('New title', $data['title']);
        self::assertSame(['fresh', 'shared'], $data['tags']);
        self::assertSame(['fresh', 'shared'], $this->tagsOf($id));

        self::assertNotContains('old', $this->tagsOf($id));
    }

    public function testRealChangeProducesANewUpdatedAt(): void
    {
        $id = TempDatabase::seedBookmark($this->pdo, 'https://example.com', 'Title', ['php']);
        $before = $this->rowOf($id);

        $response = (new UpdateBookmarkController($this->pdo))->handle(
            $this->putRequest($id, [
                'url' => 'https://example.com/changed',
                'title' => 'Title',
                'tags' => ['php'],
            ]),
            ['id' => (string) $id]
        );

        self::assertSame(200, $response->status());
        $updatedAt = $response->data()['updated_at'];
        self::assertNotSame($before['updated_at'], $updatedAt);
        self::assertSame($updatedAt, $this->rowOf($id)['updated_at']);
        self::assertSame($before['created_at'], $this->rowOf($id)['created_at']);
    }

    public function testIdenticalPayloadKeepsTheOldUpdatedAt(): void
    {
        $id = TempDatabase::seedBookmark($this->pdo, 'https://example.com', 'Title', ['php', 'api']);
        $before = $this->rowOf($id);

        $response = (new UpdateBookmarkController($this->pdo))->handle(
            $this->putRequest($id, [
                'url' => 'https://example.com',
                'title' => 'Title',
                'tags' => ['PHP', 'api', 'php'],
            ]),
            ['id' => (string) $id]
        );

        self::assertSame(200, $response->status());
        self::assertSame($before['updated_at'], $response->data()['updated_at']);
        self::assertSame($before['updated_at'], $this->rowOf($id)['updated_at']);
        self::assertSame(['php', 'api'], $response->data()['tags']);
    }

    public function testUnknownIdAnswers404NotFound(): void
    {
        try {
            (new UpdateBookmarkController($this->pdo))->handle(
                $this->putRequest(9999, [
                    'url' => 'https://example.com',
                    'title' => 'Title',
                    'tags' => [],
                ]),
                ['id' => '9999']
            );
            self::fail('Expected an ApiException for an unknown id.');
        } catch (ApiException $exception) {
            self::assertSame(404, $exception->status());
            self::assertSame('not_found', $exception->errorCode());
        }
    }

    public function testRepositoryReturnsNullForUnknownId(): void
    {
        self::assertNull(
            (new BookmarkUpdater($this->pdo))->update(9999, 'https://example.com', 'Title', [])
        );
    }

    public function testInvalidUrlAnswers400ValidationErrorWithoutTouchingTheRow(): void
    {
        $id = TempDatabase::seedBookmark($this->pdo, 'https://example.com', 'Title', ['php']);
        $before = $this->rowOf($id);

        try {
            (new UpdateBookmarkController($this->pdo))->handle(
                $this->putRequest($id, [
                    'url' => 'not-a-url',
                    'title' => 'Changed',
                    'tags' => ['other'],
                ]),
                ['id' => (string) $id]
            );
            self::fail('Expected a validation error for an invalid url.');
        } catch (ApiException $exception) {
            self::assertSame(400, $exception->status());
            self::assertSame('validation_error', $exception->errorCode());
        }

        self::assertSame($before, $this->rowOf($id));
        self::assertSame(['php'], $this->tagsOf($id));
    }

    public function testEmptyTitleAnswers400ValidationErrorWithoutTouchingTheRow(): void
    {
        $id = TempDatabase::seedBookmark($this->pdo, 'https://example.com', 'Title', ['php']);
        $before = $this->rowOf($id);

        try {
            (new UpdateBookmarkController($this->pdo))->handle(
                $this->putRequest($id, [
                    'url' => 'https://example.com/changed',
                    'title' => '   ',
                    'tags' => ['other'],
                ]),
                ['id' => (string) $id]
            );
            self::fail('Expected a validation error for an empty title.');
        } catch (ApiException $exception) {
            self::assertSame(400, $exception->status());
            self::assertSame('validation_error', $exception->errorCode());
        }

        self::assertSame($before, $this->rowOf($id));
        self::assertSame(['php'], $this->tagsOf($id));
    }

    public function testBrokenJsonAnswers400InvalidJson(): void
    {
        $id = TempDatabase::seedBookmark($this->pdo, 'https://example.com', 'Title', ['php']);

        try {
            (new UpdateBookmarkController($this->pdo))->handle(
                new Request('PUT', '/bookmarks/' . $id, [], '{"url": "https://example.com"'),
                ['id' => (string) $id]
            );
            self::fail('Expected an invalid_json error for a broken body.');
        } catch (ApiException $exception) {
            self::assertSame(400, $exception->status());
            self::assertSame('invalid_json', $exception->errorCode());
        }
    }
}
