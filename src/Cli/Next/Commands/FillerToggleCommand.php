<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Next\Support\TargetResolver;
use Damoony\Cli\Result;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\UI\Region\FillerState;
use RuntimeException;
use Throwable;

/**
 * filler:toggle для схемы domains_app: enabled живёт в .blockslocal/{block}/{block}.rules.json.
 */
final class FillerToggleCommand implements CommandInterface
{
    private const NAME_RE = '/^[a-z][a-z0-9_]*$/';

    public function __construct(
        private readonly Paths $paths,
        private readonly TargetResolver $resolver,
    ) {
    }

    public function name(): string
    {
        return 'filler:toggle';
    }

    public function description(): string
    {
        return 'Включить/выключить филлер (.rules.json)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $block = (string) ($args['block'] ?? '');
        $value = ($args['value'] ?? '1') !== '0';

        if (preg_match(self::NAME_RE, $block) !== 1) {
            $r->error('Некорректный блок');

            return $r;
        }

        try {
            $target = $this->resolver->resolve(
                (string) ($args['domain'] ?? ''),
                (string) ($args['allias'] ?? ''),
            );
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $blockDir = $this->paths->blocksLocal($target->folder(), $target->aliasName()) . '/' . $block;
        if (!Fs::isDir($blockDir)) {
            $r->error('Filler не найден: ' . $block . '. Сначала filler:new');

            return $r;
        }

        $state = new FillerState($this->paths);
        $current = $state->isEnabled($target->folder(), $target->aliasName(), $block);

        if ($current === $value) {
            $context->step('Filler уже ' . ($value ? 'включён' : 'выключен'), true, $block . '_' . $target->aliasName());

            return $r;
        }

        if ($context->isDryRun()) {
            $context->step(sprintf(
                'Dry-run: филлер %s будет %s',
                $block,
                $value ? 'включён' : 'выключен',
            ));
            $r->data = ['block' => $block, 'value' => $value, 'dry_run' => true];

            return $r;
        }

        try {
            $state->setEnabled($target->folder(), $target->aliasName(), $block, $value);
        } catch (Throwable $e) {
            $r->error('Не удалось записать .rules.json: ' . $e->getMessage());

            return $r;
        }

        $rulesFile = $blockDir . '/' . $block . '.rules.json';
        $r->file(Fs::rel($rulesFile, $this->paths->root()));
        $r->data = ['project' => $target->projectKey(), 'allias' => $target->aliasName(), 'block' => $block, 'value' => $value];

        $context->step('Filler ' . ($value ? 'включён' : 'выключен'), true, $block . '_' . $target->aliasName());

        return $r;
    }
}
