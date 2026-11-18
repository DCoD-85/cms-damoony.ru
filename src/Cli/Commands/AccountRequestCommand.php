<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Data\Persona\PersonaRepository;

/**
 * account:request — публичная заявка на регистрацию (создаётся со страницы persona).
 */
final class AccountRequestCommand implements CommandInterface
{
    public function __construct(
        private readonly PersonaRepository $persona,
        private readonly ?AlertNotifyCommand $notifier = null,
    ) {
    }

    public function name(): string
    {
        return 'account:request';
    }

    public function description(): string
    {
        return 'Создать заявку на регистрацию (уведомить админа)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $email = strtolower(trim((string) ($args['email'] ?? '')));
        $name = trim((string) ($args['name'] ?? ''));
        $comment = trim((string) ($args['comment'] ?? ''));
        $projects = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) ($args['projects'] ?? '')),
        )));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $r->error('Некорректный email');

            return $r;
        }

        $id = $this->persona->createRequest($name, $email, $comment, $projects);
        $r->data = ['request_id' => $id, 'email' => $email, 'status' => 'pending'];

        $context->step('Заявка #' . $id . ' создана для ' . $email, true, 'persona');

        if ($this->notifier !== null) {
            $this->notifier->run([
                'message' => 'Новая заявка на регистрацию: ' . $email . ' (' . $name . ')',
                'level' => 'info',
                'source' => 'persona',
            ], new Context(static function (): void {
            }));
        }

        return $r;
    }
}
