<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Cli\Scaffold\Scaffolder;
use Damoony\Cli\Support\TargetResolver;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use RuntimeException;

/**
 * skin:new для схемы domains_app.
 *
 * Скелет скина создаёт Scaffolder — единственный источник контракта скина
 * (базовые css + файл на слот схемы).
 */
final class SkinNewCommand implements CommandInterface
{
    private const NAME_RE = '/^[a-z][a-z0-9_]*$/';

    public function __construct(
        private readonly Paths $paths,
        private readonly TargetResolver $resolver,
        private readonly Scaffolder $scaffolder,
    ) {
    }

    public function name(): string
    {
        return 'skin:new';
    }

    public function description(): string
    {
        return 'Создать скин алиаса (css по слотам схемы, js/main.js, img/, skin.json)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $name = (string) ($args['name'] ?? 'default');
        if (preg_match(self::NAME_RE, $name) !== 1) {
            $r->error('Некорректное имя скина (a-z0-9_)');

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

        $skinDir = $this->paths->media($target->folder(), $target->aliasName()) . '/skin/' . $name;
        if (Fs::isDir($skinDir)) {
            $r->error('Скин уже существует');

            return $r;
        }

        if ($context->isDryRun()) {
            $context->step(sprintf('Dry-run: скин %s для %s/%s будет создан', $name, $target->projectKey(), $target->aliasName()));
            $r->data = [
                'project' => $target->projectKey(),
                'allias' => $target->aliasName(),
                'skin' => $name,
                'dry_run' => true,
            ];

            return $r;
        }

        try {
            $created = $this->scaffolder->skin($target->folder(), $target->aliasName(), $name);
        } catch (\Throwable $e) {
            $r->error('Не удалось создать скин: ' . $e->getMessage());

            return $r;
        }

        foreach ($created as $path) {
            if (!Fs::isDir($path)) {
                $r->file(Fs::rel($path, $this->paths->root()));
            }
        }

        $r->data = ['project' => $target->projectKey(), 'allias' => $target->aliasName(), 'skin' => $name];

        $context->step('Скин создан', true, $name);

        return $r;
    }
}
