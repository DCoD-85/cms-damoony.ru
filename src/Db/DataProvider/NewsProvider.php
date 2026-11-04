<?php

declare(strict_types=1);

namespace Damoony\Db\DataProvider;

final class NewsProvider extends AbstractProvider
{
    /** @var array<string, string> whitelist колонок для create/update */
    private const WRITABLE = [
        'slug' => 'slug',
        'lang' => 'lang',
        'title' => 'title',
        'content' => 'content',
        'status' => 'status',
        'published_at' => 'published_at',
    ];

    public function name(): string
    {
        return 'news';
    }

    public function table(): string
    {
        return 'news';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recent(int $limit = 10, string $lang = 'ru'): array
    {
        $limit = max(1, min(100, $limit));

        $rows = $this->query(
            'SELECT id, slug, lang, title, content, status, published_at, created_at
             FROM ' . $this->physicalTable() . '
             WHERE status = :status AND lang = :lang
             ORDER BY published_at DESC, id DESC
             LIMIT :limit',
            ['status' => 'published', 'lang' => $lang, 'limit' => $limit],
        )->fetchAll();

        $out = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = $this->castRow($row);
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function by_slug(string $slug, string $lang = 'ru'): array
    {
        $row = $this->query(
            'SELECT id, slug, lang, title, content, status, published_at, created_at
             FROM ' . $this->physicalTable() . '
             WHERE slug = :slug AND lang = :lang
             LIMIT 1',
            ['slug' => $slug, 'lang' => $lang],
        )->fetch();

        return is_array($row) ? $this->castRow($row) : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function by_id(int $id): array
    {
        $row = $this->query(
            'SELECT id, slug, lang, title, content, status, published_at, created_at
             FROM ' . $this->physicalTable() . '
             WHERE id = :id
             LIMIT 1',
            ['id' => $id],
        )->fetch();

        return is_array($row) ? $this->castRow($row) : [];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $filtered = $this->filterWritable($data);

        if (!isset($filtered['slug']) || !isset($filtered['title'])) {
            return 0;
        }

        $filtered += [
            'lang' => 'ru',
            'content' => '',
            'status' => 'draft',
            'published_at' => null,
        ];

        $cols = array_keys($filtered);
        $placeholders = array_map(static fn (string $c): string => ':' . $c, $cols);

        $sql = 'INSERT INTO ' . $this->physicalTable()
            . ' (' . implode(', ', $cols) . ')'
            . ' VALUES (' . implode(', ', $placeholders) . ')';

        $this->query($sql, $filtered);

        return (int) $this->conn->pdo()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $filtered = $this->filterWritable($data);

        if ($filtered === []) {
            return;
        }

        $sets = [];
        $params = ['id' => $id];
        foreach ($filtered as $col => $val) {
            $sets[] = $col . ' = :' . $col;
            $params[$col] = $val;
        }

        $sql = 'UPDATE ' . $this->physicalTable()
            . ' SET ' . implode(', ', $sets)
            . ' WHERE id = :id';

        $this->query($sql, $params);
    }

    public function publish(int $id): void
    {
        $this->query(
            'UPDATE ' . $this->physicalTable()
            . ' SET status = :status, published_at = COALESCE(published_at, CURRENT_TIMESTAMP)'
            . ' WHERE id = :id',
            ['status' => 'published', 'id' => $id],
        );
    }

    public function delete(int $id): void
    {
        $this->query(
            'DELETE FROM ' . $this->physicalTable() . ' WHERE id = :id',
            ['id' => $id],
        );
    }

    /**
     * Поиск по title + content через LIKE (без FULLTEXT — см. v3.1-design §4.3).
     *
     * @return list<array<string, mixed>>
     */
    public function search_like(string $query, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));

        $rows = $this->query(
            'SELECT id, slug, lang, title, content, status, published_at, created_at
             FROM ' . $this->physicalTable() . '
             WHERE status = :status AND (title LIKE :q OR content LIKE :q)
             ORDER BY published_at DESC, id DESC
             LIMIT :limit',
            [
                'status' => 'published',
                'q' => '%' . $query . '%',
                'limit' => $limit,
            ],
        )->fetchAll();

        $out = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = $this->castRow($row);
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function filterWritable(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            if (isset(self::WRITABLE[$k])) {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function castRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'slug' => (string) $row['slug'],
            'lang' => (string) $row['lang'],
            'title' => (string) $row['title'],
            'content' => (string) $row['content'],
            'status' => (string) $row['status'],
            'published_at' => $row['published_at'] !== null ? (string) $row['published_at'] : null,
            'created_at' => (string) $row['created_at'],
        ];
    }
}
