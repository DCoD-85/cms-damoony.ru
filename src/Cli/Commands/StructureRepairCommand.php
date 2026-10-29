<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Files\Fs;
use Damoony\Files\Paths;

final class StructureRepairCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'structure:repair';
    }
    public function description(): string
    {
        return 'Восстановить структуру доменов, алиасов и схем';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();
        $domain = $args['domain'] ?? '';

        if ($domain === '') {
            $r->error('Укажите --domain=example.ru');
            return $r;
        }

        $siteFile = $this->paths->siteConfig();
        if (!is_file($siteFile)) {
            $r->error('site.config не найден');
            return $r;
        }
        $site = Config::fromFile($siteFile)->all();
        if (!isset($site['domains_real'][$domain])) {
            $r->error('Домен не найден в конфиге');
            return $r;
        }

        $domainDir = $this->paths->domain($domain);
        $fixed = 0;

        // 1. Восстановление алиасов
        $alliases = $site['alliases_real'][$domain] ?? ['www' => 'www'];
        foreach ($alliases as $alias => $real) {
            $algoritmDir = $domainDir . '/' . $alias . '/algoritm';
            if (!is_dir($algoritmDir)) {
                Fs::mkdir($algoritmDir);
                $rules = ['available' => []];
                Fs::writeAtomic($algoritmDir . '/.rules.json', json_encode($rules, JSON_PRETTY_PRINT) . "\n");

                $php = "<?php\n\ndeclare(strict_types=1);\n\nnamespace " . $site['domains_namespace'][$domain] . "\\{$alias};\n\nuse Damoony\\Algoritm\\Algoritm;\nuse Damoony\\Ui\\Builder;\nuse Damoony\\Ui\\Node;\nuse Damoony\\Http\\Response;\n\nfinal class algoritm extends Algoritm\n{\n    public function default(): Node|Response|null\n    {\n        return Builder::div('page')->content('Главная страница');\n    }\n}\n";
                Fs::writeAtomic($algoritmDir . '/algoritm.php', $php);
                $context->step("Восстановлен алиас: {$alias}");
                $fixed++;
            }
        }

        // 2. Восстановление schemes.json
        $schemesDir = $domainDir . '/.alliasscheme';
        if (!is_dir($schemesDir)) {
            Fs::mkdir($schemesDir);
        }

        $schemesFile = $schemesDir . '/schemes.json';
        $schemes = is_file($schemesFile) ? json_decode((string)file_get_contents($schemesFile), true) : [];
        if (!is_array($schemes)) {
            $schemes = ['schemes' => []];
        }

        $files = glob($schemesDir . '/*.data.php') ?: [];
        foreach ($files as $file) {
            $name = basename($file, '.data.php');
            if (!isset($schemes['schemes'][$name])) {
                $schemes['schemes'][$name] = ['enabled' => true];
                $context->step("Восстановлена запись схемы в schemes.json: {$name}");
                $fixed++;
            }
        }
        Fs::writeAtomic($schemesFile, json_encode($schemes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

        // 3. Восстановление blocks.json
        $blocksDir = $domainDir . '/.alliasglobal';
        if (!is_dir($blocksDir)) {
            Fs::mkdir($blocksDir);
        }

        $blocksFile = $blocksDir . '/blocks.json';
        $blocksData = is_file($blocksFile) ? json_decode((string)file_get_contents($blocksFile), true) : [];
        if (!is_array($blocksData) || !isset($blocksData['blocks'])) {
            $blocksData = ['blocks' => []];
        }

        $dirs = array_filter(scandir($blocksDir) ?: [], fn ($d) => $d !== '.' && $d !== '..' && is_dir($blocksDir . '/' . $d));
        foreach ($dirs as $bName) {
            if (!isset($blocksData['blocks'][$bName])) {
                $blocksData['blocks'][$bName] = ['enabled' => true, 'type' => 'mixed'];
                $context->step("Восстановлена запись блока в blocks.json: {$bName}");
                $fixed++;
            }
        }
        Fs::writeAtomic($blocksFile, json_encode($blocksData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

        if ($fixed === 0) {
            $context->step('Структура в порядке, исправлений не требуется.');
        } else {
            $context->step("Ремонт завершен. Исправлено элементов: {$fixed}", true);
        }

        return $r;
    }
}
