<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Data\Persona\PersonaRepository;

final class InstallApproveCommand implements CommandInterface
{
    public function __construct(private readonly PersonaRepository $persona)
    {
    }

    public function name(): string
    {
        return 'install:approve';
    }

    public function description(): string
    {
        return 'Подтвердить установку (--id=)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $id = (int) ($args['id'] ?? 0);
        $installation = $this->persona->installationById($id);

        if ($installation === []) {
            $r->error('Установка не найдена');

            return $r;
        }

        $this->persona->setInstallationStatus($id, 'active');
        $r->data = ['installation_id' => $id, 'status' => 'active'];
        $context->step('Установка #' . $id . ' подтверждена', true, 'install');

        return $r;
    }
}
