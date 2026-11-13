<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Data\Db\Connection;
use Damoony\Data\Db\DataProvider\UserProvider;

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
        return 'Создать/обновить пользователя';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();
        $login    = (string) ($args['login'] ?? '');
        $email    = (string) ($args['email'] ?? '');
        $password = (string) ($args['password'] ?? '');
        $role     = (string) ($args['role'] ?? 'user');
        $update   = isset($args['update']) && $args['update'] !== '0';

        if (!preg_match('/^[a-zA-Z0-9_]{3,32}$/', $login)) {
            $r->error('Некорректный login');
            return $r;
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $r->error('Некорректный email');
            return $r;
        }
        if (strlen($password) < 8) {
            $r->error('Пароль минимум 8 символов');
            return $r;
        }
        if (!in_array($role, ['user', 'admin'], true)) {
            $r->error('Роль должна быть user или admin');
            return $r;
        }

        $siteFile = getcwd() . '/config/site.config';
        $site = is_file($siteFile) ? Config::fromFile($siteFile) : Config::fromArray([]);

        $provider = new UserProvider($this->connection, $site, 'local', 'www');

        if ($provider->exists($login)) {
            if (!$update) {
                $r->error("Пользователь с login '{$login}' уже существует. Используйте --update");
                return $r;
            }
            $provider->update($login, [
                'email'         => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role'          => $role,
            ]);
            $context->step("Пользователь обновлён: {$login}", true);
        } else {
            $provider->create($login, password_hash($password, PASSWORD_DEFAULT), $email, $role);
            $context->step("Пользователь создан: {$login}", true);
        }

        return $r;
    }
}
