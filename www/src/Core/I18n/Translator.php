<?php

declare(strict_types=1);

namespace Damoony\Core\I18n;

final class Translator
{
    /** @var array<string, string> */
    private readonly array $dict;

    /**
     * @param list<string> $sources директории с {lang}.json, порядок: generic -> specific
     */
    public function __construct(
        private readonly string $current,
        private readonly ?string $fallback,
        private readonly string $langDir,
        private readonly array $sources = [],
    ) {
        $dict = [];
        foreach ($this->sources as $source) {
            $dict = self::loadJson($source, $this->current) + $dict;
        }
        $dict += self::loadPhp($this->langDir, $this->current);
        if ($this->fallback !== null) {
            $dict += self::loadPhp($this->langDir, $this->fallback);
        }
        $this->dict = $dict;
    }

    public function current(): string
    {
        return $this->current;
    }

    /** @param array<string, string|int> $params */
    public function t(string $key, array $params = []): string
    {
        $tpl = $this->dict[$key] ?? $key;
        foreach ($params as $k => $v) {
            $tpl = str_replace('{' . $k . '}', (string) $v, $tpl);
        }

        return $tpl;
    }

    /**
     * Pluralization.
     * Look up keys like "cart.items.one", "cart.items.few", "cart.items.many".
     * Replaces {{amount}} with the actual count.
     */
    /** @param array<string, string|int> $params */
    public function tPlural(string $key, int|float $count, array $params = []): string
    {
        $form = $this->getPluralForm($count, $this->current);
        $tpl = $this->dict[$key . '.' . $form] ?? $this->dict[$key . '.other'] ?? $key;

        foreach ($params as $k => $v) {
            $tpl = str_replace('{' . $k . '}', (string) $v, $tpl);
        }

        return str_replace('{{amount}}', (string) $count, $tpl);
    }

    private function getPluralForm(float $count, string $lang): string
    {
        $count = abs($count);
        if ($lang === 'ru') {
            $n10 = (int) $count % 10;
            $n100 = (int) $count % 100;
            if ($n10 === 1 && $n100 !== 11) {
                return 'one';
            }
            if ($n10 >= 2 && $n10 <= 4 && ($n100 < 10 || $n100 >= 20)) {
                return 'few';
            }
            return 'many';
        }
        // Default EN / fallback
        return $count == 1 ? 'one' : 'other';
    }

    /** @return array<string, string> */
    private static function loadJson(string $dir, string $lang): array
    {
        if ($dir === '' || !preg_match('/^[a-z]{2}$/', $lang)) {
            return [];
        }
        $file = rtrim($dir, '/') . '/' . $lang . '.json';
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? self::flatten($data) : [];
    }

    /**
     * @param array<string, mixed> $array
     *
     * @return array<string, string>
     */
    private static function flatten(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result += self::flatten($value, $prefix . $key . '.');
            } else {
                $result[$prefix . $key] = (string) $value;
            }
        }
        return $result;
    }

    /** @return array<string, string> */
    private static function loadPhp(string $dir, string $lang): array
    {
        if ($dir === '' || !preg_match('/^[a-z]{2}$/', $lang)) {
            return [];
        }
        $file = rtrim($dir, '/') . '/' . $lang . '.php';
        if (!is_file($file)) {
            return [];
        }
        $data = require $file;

        return is_array($data) ? $data : [];
    }
}
