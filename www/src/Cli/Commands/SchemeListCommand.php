<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Core\Files\Paths;

final class SchemeListCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'scheme:list';
    }

    public function description(): string
    {
        return 'Список схем домена (.schemesglobal/)';
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
        $dir = $this->paths->domain($realDomain) . '/.schemesglobal';
        if (!is_dir($dir)) {
            $r->data = ['domain' => $domain, 'rows' => []];
            $context->step('Схем нет', true);
            return $r;
        }

        $rows = [];
        foreach (glob($dir . '/*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            $rulesFile = $dir . '/' . $name . '.rules.json';
            $editorFile = $dir . '/' . $name . '.editor.json';
            $enabled = false;
            if (is_file($rulesFile)) {
                $rules = json_decode((string) @file_get_contents($rulesFile), true);
                $enabled = is_array($rules) && ($rules['enabled'] ?? false) === true;
            }
            $hasEditor = is_file($editorFile);
            $rows[] = [
                'name' => $name,
                'enabled' => $enabled,
                'has_rules' => is_file($rulesFile),
                'has_editor' => $hasEditor,
            ];
            $r->file($name);
            $context->step(sprintf('%-20s enabled: %s, editor: %s', $name, $enabled ? 'yes' : 'no', $hasEditor ? 'yes' : 'no'), true, $name);
        }

        $r->data = ['domain' => $domain, 'rows' => $rows];
        $context->step(sprintf('Всего: %d', count($rows)), true);
        return $r;
    }
}
