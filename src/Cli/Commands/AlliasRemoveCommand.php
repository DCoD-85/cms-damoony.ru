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

        $domain = $args['domain'] ?? '';
        $name = $args['name'] ?? '';
        $force = isset($args['force']);

        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || !preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            $r->error('Некорректные параметры');
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

        $alliasDir = $this->paths->allias($domain, $name);

        if (Fs::isDir($alliasDir)) {
            if (!$force) {
                $r->error('Отменено пользователем (нужен --force)');
                return $r;
            }
            $trash = $alliasDir . '_trash_' . time();
            if (!@rename($alliasDir, $trash)) {
                $r->error('Не удалось переместить в карантин');
                return $r;
            }
            $context->step('Перемещено в карантин', true, Fs::rel($trash, $this->paths->root()));
        } else {
            $r->warn('Папка алиаса не найдена — чистим только конфиг');
        }

        $before = json_encode($site);
        unset($site['alliases_real'][$domain][$name]);
        if (json_encode($site) === $before) {
            $context->step('В конфиге не было', true);
        } else {
            Fs::writeAtomic($siteFile, json_encode($site, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
            $r->file(Fs::rel($siteFile, $this->paths->root()));
            $context->step('Конфиг обновлён');
        }

        return $r;
    }
}