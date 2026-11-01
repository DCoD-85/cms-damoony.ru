<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Files\Paths;

final class BlockListCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'block:list';
    }

    public function description(): string
    {
        return 'Список глобальных блоков домена (.alliasglobal/)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();
        $domain = (string) ($args['domain'] ?? '');
        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || str_contains($domain, '..')) {
            $r->error('Некорректный домен');
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
        $dir = $this->paths->domain($realDomain) . '/.alliasglobal';
        if (!is_dir($dir)) {
            $r->data = ['domain' => $domain, 'rows' => []];
            $context->step('Блоков нет', true);
            return $r;
        }

        $rows = [];
        $dirs = glob($dir . '/*', GLOB_ONLYDIR) ?: [];
        sort($dirs);
        foreach ($dirs as $blockDir) {
            $name = basename($blockDir);
            if (str_starts_with($name, '.')) {
                continue;
            }
            $rulesFile = $blockDir . '/' . $name . '.rules.json';
            $type = 'mixed';
            $enabled = false;
            if (is_file($rulesFile)) {
                $rules = json_decode((string) @file_get_contents($rulesFile), true);
                if (is_array($rules)) {
                    $type = is_string($rules['type'] ?? null) ? $rules['type'] : 'mixed';
                    $enabled = ($rules['enabled'] ?? false) === true;
                }
            }
            $rows[] = [
                'name' => $name,
                'type' => $type,
                'enabled' => $enabled,
                'has_php' => is_file($blockDir . '/' . $name . '.php'),
                'has_editor' => is_file($blockDir . '/' . $name . '.editor.json'),
            ];
            $r->file($name);
            $context->step(sprintf('%-20s type: %-8s enabled: %s', $name, $type, $enabled ? 'yes' : 'no'), true, $name);
        }

        $r->data = ['domain' => $domain, 'rows' => $rows];
        $context->step(sprintf('Всего: %d', count($rows)), true);
        return $r;
    }
}