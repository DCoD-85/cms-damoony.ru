<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Data\Admin\AdminAuthRepository;

/**
 * admin:create — создать админскую учётку и роль.
 */
final class AdminCreateCommand implements CommandInterface
{
    public function __construct(private readonly AdminAuthRepository $repo)
    {
    }

    public function name(): string
    {
        return 'admin:create';
    }

    public function description(): string
    {
        return 'Создать админа (--login=, --password=, --role=superadmin|domainadmin, --scope=)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $login = trim((string) ($args['login'] ?? ''));
        $password = (string) ($args['password'] ?? '');
        $role = trim((string) ($args['role'] ?? 'superadmin'));
        $scope = trim((string) ($args['scope'] ?? ''));

        if (preg_match('/^[a-z0-9_.-]{3,64}$/', $login) !== 1) {
            $r->error('Логин: 3–64 символа, латиница/цифры/._-');

            return $r;
        }
        if (strlen($password) < 10) {
            $r->error('Пароль минимум 10 символов');

            return $r;
        }
        if (!in_array($role, ['superadmin', 'domainadmin'], true)) {
            $r->error('Роль: superadmin или domainadmin');

            return $r;
        }
        if ($role === 'domainadmin' && $scope === '') {
            $r->error('Для domainadmin укажите --scope=домен');

            return $r;
        }
        if ($this->repo->accountByLogin($login) !== []) {
            $r->error('Логин уже существует');

            return $r;
        }

        $accountId = $this->repo->createAccount($login, password_hash($password, PASSWORD_DEFAULT));
        $this->repo->createMember($accountId, $role, $scope);

        $r->data = ['account_id' => $accountId, 'login' => $login, 'role' => $role, 'scope' => $scope];
        $context->step('Админ создан: ' . $login . ' (' . $role . ')', true, 'admin');

        return $r;
    }
}
