<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Files\Fs;
use Damoony\Files\Paths;

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

        $domain = $args['domain'] ?? '';
        $name = $args['name'] ?? '';

        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain)) {
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

        Fs::mkdir($alliasDir);
        $r->file(Fs::rel($alliasDir, $this->paths->root()));

        $algoritmDir = $this->paths->algoritm($domain, $name);
        Fs::mkdir($algoritmDir);

        // 1. Создаём .rules.json
        Fs::writeAtomic($algoritmDir . '/.rules.json', json_encode(['available' => ['']], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
        $r->file(Fs::rel($algoritmDir . '/.rules.json', $this->paths->root()));

        // 2. Создаём algoritm.php
        $namespace = (string) ($site['domains_namespace'][$domain] ?? str_replace(['.', '-'], '_', $domain));
        $php = "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace}\\{$name};\n\nuse Damoony\\Algoritm\\Page;\nuse Damoony\\Ui\\Builder;\nuse Damoony\\Ui\\Node;\nuse Damoony\\Http\\Response;\n\nfinal class algoritm extends Page\n{\n    public function default(): Node|Response|null\n    {\n        return Builder::div('page')->content('Страница {$name}');\n    }\n}\n";
        Fs::writeAtomic($algoritmDir . '/algoritm.php', $php);
        $r->file(Fs::rel($algoritmDir . '/algoritm.php', $this->paths->root()));

        $site['alliases_real'][$domain][$name] = $name;
        Fs::writeAtomic($siteFile, json_encode($site, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        $r->file(Fs::rel($siteFile, $this->paths->root()));

        $context->step('Алиас создан', true, $domain . '/' . $name);

        return $r;
    }
}
