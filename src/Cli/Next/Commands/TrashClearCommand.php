<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Next\Support\TrashScanner;
use Damoony\Cli\Result;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;

/**
 * trash:clear для схемы domains_app: очистка всех видов карантина.
 *
 * По умолчанию — dry-run. Удаление только с --force. symlink-safe Fs::rmrf.
 */
final class TrashClearCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
        private readonly TrashScanner $scanner,
    ) {
    }

    public function name(): string
    {
        return 'trash:clear';
    }

    public function description(): string
    {
        return 'Очистить карантин (dry-run без --force)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $force = isset($args['force']);
        $domainFilter = (string) ($args['domain'] ?? '');

        $olderSeconds = 0;
        $olderSpec = (string) ($args['older'] ?? '');
        if ($olderSpec !== '') {
            $olderSeconds = $this->parseAge($olderSpec);
            if ($olderSeconds < 0) {
                $r->error('--older ожидает формат: 7d, 24h, 30m');

                return $r;
            }
        }

        $now = time();
        $items = [];

        foreach ($this->scanner->find($domainFilter !== '' ? $domainFilter : null) as $entry) {
            $age = $now - $entry['ts'];
            if ($olderSeconds > 0 && $age < $olderSeconds) {
                continue;
            }
            $items[] = $entry + ['age' => $age];
        }

        if ($items === []) {
            $r->data = ['force' => $force, 'found' => 0, 'items' => []];
            $context->step('Карантин пуст', true);

            return $r;
        }

        usort($items, static fn (array $a, array $b): int => $b['age'] <=> $a['age']);

        foreach ($items as $item) {
            $rel = Fs::rel($item['path'], $this->paths->root());
            $ageStr = $this->fmtAge($item['age']);

            if (!$force) {
                $context->step(sprintf('[dry] %s (%s, %s)', $rel, $item['kind'], $ageStr));

                continue;
            }

            Fs::rmrf($item['path']);
            $r->file($rel);
            $context->step(sprintf('Удалено: %s (%s, %s)', $rel, $item['kind'], $ageStr), true);
        }

        if (!$force) {
            $context->step(sprintf('Найдено %d. Запусти с --force, чтобы удалить', count($items)));
        } else {
            $context->step(sprintf('Удалено: %d', count($items)), true);
        }

        $r->data = [
            'force' => $force,
            'found' => count($items),
            'items' => array_map(
                fn (array $item): array => [
                    'path' => Fs::rel($item['path'], $this->paths->root()),
                    'kind' => $item['kind'],
                    'age_seconds' => $item['age'],
                ],
                $items,
            ),
        ];

        return $r;
    }

    private function parseAge(string $spec): int
    {
        if (preg_match('/^(\d+)([dhm])$/', $spec, $m) !== 1) {
            return -1;
        }

        $n = (int) $m[1];
        if ($n <= 0) {
            return -1;
        }

        return match ($m[2]) {
            'd' => $n * 86400,
            'h' => $n * 3600,
            'm' => $n * 60,
        };
    }

    private function fmtAge(int $seconds): string
    {
        $d = intdiv($seconds, 86400);
        $h = intdiv($seconds % 86400, 3600);
        $m = intdiv($seconds % 3600, 60);

        if ($d > 0) {
            return sprintf('%dd %dh', $d, $h);
        }
        if ($h > 0) {
            return sprintf('%dh %dm', $h, $m);
        }

        return sprintf('%dm', $m);
    }
}
