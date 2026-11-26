<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Data\Admin\AdminAuthService;
use RuntimeException;

/**
 * admin:2fa:disable — аварийное отключение 2FA по логину.
 */
final class Admin2faDisableCommand implements CommandInterface
{
    public function __construct(private readonly AdminAuthService $auth)
    {
    }

    public function name(): string
    {
        return 'admin:2fa:disable';
    }

    public function description(): string
    {
        return 'Отключить 2FA админа (--login=)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();
        $login = (string) ($args['login'] ?? '');
        try {
            $this->auth->disableTotpByLogin($login);
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }
        $context->step('2FA отключена: ' . $login, true, 'admin');

        return $r;
    }
}
