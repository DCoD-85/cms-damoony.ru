<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Files\Fs;
use Damoony\Files\Paths;

final class DomainListCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'domain:list';
    }

    public function description(): string
    {
        return 'Список доменов (site.config + наличие папки в app/)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $siteFile = $this->paths->siteConfig();
        if (!is_file($siteFile)) {
            $r->error('site.config не найден');
            return $r;
        }
        $site = Config::fromFile($siteFile)->all();

        $domainsReal = $site['domains_real'] ?? [];
        if (!is_array($domainsReal) || $domainsReal === []) {
            $r->data = ['rows' => []];
            $context->step('Доменов нет', true);
            return $r;
        }

        $realSet = [];
        foreach ($domainsReal as $host => $real) {
            if (is_string($real) && $real !== '') {
                $realSet[$real] = true;
            }
        }

        $realDomains = array_keys($realSet);
        sort($realDomains);

        $rows = [];
        foreach ($realDomains as $domain) {
            $dir = $this->paths->domain($domain);
            $exists = Fs::isDir($dir);
            $available = (bool) ($site['domains_available'][$domain] ?? false);
            $aliasesMap = (array) ($site['alliases_real'][$domain] ?? []);
            $realAliases = [];
            foreach ($aliasesMap as $key => $real) {
                if (!is_string($real) || $real === '') {
                    continue;
                }
                $realAliases[$real] = true;
            }
            $aliases = array_keys($realAliases);
            sort($aliases);

            $row = [
                'domain' => $domain,
                'dir_exists' => $exists,
                'available' => $available,
                'aliases' => $aliases,
            ];
            $rows[] = $row;
            $r->file($domain);

            $mark = $exists ? '[ok]' : '[missing]';
            $state = $available ? 'on' : 'off';
            $context->step(
                sprintf('%s %-25s %s, aliases: %d', $mark, $domain, $state, count($aliases)),
                true,
                $domain,
            );
        }

        $r->data = ['rows' => $rows];
        $context->step(sprintf('Всего: %d', count($rows)), true);

        return $r;
    }
}