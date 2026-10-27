<?php

declare(strict_types=1);

namespace Damoony\I18n;

final class Translator
{
    /** @var array<string, string> */
    private readonly array $dict;

    public function __construct(
        private readonly string $current = 'ru',
        private readonly string $fallback = 'ru',
        private readonly ?string $langDir = null,
    ) {
        $dir = $this->langDir ?? dirname(__DIR__, 2) . '/lang';
        $this->dict = self::load($dir, $this->current) + self::load($dir, $this->fallback);
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
    private static function load(string $dir, string $lang): array
    {
        if (!preg_match('/^[a-z]{2}$/', $lang)) {
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
