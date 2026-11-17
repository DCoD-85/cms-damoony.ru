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
 * domain:remove — проект в карантин (папка app/ + media) + очистка конфига.
 *
 * В новой схеме мирроры принадлежат проекту, поэтому отдельного «удалить только
 * привязку» нет: снимайте миррор через domain:unbind. Если у проекта больше
 * одного хоста — требуется --cascade (защита от случайного сноса с зеркалами).
 */
final class DomainRemoveCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
        private readonly SiteConfig $site,
        private readonly SiteConfigWriter $writer,
        private readonly Quarantine $quarantine,
    ) {
    }

    public function name(): string
    {
        return 'domain:remove';
    }

    public function description(): string
    {
        return 'Удалить проект в карантин (--force; --cascade при нескольких миррорах)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $input = SiteConfig::normalizeHost((string) ($args['domain'] ?? ''));
        $force = isset($args['force']);
        $cascade = isset($args['cascade']);

        if ($input === '') {
            $r->error('Укажите --domain=[проект]');

            return $r;
        }

        try {
            $project = $this->site->resolve($input);
        } catch (UnknownProjectException) {
            $r->warn("Проект не найден: {$input}");
            $context->step('Нечего удалять', true);

            return $r;
        }

        if ($project->key !== $input) {
            $r->error(sprintf('Это миррор проекта %s. Укажите --domain=%s', $project->key, $project->key));

            return $r;
        }

        if (!$force) {
            $r->error('Для удаления укажите --force (файлы будут перемещены в карантин)');

            return $r;
        }

        $mirrors = $project->hostNames();
        if (count($mirrors) > 1 && !$cascade) {
            $r->error(sprintf(
                'У проекта %d хостов (%s). Используйте --cascade для удаления вместе с миррорами.',
                count($mirrors),
                implode(', ', $mirrors),
            ));

            return $r;
        }

        $domainDir = $this->paths->domain($project->folder);
        $mediaDir = $this->paths->mediaRoot() . '/' . $project->folder;
        $aliases = $project->aliasNames();

        if ($context->isDryRun()) {
            $context->step(sprintf('Dry-run: проект %s будет удалён в карантин', $project->key));
            $r->data = [
                'project' => $project->key,
                'folder' => $project->folder,
                'mirrors' => $mirrors,
                'aliases' => $aliases,
                'domain_dir' => Fs::isDir($domainDir) ? Fs::rel($domainDir, $this->paths->root()) : null,
                'media_dir' => Fs::isDir($mediaDir) ? Fs::rel($mediaDir, $this->paths->root()) : null,
                'dry_run' => true,
            ];

            return $r;
        }

        try {
            $domainTrash = $this->quarantine->inPlace($domainDir);
            $mediaTrash = $this->quarantine->toTrashDir(
                $mediaDir,
                $this->paths->mediaRoot() . '/.trash',
                $project->folder,
            );
        } catch (Throwable $e) {
            $r->error($e->getMessage());

            return $r;
        }

        if ($domainTrash !== null) {
            $r->file(Fs::rel($domainTrash, $this->paths->root()));
            $context->step('Папка проекта перемещена в карантин', true, Fs::rel($domainTrash, $this->paths->root()));
        } else {
            $r->warn('Папка проекта не найдена — чистим только конфиг');
        }

        if ($mediaTrash !== null) {
            $r->file(Fs::rel($mediaTrash, $this->paths->root()));
            $context->step('Media проекта перемещено в карантин', true, Fs::rel($mediaTrash, $this->paths->root()));
        }

        try {
            $this->writer->removeProject($project->key);
        } catch (Throwable $e) {
            $r->error('Конфиг не обновлён: ' . $e->getMessage());

            return $r;
        }

        $r->warn('Очистка карантина: php bin/cli.php trash:clear --force --older=7d');
        $r->data = [
            'project' => $project->key,
            'folder' => $project->folder,
            'mirrors' => $mirrors,
            'aliases' => $aliases,
        ];

        $context->step(sprintf('Проект %s удалён из конфига', $project->key), true);

        return $r;
    }
}
