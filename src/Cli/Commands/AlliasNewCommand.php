<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Cli\Scaffold\Scaffolder;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use Damoony\Core\Settings\UnknownProjectException;
use Throwable;

/**
 * allias:new для схемы domains_app.
 */
final class AlliasNewCommand implements CommandInterface
{
    private const NAME_RE = '/^[a-z][a-z0-9_]*$/';

    public function __construct(
        private readonly Paths $paths,
        private readonly SiteConfig $site,
        private readonly SiteConfigWriter $writer,
        private readonly Scaffolder $scaffolder,
    ) {
    }

    public function name(): string
    {
        return 'allias:new';
    }

    public function description(): string
    {
        return 'Создать алиас проекта (domains_app)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = (string) ($args['domain'] ?? '');
        $name = (string) ($args['name'] ?? '');
        $profile = (string) ($args['profile'] ?? 'minimal');

        if ($domain === '') {
            $r->error('Укажите --domain');

            return $r;
        }
        if (preg_match(self::NAME_RE, $name) !== 1) {
            $r->error('Некорректный алиас');

            return $r;
        }
        if (!in_array($profile, ['clean', 'minimal', 'full'], true)) {
            $r->error('Некорректный профиль (clean, minimal, full)');

            return $r;
        }

        try {
            $project = $this->site->resolve($domain);
        } catch (UnknownProjectException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        if ($project->hasAlias($name)) {
            $r->error('Алиас уже существует в конфиге');

            return $r;
        }

        $alliasDir = $this->paths->allias($project->folder, $name);
        if (Fs::isDir($alliasDir)) {
            $r->error('Папка алиаса уже существует');

            return $r;
        }

        if ($context->isDryRun()) {
            $context->step(sprintf('Dry-run: алиас %s для %s будет создан', $name, $project->key));
            $r->data = ['project' => $project->key, 'alias' => $name, 'dry_run' => true];

            return $r;
        }

        try {
            $created = $this->scaffolder->alias($project->folder, $project->namespace, $name, $profile);
            if ($profile !== 'clean') {
                foreach ($this->scaffolder->skin($project->folder, $name, 'default') as $path) {
                    $created[] = $path;
                }
            }
        } catch (Throwable $e) {
            $r->error('Не удалось создать структуру: ' . $e->getMessage());

            return $r;
        }

        foreach ($created as $path) {
            if (!Fs::isDir($path)) {
                $r->file(Fs::rel($path, $this->paths->root()));
            }
        }

        try {
            $this->writer->ensureAlias($project->key, $name);
        } catch (Throwable $e) {
            $r->error('Конфиг не обновлён: ' . $e->getMessage());

            return $r;
        }

        $r->data = ['project' => $project->key, 'alias' => $name];

        $context->step('Алиас создан (профиль: ' . $profile . ')', true, $project->key . '/' . $name);

        return $r;
    }
}
