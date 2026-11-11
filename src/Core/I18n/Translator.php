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

        return is_array($data) ? $data : [];
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
