<?php

declare(strict_types=1);

namespace Damoony\Db\DataProvider;

final class UserProvider extends AbstractProvider
{
    public function name(): string
    {
        return 'user';
    }

    public function table(): string
    {
        return 'users';
    }

    /**
     * @return array{id: int, login: string, email: string, password_hash: string, status: string, created_at: string}|array{}
     */
    public function by_login(string $login): array
    {
        $row = $this->query(
            'SELECT id, login, email, password_hash, status, created_at
             FROM ' . $this->physicalTable() . '
             WHERE login = :login
             LIMIT 1',
            ['login' => $login],
        )->fetch();

        return is_array($row) ? $this->castRow($row) : [];
    }

    /**
     * @return array{id: int, login: string, email: string, password_hash: string, status: string, created_at: string}|array{}
     */
    public function by_id(int $id): array
    {
        $row = $this->query(
            'SELECT id, login, email, password_hash, status, created_at
             FROM ' . $this->physicalTable() . '
             WHERE id = :id
             LIMIT 1',
            ['id' => $id],
        )->fetch();

        return is_array($row) ? $this->castRow($row) : [];
    }

    public function exists(string $login): bool
    {
        $row = $this->query(
            'SELECT 1 FROM ' . $this->physicalTable() . ' WHERE login = :login LIMIT 1',
            ['login' => $login],
        )->fetch();

        return $row !== false;
    }

    public function create(
        string $login,
        string $passwordHash,
        string $email,
        string $status = 'active',
    ): int {
        $this->query(
            'INSERT INTO ' . $this->physicalTable()
            . ' (login, password_hash, email, status)'
            . ' VALUES (:login, :password_hash, :email, :status)',
            [
                'login' => $login,
                'password_hash' => $passwordHash,
                'email' => $email,
                'status' => $status,
            ],
        );

        return (int) $this->conn->pdo()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{id: int, login: string, email: string, password_hash: string, status: string, created_at: string}
     */
    private function castRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'login' => (string) $row['login'],
            'email' => (string) $row['email'],
            'password_hash' => (string) $row['password_hash'],
            'status' => (string) $row['status'],
            'created_at' => (string) $row['created_at'],
        ];
    }
}
