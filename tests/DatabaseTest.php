<?php

declare(strict_types=1);

namespace Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\TempDatabase;

final class DatabaseTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = TempDatabase::create();
    }

    public function testMigrateCreatesTheNormalisedTables(): void
    {
        $tables = $this->pdo
            ->query("SELECT name FROM sqlite_master WHERE type = 'table'")
            ->fetchAll(PDO::FETCH_COLUMN);

        self::assertContains('bookmarks', $tables);
        self::assertContains('tags', $tables);
        self::assertContains('bookmark_tags', $tables);
    }

    public function testSeedBookmarkPersistsBookmarkAndTags(): void
    {
        $id = TempDatabase::seedBookmark($this->pdo, 'https://example.com', 'Example', ['PHP', 'php', 'API']);

        $bookmark = $this->pdo
            ->query('SELECT url, title FROM bookmarks WHERE id = ' . $id)
            ->fetch(PDO::FETCH_ASSOC);
        self::assertSame('https://example.com', $bookmark['url']);
        self::assertSame('Example', $bookmark['title']);

        $tags = $this->pdo
            ->query(
                'SELECT tags.name FROM tags
                 JOIN bookmark_tags ON bookmark_tags.tag_id = tags.id
                 WHERE bookmark_tags.bookmark_id = ' . $id . '
                 ORDER BY bookmark_tags.position'
            )
            ->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame(['php', 'api'], $tags);
    }

    public function testDeletingABookmarkCascadesToItsTagLinks(): void
    {
        $id = TempDatabase::seedBookmark($this->pdo, 'https://example.com', 'Example', ['php']);

        $this->pdo->exec('DELETE FROM bookmarks WHERE id = ' . $id);

        $links = (int) $this->pdo
            ->query('SELECT COUNT(*) FROM bookmark_tags WHERE bookmark_id = ' . $id)
            ->fetchColumn();
        self::assertSame(0, $links);
    }
}
