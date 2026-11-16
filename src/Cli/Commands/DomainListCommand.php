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

        $primaryHosts = [];
        // 1. Все значения в domains_mirrors — это реальные (primary) хосты
        foreach ($site['domains_mirrors'] ?? [] as $mHost => $mPrimary) {
            $primaryHosts[$mPrimary] = true;
        }
        // 2. Добавляем хосты из domains_real, у которых нет зеркал (их нет в ключах domains_mirrors)
        foreach (array_keys($domainsReal) as $host) {
            if (!isset($site['domains_mirrors'][$host])) {
                $primaryHosts[$host] = true;
            }
        }
        $primaryHosts = array_keys($primaryHosts);
        sort($primaryHosts);

        $rows = [];
        foreach ($primaryHosts as $host) {
            $dir = $this->paths->domain($host);
            $exists = Fs::isDir($dir);

            $context->debug("Checking domain: $host -> $dir");

            $namespace = (string) ($domainsReal[$host] ?? $host);

            $mirrors = [];
            foreach ($site['domains_mirrors'] ?? [] as $mHost => $mPrimary) {
                if ($mPrimary === $host) {
                    $mirrors[] = $mHost;
                }
            }
            sort($mirrors);

            $available = (bool) ($site['domains_mirrors_available'][$host] ?? true);
            $aliasesMap = (array) ($site['alliases'][$host] ?? []);
            $aliases = array_keys($aliasesMap);
            sort($aliases);

            $row = [
                'domain' => $host,
                'namespace' => $namespace,
                'mirrors' => $mirrors,
                'dir_exists' => $exists,
                'available' => $available,
                'aliases' => $aliases,
            ];
            $rows[] = $row;
            $r->file($host);

            $mark = $exists ? '[ok]' : '[missing]';
            $state = $available ? 'on' : 'off';
            $context->step(
                sprintf('%s %-25s %s, aliases: %d', $mark, $host, $state, count($aliases)),
                true,
                $host,
            );
        }

        $r->data = ['rows' => $rows];
        $context->step(sprintf('Всего: %d', count($rows)), true);

        return $r;
    }
}
