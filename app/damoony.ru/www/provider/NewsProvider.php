<?php

declare(strict_types=1);

namespace damoony_ru\www\provider;

use Damoony\Db\DataProvider\NewsProvider as BaseProvider;

final class NewsProvider extends BaseProvider
{
    /**
     * @return array<string, mixed>
     */
    public function published_ru(int $limit = 10): array
    {
        $limit = max(1, min(100, $limit));
        $rows = $this->query(
            'SELECT id, slug, title, published_at FROM ' . $this->physicalTable() . ' WHERE status = \'published\' AND lang = \'ru\'' . ' ORDER BY published_at DESC' . ' LIMIT ' . $limit,
            [],
        )->fetchAll();

        $out = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = $row;
            }
        }
        return $out;
    }


    /**
     * @return array<string, mixed>
     */
    public function count_published(): array
    {
        $rows = $this->query(
            'SELECT id FROM ' . $this->physicalTable() . ' WHERE status = \'published\'',
            [],
        )->fetchAll();

        $out = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = $row;
            }
        }
        return $out;
    }
}
