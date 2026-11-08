<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\UI\Page\PagePath;
use Damoony\UI\Page\PageRules;

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
        $name = $args['name'] ?? '';
        $parent = trim((string) ($args['parent'] ?? ''), '/');
        $key = $args['key'] ?? '';
        $value = ($args['value'] ?? '1') === '0' ? false : true;

        // 1. Валидация
        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain)) {
            $r->error('Некорректный домен');
            return $r;
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $allias)) {
            $r->error('Некорректный алиас');
            return $r;
        }
        if (!in_array($key, self::KEYS, true)) {
            $r->error('Недопустимый ключ: ' . $key);
            return $r;
        }

        // 2. __root__ — главная страница
        if ($name === '__root__') {
            if ($parent !== '') {
                $r->error('--parent несовместим с __root__');
                return $r;
            }
            $name = '';
        } elseif ($name === '') {
            $r->error('Укажите --name (или __root__ для главной страницы)');
            return $r;
        } elseif ($parent !== '' && !preg_match('#^[a-z][a-z0-9_]*(/[a-z][a-z0-9_]*)*$#', $parent)) {
            $r->error('Некорректный путь родительской страницы');
            return $r;
        }

        // 3. Резолвинг
        $page = PagePath::resolve($this->paths, $domain, $allias, $name, $parent);

        if (!Fs::isFile($page->phpFile)) {
            $r->error('Страница не найдена');
            return $r;
        }

        // 4. Обновление rules.json (единый источник — algoritm_{name}.rules.json)
        $current = PageRules::load($page->rulesFile);
        $merged = PageRules::withDefaults($current);
        $merged[$key] = $value;

        PageRules::save($page->rulesFile, $merged);
        $r->file(Fs::rel($page->rulesFile, $this->paths->root()));

        $context->step('Флаг обновлён', true, "{$key}=" . ($value ? '1' : '0'));

        return $r;
    }
}
