<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Cli\Support\PersonaTokens;
use Damoony\Data\Persona\PersonaRepository;
use RuntimeException;

/**
 * account:approve — подтвердить заявку и выдать приглашение (invite-ссылка).
 */
final class AccountApproveCommand implements CommandInterface
{
    private const INVITE_TTL_HOURS = 48;

    public function __construct(private readonly PersonaRepository $persona)
    {
    }

    public function name(): string
    {
        return 'account:approve';
    }

    public function description(): string
    {
        return 'Подтвердить заявку на регистрацию (--request=ID, --base=URL, --password=)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $requestId = (int) ($args['request'] ?? 0);
        $base = rtrim((string) ($args['base'] ?? 'https://persona.damoony.ru'), '/');
        $password = (string) ($args['password'] ?? '');

        $request = $this->persona->requestById($requestId);
        if ($request === [] || ($request['status'] ?? '') !== 'pending') {
            $r->error('Заявка не найдена или уже обработана');

            return $r;
        }

        $email = (string) $request['email'];
        $account = $this->persona->accountByEmail($email);
        $accountId = $account === []
            ? $this->persona->createAccount($email, null, 'pending')
            : (int) $account['id'];

        try {
            if ($password !== '') {
                if (strlen($password) < 8) {
                    throw new RuntimeException('Пароль минимум 8 символов');
                }
                $this->persona->setAccountPassword($accountId, password_hash($password, PASSWORD_DEFAULT));
                $this->persona->decideRequest($requestId, 'approved', 'cli');
                $r->data = ['account_id' => $accountId, 'email' => $email, 'invite_url' => null];

                $context->step('Аккаунт активирован: ' . $email, true, 'persona');

                return $r;
            }

            $token = PersonaTokens::generate();
            $this->persona->createInvite(
                $accountId,
                PersonaTokens::hash($token),
                PersonaTokens::expiresAt(self::INVITE_TTL_HOURS),
            );
            $this->persona->decideRequest($requestId, 'approved', 'cli');

            $inviteUrl = $base . '/invite/' . $token;
            $r->data = [
                'account_id' => $accountId,
                'email' => $email,
                'invite_url' => $inviteUrl,
                'expires_hours' => self::INVITE_TTL_HOURS,
            ];

            $context->step('Приглашение создано: ' . $inviteUrl, true, 'persona');
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());
        }

        return $r;
    }
}
