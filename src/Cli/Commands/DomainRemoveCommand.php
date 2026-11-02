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
    /** Ключи site.config, где данные скоупятся по домену. */
    private const DOMAIN_KEYS = [
        'domains_real',
        'domains_available',
        'domains_namespace',
        'domains_titles',
        'domains_skins',
        'domains_skins_allowed',
        'domains_stubs',
        'domains_scheme',
        'alliases_real',
        'alliases_available',
        'alliases_langs',
        'alliases_titles',
        'alliases_stubs',
        'alliases_skins',
        'alliases_skins_allowed',
        'fillers',
    ];

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
        $domain = (string) ($args['domain'] ?? '');
        $force = isset($args['force']);

        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || str_contains($domain, '..')) {
            $r->error('Некорректный домен (укажите --domain=example.ru)');
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

        // Reject non-self-mapped: paths->domain() resolves via realDomain,
        // config cleanup uses $domain directly. Removing 'alias.ru' when it
        // maps to 'real.ru' would orphan app/real.ru and partially clean config.
        $realDomain = (string) ($site['domains_real'][$domain] ?? '');
        if ($realDomain !== '' && $realDomain !== $domain) {
            $r->error(
                "Домен '{$domain}' мапится на '{$realDomain}'. "
                . 'Удаление non-self-mapped домена запрещено. '
                . "Укажите --domain={$realDomain} или отредактируйте site.config вручную.",
            );
            return $r;
        }

        $domainDir = $this->paths->domain($domain);
        $domainInConfig = isset($site['domains_real'][$domain]);

        if (!is_dir($domainDir) && !$domainInConfig) {
            $r->warn("Домен не найден ни на диске, ни в конфиге: {$domain}");
            $context->step('Нечего удалять', true);
            return $r;
        }

        // 1. Карантин (только если папка есть)
        if (is_dir($domainDir)) {
            $trashDir = $domainDir . '_trash_' . time();
            if (!@rename($domainDir, $trashDir)) {
                $r->error('Не удалось переместить папку домена в карантин');
                return $r;
            }
            $context->step('Папка домена перемещена в карантин', true, basename($trashDir));
            $r->warn('Очистка карантина: php bin/cli.php trash:clear --force --older=7d');
        } else {
            $r->warn('Папка домена не найдена — чистим только конфиг');
        }

        // 2. Полная очистка конфига (все ключи по домену)
        $removed = 0;
        foreach (self::DOMAIN_KEYS as $key) {
            if (isset($site[$key][$domain])) {
                unset($site[$key][$domain]);
                $removed++;
            }
        }

        if ($removed > 0) {
            Fs::writeAtomic(
                $siteFile,
                json_encode($site, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
            );
            $r->file(Fs::rel($siteFile, $this->paths->root()));
            $context->step("Домен {$domain} удалён из конфига ({$removed} ключей)", true);
        } else {
            $context->step('В конфиге не было', true);
        }

        return $r;
    }
}
