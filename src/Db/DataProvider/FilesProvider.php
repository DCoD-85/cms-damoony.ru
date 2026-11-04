<?php

declare(strict_types=1);

namespace Damoony\Db\DataProvider;

final class FilesProvider extends AbstractProvider
{
    public function name(): string
    {
        return 'files';
    }

    public function table(): string
    {
        return 'files';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all_active(): array
    {
        $rows = $this->query(
            'SELECT id, slug, filename, filesize, status, downloads, created_at
             FROM ' . $this->physicalTable() . '
             WHERE status = :status
             ORDER BY id ASC',
            ['status' => 'active'],
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
    public function by_slug(string $slug): array
    {
        $row = $this->query(
            'SELECT id, slug, filename, filesize, status, downloads, created_at
             FROM ' . $this->physicalTable() . '
             WHERE slug = :slug
             LIMIT 1',
            ['slug' => $slug],
        )->fetch();

        return is_array($row) ? $this->castRow($row) : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function by_id(int $id): array
    {
        $row = $this->query(
            'SELECT id, slug, filename, filesize, status, downloads, created_at
             FROM ' . $this->physicalTable() . '
             WHERE id = :id
             LIMIT 1',
            ['id' => $id],
        )->fetch();

        return is_array($row) ? $this->castRow($row) : [];
    }

    public function create(
        string $slug,
        string $filename,
        int $filesize = 0,
        string $status = 'active',
    ): int {
        if ($slug === '' || $filename === '') {
            return 0;
        }

        $this->query(
            'INSERT INTO ' . $this->physicalTable()
            . ' (slug, filename, filesize, status)'
            . ' VALUES (:slug, :filename, :filesize, :status)',
            [
                'slug' => $slug,
                'filename' => $filename,
                'filesize' => max(0, $filesize),
                'status' => $status,
            ],
        );

        return (int) $this->conn->pdo()->lastInsertId();
    }

    public function update_filesize(int $id, int $filesize): void
    {
        $this->query(
            'UPDATE ' . $this->physicalTable()
            . ' SET filesize = :filesize WHERE id = :id',
            ['filesize' => max(0, $filesize), 'id' => $id],
        );
    }

    public function increment_downloads(int $id): void
    {
        $this->query(
            'UPDATE ' . $this->physicalTable()
            . ' SET downloads = downloads + 1 WHERE id = :id',
            ['id' => $id],
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
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function castRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'slug' => (string) $row['slug'],
            'filename' => (string) $row['filename'],
            'filesize' => (int) $row['filesize'],
            'status' => (string) $row['status'],
            'downloads' => (int) $row['downloads'],
            'created_at' => (string) $row['created_at'],
        ];
    }
}
