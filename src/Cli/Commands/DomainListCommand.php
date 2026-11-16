<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;

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

        $realToHosts = [];
        foreach ($domainsReal as $host => $real) {
            if (is_string($real) && $real !== '') {
                $realToHosts[$real][] = $host;
            }
        }
        ksort($realToHosts);

        $rows = [];
        foreach ($realToHosts as $realDomain => $hosts) {
            $dir = $this->paths->domain($realDomain);
            $exists = Fs::isDir($dir);

            // Берем primary host для available и alliases
            $primaryHost = $hosts[0];
            $available = (bool) ($site['domains_mirrors_available'][$primaryHost] ?? true);
            $aliasesMap = (array) ($site['alliases'][$primaryHost] ?? []);
            $aliases = array_keys($aliasesMap);
            sort($aliases);

            $row = [
                'domain' => $realDomain,
                'hosts' => $hosts,
                'dir_exists' => $exists,
                'available' => $available,
                'aliases' => $aliases,
            ];
            $rows[] = $row;
            $r->file($realDomain);

            $mark = $exists ? '[ok]' : '[missing]';
            $state = $available ? 'on' : 'off';
            $context->step(
                sprintf('%s %-25s %s, aliases: %d', $mark, $realDomain, $state, count($aliases)),
                true,
                $realDomain,
            );
        }

        $r->data = ['rows' => $rows];
        $context->step(sprintf('Всего: %d', count($rows)), true);

        return $r;
    }
}
