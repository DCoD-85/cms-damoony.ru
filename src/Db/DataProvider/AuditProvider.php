<?php

declare(strict_types=1);

namespace Damoony\Db\DataProvider;

final class AuditProvider extends AbstractProvider
{
    public function name(): string
    {
        return 'audit';
    }

    public function table(): string
    {
        return 'audit_log';
    }

    protected function isGlobal(): bool
    {
        return true;
    }

    /**
     * @param array<string, mixed>|null $params
     */
    public function log(
        string $domain,
        string $alias,
        string $provider,
        string $method,
        ?array $params,
        bool $ok,
        string $error,
        string $source = 'cli',
        string $role = '',
    ): int {
        $this->query(
            'INSERT INTO ' . $this->physicalTable()
            . ' (domain, alias_name, provider, method, params_json, result_ok, error_text, source, role_name)'
            . ' VALUES (:domain, :alias_name, :provider, :method, :params_json, :result_ok, :error_text, :source, :role_name)',
            [
                'domain' => $domain,
                'alias_name' => $alias,
                'provider' => $provider,
                'method' => $method,
                'params_json' => $params === null ? null : (string) json_encode($params, JSON_UNESCAPED_UNICODE),
                'result_ok' => $ok ? 1 : 0,
                'error_text' => $error === '' ? null : $error,
                'source' => $source,
                'role_name' => $role,
            ],
        );

        return (int) $this->conn->pdo()->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recent(int $limit = 50): array
    {
        $limit = max(1, min(500, $limit));

        $rows = $this->query(
            'SELECT id, domain, alias_name, provider, method, params_json, result_ok, error_text, source, role_name, created_at
             FROM ' . $this->physicalTable() . '
             ORDER BY id DESC
             LIMIT ' . $limit,
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
     * @return list<array<string, mixed>>
     */
    public function by_entity(string $provider, string $method, int $limit = 50): array
    {
        $limit = max(1, min(500, $limit));

        $rows = $this->query(
            'SELECT id, domain, alias_name, provider, method, params_json, result_ok, error_text, source, role_name, created_at
             FROM ' . $this->physicalTable() . '
             WHERE provider = :provider AND method = :method
             ORDER BY id DESC
             LIMIT ' . $limit,
            ['provider' => $provider, 'method' => $method],
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
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function castRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'domain' => (string) $row['domain'],
            'alias' => (string) $row['alias_name'],
            'provider' => (string) $row['provider'],
            'method' => (string) $row['method'],
            'params' => $row['params_json'] === null ? null : (string) $row['params_json'],
            'ok' => (int) $row['result_ok'] === 1,
            'error' => $row['error_text'] === null ? null : (string) $row['error_text'],
            'source' => (string) $row['source'],
            'role' => (string) $row['role_name'],
            'created_at' => (string) $row['created_at'],
        ];
    }
}
