<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Files\Fs;
use Damoony\Files\Paths;

final class BlockNewCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'block:new';
    }

    public function description(): string
    {
        return 'Создать блок alliasglobal (--type=logic|ui|mixed)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = $args['domain'] ?? '';
        $name = $args['name'] ?? '';
        $type = (string)($args['type'] ?? 'mixed');

        if (!in_array($type, ['script', 'visual', 'mixed'], true)) {
            $r->error('Некорректный тип блока (logic, ui, mixed)');
            return $r;
        }

        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain)) {
            $r->error('Некорректный домен');
            return $r;
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            $r->error('Некорректное имя блока');
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

        $blocksDir = $this->paths->domain($domain) . '/.alliasglobal';
        $blockDir = $blocksDir . '/' . $name;

        if (Fs::isDir($blockDir)) {
            $r->error('Блок уже существует');
            return $r;
        }

        Fs::mkdir($blockDir);
        $r->file(Fs::rel($blockDir, $this->paths->root()));

        if ($type === 'logic') {
            $php = "<?php\n\n"
                . "declare(strict_types=1);\n\n"
                . "namespace {$namespace};\n\n"
                . "final class alliasglobal_{$name}_logic\n"
                . "{\n"
                . "    /** @return array<string, mixed> */\n"
                . "    public function run(): array\n"
                . "    {\n"
                . "        return ['status' => 'ok'];\n"
                . "    }\n"
                . "}\n";
        } else {
            $php = "<?php\n\n"
                . "declare(strict_types=1);\n\n"
                . "namespace {$namespace};\n\n"
                . "use Damoony\\Region\\RegionContext;\n"
                . "use Damoony\\Region\\RegionInterface;\n"
                . "use Damoony\\Ui\\Builder;\n"
                . "use Damoony\\Ui\\Node;\n\n"
                . "final class alliasglobal_{$name} implements RegionInterface\n"
                . "{\n"
                . "    public function name(): string\n    {\n        return '{$name}';\n    }\n\n"
                . "    public function enabled(): bool\n    {\n        return true;\n    }\n\n"
                . "    public function render(RegionContext \$context): Node\n    {\n"
                . "        \$wrap = Builder::div('{$name}');\n"
                . "        \$wrap->append(Builder::p()->content('Блок {$name} ({$type})'));\n"
                . "        return \$wrap;\n    }\n"
                . "}\n";
        }
        Fs::writeAtomic($blockDir . '/' . $name . '.php', $php);
        $r->file(Fs::rel($blockDir . '/' . $name . '.php', $this->paths->root()));

        $data = "<?php\n\nreturn [\n    'wrapper' => ['tag' => 'div', 'attrs' => ['class' => '{$name}']],\n    'sections' => [],\n];\n";
        Fs::writeAtomic($blockDir . '/' . $name . '.data.php', $data);
        $r->file(Fs::rel($blockDir . '/' . $name . '.data.php', $this->paths->root()));

        $manifestFile = $blocksDir . '/blocks.json';
        $manifest = is_file($manifestFile)
            ? json_decode((string) file_get_contents($manifestFile), true)
            : [];
        if (!is_array($manifest)) {
            $manifest = [];
        }
        $manifest['blocks'][$name] = ['enabled' => true, 'type' => $type];
        Fs::writeAtomic($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        $r->file(Fs::rel($manifestFile, $this->paths->root()));

        $context->step("Блок создан ({$type})", true, $name);

        return $r;
    }
}
