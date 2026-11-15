<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Core\Files\Paths;

final class SkinListCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'skin:list';
    }

    public function description(): string
    {
        return 'Список скинов алиаса (www/media/<domain>/<allias>/skin/)';
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
        $realDomain = (string) ($site['domains_mirrors'][$domain] ?? $domain);
        if (!isset($site['domains_real'][$realDomain])) {
            $r->error('Домен не найден');
            return $r;
        }

        $skinRoot = $this->paths->media($realDomain, $allias) . '/skin';
        if (!is_dir($skinRoot)) {
            $r->data = ['domain' => $domain, 'allias' => $allias, 'rows' => []];
            $context->step('Скинов нет', true);
            return $r;
        }

        $rows = [];
        foreach (glob($skinRoot . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $name = basename($dir);
            if (str_starts_with($name, '.')) continue;
            
            $rows[] = [
                'name' => $name,
                'has_css' => is_file($dir . '/css/main.css'),
                'has_js' => is_file($dir . '/js/main.js'),
                'has_meta' => is_file($dir . '/skin.json'),
            ];
            $r->file($name);
            $context->step(sprintf('%-15s css:%s js:%s', $name, is_file($dir . '/css/main.css') ? 'y' : 'n', is_file($dir . '/js/main.js') ? 'y' : 'n'), true, $name);
        }
        usort($rows, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));
        $r->data = ['domain' => $domain, 'allias' => $allias, 'rows' => $rows];
        $context->step(sprintf('Всего: %d', count($rows)), true);

        return $r;
    }
}
