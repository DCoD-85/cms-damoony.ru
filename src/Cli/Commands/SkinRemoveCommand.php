<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Files\Fs;
use Damoony\Files\Paths;

final class SkinRemoveCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'skin:remove';
    }

    public function description(): string
    {
        return 'Удалить скин в карантин (www/media/.trash/)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = (string) ($args['domain'] ?? '');
        $allias = (string) ($args['allias'] ?? '');
        $name = (string) ($args['name'] ?? '');
        $force = isset($args['force']);

        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || str_contains($domain, '..')) {
            $r->error('Некорректный домен');
            return $r;
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $allias)) {
            $r->error('Некорректный алиас');
            return $r;
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            $r->error('Некорректное имя скина');
            return $r;
        }
        if (!$force) {
            $r->error('Укажите --force (перемещение в карантин)');
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
        $realDomain = (string) $site['domains_real'][$domain];

        $skinDir = $this->paths->media($realDomain, $allias) . '/skin/' . $name;
        if (!Fs::isDir($skinDir)) {
            $r->warn("Скин не найден: {$name}");
            $context->step('Нечего удалять', true);
            return $r;
        }

        $trashRoot = $this->paths->mediaRoot() . '/.trash';
        $trashDir = $trashRoot . '/' . $realDomain . '_' . $allias . '_skin_' . $name . '_trash_' . time();
        Fs::mkdir($trashDir);

        if (!@rename($skinDir, $trashDir . '/' . $name)) {
            $r->error('Не удалось переместить в карантин');
            return $r;
        }
        $r->file(Fs::rel($trashDir . '/' . $name, $this->paths->root()));

        // Обновить конфиг
        if (isset($site['alliases_skins_allowed'][$domain][$allias]) && is_array($site['alliases_skins_allowed'][$domain][$allias])) {
            $allowed = array_values(array_filter(
                $site['alliases_skins_allowed'][$domain][$allias],
                static fn ($s): bool => $s !== $name,
            ));
            $site['alliases_skins_allowed'][$domain][$allias] = $allowed;
        }
        if (($site['alliases_skins'][$domain][$allias] ?? null) === $name) {
            $fallback = $site['alliases_skins_allowed'][$domain][$allias][0] ?? 'default';
            $site['alliases_skins'][$domain][$allias] = $fallback;
        }

        Fs::writeAtomic(
            $siteFile,
            json_encode($site, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        );

        $context->step('Скин перемещён в карантин', true, Fs::rel($trashDir, $this->paths->root()));
        $r->warn('Очистка: php bin/cli.php trash:clear --force --older=7d');

        return $r;
    }
}
