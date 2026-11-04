<?php

declare(strict_types=1);

namespace Damoony\Db\DataProvider;

use Damoony\Core\Config;
use Damoony\Db\Connection;
use PDOStatement;
use RuntimeException;

/**
 * Базовая реализация провайдера.
 *
 * Резолвит физическое имя таблицы из site.config.domains_aliases_storage:
 *   {"domains_aliases_storage": {"d.ru": {"www": {"db": "main", "prefix": "d1_www__"}}}}
 * Fallback: prefix = "" (текущая схема без префиксов).
 *
 * @see ProviderInterface
 */
abstract class AbstractProvider implements ProviderInterface
{
    public function __construct(
        protected readonly Connection $conn,
        protected readonly Config $site,
        protected readonly string $domain,
        protected readonly string $alias,
    ) {
    }

    final public function domain(): string
    {
        return $this->domain;
    }

    final public function alias(): string
    {
        return $this->alias;
    }

    final public function physicalTable(): string
    {
        $map = $this->site->getByKey(['domains_aliases_storage'], []);
        if (!is_array($map)) {
            return $this->table();
        }

        $domainMap = $map[$this->domain] ?? null;
        if (!is_array($domainMap)) {
            return $this->table();
        }

        $aliasMap = $domainMap[$this->alias] ?? null;
        if (!is_array($aliasMap)) {
            return $this->table();
        }

        $prefix = $aliasMap['prefix'] ?? '';
        if (!is_string($prefix)) {
            $prefix = '';
        }

        return $prefix . $this->table();
    }

    /**
     * @param array<string, mixed> $params
     *
     * @deprecated-by-design: не биндить LIMIT как параметр — MariaDB требует
     *                        integer literal, а PDO::execute() биндит как строку.
     *                        Инлайнить: 'LIMIT ' . (int) $limit
     */
    protected function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->conn->pdo()->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare statement');
        }
        $stmt->execute($params);

        return $stmt;
    }
}
