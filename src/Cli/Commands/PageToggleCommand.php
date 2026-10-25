<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Files\Fs;
use Damoony\Files\Paths;

final class PageToggleCommand implements CommandInterface
{
    private const KEYS = ['auth', 'search', 'default'];

    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'page:toggle';
    }

    public function description(): string
    {
        return 'Переключить флаг страницы (auth/search/default)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = $args['domain'] ?? '';
        $allias = $args['allias'] ?? '';
        $path = $args['path'] ?? '';
        $key = $args['key'] ?? '';
        $value = ($args['value'] ?? '1') === '0' ? false : true;

        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain)) {
            $r->error('Некорректный домен');
            return $r;
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $allias)) {
            $r->error('Некорректный алиас');
            return $r;
        }
        if ($path !== '' && !preg_match('#^[a-z][a-z0-9_]*(/[a-z][a-z0-9_]*)*$#i', $path)) {
            $r->error('Некорректный путь');
            return $r;
        }
        if (!in_array($key, self::KEYS, true)) {
            $r->error('Недопустимый ключ: ' . $key);
            return $r;
        }

        $logic = (string) ($args['logic'] ?? 'alpha.0.0');
        $algoritmDir = $this->paths->algoritm($logic, $domain, $allias);
        $pageDir = $path === '' ? $algoritmDir : $algoritmDir . '/' . $path;

        if (!Fs::isDir($pageDir)) {
            $r->error('Страница не найдена');
            return $r;
        }

        $rulesFile = $pageDir . '/.rules.json';
        $default = [
            'available' => [''],
            'auth' => false,
            'search' => true,
            'default' => false,
        ];

        $current = Fs::isFile($rulesFile)
            ? json_decode((string) file_get_contents($rulesFile), true)
            : [];
        if (!is_array($current)) {
            $current = [];
        }

        $merged = array_merge($default, $current);
        $merged[$key] = $value;

        Fs::writeAtomic($rulesFile, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
        $r->file(Fs::rel($rulesFile, $this->paths->root()));

        $context->step('Флаг обновлён', true, "{$key}=" . ($value ? '1' : '0'));

        return $r;
    }
}