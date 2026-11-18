<?php

declare(strict_types=1);

namespace Damoony\Data\Persona;

use Damoony\Cli\Support\PersonaTokens;
use RuntimeException;

/**
 * Единая логика подтверждения заявок и установок (CLI и веб используют её).
 */
final class PersonaService
{
    public function __construct(private readonly PersonaRepository $repo)
    {
    }

    /**
     * Подтверждает заявку: создаёт аккаунт, при необходимости invite-ссылку.
     *
     * @return array{account_id: int, email: string, invite_url: ?string, expires_hours: int}
     */
    public function approveRequest(int $requestId, string $base, string $actor = 'cli', ?string $password = null): array
    {
        $request = $this->repo->requestById($requestId);
        if ($request === [] || ($request['status'] ?? '') !== 'pending') {
            throw new RuntimeException('Заявка не найдена или уже обработана');
        }

        $email = (string) $request['email'];
        $account = $this->repo->accountByEmail($email);
        $accountId = $account === []
            ? $this->repo->createAccount($email, null, 'pending')
            : (int) $account['id'];

        if ($password !== null) {
            if (strlen($password) < 8) {
                throw new RuntimeException('Пароль минимум 8 символов');
            }
            $this->repo->setAccountPassword($accountId, password_hash($password, PASSWORD_DEFAULT));
            $this->repo->decideRequest($requestId, 'approved', $actor);

            return ['account_id' => $accountId, 'email' => $email, 'invite_url' => null, 'expires_hours' => 0];
        }

        $token = PersonaTokens::generate();
        $ttl = 48;
        $this->repo->createInvite($accountId, PersonaTokens::hash($token), PersonaTokens::expiresAt($ttl));
        $this->repo->decideRequest($requestId, 'approved', $actor);

        return [
            'account_id' => $accountId,
            'email' => $email,
            'invite_url' => rtrim($base, '/') . '/invite/' . $token,
            'expires_hours' => $ttl,
        ];
    }
}
