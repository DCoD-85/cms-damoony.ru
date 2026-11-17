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
 * scheme:copy для схемы domains_app.
 */
final class SchemeCopyCommand implements CommandInterface
{
    private const NAME_RE = '/^[a-z][a-z0-9_]*$/';

    public function __construct(
        private readonly Paths $paths,
        private readonly SiteConfig $site,
    ) {
    }

    public function name(): string
    {
        return 'scheme:copy';
    }

    public function description(): string
    {
        return 'Копировать схему проекта (3 файла)';
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
        $srcPhp = $schemesDir . '/' . $name . '.php';

        if (!is_file($srcPhp)) {
            $r->error('Схема не найдена: ' . $name);

            return $r;
        }

        $newName = $name . '_copy';
        $i = 1;
        while (is_file($schemesDir . '/' . $newName . '.php')) {
            $newName = $name . '_copy_' . $i++;
        }

        if ($context->isDryRun()) {
            $context->step(sprintf('Dry-run: схема %s будет скопирована в %s', $name, $newName));
            $r->data = ['project' => $project->key, 'from' => $name, 'to' => $newName, 'dry_run' => true];

            return $r;
        }

        $dstPhp = $schemesDir . '/' . $newName . '.php';
        copy($srcPhp, $dstPhp);
        $r->file(Fs::rel($dstPhp, $this->paths->root()));

        $srcRules = $schemesDir . '/' . $name . '.rules.json';
        $dstRules = $schemesDir . '/' . $newName . '.rules.json';
        if (is_file($srcRules)) {
            copy($srcRules, $dstRules);
        } else {
            Fs::writeAtomic($dstRules, json_encode(['enabled' => true], JSON_PRETTY_PRINT) . "\n");
        }
        $r->file(Fs::rel($dstRules, $this->paths->root()));

        $srcEditor = $schemesDir . '/' . $name . '.editor.json';
        $dstEditor = $schemesDir . '/' . $newName . '.editor.json';
        if (is_file($srcEditor)) {
            copy($srcEditor, $dstEditor);
            $r->file(Fs::rel($dstEditor, $this->paths->root()));
        } else {
            $r->warn('Исходная схема без .editor.json — копия создана без UI-дерева');
        }

        $r->data = ['project' => $project->key, 'from' => $name, 'to' => $newName];
        $context->step('Схема скопирована', true, $name . ' → ' . $newName);

        return $r;
    }
}
