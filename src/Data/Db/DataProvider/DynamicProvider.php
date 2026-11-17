<?php

declare(strict_types=1);

namespace Damoony\Data\Db\DataProvider;

use Damoony\Data\Db\Connection;

final class DynamicProvider extends BasicCrudProvider
{
    /**
     * @param list<string> $writableCols
     */
    public function __construct(
        Connection $conn,
        string $tablePrefix,
        string $domain,
        string $alias,
        private readonly string $tableName,
        private readonly array $writableCols,
    ) {
        parent::__construct($conn, $tablePrefix, $domain, $alias);
    }

    public function name(): string
    {
        return $this->tableName;
    }
    public function table(): string
    {
        return $this->tableName;
    }

    protected function writable(): array
    {
        return $this->writableCols;
    }
}
