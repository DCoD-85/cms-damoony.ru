<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Commands\Concerns\ResolvesRealDomain;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;

final class SchemeRemoveCommand implements CommandInterface
{
    use ResolvesRealDomain;

    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'scheme:remove';
    }

    public function description(): string
    {
        return 'Удалить схему в карантин (3 файла)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();
        $domain = $args['domain'] ?? '';
        $name = $args['name'] ?? '';
        $force = isset($args['force']);

        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain)) {
            $r->error('Некорректный домен');
            return $r;
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            $r->error('Некорректное имя схемы');
            return $r;
        }
        if (!$force) {
            $r->error('Укажите --force (destructive-операция). Перед запуском сделайте git commit.');
            return $r;
        }

        try {
            $realDomain = $this->resolveRealDomain($this->paths, $domain);
        } catch (\RuntimeException $e) {
            $r->error($e->getMessage());
            return $r;
        }

        $schemesDir = $this->paths->domain($realDomain) . '/.schemesglobal';
        $files = [
            $schemesDir . '/' . $name . '.php',
            $schemesDir . '/' . $name . '.rules.json',
            $schemesDir . '/' . $name . '.editor.json',
        ];

        $existing = array_values(array_filter($files, 'is_file'));
        if ($existing === []) {
            $r->warn("Схема не найдена: {$name}");
            $context->step('Нечего удалять', true, $name);
            return $r;
        }

        // Карантин
        $trashDir = $schemesDir . '/' . $name . '_trash_' . time();
        Fs::mkdir($trashDir);

        foreach ($existing as $file) {
            $basename = basename($file);
            $target = $trashDir . '/' . $basename;
            if (!@rename($file, $target)) {
                // Откат: вернуть уже перенесённые
                $r->error('Не удалось переместить в карантин: ' . $basename);
                return $r;
            }
            $r->file(Fs::rel($target, $this->paths->root()));
        }

        $context->step('Схема перемещена в карантин', true, Fs::rel($trashDir, $this->paths->root()));
        $r->warn('Очистка карантина: php bin/cli.php trash:clear --force --older=7d');

        return $r;
    }
}
