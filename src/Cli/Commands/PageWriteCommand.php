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
        $parent = trim((string)($args['parent'] ?? ''), '/');

        // 1. Обязательные аргументы — проверяются ДО конвертации __root__
        if ($domain === '' || $allias === '') {
            $r->error('Укажите --domain и --allias');
            return $r;
        }

        // 2. __root__ — специальное имя для главной страницы (/)
        if ($name === '__root__') {
            if ($parent !== '') {
                $r->error('--parent несовместим с __root__ (главная страница всегда в корне)');
                return $r;
            }
            $name = '';
        } elseif ($name === '') {
            $r->error('Укажите --name (или __root__ для главной страницы)');
            return $r;
        }

        // 3. JSON (base64 от агента или plain от CLI)
        if (isset($args['json_b64'])) {
            $decoded = base64_decode((string)$args['json_b64'], true);
            if ($decoded === false) {
                $r->error('Некорректный base64');
                return $r;
            }
            $json = $decoded;
        } else {
            $json = (string)($args['json'] ?? '[]');
        }

        $blocks = json_decode($json, true);
        if (!is_array($blocks)) {
            $r->error('Некорректный JSON (ожидается массив блоков)');
            return $r;
        }

        $siteFile = $this->paths->siteConfig();
        $site = is_file($siteFile) ? Config::fromFile($siteFile)->all() : [];
        $namespace = (string) ($site['domains_namespace'][$domain] ?? str_replace(['.', '-'], '_', $domain));

        // 4. Путь без двойных слэшей (fix manifest §6.25)
        $targetRelPath = $parent === '' ? $name : $parent . '/' . $name;
        $pageDir = rtrim($this->paths->algoritm($domain, $allias), '/');
        if ($targetRelPath !== '') {
            $pageDir .= '/' . $targetRelPath;
        }

        if (!is_dir($pageDir)) {
            $r->error('Папка страницы не найдена. Сначала используйте page:new');
            return $r;
        }

        $classChain = $targetRelPath === '' ? 'algoritm' : 'algoritm_' . str_replace('/', '_', $targetRelPath);
        $withSchema = isset($args['with_schema']) && $args['with_schema'] === '1';

        $compiler = new PageCompiler();
        $php = $compiler->compile($namespace . '\\' . $allias, $classChain, $blocks, $withSchema);

        $phpFile = $pageDir . '/' . $classChain . '.php';
        Fs::writeAtomic($phpFile, $php);

        $r->file(Fs::rel($phpFile, $this->paths->root()));
        $context->step('Контент страницы обновлен', true, $name !== '' ? $name : '__root__');

        return $r;
    }
}
