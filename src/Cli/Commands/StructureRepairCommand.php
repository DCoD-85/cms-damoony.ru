<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\UI\Page\PageCompiler;
use Damoony\UI\Page\PageRules;
use RuntimeException;

/**
 * structure:repair — восстановить отсутствующие файлы по правилу 3 файлов.
 *
 * Не «лечит» содержимое: только досоздаёт то, чего нет (root-страницы алиасов,
 * правила схем, правила/editor блоков).
 */
final class StructureRepairCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
        private readonly SiteConfig $site,
    ) {
    }

    public function name(): string
    {
        return 'structure:repair';
    }

    public function description(): string
    {
        return 'Восстановить отсутствующие файлы структуры';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        try {
            $project = $this->site->resolve((string) ($args['domain'] ?? ''));
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $dry = $context->isDryRun();
        $fixed = 0;

        $aliases = $project->aliasNames();
        if ($aliases === []) {
            $aliases = [$project->defaultAlias];
        }

        foreach ($aliases as $alias) {
            $algoritmDir = $this->paths->algoritm($project->folder, $alias);
            if (!Fs::isDir($algoritmDir)) {
                if ($dry) {
                    $context->step('[dry] создать папку ' . Fs::rel($algoritmDir, $this->paths->root()));
                    $fixed++;

                    continue;
                }
                Fs::mkdir($algoritmDir);
            }

            $phpFile = $algoritmDir . '/algoritm.php';
            if (!is_file($phpFile)) {
                $fixed++;
                if ($dry) {
                    $context->step('[dry] восстановить algoritm.php для ' . $alias);
                } else {
                    $namespace = $project->namespace . '\\' . $alias;
                    Fs::writeAtomic($phpFile, new PageCompiler()->compile($namespace, 'algoritm', []));
                    $r->file(Fs::rel($phpFile, $this->paths->root()));
                    $context->step('Восстановлен algoritm.php для алиаса: ' . $alias);
                }
            }

            $rulesFile = $algoritmDir . '/algoritm.rules.json';
            if (!is_file($rulesFile)) {
                $fixed++;
                if ($dry) {
                    $context->step('[dry] восстановить algoritm.rules.json для ' . $alias);
                } else {
                    PageRules::save($rulesFile, PageRules::defaults());
                    $r->file(Fs::rel($rulesFile, $this->paths->root()));
                    $context->step('Восстановлен algoritm.rules.json для алиаса: ' . $alias);
                }
            }

            $editorFile = $algoritmDir . '/algoritm.editor.json';
            if (!is_file($editorFile)) {
                $fixed++;
                if ($dry) {
                    $context->step('[dry] восстановить algoritm.editor.json для ' . $alias);
                } else {
                    Fs::writeAtomic($editorFile, "[]\n");
                    $r->file(Fs::rel($editorFile, $this->paths->root()));
                    $context->step('Восстановлен algoritm.editor.json для алиаса: ' . $alias);
                }
            }
        }

        $fixed += $this->repairSchemes($project->folder, $dry, $r, $context);
        $fixed += $this->repairBlocks($project->folder, $dry, $r, $context);

        $r->data = ['project' => $project->key, 'fixed' => $fixed, 'dry_run' => $dry];

        $context->step(
            $fixed === 0
                ? 'Структура в порядке, исправлений не требуется'
                : sprintf('Ремонт завершён. Исправлено элементов: %d%s', $fixed, $dry ? ' (dry-run)' : ''),
            true,
        );

        return $r;
    }

    private function repairSchemes(string $folder, bool $dry, Result $r, Context $context): int
    {
        $schemesDir = $this->paths->schemesGlobal($folder);
        if (!Fs::isDir($schemesDir)) {
            return 0;
        }

        $fixed = 0;
        foreach (glob($schemesDir . '/*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            $rulesFile = $schemesDir . '/' . $name . '.rules.json';
            if (is_file($rulesFile)) {
                continue;
            }

            $fixed++;
            if ($dry) {
                $context->step('[dry] восстановить ' . $name . '.rules.json для схемы');

                continue;
            }

            Fs::writeAtomic($rulesFile, json_encode(['enabled' => true], JSON_PRETTY_PRINT) . "\n");
            $r->file(Fs::rel($rulesFile, $this->paths->root()));
            $context->step('Восстановлен ' . $name . '.rules.json для схемы');
        }

        return $fixed;
    }

    private function repairBlocks(string $folder, bool $dry, Result $r, Context $context): int
    {
        $blocksDir = $this->paths->blocksGlobal($folder);
        if (!Fs::isDir($blocksDir)) {
            return 0;
        }

        $fixed = 0;
        foreach (scandir($blocksDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || !Fs::isDir($blocksDir . '/' . $entry)) {
                continue;
            }

            $rulesFile = $blocksDir . '/' . $entry . '/' . $entry . '.rules.json';
            if (!is_file($rulesFile)) {
                $fixed++;
                if ($dry) {
                    $context->step('[dry] восстановить ' . $entry . '.rules.json');
                } else {
                    Fs::writeAtomic(
                        $rulesFile,
                        json_encode(['enabled' => true, 'type' => 'mixed'], JSON_PRETTY_PRINT) . "\n",
                    );
                    $r->file(Fs::rel($rulesFile, $this->paths->root()));
                    $context->step('Восстановлен ' . $entry . '.rules.json');
                }
            }

            $editorFile = $blocksDir . '/' . $entry . '/' . $entry . '.editor.json';
            if (!is_file($editorFile)) {
                $fixed++;
                if ($dry) {
                    $context->step('[dry] восстановить ' . $entry . '.editor.json');
                } else {
                    Fs::writeAtomic($editorFile, "[]\n");
                    $r->file(Fs::rel($editorFile, $this->paths->root()));
                    $context->step('Восстановлен ' . $entry . '.editor.json');
                }
            }
        }

        return $fixed;
    }
}
