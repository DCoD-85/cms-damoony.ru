<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Files\Paths;

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
        if (!isset($site['domains_real'][$domain])) {
            $r->error('Домен не найден');
            return $r;
        }
        $realDomain = (string) $site['domains_real'][$domain];

        $skinRoot = $this->paths->media($realDomain, $allias) . '/skin';
        if (!is_dir($skinRoot)) {
            $r->data = ['domain' => $domain, 'allias' => $allias, 'active' => null, 'rows' => []];
            $context->step('Скинов нет', true);
            return $r;
        }

        $active = $site['alliases_skins'][$domain][$allias] ?? null;
        $allowed = $site['alliases_skins_allowed'][$domain][$allias] ?? [];
        if (!is_array($allowed)) {
            $allowed = [];
        }

        $rows = [];
        foreach (glob($skinRoot . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $name = basename($dir);
            if (str_starts_with($name, '.')) {
                continue;
            }
            $hasCss = is_file($dir . '/css/main.css');
            $hasJs = is_file($dir . '/js/main.js');
            $hasMeta = is_file($dir . '/skin.json');
            $isActive = $active === $name;
            $isAllowed = in_array($name, $allowed, true);

            $rows[] = [
                'name' => $name,
                'active' => $isActive,
                'allowed' => $isAllowed,
                'has_css' => $hasCss,
                'has_js' => $hasJs,
                'has_meta' => $hasMeta,
            ];
            $r->file($name);

            $flags = [];
            if ($isActive) {
                $flags[] = 'active';
            }
            if ($isAllowed) {
                $flags[] = 'allowed';
            }
            $flagsStr = $flags === [] ? '' : ' [' . implode(',', $flags) . ']';
            $context->step(
                sprintf('%-15s css:%s js:%s%s', $name, $hasCss ? 'y' : 'n', $hasJs ? 'y' : 'n', $flagsStr),
                true,
                $name,
            );
        }
        usort($rows, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        $r->data = ['domain' => $domain, 'allias' => $allias, 'active' => $active, 'rows' => $rows];
        $context->step(sprintf('Всего: %d', count($rows)), true);

        return $r;
    }
}
