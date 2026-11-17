<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Cli\Support\ContentLayout;
use Damoony\Cli\Support\TargetResolver;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use RuntimeException;

/**
 * content:remove для схемы domains_app: файлы контента в карантин.
 */
final class ContentRemoveCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
        private readonly TargetResolver $resolver,
        private readonly ContentLayout $layout,
    ) {
    }

    public function name(): string
    {
        return 'content:remove';
    }

    public function description(): string
    {
        return 'Удалить контент в карантин (--force)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        try {
            $target = $this->resolver->resolve(
                (string) ($args['domain'] ?? ''),
                (string) ($args['allias'] ?? ''),
            );
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $category = (string) ($args['category'] ?? '');
        $hash = (string) ($args['hash'] ?? '');

        if (preg_match(ContentLayout::CATEGORY_RE, $category) !== 1) {
            $r->error('Некорректная category');

            return $r;
        }
        if (preg_match(ContentLayout::HASH_RE, $hash) !== 1) {
            $r->error('Некорректный hash (16 hex)');

            return $r;
        }
        if (!isset($args['force'])) {
            $r->error('Укажите --force (перемещение в карантин)');

            return $r;
        }

        $files = $this->layout->filesForHash($target->folder(), $target->aliasName(), $category, $hash);
        if ($files === []) {
            $r->warn('Контент не найден: ' . $hash);
            $context->step('Нечего удалять', true);

            return $r;
        }

        $trashDir = $this->paths->contentRoot() . '/.trash/' . $target->folder() . '_'
            . $target->aliasName() . '_' . $category . '_' . $hash . '_trash_' . time();

        if ($context->isDryRun()) {
            $context->step('Dry-run: контент ' . $hash . ' будет перемещён в карантин');
            $r->data = [
                'hash' => $hash,
                'category' => $category,
                'files' => array_map('basename', $files),
                'dry_run' => true,
            ];

            return $r;
        }

        Fs::mkdir($trashDir);

        foreach ($files as $file) {
            $basename = basename($file);
            if (!@rename($file, $trashDir . '/' . $basename)) {
                $r->error('Не удалось переместить в карантин: ' . $basename);

                return $r;
            }
            $r->file(Fs::rel($trashDir . '/' . $basename, $this->paths->root()));
        }

        $r->warn('Очистка: php bin/cli.php trash:clear --force --older=7d');
        $r->data = [
            'project' => $target->projectKey(),
            'allias' => $target->aliasName(),
            'category' => $category,
            'hash' => $hash,
            'trash_dir' => Fs::rel($trashDir, $this->paths->root()),
        ];

        $context->step('Контент в карантине', true, Fs::rel($trashDir, $this->paths->root()));

        return $r;
    }
}
