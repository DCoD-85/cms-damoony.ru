<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Data\Admin\AdminAuthService;
use RuntimeException;

/**
 * admin:password — сменить пароль админа по логину.
 */
final class AdminPasswordCommand implements CommandInterface
{
    public function __construct(private readonly AdminAuthService $auth)
    {
    }

    public function name(): string
    {
        return 'admin:password';
    }

    public function description(): string
    {
        return 'Сменить пароль админа (--login=, --password=); сессии отзываются';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();
        $login = (string) ($args['login'] ?? '');

        try {
            $this->auth->changePasswordByLogin($login, (string) ($args['password'] ?? ''));
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $r->data = ['login' => $login];
        $context->step('Пароль изменён: ' . $login . ' (сессии отозваны)', true, 'admin');

        return $r;
    }
}
