<?php

declare(strict_types=1);

namespace damoony_ru\protonews;

use Damoony\Db\DataProvider\BasicCrudProvider;

final class ArticlesProvider extends BasicCrudProvider
{
    public function name(): string { return 'articles'; }
    public function table(): string { return 'articles'; }
    protected function writable(): array
    {
        return ['category_id', 'title', 'content', 'published_at'];
    }

    /**
     * Получить статьи с названием категории (без N+1 запросов).
     * @return list<array<string, mixed>>
     */
    public function getWithCategory(int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));
        $sql = 'SELECT a.id, a.title, a.content, a.published_at, c.name as category_name
                FROM ' . $this->physicalTable() . ' a
                LEFT JOIN ' . $this->prefixTable('categories') . ' c ON c.id = a.category_id
                ORDER BY a.published_at DESC
                LIMIT ' . $limit;
        $rows = $this->query($sql)->fetchAll();
        return is_array($rows) ? $rows : [];
    }
}
