<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Data\Persona\PersonaRepository;
use Damoony\Data\Persona\PersonaService;
use RuntimeException;

/**
 * account:approve — подтвердить заявку и выдать приглашение (invite-ссылка).
 */
final class AccountApproveCommand implements CommandInterface
{
    private readonly PersonaService $service;

    public function __construct(private readonly PersonaRepository $persona)
    {
        $this->service = new PersonaService($persona);
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

        try {
            $result = $this->service->approveRequest(
                $requestId,
                $base,
                'cli',
                $password === '' ? null : $password,
            );
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $r->data = $result;
        $context->step(
            $result['invite_url'] === null
                ? 'Аккаунт активирован: ' . $result['email']
                : 'Приглашение создано: ' . $result['invite_url'],
            true,
            'persona',
        );

        return $r;
    }
}
