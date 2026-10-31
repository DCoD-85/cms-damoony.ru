<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Files\Fs;
use Damoony\Files\Paths;

final class DomainRemoveCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'domain:remove';
    }

    public function description(): string
    {
        return 'Удалить домен (в карантин) и вычистить конфиг';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();
        $domain = $args['domain'] ?? '';
        $force = isset($args['force']);

        if ($domain === '') {
            $r->error('Укажите --domain=example.ru');
            return $r;
        }

        if (!$force) {
            $r->error('Для удаления укажите --force (файлы будут перемещены в карантин)');
            return $r;
        }

        $siteFile = $this->paths->siteConfig();
        if (!is_file($siteFile)) {
            $r->error('site.config не найден');
            return $r;
        }
        $site = Config::fromFile($siteFile)->all();

        $domainDir = $this->paths->domain($domain);
        
        // 1. Перемещаем папку в карантин
        if (is_dir($domainDir)) {
            $trashDir = $domainDir . '_trash_' . time();
            rename($domainDir, $trashDir);
            $context->step("Папка домена перемещена в карантин: " . basename($trashDir));
        }

        // 2. Чистим конфиг
        unset($site['domains_real'][$domain]);
        unset($site['domains_available'][$domain]);
        unset($site['domains_namespace'][$domain]);
        unset($site['alliases_real'][$domain]);
        unset($site['alliases_available'][$domain]);
        unset($site['alliases_langs'][$domain]);
        unset($site['domains_scheme'][$domain]);
        
        Fs::writeAtomic($siteFile, json_encode($site, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        
        $context->step("Домен {$domain} удален из конфига", true);

        return $r;
    }
}