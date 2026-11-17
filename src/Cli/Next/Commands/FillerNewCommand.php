<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Next\Support\TargetResolver;
use Damoony\Cli\Result;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use RuntimeException;

/**
 * filler:new для схемы domains_app: локальный блок алиаса (.blockslocal/).
 *
 * Конфиг не трогаем: состояние — только .rules.json.
 */
final class FillerNewCommand implements CommandInterface
{
    private const NAME_RE = '/^[a-z][a-z0-9_]*$/';

    public function __construct(
        private readonly Paths $paths,
        private readonly TargetResolver $resolver,
    ) {
    }

    public function name(): string
    {
        return 'filler:new';
    }

    public function description(): string
    {
        return 'Создать филлер (локальный блок) для алиаса';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $block = (string) ($args['block'] ?? '');
        if (preg_match(self::NAME_RE, $block) !== 1) {
            $r->error('Некорректный блок');

            return $r;
        }

        try {
            $target = $this->resolver->resolve(
                (string) ($args['domain'] ?? ''),
                (string) ($args['allias'] ?? ''),
            );
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $alliasDir = $this->paths->allias($target->folder(), $target->aliasName());
        if (!Fs::isDir($alliasDir)) {
            $r->error('Алиас не найден на диске: ' . $target->projectKey() . '/' . $target->aliasName());

            return $r;
        }

        $globalBlockDir = $this->paths->blocksGlobal($target->folder()) . '/' . $block;
        if (!Fs::isDir($globalBlockDir)) {
            $r->error('Блок не найден: ' . $block);

            return $r;
        }

        $blockDir = $this->paths->blocksLocal($target->folder(), $target->aliasName()) . '/' . $block;
        $phpFile = $blockDir . '/' . $block . '.php';
        $rulesFile = $blockDir . '/' . $block . '.rules.json';

        if (Fs::isFile($phpFile) || Fs::isFile($rulesFile)) {
            $r->error('Filler уже существует');

            return $r;
        }

        if ($context->isDryRun()) {
            $context->step(sprintf('Dry-run: филлер %s для %s/%s будет создан', $block, $target->projectKey(), $target->aliasName()));
            $r->data = [
                'project' => $target->projectKey(),
                'allias' => $target->aliasName(),
                'block' => $block,
                'dry_run' => true,
            ];

            return $r;
        }

        Fs::mkdir($blockDir);

        $className = 'blockslocal_' . $target->aliasName() . '_' . $block;

        Fs::writeAtomic($phpFile, $this->stub($target->project->namespace, $className, $block, $target->aliasName()));
        $r->file(Fs::rel($phpFile, $this->paths->root()));

        Fs::writeAtomic($rulesFile, json_encode(['enabled' => true, 'type' => 'mixed'], JSON_PRETTY_PRINT) . "\n");
        $r->file(Fs::rel($rulesFile, $this->paths->root()));

        Fs::writeAtomic($blockDir . '/' . $block . '.editor.json', "[]\n");
        $r->file(Fs::rel($blockDir . '/' . $block . '.editor.json', $this->paths->root()));

        $r->data = ['project' => $target->projectKey(), 'allias' => $target->aliasName(), 'block' => $block];

        $context->step('Filler создан', true, $className);

        return $r;
    }

    private function stub(string $namespace, string $className, string $block, string $allias): string
    {
        return "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace {$namespace};\n\n"
            . "use Damoony\\UI\\Region\\RegionContext;\n"
            . "use Damoony\\UI\\Region\\RegionInterface;\n"
            . "use Damoony\\UI\\Builder;\n"
            . "use Damoony\\UI\\Node;\n\n"
            . "final class {$className} implements RegionInterface\n"
            . "{\n"
            . "    public function name(): string\n    {\n        return '{$block}';\n    }\n\n"
            . "    public function enabled(): bool\n    {\n        return true;\n    }\n\n"
            . "    public function render(RegionContext \$context): Node\n    {\n"
            . "        \$wrap = Builder::div('{$block}-{$allias}');\n"
            . "        \$wrap->append(Builder::p()->content('это локальный блок {$block}'));\n"
            . "        return \$wrap;\n    }\n"
            . "}\n";
    }
}
