<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\ListBookmarksController;
use App\Http\Request;
use App\Repository\BookmarkLister;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\TempDatabase;

final class ListBookmarksTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function list(PDO $pdo, ?string $tag = null): array
    {
        $query = $tag === null ? [] : ['tag' => $tag];
        $controller = new ListBookmarksController($pdo);
        $response = $controller->handle(new Request('GET', '/bookmarks', $query), []);

        self::assertSame(200, $response->status());
        $data = $response->data();
        self::assertIsArray($data);

        return $data;
    }

    private function setCreatedAt(PDO $pdo, int $id, string $createdAt): void
    {
        $statement = $pdo->prepare('UPDATE bookmarks SET created_at = :created_at WHERE id = :id');
        $statement->execute([':created_at' => $createdAt, ':id' => $id]);
    }

    public function testEmptyDatabaseReturnsEmptyList(): void
    {
        $pdo = TempDatabase::create();

        $data = $this->list($pdo);

        self::assertSame(['bookmarks' => []], $data);
    }

    public function testReturnsBookmarksNewestFirst(): void
    {
        $pdo = TempDatabase::create();
        $oldest = TempDatabase::seedBookmark($pdo, 'https://oldest.test', 'Oldest');
        $middle = TempDatabase::seedBookmark($pdo, 'https://middle.test', 'Middle');
        $newest = TempDatabase::seedBookmark($pdo, 'https://newest.test', 'Newest');

        $this->setCreatedAt($pdo, $oldest, '2024-01-01T00:00:00Z');
        $this->setCreatedAt($pdo, $middle, '2024-06-01T00:00:00Z');
        $this->setCreatedAt($pdo, $newest, '2024-12-01T00:00:00Z');

        $data = $this->list($pdo);

        self::assertSame([$newest, $middle, $oldest], array_column($data['bookmarks'], 'id'));
    }

    public function testBreaksTiesOnCreatedAtByIdDescending(): void
    {
        $pdo = TempDatabase::create();
        $first = TempDatabase::seedBookmark($pdo, 'https://first.test', 'First');
        $second = TempDatabase::seedBookmark($pdo, 'https://second.test', 'Second');
        $third = TempDatabase::seedBookmark($pdo, 'https://third.test', 'Third');

        $data = $this->list($pdo);

        self::assertSame([$third, $second, $first], array_column($data['bookmarks'], 'id'));
    }

    public function testFiltersByTagCaseInsensitively(): void
    {
        $pdo = TempDatabase::create();
        $php = TempDatabase::seedBookmark($pdo, 'https://php.test', 'PHP', ['php']);
        TempDatabase::seedBookmark($pdo, 'https://other.test', 'Other', ['other']);

        $data = $this->list($pdo, 'PHP');

        self::assertSame([$php], array_column($data['bookmarks'], 'id'));
    }

    public function testUnknownTagReturnsEmptyListWithStatus200(): void
    {
        $pdo = TempDatabase::create();
        TempDatabase::seedBookmark($pdo, 'https://php.test', 'PHP', ['php']);

        $data = $this->list($pdo, 'does-not-exist');

        self::assertSame([], $data['bookmarks']);
    }

    public function testEveryBookmarkCarriesItsTagsInStoredOrder(): void
    {
        $pdo = TempDatabase::create();
        $tagged = TempDatabase::seedBookmark($pdo, 'https://tagged.test', 'Tagged', ['php', 'api', 'rest']);
        $untagged = TempDatabase::seedBookmark($pdo, 'https://untagged.test', 'Untagged');

        $lister = new BookmarkLister($pdo);
        $bookmarks = [];
        foreach ($lister->all() as $bookmark) {
            $bookmarks[$bookmark['id']] = $bookmark;
        }

        self::assertSame(['php', 'api', 'rest'], $bookmarks[$tagged]['tags']);
        self::assertSame([], $bookmarks[$untagged]['tags']);
    }

    public function testFindByIdReturnsBookmarkWithTags(): void
    {
        $pdo = TempDatabase::create();
        $id = TempDatabase::seedBookmark($pdo, 'https://found.test', 'Found', ['php', 'api']);

        $bookmark = (new BookmarkLister($pdo))->findById($id);

        self::assertIsArray($bookmark);
        self::assertSame($id, $bookmark['id']);
        self::assertSame('https://found.test', $bookmark['url']);
        self::assertSame('Found', $bookmark['title']);
        self::assertSame(['php', 'api'], $bookmark['tags']);
    }

    public function testFindByIdReturnsNullForUnknownId(): void
    {
        $pdo = TempDatabase::create();

        self::assertNull((new BookmarkLister($pdo))->findById(999999));
    }
}
