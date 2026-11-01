<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Files\Fs;
use Damoony\Files\Paths;

final class AlliasListCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'allias:list';
    }

    public function description(): string
    {
        return 'Список алиасов домена (site.config + наличие папки в app/)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = (string) ($args['domain'] ?? '');
        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || str_contains($domain, '..')) {
            $r->error('Некорректный домен (укажите --domain=example.ru)');
            return $r;
        }

        $siteFile = $this->paths->siteConfig();
        if (!is_file($siteFile)) {
            $r->error('site.config не найден');
            return $r;
        }
        $site = Config::fromFile($siteFile)->all();

        if (!isset($site['domains_real'][$domain])) {
            $r->error('Домен не найден в конфиге');
            return $r;
        }

        $realDomain = (string) $site['domains_real'][$domain];
        $aliasesMap = $site['alliases_real'][$domain] ?? [];
        if (!is_array($aliasesMap) || $aliasesMap === []) {
            $r->data = ['domain' => $domain, 'rows' => []];
            $context->step('Алиасов нет', true);
            return $r;
        }

        $rows = [];
        foreach ($aliasesMap as $key => $real) {
            if (!is_string($key) || $key === '' || !is_string($real) || $real === '') {
                continue;
            }
            $dir = $this->paths->allias($realDomain, $real);
            $exists = Fs::isDir($dir);
            $pageCount = 0;
            $alg = $this->paths->algoritm($realDomain, $real);
            if (is_dir($alg)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($alg, \FilesystemIterator::SKIP_DOTS),
                );
                foreach ($iterator as $file) {
                    if ($file instanceof \SplFileInfo && $file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                        $pageCount++;
                    }
                }
            }
            $available = (bool) ($site['alliases_available'][$domain][$key] ?? false);

            $row = [
                'name' => $key,
                'real' => $real,
                'dir_exists' => $exists,
                'available' => $available,
                'page_count' => $pageCount,
            ];
            $rows[] = $row;
            $r->file($key);

            $mark = $exists ? '[ok]' : '[missing]';
            $state = $available ? 'on' : 'off';
            $context->step(
                sprintf('%s %-15s → %-15s %s, pages: %d', $mark, $key, $real, $state, $pageCount),
                true,
                $key,
            );
        }

        $r->data = ['domain' => $domain, 'rows' => $rows];
        $context->step(sprintf('Всего: %d', count($rows)), true);

        return $r;
    }
}