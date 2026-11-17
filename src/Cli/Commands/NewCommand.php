<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Assembly\AssemblyBuilder;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use RuntimeException;
use Throwable;

/**
 * new --assembly=developer — проект из готовой сборки (stubs/assemblies/).
 *
 * Сборка раскладывается в app/{folder}/ и www/media/{folder}/{alias}/ с подстановкой
 * {{domain}}, {{real}}, {{alias}}, {{namespace}}. Конфиг дополняется проектом,
 * миррором и алиасом; языки проекта/алиаса выставляются согласованно.
 */
final class NewCommand implements CommandInterface
{
    private const ALIAS_RE = '/^[a-z][a-z0-9_]*$/';
    private const DEFAULT_LANGS = ['ru', 'en'];

    public function __construct(
        private readonly Paths $paths,
        private readonly SiteConfig $site,
        private readonly SiteConfigWriter $writer,
    ) {
    }

    public function name(): string
    {
        return 'new';
    }

    public function description(): string
    {
        return 'Создать проект из сборки (--assembly=developer)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = SiteConfig::normalizeHost((string) ($args['domain'] ?? ''));
        $assembly = (string) ($args['assembly'] ?? 'developer');
        $alias = (string) ($args['alias'] ?? 'www');

        if ($domain === '' || !SiteConfig::isValidHost($domain)) {
            $r->error('Некорректный домен');

            return $r;
        }
        if (preg_match(self::ALIAS_RE, $alias) !== 1) {
            $r->error('Некорректный алиас');

            return $r;
        }

        $existing = $this->site->project($domain);
        $folder = $existing === null ? $domain : $existing->folder;
        $namespace = $existing === null
            ? (string) ($args['namespace'] ?? str_replace(['.', '-'], '_', $domain))
            : $existing->namespace;

        if (!is_dir($this->paths->root() . '/stubs/assemblies/' . $assembly)) {
            $r->error(sprintf('Сборка "%s" не найдена в stubs/assemblies/', $assembly));

            return $r;
        }

        if ($context->isDryRun()) {
            $context->step(sprintf('Dry-run: проект %s из сборки %s (app/%s)', $domain, $assembly, $folder));
            $r->data = [
                'project' => $domain,
                'folder' => $folder,
                'assembly' => $assembly,
                'alias' => $alias,
                'dry_run' => true,
            ];

            return $r;
        }

        try {
            new AssemblyBuilder($this->paths)->build($assembly, $domain, [
                'namespace' => $namespace,
                'real' => $folder,
                'alias' => $alias,
            ]);
        } catch (Throwable $e) {
            $r->error('Сборка не применена: ' . $e->getMessage());

            return $r;
        }

        try {
            $this->writer->ensureProject($domain, $folder, $namespace);
            $this->writer->ensureMirror($domain, $domain);
            $this->writer->ensureAlias($domain, $alias, self::DEFAULT_LANGS);
            $this->writer->setProjectLangs($domain, self::DEFAULT_LANGS);
        } catch (RuntimeException $e) {
            $r->error('Конфиг не обновлён: ' . $e->getMessage());

            return $r;
        }

        $r->data = [
            'project' => $domain,
            'folder' => $folder,
            'namespace' => $namespace,
            'assembly' => $assembly,
            'alias' => $alias,
            'app_dir' => Fs::rel($this->paths->domain($folder), $this->paths->root()),
        ];

        $context->step('Проект собран из сборки: ' . $assembly, true, $domain);

        return $r;
    }
}
