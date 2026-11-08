<?php

declare(strict_types=1);

namespace damoony_ru\www\provider;

use Damoony\Data\Db\DataProvider\FilesProvider as BaseProvider;

final class FilesProvider extends BaseProvider
{

    public function create_file(string $slug, string $filename): int
    {
        $this->query(
            'INSERT INTO ' . $this->physicalTable() . ' (slug, filename) VALUES (:slug, :filename)',
            [
            'slug' => $slug,
            'filename' => $filename,
        ]
        );

        return (int) $this->conn->pdo()->lastInsertId();
    }
}
