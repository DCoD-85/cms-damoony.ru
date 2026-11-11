<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;

final class LangListCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'lang:list';
    }

    public function description(): string
    {
        return 'Список языков (.l18n/{lang}.json) для домена или алиаса';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = (string) ($args['domain'] ?? '');
        $allias = (string) ($args['allias'] ?? '');

        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || str_contains($domain, '..')) {
            $r->error('Некорректный домен');
            return $r;
        }
        if ($allias !== '' && !preg_match('/^[a-z][a-z0-9_]*$/', $allias)) {
            $r->error('Некорректный алиас');
            return $r;
        }

        $dir = $allias !== ''
            ? $this->paths->l18nAllias($domain, $allias)
            : $this->paths->l18nDomain($domain);

        if (!Fs::isDir($dir)) {
            $r->data = ['domain' => $domain, 'allias' => $allias, 'rows' => []];
            $context->step('Языков нет', true);
            return $r;
        }

        $files = glob($dir . '/*.json') ?: [];
        sort($files);

        $rows = [];
        foreach ($files as $file) {
            $lang = basename($file, '.json');
            if (!preg_match('/^[a-z]{2}$/', $lang)) {
                continue;
            }
            $rel = Fs::rel($file, $this->paths->root());
            $rows[] = ['lang' => $lang, 'file' => $rel];
            $r->file($rel);
            $context->step(sprintf('%-4s %s', $lang, $rel), true, $lang);
        }

        $r->data = ['domain' => $domain, 'allias' => $allias, 'rows' => $rows];
        $context->step(sprintf('Всего: %d', count($rows)), true);

        return $r;
    }
}
