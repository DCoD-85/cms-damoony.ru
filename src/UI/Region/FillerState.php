<?php

declare(strict_types=1);

namespace Damoony\UI\Region;

use Damoony\Core\Files\Paths;

/**
 * Единая точка чтения/записи состояния filler'а.
 *
 * Источник истины — rules.json в .blockslocal/{block}/{block}.rules.json.
 * site.config['fillers'] — deprecated, убран из всех команд (R2-R7).
 */
final class FillerState
{
    public function __construct(private readonly Paths $paths)
    {
    }

    /**
     * rules.json path для filler'а.
     * Путь строится от realDomain (host), не от namespace.
     */
    public function rulesPath(string $realDomain, string $alias, string $block): string
    {
        return $this->paths->allias($realDomain, $alias) . '/.blockslocal/' . $block . '/' . $block . '.rules.json';
    }

    /**
     * Default true если rules.json нет/битый — backward compat
     * (до рефакторинга filler без config-ключа считался активным).
     */
    public function isEnabled(string $realDomain, string $alias, string $block): bool
    {
        $path = $this->rulesPath($realDomain, $alias, $block);
        if (!is_file($path)) {
            return true;
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return true;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return true;
        }
        return (bool) ($decoded['enabled'] ?? true);
    }

    public function setEnabled(string $realDomain, string $alias, string $block, bool $enabled): void
    {
        $path = $this->rulesPath($realDomain, $alias, $block);
        $current = [];
        if (is_file($path)) {
            $raw = @file_get_contents($path);
            if ($raw !== false) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $current = $decoded;
                }
            }
        }
        $current['enabled'] = $enabled;
        if (!isset($current['type'])) {
            $current['type'] = 'mixed';
        }
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0o775, true);
        }
        file_put_contents(
            $path,
            json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        );
    }
}
