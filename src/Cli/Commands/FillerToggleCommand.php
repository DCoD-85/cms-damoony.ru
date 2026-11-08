<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;

final class FillerToggleCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'filler:toggle';
    }

    public function description(): string
    {
        return 'Включить/выключить filler';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = (string) ($args['domain'] ?? '');
        $allias = (string) ($args['allias'] ?? '');
        $block = (string) ($args['block'] ?? '');
        $value = ($args['value'] ?? '1') === '0' ? false : true;

        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || str_contains($domain, '..')) {
            $r->error('Некорректный домен');
            return $r;
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $allias) || !preg_match('/^[a-z][a-z0-9_]*$/', $block)) {
            $r->error('Некорректные allias/block');
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
        if (!isset($site['alliases_real'][$domain][$allias])) {
            $r->error('Алиас не найден');
            return $r;
        }

        $realDomain = (string) $site['domains_real'][$domain];

        // Проверяем, что блок существует на диске (не пишем мусор в конфиг)
        $blockDir = $this->paths->domain($realDomain) . '/.alliasglobal/' . $block;
        if (!Fs::isDir($blockDir)) {
            $r->error('Блок не найден: ' . $block);
            return $r;
        }

        // Idempotent: если значение не меняется — не пишем конфиг
        $current = $site['fillers'][$domain][$allias][$block] ?? null;
        if ($current === $value) {
            $context->step('Filler уже ' . ($value ? 'включён' : 'выключен'), true, $block . '_' . $allias);
            return $r;
        }

        $site['fillers'][$domain][$allias][$block] = $value;
        Fs::writeAtomic(
            $siteFile,
            json_encode($site, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        );
        $r->file(Fs::rel($siteFile, $this->paths->root()));

        $context->step('Filler ' . ($value ? 'включён' : 'выключен'), true, $block . '_' . $allias);

        return $r;
    }
}
