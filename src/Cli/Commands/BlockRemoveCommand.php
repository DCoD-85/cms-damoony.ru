<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Files\Fs;
use Damoony\Files\Paths;

final class BlockRemoveCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'block:remove';
    }

    public function description(): string
    {
        return 'Удалить блок alliasglobal';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = $args['domain'] ?? '';
        $name = $args['name'] ?? '';

        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || !preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            $r->error('Некорректные параметры');
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

        $logic = (string) ($args['logic'] ?? 'alpha.0.0');
        $blocksDir = $this->paths->domain($logic, $domain) . '/alliasglobal';
        $blockDir = $blocksDir . '/' . $name;

        if (!Fs::isDir($blockDir)) {
            $r->error('Блок не найден');
            return $r;
        }

        $this->rmrf($blockDir);
        $context->step('Папка блока удалена', true, $name);

        $manifestFile = $blocksDir . '/blocks.json';
        if (is_file($manifestFile)) {
            $manifest = json_decode((string) file_get_contents($manifestFile), true);
            if (is_array($manifest) && isset($manifest['blocks'][$name])) {
                unset($manifest['blocks'][$name]);
                Fs::writeAtomic($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
                $r->file(Fs::rel($manifestFile, $this->paths->root()));
                $context->step('Манифест обновлён');
            }
        }

        return $r;
    }

    private function rmrf(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $i) {
            if ($i === '.' || $i === '..') {
                continue;
            }
            $p = $dir . '/' . $i;
            is_dir($p) ? $this->rmrf($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}