<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Assembly\AssemblyManifest;
use Damoony\Core\Files\Paths;
use Throwable;

/**
 * lang:check — полнота переводов сценария относительно base_lang.
 */
final class LangCheckCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'lang:check';
    }

    public function description(): string
    {
        return 'Проверить переводы сценариев относительно base_lang (--assembly=, --strict)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();
        $only = trim((string) ($args['assembly'] ?? ''));
        $strict = isset($args['strict']) && (string) $args['strict'] !== '0';

        $baseDir = $this->paths->root() . '/stubs/assemblies';
        $dirs = is_dir($baseDir) ? (scandir($baseDir) ?: []) : [];
        $checked = 0;
        $missingTotal = 0;

        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..' || ($only !== '' && $dir !== $only)) {
                continue;
            }
            if (!is_file($baseDir . '/' . $dir . '/assembly.json')) {
                continue;
            }

            try {
                $manifest = AssemblyManifest::fromDirectory($this->paths, $dir);
            } catch (Throwable $e) {
                $r->error($dir . ': ' . $e->getMessage());
                continue;
            }

            $base = $manifest->baseLang();
            $baseFile = $baseDir . '/' . $dir . '/app/.i18nglobal/' . $base . '.json';
            if (!is_file($baseFile)) {
                $r->error($dir . ': нет базового перевода app/.i18nglobal/' . $base . '.json');
                continue;
            }

            $baseKeys = array_keys($this->keys($baseFile));
            $checked++;

            foreach ($manifest->aliases() as $alias) {
                foreach ($manifest->aliasLangs($alias) as $lang) {
                    if ($lang === $base) {
                        continue;
                    }

                    $file = $baseDir . '/' . $dir . '/app/.i18nglobal/' . $lang . '.json';
                    if (!is_file($file)) {
                        $missingTotal++;
                        $r->warn($dir . '/' . $alias . ': нет перевода ' . $lang . '.json');
                        continue;
                    }

                    $keys = $this->keys($file);
                    $missing = array_values(array_diff($baseKeys, array_keys($keys)));
                    if ($missing !== []) {
                        $missingTotal += count($missing);
                        $preview = implode(', ', array_slice($missing, 0, 5));
                        $r->warn(sprintf(
                            '%s/%s/%s: нет %d ключей (%s%s)',
                            $dir,
                            $alias,
                            $lang,
                            count($missing),
                            $preview,
                            count($missing) > 5 ? ', …' : '',
                        ));
                    }
                }
            }
        }

        if ($checked === 0) {
            $r->error('Сценарии не найдены');

            return $r;
        }

        $r->data = ['assemblies' => $checked, 'missing' => $missingTotal, 'strict' => $strict];

        if ($missingTotal > 0 && $strict) {
            $r->error('Неполные переводы: ' . $missingTotal);
        } else {
            $context->step(
                'Проверено сценариев: ' . $checked . ', проблем: ' . $missingTotal,
                true,
                'lang',
            );
        }

        return $r;
    }

    /** @return array<string, true> */
    private function keys(string $file): array
    {
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) {
            return [];
        }

        $keys = [];
        foreach (array_keys($data) as $key) {
            $keys[(string) $key] = true;
        }

        return $keys;
    }
}
