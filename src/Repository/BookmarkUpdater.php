<?php

declare(strict_types=1);

namespace App\Repository;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

final class BookmarkUpdater
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Replace url, title and tags of an existing bookmark completely.
     *
     * @param list<string> $tags
     *
     * @return array<string, mixed>|null null when the id is unknown
     */
    public function update(int $id, string $url, string $title, array $tags): ?array
    {
        $bookmark = $this->loadBookmark($id);
        if ($bookmark === null) {
            return null;
        }

        $normalizedTags = $this->normalizeTags($tags);
        $currentTags = $this->loadTags($id);

        $changed = $bookmark['url'] !== $url
            || $bookmark['title'] !== $title
            || $currentTags !== $normalizedTags;

        if (!$changed) {
            return [
                'id' => $id,
                'url' => (string) $bookmark['url'],
                'title' => (string) $bookmark['title'],
                'tags' => $currentTags,
                'created_at' => (string) $bookmark['created_at'],
                'updated_at' => (string) $bookmark['updated_at'],
            ];
        }

        $updatedAt = $this->nextTimestamp((string) $bookmark['updated_at']);

        $this->pdo->beginTransaction();
        try {
            $update = $this->pdo->prepare(
                'UPDATE bookmarks
                 SET url = :url, title = :title, updated_at = :updated_at
                 WHERE id = :id'
            );
            $update->execute([
                ':url' => $url,
                ':title' => $title,
                ':updated_at' => $updatedAt,
                ':id' => $id,
            ]);

            $deleteLinks = $this->pdo->prepare('DELETE FROM bookmark_tags WHERE bookmark_id = :id');
            $deleteLinks->execute([':id' => $id]);

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
                    ':bookmark_id' => $id,
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
            'id' => $id,
            'url' => $url,
            'title' => $title,
            'tags' => $normalizedTags,
            'created_at' => (string) $bookmark['created_at'],
            'updated_at' => $updatedAt,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadBookmark(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, url, title, created_at, updated_at FROM bookmarks WHERE id = :id'
        );
        $statement->execute([':id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @return list<string>
     */
    private function loadTags(int $id): array
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
     * @param array<int, mixed> $tags
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

    /**
     * Build an updated_at that is strictly newer than the stored value.
     *
     * Timestamps have second precision, so a change made in the same second as
     * the previous write would otherwise be indistinguishable to a client.
     */
    private function nextTimestamp(string $previous): string
    {
        $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');

        if ($now > $previous) {
            return $now;
        }

        return (new DateTimeImmutable($previous))
            ->modify('+1 second')
            ->format('Y-m-d\TH:i:s\Z');
    }
}
