<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;

final class SchemeCopyCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'scheme:copy';
    }

    public function description(): string
    {
        return 'Копировать схему (3 файла)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();
        $domain = $args['domain'] ?? '';
        $name = $args['name'] ?? '';

        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain)) {
            $r->error('Некорректный домен');
            return $r;
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            $r->error('Некорректное имя схемы');
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

        $schemesDir = $this->paths->domain($domain) . '/.alliasscheme';
        if (!is_dir($schemesDir)) {
            $r->error('Папка схем не найдена');
            return $r;
        }

        $srcPhp = $schemesDir . '/' . $name . '.php';
        if (!is_file($srcPhp)) {
            $r->error('Схема не найдена: ' . $name);
            return $r;
        }

        // Генерируем свободное имя: name_copy, name_copy_1, name_copy_2, ...
        $newName = $name . '_copy';
        $i = 1;
        while (is_file($schemesDir . '/' . $newName . '.php')) {
            $newName = $name . '_copy_' . $i++;
        }

        // Копируем .php (обязательный)
        copy($srcPhp, $schemesDir . '/' . $newName . '.php');
        $r->file(Fs::rel($schemesDir . '/' . $newName . '.php', $this->paths->root()));

        // Копируем .rules.json (если есть) или создаём дефолт
        $srcRules = $schemesDir . '/' . $name . '.rules.json';
        $dstRules = $schemesDir . '/' . $newName . '.rules.json';
        if (is_file($srcRules)) {
            copy($srcRules, $dstRules);
        } else {
            Fs::writeAtomic($dstRules, json_encode(['enabled' => true], JSON_PRETTY_PRINT) . "\n");
        }
        $r->file(Fs::rel($dstRules, $this->paths->root()));

        // Копируем .editor.json (если есть)
        $srcEditor = $schemesDir . '/' . $name . '.editor.json';
        $dstEditor = $schemesDir . '/' . $newName . '.editor.json';
        if (is_file($srcEditor)) {
            copy($srcEditor, $dstEditor);
            $r->file(Fs::rel($dstEditor, $this->paths->root()));
        } else {
            $r->warn('Исходная схема без .editor.json — копия создана без UI-дерева');
        }

        $context->step('Схема скопирована', true, "{$name} → {$newName}");

        return $r;
    }
}
