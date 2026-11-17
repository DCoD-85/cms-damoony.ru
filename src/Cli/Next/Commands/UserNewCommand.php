<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Data\Db\Connection;
use Damoony\Data\Db\DataProvider\UserProvider;
use Throwable;

/**
 * user:new — пользователь сайта (таблица users, не админка).
 */
final class UserNewCommand implements CommandInterface
{
    private const LOGIN_RE = '/^[a-zA-Z0-9_]{3,32}$/';
    private const MIN_PASSWORD = 8;
    private const ROLES = ['user', 'admin'];

    public function __construct(private readonly ?Connection $db)
    {
    }

    public function name(): string
    {
        return 'user:new';
    }

    public function description(): string
    {
        return 'Создать/обновить пользователя сайта';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $login = (string) ($args['login'] ?? '');
        $email = (string) ($args['email'] ?? '');
        $password = (string) ($args['password'] ?? '');
        $role = (string) ($args['role'] ?? 'user');
        $update = isset($args['update']) && (string) $args['update'] !== '0';

        if (preg_match(self::LOGIN_RE, $login) !== 1) {
            $r->error('Некорректный login');

            return $r;
        }
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $r->error('Некорректный email');

            return $r;
        }
        if (strlen($password) < self::MIN_PASSWORD) {
            $r->error('Пароль минимум ' . self::MIN_PASSWORD . ' символов');

            return $r;
        }
        if (!in_array($role, self::ROLES, true)) {
            $r->error('Роль должна быть user или admin');

            return $r;
        }
        if ($this->db === null) {
            $r->error('БД не сконфигурирована (config/engine.config → db)');

            return $r;
        }

        // Таблица users глобальная: префикс алиаса не применяется.
        $provider = new UserProvider($this->db, '', 'local', 'www');

        if ($context->isDryRun()) {
            $exists = $provider->exists($login);
            $context->step(
                sprintf('Dry-run: пользователь %s будет %s', $login, $exists ? 'обновлён' : 'создан'),
                true,
            );
            $r->data = ['login' => $login, 'exists' => $exists, 'dry_run' => true];

            return $r;
        }

        try {
            if ($provider->exists($login)) {
                if (!$update) {
                    $r->error(sprintf("Пользователь с login '%s' уже существует. Используйте --update", $login));

                    return $r;
                }

                $provider->update($login, [
                    'email' => $email,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => $role,
                ]);

                $r->data = ['login' => $login, 'updated' => true];
                $context->step('Пользователь обновлён', true, $login);

                return $r;
            }

            $provider->create($login, password_hash($password, PASSWORD_DEFAULT), $email, $role);
        } catch (Throwable $e) {
            $r->error('Не удалось сохранить пользователя: ' . $e->getMessage());

            return $r;
        }

        $r->data = ['login' => $login, 'created' => true, 'role' => $role];
        $context->step('Пользователь создан', true, $login);

        return $r;
    }
}
