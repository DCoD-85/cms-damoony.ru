<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Db\Connection;

final class UserNewCommand implements CommandInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function name(): string
    {
        return 'user:new';
    }

    public function description(): string
    {
        return 'Создать пользователя (login/email/password)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $login = (string) ($args['login'] ?? '');
        $email = (string) ($args['email'] ?? '');
        $password = (string) ($args['password'] ?? '');

        if ($login === '' || !preg_match('/^[a-zA-Z0-9_]{3,32}$/', $login)) {
            $r->error('Некорректный login (3-32, [a-zA-Z0-9_])');
            return $r;
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $r->error('Некорректный email');
            return $r;
        }
        if (strlen($password) < 6) {
            $r->error('Пароль минимум 6 символов');
            return $r;
        }

        $pdo = $this->connection->pdo();

        $check = $pdo->prepare('SELECT id FROM users WHERE login = ?');
        $check->execute([$login]);
        if ($check->fetch()) {
            $r->error('Пользователь уже существует');
            return $r;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            'INSERT INTO users (login, email, password_hash, status) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$login, $email, $hash, 'active']);

        $context->step('Пользователь создан', true, $login);

        return $r;
    }
}