<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Data\Persona\PersonaRepository;

/**
 * member:approve — подтвердить доступ аккаунта к проекту/алиасу.
 */
final class MemberApproveCommand implements CommandInterface
{
    public function __construct(private readonly PersonaRepository $persona)
    {
    }

    public function name(): string
    {
        return 'member:approve';
    }

    public function description(): string
    {
        return 'Подтвердить доступ к проекту (--account=email, --project=, --alias=, --role=)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $email = strtolower(trim((string) ($args['account'] ?? '')));
        $project = trim((string) ($args['project'] ?? ''));
        $alias = trim((string) ($args['alias'] ?? ''));
        $role = trim((string) ($args['role'] ?? 'user'));

        if ($project === '') {
            $r->error('Укажите --project');

            return $r;
        }
        if (!in_array($role, ['user', 'admin'], true)) {
            $r->error('Роль: user или admin');

            return $r;
        }

        $account = $this->persona->accountByEmail($email);
        if ($account === []) {
            $r->error('Аккаунт не найден: ' . $email);

            return $r;
        }

        $memberId = $this->persona->createMember((int) $account['id'], $project, $alias, $role, 'active');
        $this->persona->setMemberStatus($memberId, 'active');

        $r->data = [
            'member_id' => $memberId,
            'account_id' => (int) $account['id'],
            'project' => $project,
            'alias' => $alias,
            'role' => $role,
            'status' => 'active',
        ];
        $context->step('Доступ подтверждён: ' . $email . ' → ' . $project . ($alias !== '' ? '/' . $alias : ''), true, 'persona');

        return $r;
    }
}
