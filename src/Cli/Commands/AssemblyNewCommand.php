<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Assembly\AssemblyBuilder;
use Damoony\Core\Config;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;

final class AssemblyNewCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'new';
    }

    public function description(): string
    {
        return 'Создать проект из готовой сборки (--assembly=developer)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();
        $domain = (string) ($args['domain'] ?? '');
        $assembly = (string) ($args['assembly'] ?? 'developer');
        $alias = (string) ($args['alias'] ?? 'www');

        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || str_contains($domain, '..')) {
            $r->error('Некорректный домен');
            return $r;
        }

        $siteFile = $this->paths->siteConfig();
        $site = is_file($siteFile) ? Config::fromFile($siteFile)->all() : [];
        $realDomain = (string) ($site['domains_real'][$domain] ?? $domain);
        $namespace = (string) ($site['domains_namespace'][$realDomain] ?? str_replace(['.', '-'], '_', $realDomain));

        $builder = new AssemblyBuilder($this->paths);

        try {
            $builder->build($assembly, $domain, [
                'namespace' => $namespace,
                'real' => $realDomain,
                'alias' => $alias,
            ]);
        } catch (\Throwable $e) {
            $r->error('Ошибка сборки: ' . $e->getMessage());
            return $r;
        }

        // Записываем в site.config
        $site['domains_real'][$domain] = $realDomain;
        $site['domains_available'][$domain] = true;
        $site['domains_namespace'][$realDomain] = $namespace;
        $site['domains_structure'][$realDomain] = 'v4';
        $site['alliases_real'][$realDomain] ??= [$alias => $alias];
        $site['alliases_available'][$realDomain][$alias] = true;
        $site['alliases_langs'][$realDomain][$alias] = ['ru', 'en'];
        $site['domains_langs'][$realDomain] = ['ru', 'en'];

        Fs::writeAtomic($siteFile, json_encode($site, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");

        $context->step('Проект собран из шаблона: ' . $assembly, true, $domain);
        return $r;
    }
}
