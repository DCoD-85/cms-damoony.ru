<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\UI\Page\PageCompiler;
use Damoony\UI\Page\PageNamespace;

final class AlliasNewCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'allias:new';
    }

    public function description(): string
    {
        return 'Создать алиас домена';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = (string) ($args['domain'] ?? '');
        $name = (string) ($args['name'] ?? '');

        // 1. Валидация
        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || str_contains($domain, '..')) {
            $r->error('Некорректный домен');
            return $r;
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            $r->error('Некорректный алиас');
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
            $r->error('Алиас уже существует');
            return $r;
        }

        // 2. Папки и файлы
        Fs::mkdir($alliasDir);
        $r->file(Fs::rel($alliasDir, $this->paths->root()));

        $algoritmDir = $this->paths->algoritm($domain, $name);
        Fs::mkdir($algoritmDir);

        // 2a. Root страница через PageCompiler + rules + editor
        $namespace = PageNamespace::fromSite($site, $domain);
        $compiler = new PageCompiler();
        $rootPhp = $compiler->compile($namespace . '\\' . $name, 'algoritm', []);

        Fs::writeAtomic($algoritmDir . '/algoritm.php', $rootPhp);
        $r->file(Fs::rel($algoritmDir . '/algoritm.php', $this->paths->root()));

        Fs::writeAtomic(
            $algoritmDir . '/algoritm.rules.json',
            json_encode(
                ['available' => [], 'auth' => false, 'search' => true, 'default' => false],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
            ) . "\n",
        );
        $r->file(Fs::rel($algoritmDir . '/algoritm.rules.json', $this->paths->root()));

        Fs::writeAtomic($algoritmDir . '/algoritm.editor.json', "[]\n");
        $r->file(Fs::rel($algoritmDir . '/algoritm.editor.json', $this->paths->root()));

        // 2b. Схема по умолчанию для алиаса
        $schemesDir = $this->paths->domain($domain) . '/.alliasscheme';
        if (!Fs::isDir($schemesDir)) {
            Fs::mkdir($schemesDir);
        }

        $schemeName = $name;
        $schemeEditor = $schemesDir . '/' . $schemeName . '.editor.json';
        $schemePhp = $schemesDir . '/' . $schemeName . '.php';
        $schemeRules = $schemesDir . '/' . $schemeName . '.rules.json';

        if (!is_file($schemePhp)) {
            $defaultScheme = [
                'tag' => 'div',
                'class' => 'layout',
                'children' => [
                    ['tag' => 'main', 'slot' => 'center'],
                ],
            ];
            Fs::writeAtomic($schemePhp, "<?php\n\nreturn " . var_export($defaultScheme, true) . ";\n");
            $r->file(Fs::rel($schemePhp, $this->paths->root()));

            Fs::writeAtomic(
                $schemeEditor,
                json_encode(['scheme' => ['children' => [['type' => 'slot', 'name' => 'center']]]], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n",
            );
            $r->file(Fs::rel($schemeEditor, $this->paths->root()));

            Fs::writeAtomic($schemeRules, json_encode(['enabled' => true], JSON_PRETTY_PRINT) . "\n");
            $r->file(Fs::rel($schemeRules, $this->paths->root()));
        }

        // 3. Конфиг (все ключи)
        $site['alliases_real'][$domain][$name] = $name;
        $site['alliases_available'][$domain][$name] = true;

        Fs::writeAtomic(
            $siteFile,
            json_encode($site, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        );
        $r->file(Fs::rel($siteFile, $this->paths->root()));

        $context->step('Алиас создан', true, $domain . '/' . $name);

        return $r;
    }
}
