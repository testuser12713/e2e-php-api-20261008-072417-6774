<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

final class Schema
{
    public static function migrate(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS bookmarks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                url TEXT NOT NULL,
                title TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS tags (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE
            )'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS bookmark_tags (
                bookmark_id INTEGER NOT NULL,
                tag_id INTEGER NOT NULL,
                position INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY (bookmark_id, tag_id),
                FOREIGN KEY (bookmark_id) REFERENCES bookmarks(id) ON DELETE CASCADE,
                FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
            )'
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_bookmarks_created_at ON bookmarks (created_at)'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_bookmark_tags_tag ON bookmark_tags (tag_id)'
        );
    }
}
