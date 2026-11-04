<?php

declare(strict_types=1);

namespace Damoony\Db\DataProvider;

final class FormSubmissionProvider extends AbstractProvider
{
    public function name(): string
    {
        return 'form_submission';
    }

    public function table(): string
    {
        return 'form_submissions';
    }

    protected function isGlobal(): bool
    {
        return true;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function submit(array $data, string $formName = 'contact', string $ip = ''): int
    {
        $this->query(
            'INSERT INTO ' . $this->physicalTable() . ' (form_name, data_json, ip) VALUES (:form_name, :data_json, :ip)',
            [
                'form_name' => $formName,
                'data_json' => (string) json_encode(
                    $data,
                    JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR,
                ),
                'ip' => $ip,
            ],
        );

        return (int) $this->conn->pdo()->lastInsertId();
    }

    public function delete(int $id): void
    {
        $this->query('DELETE FROM ' . $this->physicalTable() . ' WHERE id = :id', ['id' => $id]);
    }

    public function delete_all(): int
    {
        $row = $this->query('SELECT COUNT(*) AS c FROM ' . $this->physicalTable())->fetch();
        $count = (int) (is_array($row) ? ($row['c'] ?? 0) : 0);
        $this->query('DELETE FROM ' . $this->physicalTable());

        return $count;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function by_form(string $formName, int $limit = 50): array
    {
        $limit = max(1, min(500, $limit));
        $rows = $this->query(
            'SELECT id, form_name, data_json, ip, created_at FROM ' . $this->physicalTable()
            . ' WHERE form_name = :form_name ORDER BY id DESC LIMIT ' . $limit,
            ['form_name' => $formName],
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
     * @return list<array<string, mixed>>
     */
    public function recent(int $limit = 50): array
    {
        $limit = max(1, min(500, $limit));
        $rows = $this->query(
            'SELECT id, form_name, data_json, ip, created_at FROM ' . $this->physicalTable()
            . ' ORDER BY id DESC LIMIT ' . $limit,
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
