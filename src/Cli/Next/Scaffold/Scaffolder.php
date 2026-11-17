<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Scaffold;

use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;

/**
 * Генерация файлового скелета проекта/алиаса для нового CLI-стека.
 *
 * Класс не знает про site.config: ему передают folder (папка в app/) и
 * namespace (PHP-namespace домена). Запись конфига — забота команд.
 */
final class Scaffolder
{
    private const SLOTS = ['top', 'left', 'center', 'right', 'footer', 'bottom', 'policy', 'toast'];

    public function __construct(private readonly Paths $paths)
    {
    }

    /**
     * @return list<string> абсолютные пути созданных файлов/папок
     */
    public function domain(string $folder, string $namespace, string $alias = 'www', string $profile = 'minimal'): array
    {
        $created = [];

        $domainDir = $this->paths->domain($folder);
        Fs::mkdir($domainDir);
        $created[] = $domainDir;

        foreach ($this->alias($folder, $namespace, $alias, $profile) as $path) {
            $created[] = $path;
        }

        if ($profile !== 'clean') {
            foreach ($this->globalScheme($folder, $alias) as $path) {
                $created[] = $path;
            }
            foreach ($this->globalCenterBlock($folder, $namespace) as $path) {
                $created[] = $path;
            }
            foreach ($this->skin($folder, $alias, 'default') as $path) {
                $created[] = $path;
            }
        }

        return $created;
    }

    /**
     * @return list<string>
     */
    public function alias(string $folder, string $namespace, string $alias, string $profile = 'minimal'): array
    {
        $created = [];

        $alliasDir = $this->paths->allias($folder, $alias);
        Fs::mkdir($alliasDir);
        $created[] = $alliasDir;

        $algoritmDir = $this->paths->algoritm($folder, $alias);
        Fs::mkdir($algoritmDir);

        $created[] = $this->write($algoritmDir . '/algoritm.php', $this->rootPage($namespace, $alias));
        $created[] = $this->write($algoritmDir . '/algoritm.rules.json', $this->rootRules($profile));
        $created[] = $this->write($algoritmDir . '/algoritm.editor.json', "[]\n");

        $langDir = $algoritmDir . '/lang';
        Fs::mkdir($langDir);
        $created[] = $this->write($langDir . '/algoritm_lang.php', $this->langPage($namespace, $alias));
        $created[] = $this->write(
            $langDir . '/algoritm_lang.rules.json',
            json_encode(['available' => ['' => '^$', 'tests' => '^tests$'], 'auth' => false], JSON_PRETTY_PRINT) . "\n",
        );
        $created[] = $this->write($langDir . '/algoritm_lang.editor.json', "[]\n");

        if ($profile !== 'clean') {
            foreach ($this->aliasScheme($folder, $alias) as $path) {
                $created[] = $path;
            }
        }

        return $created;
    }

    /**
     * @return list<string>
     */
    public function skin(string $folder, string $alias, string $name = 'default'): array
    {
        $skinDir = $this->paths->media($folder, $alias) . '/skin/' . $name;

        if (Fs::isDir($skinDir)) {
            return [];
        }

        Fs::mkdir($skinDir . '/css');
        Fs::mkdir($skinDir . '/js');
        Fs::mkdir($skinDir . '/img');

        return [
            $this->write($skinDir . '/css/main.css', "/* Skin: {$name} */\n"),
            $this->write($skinDir . '/js/main.js', "// Skin: {$name}\n"),
            $this->write(
                $skinDir . '/skin.json',
                json_encode(
                    ['name' => $name, 'version' => '1.0', 'created_at' => gmdate('Y-m-d\TH:i:s\Z')],
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
                ) . "\n",
            ),
        ];
    }

    /**
     * @return list<string>
     */
    private function globalScheme(string $folder, string $alias): array
    {
        $dir = $this->paths->schemesGlobal($folder);
        Fs::mkdir($dir);

        $scheme = $this->schemeArray($folder, $alias);

        return [
            $this->write($dir . '/www.php', "<?php\n\nreturn " . var_export($scheme, true) . ";\n"),
            $this->write($dir . '/www.rules.json', json_encode(['enabled' => true], JSON_PRETTY_PRINT) . "\n"),
        ];
    }

    /**
     * @return list<string>
     */
    private function aliasScheme(string $folder, string $alias): array
    {
        $dir = $this->paths->schemesGlobal($folder);
        Fs::mkdir($dir);

        if (is_file($dir . '/' . $alias . '.php')) {
            return [];
        }

        $scheme = $this->schemeArray($folder, $alias);

        return [
            $this->write($dir . '/' . $alias . '.php', "<?php\n\nreturn " . var_export($scheme, true) . ";\n"),
            $this->write(
                $dir . '/' . $alias . '.editor.json',
                json_encode(['scheme' => ['children' => [['type' => 'slot', 'name' => 'center']]]], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n",
            ),
            $this->write($dir . '/' . $alias . '.rules.json', json_encode(['enabled' => true], JSON_PRETTY_PRINT) . "\n"),
        ];
    }

    /**
     * @return list<string>
     */
    private function globalCenterBlock(string $folder, string $namespace): array
    {
        $dir = $this->paths->blocksGlobal($folder) . '/center';
        Fs::mkdir($dir);

        $php = "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace};\n\n"
            . "use Damoony\\UI\\Region\\RegionContext;\n"
            . "use Damoony\\UI\\Region\\RegionInterface;\n"
            . "use Damoony\\UI\\Builder;\n"
            . "use Damoony\\UI\\Node;\n\n"
            . "final class blocksglobal_center implements RegionInterface\n"
            . "{\n"
            . "    public function name(): string { return 'center'; }\n"
            . "    public function enabled(): bool { return true; }\n\n"
            . "    public function render(RegionContext \$context): Node\n"
            . "    {\n"
            . "        \$wrap = Builder::div('center');\n"
            . "        \$wrap->append(Builder::p()->content('это глобальный блок center'));\n"
            . "        return \$wrap;\n"
            . "    }\n"
            . "}\n";

        return [
            $this->write($dir . '/center.php', $php),
            $this->write($dir . '/center.rules.json', json_encode(['enabled' => true, 'type' => 'mixed'], JSON_PRETTY_PRINT) . "\n"),
            $this->write($dir . '/center.editor.json', "[]\n"),
        ];
    }

    /**
     * @return array{tag: string, class: string, children: list<array{tag: string, slot: string}>, meta: array{css: list<string>, js: list<string>}}
     */
    private function schemeArray(string $folder, string $alias): array
    {
        $children = [];
        foreach (self::SLOTS as $slot) {
            $tag = match ($slot) {
                'top' => 'header',
                'left', 'right' => 'aside',
                'center' => 'main',
                'footer' => 'footer',
                'bottom' => 'section',
                default => 'div',
            };
            $children[] = ['tag' => $tag, 'slot' => $slot];
        }

        return [
            'tag' => 'div',
            'class' => 'layout',
            'children' => $children,
            'meta' => [
                'css' => [
                    '/media/' . $folder . '/' . $alias . '/css/main.css',
                    '/media/' . $folder . '/' . $alias . '/skin/{skin}/css/main.css',
                ],
                'js' => [
                    '/media/' . $folder . '/' . $alias . '/skin/{skin}/js/main.js',
                    '/media/shared/scripts/cliajax.js',
                ],
            ],
        ];
    }

    private function rootPage(string $namespace, string $alias): string
    {
        return "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace {$namespace}\\{$alias};\n\n"
            . "use Damoony\\Core\\Http\\Response;\n"
            . "use Damoony\\UI\\Algoritm\\Page;\n"
            . "use Damoony\\UI\\Node;\n\n"
            . "final class algoritm extends Page\n"
            . "{\n"
            . "    protected function content(): Node|Response|null\n"
            . "    {\n"
            . "        \$lang = \$this->args['lang'] ?? '';\n"
            . "        if (\$lang === '') {\n"
            . "            return Response::redirect('/ru/');\n"
            . "        }\n\n"
            . "        return null;\n"
            . "    }\n"
            . "}\n";
    }

    private function langPage(string $namespace, string $alias): string
    {
        return "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace {$namespace}\\{$alias};\n\n"
            . "use Damoony\\UI\\Algoritm\\Page;\n"
            . "use Damoony\\UI\\Node;\n\n"
            . "final class algoritm_lang extends Page\n"
            . "{\n"
            . "    protected function content(): Node|null\n"
            . "    {\n"
            . "        return null;\n"
            . "    }\n"
            . "}\n";
    }

    private function rootRules(string $profile): string
    {
        $rules = [
            'available' => ['' => '^$', 'lang' => '^([a-z]{2})$'],
            'auth' => false,
            'search' => true,
            'default' => false,
        ];
        if ($profile === 'full') {
            $rules['available']['testscheme'] = '^testscheme$';
        }

        return json_encode($rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }

    private function write(string $path, string $content): string
    {
        Fs::writeAtomic($path, $content);

        return $path;
    }
}
