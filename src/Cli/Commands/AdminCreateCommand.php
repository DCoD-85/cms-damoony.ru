<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Data\Admin\AdminAuthService;
use RuntimeException;

/**
 * admin:create — создать админскую учётку и роль.
 */
final class AdminCreateCommand implements CommandInterface
{
    public function __construct(private readonly AdminAuthService $auth)
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
        $login = (string) ($args['login'] ?? '');
        $role = (string) ($args['role'] ?? 'superadmin');
        $scope = (string) ($args['scope'] ?? '');

        try {
            $accountId = $this->auth->createAdmin($login, (string) ($args['password'] ?? ''), $role, $scope);
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $r->data = ['account_id' => $accountId, 'login' => $login, 'role' => $role, 'scope' => $scope];
        $context->step('Админ создан: ' . $login . ' (' . $role . ')', true, 'admin');

        return $r;
    }
}
