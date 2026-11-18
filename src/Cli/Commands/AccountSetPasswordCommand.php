<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Cli\Support\PersonaTokens;
use Damoony\Data\Persona\PersonaRepository;

/**
 * account:set-password — установка пароля по одноразовой invite-ссылке.
 */
final class AccountSetPasswordCommand implements CommandInterface
{
    public function __construct(private readonly PersonaRepository $persona)
    {
    }

    public function name(): string
    {
        return 'account:set-password';
    }

    public function description(): string
    {
        return 'Установить пароль по invite-токену (--token=, --password=)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $token = (string) ($args['token'] ?? '');
        $password = (string) ($args['password'] ?? '');

        if (strlen($password) < 8) {
            $r->error('Пароль минимум 8 символов');

            return $r;
        }

        $invite = $token === '' ? [] : $this->persona->inviteByToken(PersonaTokens::hash($token));
        if ($invite === []) {
            $r->error('Ссылка недействительна или уже использована');

            return $r;
        }

        if (strtotime((string) $invite['expires_at']) < time()) {
            $r->error('Срок действия ссылки истёк');

            return $r;
        }

        $accountId = (int) $invite['account_id'];
        $this->persona->setAccountPassword($accountId, password_hash($password, PASSWORD_DEFAULT));
        $this->persona->consumeInvite((int) $invite['id']);

        $r->data = ['account_id' => $accountId, 'status' => 'active'];
        $context->step('Пароль установлен, аккаунт активирован', true, 'persona');

        return $r;
    }
}
