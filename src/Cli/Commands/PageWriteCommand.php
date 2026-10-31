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
        $json = $args['json'] ?? '[]';
        $parent = trim((string)($args['parent'] ?? ''), '/');

        if ($domain === '' || $allias === '' || $name === '') {
            $r->error('Укажите --domain, --allias и --name');
            return $r;
        }

        $blocks = json_decode($json, true);
        if (!is_array($blocks)) {
            $r->error('Некорректный JSON (ожидается массив блоков)');
            return $r;
        }

        $siteFile = $this->paths->siteConfig();
        $site = is_file($siteFile) ? Config::fromFile($siteFile)->all() : [];
        $namespace = (string) ($site['domains_namespace'][$domain] ?? str_replace(['.', '-'], '_', $domain));

        $targetRelPath = $parent === '' ? $name : $parent . '/' . $name;
        $pageDir = $this->paths->algoritm($domain, $allias) . '/' . $targetRelPath;

        if (!is_dir($pageDir)) {
            $r->error('Папка страницы не найдена. Сначала используйте page:new');
            return $r;
        }

        $classChain = 'algoritm_' . str_replace('/', '_', $targetRelPath);
        $compiler = new PageCompiler();
        $php = $compiler->compile($namespace . '\\' . $allias, $classChain, $blocks);

        $phpFile = $pageDir . '/' . $classChain . '.php';
        Fs::writeAtomic($phpFile, $php);
        
        $r->file(Fs::rel($phpFile, $this->paths->root()));
        $context->step("Контент страницы обновлен", true, $name);

        return $r;
    }
}