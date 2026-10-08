<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;
use Throwable;

final class BookmarkCreator
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param list<string> $tags
     *
     * @return array<string, mixed>
     */
    public function insert(string $url, string $title, array $tags): array
    {
        $normalizedTags = $this->normalizeTags($tags);
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');

        $this->pdo->beginTransaction();

        try {
            $insertBookmark = $this->pdo->prepare(
                'INSERT INTO bookmarks (url, title, created_at, updated_at)
                 VALUES (:url, :title, :created_at, :updated_at)'
            );
            $insertBookmark->execute([
                ':url' => $url,
                ':title' => $title,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);

            $bookmarkId = (int) $this->pdo->lastInsertId();

            $findTag = $this->pdo->prepare('SELECT id FROM tags WHERE name = :name');
            $insertTag = $this->pdo->prepare('INSERT INTO tags (name) VALUES (:name)');
            $linkTag = $this->pdo->prepare(
                'INSERT INTO bookmark_tags (bookmark_id, tag_id, position)
                 VALUES (:bookmark_id, :tag_id, :position)'
            );

            $position = 0;
            foreach ($normalizedTags as $tag) {
                $findTag->execute([':name' => $tag]);
                $tagId = $findTag->fetchColumn();

                if ($tagId === false) {
                    $insertTag->execute([':name' => $tag]);
                    $tagId = $this->pdo->lastInsertId();
                }

                $linkTag->execute([
                    ':bookmark_id' => $bookmarkId,
                    ':tag_id' => (int) $tagId,
                    ':position' => $position,
                ]);
                $position++;
            }

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();

            throw $exception;
        }

        return [
            'id' => $bookmarkId,
            'url' => $url,
            'title' => $title,
            'tags' => $normalizedTags,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @param array<array-key, mixed> $tags
     *
     * @return list<string>
     */
    private function normalizeTags(array $tags): array
    {
        $normalized = [];
        foreach ($tags as $tag) {
            if (!is_string($tag)) {
                continue;
            }

            $tag = strtolower(trim($tag));
            if ($tag === '' || in_array($tag, $normalized, true)) {
                continue;
            }

            $normalized[] = $tag;
        }

        return $normalized;
    }
}
