<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use RuntimeException;

/**
 * scheme:new для схемы domains_app.
 */
final class SchemeNewCommand implements CommandInterface
{
    private const NAME_RE = '/^[a-z][a-z0-9_]*$/';

    public function __construct(
        private readonly Paths $paths,
        private readonly SiteConfig $site,
    ) {
    }

    public function name(): string
    {
        return 'scheme:new';
    }

    public function description(): string
    {
        return 'Создать схему проекта (.schemesglobal/)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $name = (string) ($args['name'] ?? '');
        if (preg_match(self::NAME_RE, $name) !== 1) {
            $r->error('Некорректное имя схемы');

            return $r;
        }

        try {
            $project = $this->site->resolve((string) ($args['domain'] ?? ''));
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $schemesDir = $this->paths->schemesGlobal($project->folder);
        $phpFile = $schemesDir . '/' . $name . '.php';
        $rulesFile = $schemesDir . '/' . $name . '.rules.json';
        $editorFile = $schemesDir . '/' . $name . '.editor.json';

        if (is_file($phpFile) || is_file($editorFile)) {
            $r->error('Схема уже существует');

            return $r;
        }

        if ($context->isDryRun()) {
            $context->step(sprintf('Dry-run: схема %s будет создана', $name));
            $r->data = ['project' => $project->key, 'scheme' => $name, 'dry_run' => true];

            return $r;
        }

        Fs::mkdir($schemesDir);

        $meta = [
            'css' => ['/media/shared/css/main.css'],
            'js' => ['/media/shared/scripts/cliajax.js'],
        ];

        $editor = [
            'fields' => $this->blockFields($project->folder),
            'scheme' => ['children' => [], 'scripts' => [], 'meta' => $meta],
        ];

        Fs::writeAtomic($editorFile, json_encode($editor, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
        $r->file(Fs::rel($editorFile, $this->paths->root()));

        $php = "<?php\n\nreturn " . var_export([
            'tag' => 'div',
            'class' => 'layout',
            'children' => [],
            'scripts' => [],
            'meta' => $meta,
        ], true) . ";\n";
        Fs::writeAtomic($phpFile, $php);
        $r->file(Fs::rel($phpFile, $this->paths->root()));

        Fs::writeAtomic($rulesFile, json_encode(['enabled' => true], JSON_PRETTY_PRINT) . "\n");
        $r->file(Fs::rel($rulesFile, $this->paths->root()));

        $r->data = [
            'project' => $project->key,
            'scheme' => $name,
            'files' => [
                Fs::rel($phpFile, $this->paths->root()),
                Fs::rel($rulesFile, $this->paths->root()),
                Fs::rel($editorFile, $this->paths->root()),
            ],
        ];

        $context->step('Схема создана', true, $name);

        return $r;
    }

    /**
     * Список доступных глобальных блоков проекта (для editor.json).
     *
     * @return list<array{name: string, type: string}>
     */
    private function blockFields(string $folder): array
    {
        $blocksDir = $this->paths->blocksGlobal($folder);
        if (!is_dir($blocksDir)) {
            return [];
        }

        $fields = [];
        foreach (scandir($blocksDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || !is_dir($blocksDir . '/' . $entry)) {
                continue;
            }

            $rulesFile = $blocksDir . '/' . $entry . '/' . $entry . '.rules.json';
            $rules = is_file($rulesFile) ? json_decode((string) file_get_contents($rulesFile), true) : [];
            $type = is_array($rules) && is_string($rules['type'] ?? null) ? $rules['type'] : 'mixed';

            $fields[] = ['name' => $entry, 'type' => $type];
        }

        usort($fields, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $fields;
    }
}
