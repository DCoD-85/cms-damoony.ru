<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Files\Fs;
use Damoony\Files\Paths;
use Damoony\Page\PageCompiler;
use Damoony\Page\PageNamespace;
use Damoony\Page\PagePath;

final class PageWriteCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'page:write';
    }

    public function description(): string
    {
        return 'Записать контент (JSON) в существующую страницу';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = $args['domain'] ?? '';
        $allias = $args['allias'] ?? '';
        $name = $args['name'] ?? '';
        $parent = trim((string) ($args['parent'] ?? ''), '/');

        // 1. Валидация
        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain)) {
            $r->error('Некорректный домен');
            return $r;
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $allias)) {
            $r->error('Некорректный алиас');
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

        // 3. JSON
        if (isset($args['json_b64'])) {
            $decoded = base64_decode((string) $args['json_b64'], true);
            if ($decoded === false) {
                $r->error('Некорректный base64');
                return $r;
            }
            $json = $decoded;
        } else {
            $json = (string) ($args['json'] ?? '[]');
        }

        $blocks = json_decode($json, true);
        if (!is_array($blocks)) {
            $r->error('Некорректный JSON (ожидается массив блоков)');
            return $r;
        }

        // 4. Резолвинг
        $page = PagePath::resolve($this->paths, $domain, $allias, $name, $parent);

        if (!is_file($page->phpFile)) {
            $r->error('Страница не найдена. Сначала используйте page:new');
            return $r;
        }

        // 5. Namespace из site.config
        $siteFile = $this->paths->siteConfig();
        $site = is_file($siteFile) ? Config::fromFile($siteFile)->all() : [];
        $namespace = PageNamespace::fromSite($site, $domain);

        // 6. Компиляция и запись
        $withSchema = isset($args['with_schema']) && $args['with_schema'] === '1';
        $compiler = new PageCompiler();
        $php = $compiler->compile($namespace . '\\' . $allias, $page->classChain, $blocks, $withSchema);

        Fs::writeAtomic($page->phpFile, $php);
        $r->file(Fs::rel($page->phpFile, $this->paths->root()));

        $context->step('Контент страницы обновлен', true, $name !== '' ? $name : '__root__');

        return $r;
    }
}
