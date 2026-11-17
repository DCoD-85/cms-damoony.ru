<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Cli\Support\Quarantine;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use Damoony\Core\Settings\UnknownProjectException;
use Throwable;

/**
 * allias:remove — алиас проекта в карантин (app/ + media) + очистка конфига.
 */
final class AlliasRemoveCommand implements CommandInterface
{
    private const NAME_RE = '/^[a-z][a-z0-9_]*$/';

    public function __construct(
        private readonly Paths $paths,
        private readonly SiteConfig $site,
        private readonly SiteConfigWriter $writer,
        private readonly Quarantine $quarantine,
    ) {
    }

    public function name(): string
    {
        return 'allias:remove';
    }

    public function description(): string
    {
        return 'Удалить алиас проекта в карантин (--force)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = (string) ($args['domain'] ?? '');
        $name = (string) ($args['name'] ?? '');
        $force = isset($args['force']);

        if ($domain === '') {
            $r->error('Укажите --domain');

            return $r;
        }
        if (preg_match(self::NAME_RE, $name) !== 1) {
            $r->error('Некорректный алиас');

            return $r;
        }

        try {
            $project = $this->site->resolve($domain);
        } catch (UnknownProjectException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $alliasDir = $this->paths->allias($project->folder, $name);
        $inConfig = $project->hasAlias($name);

        if (!Fs::isDir($alliasDir) && !$inConfig) {
            $r->warn("Алиас не найден ни на диске, ни в конфиге: {$project->key}/{$name}");
            $context->step('Нечего удалять', true);

            return $r;
        }

        if (!$force) {
            $r->error('Для удаления укажите --force (файлы будут перемещены в карантин)');

            return $r;
        }

        $mediaDir = $this->paths->media($project->folder, $name);

        if ($context->isDryRun()) {
            $context->step(sprintf('Dry-run: алиас %s/%s будет удалён в карантин', $project->key, $name));
            $r->data = [
                'project' => $project->key,
                'alias' => $name,
                'allias_dir' => Fs::isDir($alliasDir) ? Fs::rel($alliasDir, $this->paths->root()) : null,
                'media_dir' => Fs::isDir($mediaDir) ? Fs::rel($mediaDir, $this->paths->root()) : null,
                'in_config' => $inConfig,
                'dry_run' => true,
            ];

            return $r;
        }

        try {
            $alliasTrash = $this->quarantine->inPlace($alliasDir);
            $mediaTrash = $this->quarantine->toTrashDir(
                $mediaDir,
                $this->paths->mediaRoot() . '/.trash',
                $project->folder . '_' . $name,
            );
            $schemesTrash = $this->quarantineSchemes($project->folder, $name, $alliasTrash);
        } catch (Throwable $e) {
            $r->error($e->getMessage());

            return $r;
        }

        if ($alliasTrash !== null) {
            $r->file(Fs::rel($alliasTrash, $this->paths->root()));
            $context->step('Перемещено в карантин', true, Fs::rel($alliasTrash, $this->paths->root()));
        } else {
            $r->warn('Папка алиаса не найдена — чистим только конфиг');
        }

        if ($mediaTrash !== null) {
            $r->file(Fs::rel($mediaTrash, $this->paths->root()));
            $context->step('Media перемещено в карантин', true, Fs::rel($mediaTrash, $this->paths->root()));
        }

        if ($schemesTrash !== []) {
            foreach ($schemesTrash as $path) {
                $r->file(Fs::rel($path, $this->paths->root()));
            }
            $context->step('Схемы алиаса в карантине', true);
        }

        try {
            $this->writer->removeAlias($project->key, $name);
        } catch (Throwable $e) {
            $r->error('Конфиг не обновлён: ' . $e->getMessage());

            return $r;
        }

        $r->warn('Очистка карантина: php bin/cli.php trash:clear --force --older=7d');
        $r->data = ['project' => $project->key, 'alias' => $name];

        return $r;
    }

    /**
     * Схемы алиаса (`.schemesglobal/{alias}.{php,editor.json,rules.json}`) — в карантин.
     *
     * Кладём в карантин папки алиаса, если он есть; иначе — отдельным каталогом в `/var/trash`.
     *
     * @return list<string>
     */
    private function quarantineSchemes(string $folder, string $alias, ?string $alliasTrash): array
    {
        $schemesDir = $this->paths->schemesGlobal($folder);
        $files = [];
        foreach (['php', 'editor.json', 'rules.json'] as $ext) {
            $file = $schemesDir . '/' . $alias . '.' . $ext;
            if (is_file($file)) {
                $files[] = $file;
            }
        }

        if ($files === []) {
            return [];
        }

        if ($alliasTrash !== null) {
            $target = $alliasTrash . '/.schemesglobal';
            Fs::mkdir($target);

            $moved = [];
            foreach ($files as $file) {
                $dst = $target . '/' . basename($file);
                if (@rename($file, $dst)) {
                    $moved[] = $dst;
                }
            }

            return $moved;
        }

        $trashDir = $this->paths->root() . '/var/trash/schemes_trash_' . time();
        Fs::mkdir($trashDir);

        return $this->quarantine->files($trashDir, $files);
    }
}
