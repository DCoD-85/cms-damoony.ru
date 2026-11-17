<?php

declare(strict_types=1);

namespace Damoony\Data\Db\DataProvider;

use Damoony\Data\Db\Connection;
use PDOStatement;
use RuntimeException;

/**
 * Базовая реализация провайдера.
 *
 * Префикс таблиц алиаса передаётся снаружи (из domains_app[key].aliases_storage),
 * провайдер не читает конфиг. Глобальные таблицы не префиксуются.
 *
 * @see ProviderInterface
 */
abstract class AbstractProvider implements ProviderInterface
{
    public function __construct(
        protected readonly Connection $conn,
        protected readonly string $tablePrefix,
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

    /**
     * Глобальные таблицы (users, sessions, audit_log, ...) не префиксуются,
     * даже если алиас настроен с prefix в domains_app[key].aliases_storage.
     */
    protected function isGlobal(): bool
    {
        return false;
    }

    final public function physicalTable(): string
    {
        return $this->prefixTable($this->table());
    }

    /**
     * Резолвит физическое имя для ЛЮБОЙ логической таблицы.
     */
    protected function prefixTable(string $table): string
    {
        if ($this->isGlobal()) {
            return $table;
        }

        return $this->tablePrefix . $table;
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
