<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Cli\Support\Quarantine;
use Damoony\Cli\Support\TargetResolver;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use RuntimeException;
use Throwable;

/**
 * skin:remove для схемы domains_app: каталог скина в www/media/.trash/.
 */
final class SkinRemoveCommand implements CommandInterface
{
    private const NAME_RE = '/^[a-z][a-z0-9_]*$/';

    public function __construct(
        private readonly Paths $paths,
        private readonly TargetResolver $resolver,
        private readonly Quarantine $quarantine,
    ) {
    }

    public function name(): string
    {
        return 'skin:remove';
    }

    public function description(): string
    {
        return 'Удалить скин алиаса в карантин (--force)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $name = (string) ($args['name'] ?? '');
        $force = isset($args['force']);

        if (preg_match(self::NAME_RE, $name) !== 1) {
            $r->error('Некорректное имя скина');

            return $r;
        }
        if (!$force) {
            $r->error('Удаление требует --force (файлы будут перемещены в карантин)');

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
        if (!Fs::isDir($skinDir)) {
            $r->warn('Скин не найден: ' . $name);
            $context->step('Нечего удалять', true, $name);

            return $r;
        }

        if ($context->isDryRun()) {
            $context->step('Dry-run: скин ' . $name . ' будет удалён в карантин');
            $r->data = [
                'project' => $target->projectKey(),
                'allias' => $target->aliasName(),
                'skin' => $name,
                'dir' => Fs::rel($skinDir, $this->paths->root()),
                'dry_run' => true,
            ];

            return $r;
        }

        try {
            $trash = $this->quarantine->toTrashDir(
                $skinDir,
                $this->paths->mediaRoot() . '/.trash',
                $target->folder() . '_' . $target->aliasName() . '_' . $name,
            );
        } catch (Throwable $e) {
            $r->error($e->getMessage());

            return $r;
        }

        if ($trash !== null) {
            $r->file(Fs::rel($trash, $this->paths->root()));
        }

        $r->warn('Очистка карантина: php bin/cli.php trash:clear --force --older=7d');
        $r->data = [
            'project' => $target->projectKey(),
            'allias' => $target->aliasName(),
            'skin' => $name,
            'trash_dir' => $trash === null ? null : Fs::rel($trash, $this->paths->root()),
        ];

        $context->step('Скин перемещён в карантин', true, $name);

        return $r;
    }
}
