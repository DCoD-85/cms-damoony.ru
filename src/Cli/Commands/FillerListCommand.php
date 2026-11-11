<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Core\Files\Paths;

final class FillerListCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'filler:list';
    }

    public function description(): string
    {
        return 'Список филлеров алиаса (<block>_<allias>.php в .blocksglobal/)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();
        $domain = (string) ($args['domain'] ?? '');
        $allias = (string) ($args['allias'] ?? '');
        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || str_contains($domain, '..')) {
            $r->error('Некорректный домен');
            return $r;
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $allias)) {
            $r->error('Некорректный алиас');
            return $r;
        }
        $siteFile = $this->paths->siteConfig();
        if (!is_file($siteFile)) {
            $r->error('site.config не найден');
            return $r;
        }
        $site = Config::fromFile($siteFile)->all();
        if (!isset($site['domains_real'][$domain])) {
            $r->error('Домен не найден');
            return $r;
        }
        $realDomain = (string) $site['domains_real'][$domain];
        $dir = $this->paths->domain($realDomain) . '/.blocksglobal';
        if (!is_dir($dir)) {
            $r->data = ['domain' => $domain, 'allias' => $allias, 'rows' => []];
            $context->step('Филлеров нет', true);
            return $r;
        }

        $enabledMap = $site['fillers'][$domain][$allias] ?? [];
        if (!is_array($enabledMap)) {
            $enabledMap = [];
        }

        $rows = [];
        $blocks = glob($dir . '/*', GLOB_ONLYDIR) ?: [];
        sort($blocks);
        foreach ($blocks as $blockDir) {
            $blockName = basename($blockDir);
            if (str_starts_with($blockName, '.')) {
                continue;
            }
            $fillerPhp = $blockDir . '/' . $blockName . '_' . $allias . '.php';
            if (!is_file($fillerPhp)) {
                continue;
            }
            $enabled = (bool) ($enabledMap[$blockName] ?? false);
            $rows[] = [
                'block' => $blockName,
                'allias' => $allias,
                'enabled' => $enabled,
                'php' => $fillerPhp,
            ];
            $r->file($blockName . '_' . $allias);
            $context->step(sprintf('%-20s_%s enabled: %s', $blockName, $allias, $enabled ? 'yes' : 'no'), true, $blockName);
        }

        $r->data = ['domain' => $domain, 'allias' => $allias, 'rows' => $rows];
        $context->step(sprintf('Всего: %d', count($rows)), true);
        return $r;
    }
}
