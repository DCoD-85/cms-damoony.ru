<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Next\Scaffold\Scaffolder;
use Damoony\Cli\Result;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use Throwable;

/**
 * domain:new для схемы domains_app.
 *
 * Создаёт папку проекта в app/, алиас www (3 файла страницы + язык), глобальную
 * схему, глобальный блок center и скин по умолчанию. Конфиг пишется в
 * config/site.config (схема domains_app).
 */
final class DomainNewCommand implements CommandInterface
{
    private const PROFILE_RE = '/^[a-z][a-z0-9_]*$/';

    public function __construct(
        private readonly Paths $paths,
        private readonly SiteConfig $site,
        private readonly SiteConfigWriter $writer,
        private readonly Scaffolder $scaffolder,
    ) {
    }

    public function name(): string
    {
        return 'domain:new';
    }

    public function description(): string
    {
        return 'Создать проект (domains_app) + алиас www';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = SiteConfig::normalizeHost((string) ($args['domain'] ?? ''));
        $profile = (string) ($args['profile'] ?? 'minimal');
        $folder = (string) ($args['folder'] ?? $domain);
        $namespace = (string) ($args['namespace'] ?? str_replace(['.', '-'], '_', $domain));
        $alias = (string) ($args['allias'] ?? 'www');

        if ($domain === '' || !SiteConfig::isValidHost($domain)) {
            $r->error('Некорректный домен');

            return $r;
        }
        if (!in_array($profile, ['clean', 'minimal', 'full'], true)) {
            $r->error('Некорректный профиль (clean, minimal, full)');

            return $r;
        }
        if ($folder === '' || preg_match(self::PROFILE_RE, $namespace) !== 1) {
            $r->error('Некорректный namespace');

            return $r;
        }
        if (preg_match(self::PROFILE_RE, $alias) !== 1) {
            $r->error('Некорректный алиас');

            return $r;
        }

        if ($this->site->has($domain)) {
            $r->error('Проект уже существует в конфиге');

            return $r;
        }
        if ($this->site->projectByHost($domain) !== null) {
            $r->error('Хост уже привязан к другому проекту');

            return $r;
        }

        $domainDir = $this->paths->domain($folder);
        if (Fs::isDir($domainDir)) {
            $r->error('Папка проекта уже существует');

            return $r;
        }

        if ($context->isDryRun()) {
            $context->step(
                sprintf('Dry-run: проект %s (app/%s, namespace: %s) будет создан', $domain, $folder, $namespace),
            );
            $r->data = ['project' => $domain, 'folder' => $folder, 'namespace' => $namespace, 'dry_run' => true];

            return $r;
        }

        try {
            $created = $this->scaffolder->domain($folder, $namespace, $alias, $profile);
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
            $this->writer->ensureProject($domain, $folder, $namespace);
            $this->writer->ensureMirror($domain, $domain);
            $this->writer->ensureAlias($domain, $alias);
        } catch (Throwable $e) {
            $r->error('Конфиг не обновлён: ' . $e->getMessage());

            return $r;
        }

        $r->data = ['project' => $domain, 'folder' => $folder, 'namespace' => $namespace];

        $context->step('Проект создан (профиль: ' . $profile . ')', true, $domain);
        $context->step('Конфиг обновлён');

        return $r;
    }
}
