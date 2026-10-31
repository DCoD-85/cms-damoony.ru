<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Files\Fs;
use Damoony\Files\Paths;

final class PageNewCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'page:new';
    }

    public function description(): string
    {
        return 'Создать страницу алиаса';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = $args['domain'] ?? '';
        $allias = $args['allias'] ?? '';
        $name = $args['name'] ?? '';
        $parent = $args['parent'] ?? '';

        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain)) {
            $r->error('Некорректный домен');
            return $r;
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $allias) || !preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            $r->error('Некорректные allias/name');
            return $r;
        }
        if ($parent !== '' && !preg_match('#^[a-z][a-z0-9_]*(/[a-z][a-z0-9_]*)*$#i', $parent)) {
            $r->error('Некорректный родительский путь');
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

        $realDomain = (string) $site['domains_real'][$domain];
        $namespace = (string) ($site['domains_namespace'][$realDomain]
            ?? str_replace(['.', '-'], '_', $realDomain));

        $algoritmDir = $this->paths->algoritm($domain, $allias);
        if (!Fs::isDir($algoritmDir)) {
            $r->error('Алиас не найден');
            return $r;
        }

        $parentDir = $parent === '' ? $algoritmDir : $algoritmDir . '/' . $parent;
        if (!Fs::isDir($parentDir)) {
            $r->error('Родительский каталог не найден');
            return $r;
        }

        $pageDir = $parentDir . '/' . $name;
        $classChain = 'algoritm' . ($parent === '' ? '' : '_' . str_replace('/', '_', $parent)) . '_' . $name;

        if (Fs::isDir($pageDir)) {
            $r->error('Страница уже существует');
            return $r;
        }

        Fs::mkdir($pageDir);
        $r->file(Fs::rel($pageDir, $this->paths->root()));

        $php = "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace {$namespace}\\{$allias};\n\n"
            . "use Damoony\\Algoritm\\Page;\n"
            . "use Damoony\\Http\\Response;\n"
            . "use Damoony\\Ui\\Builder;\n"
            . "use Damoony\\Ui\\Node;\n\n"
            . "final class {$classChain} extends Page\n"
            . "{\n"
            . "    public function default(): Node|Response|null\n"
            . "    {\n"
            . "        return Builder::div('page {$name}')->content('{$name}');\n"
            . "    }\n"
            . "}\n";
        Fs::writeAtomic($pageDir . '/' . $classChain . '.php', $php);
        $r->file(Fs::rel($pageDir . '/' . $classChain . '.php', $this->paths->root()));

        Fs::writeAtomic($pageDir . '/.rules.json', json_encode(['available' => [$name]], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
        $r->file(Fs::rel($pageDir . '/.rules.json', $this->paths->root()));

        $parentRules = $parentDir . '/.rules.json';
        if (!Fs::isFile($parentRules)) {
            $r->warn('Родительский .rules.json не найден — не патчим');
        } else {
            $decoded = json_decode((string) file_get_contents($parentRules), true);
            if (!is_array($decoded)) {
                $r->error('Родительский .rules.json битый');
                return $r;
            }
            $available = is_array($decoded['available'] ?? null) ? $decoded['available'] : [];
            if (!in_array($name, $available, true)) {
                $available[] = $name;
            }
            $decoded['available'] = $available;
            Fs::writeAtomic($parentRules, json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
            $r->file(Fs::rel($parentRules, $this->paths->root()));
        }

        $context->step('Страница создана', true, $classChain);

        return $r;
    }
}
