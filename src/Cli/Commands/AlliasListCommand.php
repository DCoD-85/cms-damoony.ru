<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;

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

        $realDomain = (string) ($site['domains_mirrors'][$domain] ?? $domain);
        if (!isset($site['domains_real'][$realDomain])) {
            $r->error('Домен не найден в конфиге');
            return $r;
        }
        $context->debug("Resolved realDomain (host): $realDomain");

        $aliasesMap = $site['alliases'][$realDomain] ?? [];
        if (!is_array($aliasesMap) || $aliasesMap === []) {
            $r->data = ['domain' => $domain, 'rows' => []];
            $context->step('Алиасов нет', true);
            return $r;
        }

        ksort($aliasesMap, SORT_STRING);

        $rows = [];
        foreach ($aliasesMap as $aliasName => $aliasData) {
            if (!is_string($aliasName) || $aliasName === '') {
                continue;
            }

            $dir = $this->paths->allias($realDomain, $aliasName);
            $exists = Fs::isDir($dir);

            $pageCount = 0;
            $alg = $this->paths->algoritm($realDomain, $aliasName);
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

            $available = (bool) ($aliasData['available'] ?? false);

            $row = [
                'name' => $aliasName,
                'keys' => [$aliasName], // Теперь у алиаса только один ключ
                'dir_exists' => $exists,
                'available' => $available,
                'page_count' => $pageCount,
                'has_empty_key' => false,
            ];
            $rows[] = $row;
            $r->file($aliasName);

            $mark = $exists ? '[ok]' : '[!!]';
            $state = $available ? 'on' : 'off';
            $context->step(
                sprintf('%s %-15s %s, pages: %d', $mark, $aliasName, $state, $pageCount),
                true,
                $aliasName,
            );
        }

        $r->data = ['domain' => $domain, 'rows' => $rows];
        $context->step(sprintf('Всего: %d', count($rows)), true);

        return $r;
    }
}
