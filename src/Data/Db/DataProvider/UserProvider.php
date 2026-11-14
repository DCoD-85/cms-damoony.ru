<?php

declare(strict_types=1);

namespace Damoony\Data\Db\DataProvider;

class UserProvider extends AbstractProvider
{
    public function name(): string
    {
        return 'user';
    }

    public function table(): string
    {
        return 'users';
    }

    public function exists(string $login): bool
    {
        $row = $this->query(
            'SELECT 1 FROM ' . $this->physicalTable() . ' WHERE login = :login LIMIT 1',
            ['login' => $login],
        )->fetch();
        return $row !== false;
    }

    /**
     * @return array<string, mixed>|array{}
     */
    public function by_login(string $login): array
    {
        $row = $this->query(
            'SELECT * FROM ' . $this->physicalTable() . ' WHERE login = :login LIMIT 1',
            ['login' => $login],
        )->fetch();
        return $row === false ? [] : $row;
    }

    /**
     * @return array<string, mixed>|array{}
     */
    public function by_id(int $id): array
    {
        $row = $this->query(
            'SELECT * FROM ' . $this->physicalTable() . ' WHERE id = :id LIMIT 1',
            ['id' => $id],
        )->fetch();
        return $row === false ? [] : $row;
    }

    public function create(
        string $login,
        string $passwordHash,
        string $email,
        string $role = 'user',
        string $status = 'active',
    ): int {
        $this->query(
            'INSERT INTO ' . $this->physicalTable()
            . ' (login, password_hash, email, role, status)'
            . ' VALUES (:login, :password_hash, :email, :role, :status)',
            [
                'login' => $login,
                'password_hash' => $passwordHash,
                'email' => $email,
                'role' => $role,
                'status' => $status,
            ],
        );

        return (int) $this->conn->pdo()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(string $login, array $data): void
    {
        $sets = [];
        $params = ['login' => $login];

        foreach ($data as $col => $val) {
            $sets[] = $col . ' = :' . $col;
            $params[$col] = $val;
        }

        if ($sets === []) {
            return;
        }

        $sql = 'UPDATE ' . $this->physicalTable()
             . ' SET ' . implode(', ', $sets)
             . ' WHERE login = :login';

        $this->query($sql, $params);
    }
}
