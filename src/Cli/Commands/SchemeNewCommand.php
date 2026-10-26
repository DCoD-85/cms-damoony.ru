<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Files\Fs;
use Damoony\Files\Paths;

final class SchemeNewCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'scheme:new';
    }

    public function description(): string
    {
        return 'Создать схему домена';
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
        $schemeFile = $schemesDir . '/' . $name . '.data.php';

        if (Fs::isFile($schemeFile)) {
            $r->error('Схема уже существует');
            return $r;
        }

        Fs::mkdir($schemesDir);

        $data = "<?php\n\nreturn [\n    'tag' => 'div',\n    'class' => 'layout-{$name}',\n    'layout' => 'vertical',\n    'children' => [\n        ['tag' => 'div', 'class' => 'top'],\n        ['tag' => 'div', 'class' => 'center'],\n        ['tag' => 'div', 'class' => 'bottom'],\n    ],\n];\n";
        Fs::writeAtomic($schemeFile, $data);
        $r->file(Fs::rel($schemeFile, $this->paths->root()));

        $manifestFile = $schemesDir . '/schemes.json';
        $manifest = is_file($manifestFile)
            ? json_decode((string) file_get_contents($manifestFile), true)
            : [];
        if (!is_array($manifest)) {
            $manifest = [];
        }
        $manifest['schemes'][$name] = ['enabled' => true];
        Fs::writeAtomic($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        $r->file(Fs::rel($manifestFile, $this->paths->root()));

        $context->step('Схема создана', true, $name);

        return $r;
    }
}