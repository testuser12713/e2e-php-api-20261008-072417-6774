<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Database\Connection;
use App\Database\Schema;
use PDO;
use RuntimeException;

final class TempDatabase
{
    public static function create(): PDO
    {
        $path = tempnam(sys_get_temp_dir(), 'bookmarks_');
        if ($path === false) {
            throw new RuntimeException('Could not create a temporary database file.');
        }

        $pdo = Connection::open($path);
        Schema::migrate($pdo);

        return $pdo;
    }

    /**
     * @param list<string> $tags
     */
    public static function seedBookmark(PDO $pdo, string $url, string $title, array $tags = []): int
    {
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');

        $insertBookmark = $pdo->prepare(
            'INSERT INTO bookmarks (url, title, created_at, updated_at)
             VALUES (:url, :title, :created_at, :updated_at)'
        );
        $insertBookmark->execute([
            ':url' => $url,
            ':title' => $title,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        $bookmarkId = (int) $pdo->lastInsertId();

        $normalized = [];
        foreach ($tags as $tag) {
            $tag = strtolower(trim($tag));
            if ($tag === '' || in_array($tag, $normalized, true)) {
                continue;
            }
            $normalized[] = $tag;
        }

        $findTag = $pdo->prepare('SELECT id FROM tags WHERE name = :name');
        $insertTag = $pdo->prepare('INSERT INTO tags (name) VALUES (:name)');
        $linkTag = $pdo->prepare(
            'INSERT INTO bookmark_tags (bookmark_id, tag_id, position) VALUES (:bookmark_id, :tag_id, :position)'
        );

        $position = 0;
        foreach ($normalized as $tag) {
            $findTag->execute([':name' => $tag]);
            $tagId = $findTag->fetchColumn();

            if ($tagId === false) {
                $insertTag->execute([':name' => $tag]);
                $tagId = $pdo->lastInsertId();
            }

            $linkTag->execute([
                ':bookmark_id' => $bookmarkId,
                ':tag_id' => (int) $tagId,
                ':position' => $position,
            ]);
            $position++;
        }

        return $bookmarkId;
    }
}
