<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use RuntimeException;

/**
 * block:new для схемы domains_app: 3 файла блока в .blocksglobal/.
 */
final class BlockNewCommand implements CommandInterface
{
    private const NAME_RE = '/^[a-z][a-z0-9_]*$/';
    private const TYPES = ['script', 'visual', 'mixed'];

    public function __construct(
        private readonly Paths $paths,
        private readonly SiteConfig $site,
    ) {
    }

    public function name(): string
    {
        return 'block:new';
    }

    public function description(): string
    {
        return 'Создать глобальный блок проекта (3 файла)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $name = (string) ($args['name'] ?? '');
        $type = (string) ($args['type'] ?? 'mixed');

        if (preg_match(self::NAME_RE, $name) !== 1) {
            $r->error('Некорректное имя блока');

            return $r;
        }
        if (!in_array($type, self::TYPES, true)) {
            $r->error('Некорректный тип блока (script, visual, mixed)');

            return $r;
        }

        try {
            $project = $this->site->resolve((string) ($args['domain'] ?? ''));
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $blockDir = $this->paths->blocksGlobal($project->folder) . '/' . $name;
        if (Fs::isDir($blockDir)) {
            $r->error('Блок уже существует');

            return $r;
        }

        if ($context->isDryRun()) {
            $context->step(sprintf('Dry-run: блок %s (%s) будет создан', $name, $type));
            $r->data = ['project' => $project->key, 'block' => $name, 'type' => $type, 'dry_run' => true];

            return $r;
        }

        Fs::mkdir($blockDir);
        $r->file(Fs::rel($blockDir, $this->paths->root()));

        $php = $type === 'script'
            ? $this->scriptStub($project->namespace, $name)
            : $this->regionStub($project->namespace, $name);

        Fs::writeAtomic($blockDir . '/' . $name . '.php', $php);
        $r->file(Fs::rel($blockDir . '/' . $name . '.php', $this->paths->root()));

        Fs::writeAtomic(
            $blockDir . '/' . $name . '.rules.json',
            json_encode(['enabled' => true, 'type' => $type], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n",
        );
        $r->file(Fs::rel($blockDir . '/' . $name . '.rules.json', $this->paths->root()));

        Fs::writeAtomic($blockDir . '/' . $name . '.editor.json', "[]\n");
        $r->file(Fs::rel($blockDir . '/' . $name . '.editor.json', $this->paths->root()));

        $r->data = ['project' => $project->key, 'block' => $name, 'type' => $type];

        $context->step(sprintf('Блок создан (%s)', $type), true, $name);

        return $r;
    }

    private function scriptStub(string $namespace, string $name): string
    {
        return "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace {$namespace};\n\n"
            . "final class blocksglobal_{$name}_logic\n"
            . "{\n"
            . "    /** @return array<string, mixed> */\n"
            . "    public function run(): array\n"
            . "    {\n"
            . "        return ['status' => 'ok'];\n"
            . "    }\n"
            . "}\n";
    }

    private function regionStub(string $namespace, string $name): string
    {
        return "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace {$namespace};\n\n"
            . "use Damoony\\UI\\Region\\RegionContext;\n"
            . "use Damoony\\UI\\Region\\RegionInterface;\n"
            . "use Damoony\\UI\\Builder;\n"
            . "use Damoony\\UI\\Node;\n\n"
            . "final class blocksglobal_{$name} implements RegionInterface\n"
            . "{\n"
            . "    public function name(): string\n    {\n        return '{$name}';\n    }\n\n"
            . "    public function enabled(): bool\n    {\n        return true;\n    }\n\n"
            . "    public function render(RegionContext \$context): Node\n    {\n"
            . "        \$wrap = Builder::div('{$name}');\n"
            . "        \$wrap->append(Builder::p()->content('это глобальный блок {$name}'));\n"
            . "        return \$wrap;\n    }\n"
            . "}\n";
    }
}
