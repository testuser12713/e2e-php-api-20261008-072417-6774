<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class BookmarkLister
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(?string $tag = null): array
    {
        $normalizedTag = $tag === null ? null : strtolower(trim($tag));

        if ($normalizedTag !== null) {
            $statement = $this->pdo->prepare(
                'SELECT b.id, b.url, b.title, b.created_at, b.updated_at
                 FROM bookmarks b
                 INNER JOIN bookmark_tags bt ON bt.bookmark_id = b.id
                 INNER JOIN tags t ON t.id = bt.tag_id
                 WHERE t.name = :tag
                 ORDER BY b.created_at DESC, b.id DESC'
            );
            $statement->execute([':tag' => $normalizedTag]);
        } else {
            $statement = $this->pdo->prepare(
                'SELECT id, url, title, created_at, updated_at
                 FROM bookmarks
                 ORDER BY created_at DESC, id DESC'
            );
            $statement->execute();
        }

        $bookmarks = [];
        $ids = [];
        foreach ($statement->fetchAll() as $row) {
            $id = (int) $row['id'];
            $ids[] = $id;
            $bookmarks[$id] = [
                'id' => $id,
                'url' => (string) $row['url'],
                'title' => (string) $row['title'],
                'tags' => [],
                'created_at' => (string) $row['created_at'],
                'updated_at' => (string) $row['updated_at'],
            ];
        }

        $tagsByBookmark = $this->loadTags($ids);
        foreach ($bookmarks as $id => $bookmark) {
            $bookmarks[$id]['tags'] = $tagsByBookmark[$id] ?? [];
        }

        return array_values($bookmarks);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, url, title, created_at, updated_at FROM bookmarks WHERE id = :id'
        );
        $statement->execute([':id' => $id]);

        $row = $statement->fetch();
        if ($row === false) {
            return null;
        }

        $bookmarkId = (int) $row['id'];
        $tagsByBookmark = $this->loadTags([$bookmarkId]);

        return [
            'id' => $bookmarkId,
            'url' => (string) $row['url'],
            'title' => (string) $row['title'],
            'tags' => $tagsByBookmark[$bookmarkId] ?? [],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, list<string>>
     */
    private function loadTags(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $statement = $this->pdo->prepare(
            'SELECT bt.bookmark_id, t.name
             FROM bookmark_tags bt
             INNER JOIN tags t ON t.id = bt.tag_id
             WHERE bt.bookmark_id IN (' . $placeholders . ')
             ORDER BY bt.bookmark_id ASC, bt.position ASC'
        );
        $statement->execute($ids);

        $tagsByBookmark = [];
        foreach ($statement->fetchAll() as $row) {
            $tagsByBookmark[(int) $row['bookmark_id']][] = (string) $row['name'];
        }

        return $tagsByBookmark;
    }
}
