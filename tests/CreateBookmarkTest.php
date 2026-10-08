<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\CreateBookmarkController;
use App\Database\Connection;
use App\Http\ApiException;
use App\Http\Request;
use App\Http\Response;
use App\Repository\BookmarkCreator;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\TempDatabase;

final class CreateBookmarkTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = TempDatabase::create();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function request(array $payload): Request
    {
        return new Request('POST', '/bookmarks', [], (string) json_encode($payload));
    }

    private function rawRequest(string $body): Request
    {
        return new Request('POST', '/bookmarks', [], $body);
    }

    private function controller(): CreateBookmarkController
    {
        return new CreateBookmarkController($this->pdo);
    }

    private function countRows(string $table): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }

    public function testCreateReturns201WithCompleteBookmark(): void
    {
        $request = $this->request([
            'url' => 'https://example.com/php',
            'title' => 'PHP Manual',
            'tags' => ['PHP', 'Web'],
        ]);

        $response = $this->controller()->handle($request, []);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(201, $response->status());

        $data = $response->data();
        self::assertIsArray($data);
        self::assertSame('https://example.com/php', $data['url']);
        self::assertSame('PHP Manual', $data['title']);
        self::assertSame(['php', 'web'], $data['tags']);
        self::assertIsInt($data['id']);
        self::assertGreaterThan(0, $data['id']);

        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $data['created_at']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $data['updated_at']);
        self::assertSame($data['created_at'], $data['updated_at']);
    }

    public function testCreatedBookmarkIsPersistedInTheSqliteFile(): void
    {
        $request = $this->request([
            'url' => 'https://example.com/persisted',
            'title' => 'Persisted',
            'tags' => ['php'],
        ]);

        $response = $this->controller()->handle($request, []);
        $id = $response->data()['id'];

        $databasePath = (string) $this->pdo->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC)[0]['file'];
        self::assertNotSame('', $databasePath);

        $fresh = Connection::open($databasePath);
        $row = $fresh->query('SELECT url, title, created_at, updated_at FROM bookmarks WHERE id = ' . (int) $id)
            ->fetch(PDO::FETCH_ASSOC);

        self::assertSame('https://example.com/persisted', $row['url']);
        self::assertSame('Persisted', $row['title']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $row['created_at']);
    }

    public function testTagsAreNormalisedAndDeduplicatedInFirstOccurrenceOrder(): void
    {
        $request = $this->request([
            'url' => 'https://example.com/tags',
            'title' => 'Tags',
            'tags' => ['  PHP ', 'web', 'php', 'API', 'Web'],
        ]);

        $response = $this->controller()->handle($request, []);
        self::assertSame(['php', 'web', 'api'], $response->data()['tags']);

        $stored = $this->pdo->query(
            'SELECT tags.name FROM tags
             JOIN bookmark_tags ON bookmark_tags.tag_id = tags.id
             WHERE bookmark_tags.bookmark_id = ' . (int) $response->data()['id'] . '
             ORDER BY bookmark_tags.position'
        )->fetchAll(PDO::FETCH_COLUMN);

        self::assertSame(['php', 'web', 'api'], $stored);
    }

    public function testTwoBookmarksMayShareTheSameTag(): void
    {
        $this->controller()->handle(
            $this->request([
                'url' => 'https://example.com/one',
                'title' => 'One',
                'tags' => ['shared', 'one'],
            ]),
            []
        );
        $this->controller()->handle(
            $this->request([
                'url' => 'https://example.com/two',
                'title' => 'Two',
                'tags' => ['shared', 'two'],
            ]),
            []
        );

        self::assertSame(2, $this->countRows('bookmarks'));
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM tags WHERE name = 'shared'")->fetchColumn());
        self::assertSame(4, $this->countRows('bookmark_tags'));
    }

    public function testCreatorReturnsStoredTagsInOrder(): void
    {
        $creator = new BookmarkCreator($this->pdo);

        $bookmark = $creator->insert('https://example.com/repo', 'Repo', ['PHP', 'php', 'API']);

        self::assertSame(['php', 'api'], $bookmark['tags']);
        self::assertSame(1, $this->countRows('bookmarks'));
    }

    public function testMissingUrlAnswersValidationErrorWithoutStoring(): void
    {
        $this->assertApiError(
            $this->request(['title' => 'No URL', 'tags' => []]),
            'validation_error'
        );
    }

    public function testNonHttpUrlAnswersValidationErrorWithoutStoring(): void
    {
        $this->assertApiError(
            $this->request(['url' => 'ftp://example.com', 'title' => 'FTP', 'tags' => []]),
            'validation_error'
        );
    }

    public function testMissingTitleAnswersValidationErrorWithoutStoring(): void
    {
        $this->assertApiError(
            $this->request(['url' => 'https://example.com', 'tags' => []]),
            'validation_error'
        );
    }

    public function testEmptyTitleAnswersValidationErrorWithoutStoring(): void
    {
        $this->assertApiError(
            $this->request(['url' => 'https://example.com', 'title' => '   ', 'tags' => []]),
            'validation_error'
        );
    }

    public function testBrokenJsonAnswersInvalidJsonWithoutStoring(): void
    {
        $this->assertApiError($this->rawRequest('{"url": '), 'invalid_json');
    }

    private function assertApiError(Request $request, string $expectedCode): void
    {
        try {
            $this->controller()->handle($request, []);
            self::fail('Expected an ApiException with code ' . $expectedCode . '.');
        } catch (ApiException $exception) {
            self::assertSame($expectedCode, $exception->errorCode());
            self::assertSame(400, $exception->status());
        }

        self::assertSame(0, $this->countRows('bookmarks'));
        self::assertSame(0, $this->countRows('tags'));
        self::assertSame(0, $this->countRows('bookmark_tags'));
    }
}
