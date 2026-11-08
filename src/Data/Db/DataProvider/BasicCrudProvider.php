<?php

declare(strict_types=1);

namespace Damoony\Data\Db\DataProvider;

abstract class BasicCrudProvider extends AbstractProvider
{
    /**
     * @return list<string>
     */
    abstract protected function writable(): array;

    /**
     * @param array<string, mixed> $where
     *
     * @return list<array<string, mixed>>
     */
    final public function get(array $where = [], int $limit = 50, int $offset = 0, string $orderBy = 'id DESC'): array
    {
        [$whereSql, $params] = $this->buildWhere($where);
        $limit = max(1, min(1000, $limit));

        $sql = 'SELECT * FROM ' . $this->physicalTable()
            . $whereSql
            . ' ORDER BY ' . $orderBy
            . ' LIMIT ' . $limit . ' OFFSET ' . (int) $offset;

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->query($sql, $params)->fetchAll();

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    final public function find(int $id): array
    {
        $row = $this->query(
            'SELECT * FROM ' . $this->physicalTable() . ' WHERE id = :id LIMIT 1',
            ['id' => $id],
        )->fetch();

        return is_array($row) ? $row : [];
    }

    /**
     * @param array<string, mixed> $where
     */
    final public function count(array $where = []): int
    {
        [$whereSql, $params] = $this->buildWhere($where);

        $row = $this->query(
            'SELECT COUNT(*) as cnt FROM ' . $this->physicalTable() . $whereSql,
            $params,
        )->fetch();

        return is_array($row) ? (int) $row['cnt'] : 0;
    }

    /**
     * @param array<string, mixed> $data
     */
    final public function create(array $data): int
    {
        $data = $this->filterWritable($data);
        if ($data === []) {
            return 0;
        }

        $cols = array_keys($data);
        $placeholders = array_map(static fn (string $c): string => ':' . $c, $cols);

        $sql = 'INSERT INTO ' . $this->physicalTable()
            . ' (' . implode(', ', $cols) . ')'
            . ' VALUES (' . implode(', ', $placeholders) . ')';

        $this->query($sql, $data);

        return (int) $this->conn->pdo()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    final public function update(int $id, array $data): void
    {
        $data = $this->filterWritable($data);
        if ($data === []) {
            return;
        }

        $sets = [];
        $params = ['id' => $id];
        foreach ($data as $col => $val) {
            $sets[] = $col . ' = :' . $col;
            $params[$col] = $val;
        }

        $sql = 'UPDATE ' . $this->physicalTable()
            . ' SET ' . implode(', ', $sets)
            . ' WHERE id = :id';

        $this->query($sql, $params);
    }

    final public function delete(int $id): void
    {
        $this->query(
            'DELETE FROM ' . $this->physicalTable() . ' WHERE id = :id',
            ['id' => $id],
        );
    }

    /**
     * @param list<string> $columns
     *
     * @return list<array<string, mixed>>
     */
    final public function search(string $query, array $columns, int $limit = 50): array
    {
        if ($columns === []) {
            return [];
        }

        $parts = [];
        foreach ($columns as $col) {
            $parts[] = $col . ' LIKE :q';
        }
        $params = ['q' => '%' . $query . '%'];

        $limit = max(1, min(100, $limit));
        $sql = 'SELECT * FROM ' . $this->physicalTable()
            . ' WHERE (' . implode(' OR ', $parts) . ')'
            . ' LIMIT ' . $limit;

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->query($sql, $params)->fetchAll();

        return $rows;
    }

    /**
     * @param array<string, mixed> $where
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    protected function buildWhere(array $where): array
    {
        if ($where === []) {
            return ['', []];
        }

        $parts = [];
        foreach ($where as $col => $val) {
            if ($val === null) {
                $parts[] = $col . ' IS NULL';
            } else {
                $parts[] = $col . ' = :' . $col;
            }
        }

        return [' WHERE ' . implode(' AND ', $parts), $where];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function filterWritable(array $data): array
    {
        return array_intersect_key($data, array_flip($this->writable()));
    }
}
