<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Files\Fs;
use Damoony\Files\Paths;

final class AlliasRemoveCommand implements CommandInterface
{
    /** Ключи site.config, где данные скоупятся по домену+алиасу. */
    private const ALLIAS_KEYS = [
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
        return 'allias:remove';
    }

    public function description(): string
    {
        return 'Удалить алиас домена (в карантин)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = (string) ($args['domain'] ?? '');
        $name = (string) ($args['name'] ?? '');
        $force = isset($args['force']);

        // 1. Валидация
        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || str_contains($domain, '..')) {
            $r->error('Некорректный домен');
            return $r;
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            $r->error('Некорректный алиас');
            return $r;
        }

        $siteFile = $this->paths->siteConfig();
        if (!is_file($siteFile)) {
            $r->error('site.config не найден');
            return $r;
        }
        $site = Config::fromFile($siteFile)->all();

        // --force required for ANY mutation (disk OR config-only). See arch.md §5.
        if (!$force) {
            $r->error('Для удаления укажите --force (файлы будут перемещены в карантин)');
            return $r;
        }

        if (!isset($site['domains_real'][$domain])) {
            $r->error('Домен не найден');
            return $r;
        }

        // Reject non-self-mapped: paths->allias() and config use different keys.
        $realDomain = (string) ($site['domains_real'][$domain] ?? '');
        if ($realDomain !== '' && $realDomain !== $domain) {
            $r->error(
                "Домен '{$domain}' мапится на '{$realDomain}'. "
                . "Удаление non-self-mapped домена запрещено. Укажите --domain={$realDomain}.",
            );
            return $r;
        }

        $alliasDir = $this->paths->allias($domain, $name);
        $alliasInConfig = isset($site['alliases_real'][$domain][$name]);

        if (!is_dir($alliasDir) && !$alliasInConfig) {
            $r->warn("Алиас не найден ни на диске, ни в конфиге: {$domain}/{$name}");
            $context->step('Нечего удалять', true);
            return $r;
        }

        // 2. Карантин
        if (is_dir($alliasDir)) {
            $trash = $alliasDir . '_trash_' . time();
            if (!@rename($alliasDir, $trash)) {
                $r->error('Не удалось переместить в карантин');
                return $r;
            }
            $context->step('Перемещено в карантин', true, Fs::rel($trash, $this->paths->root()));
            $r->warn('Очистка карантина: php bin/cli.php trash:clear --force --older=7d');
        } else {
            $r->warn('Папка алиаса не найдена — чистим только конфиг');
        }

        // 3. Полная очистка конфига (все ключи по домену+алиасу)
        $removed = 0;
        foreach (self::ALLIAS_KEYS as $key) {
            if (isset($site[$key][$domain][$name])) {
                unset($site[$key][$domain][$name]);
                $removed++;
            }
        }

        if ($removed > 0) {
            Fs::writeAtomic(
                $siteFile,
                json_encode($site, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
            );
            $r->file(Fs::rel($siteFile, $this->paths->root()));
            $context->step("Конфиг обновлён ({$removed} ключей)", true);
        } else {
            $context->step('В конфиге не было', true);
        }

        return $r;
    }
}
