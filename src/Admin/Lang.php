<?php

declare(strict_types=1);

namespace Damoony\Admin;

final class Lang
{
    /** @var array<string, string> */
    private readonly array $dict;

    public function __construct(
        private readonly string $current = 'ru',
        private readonly string $fallback = 'ru',
    ) {
        $this->dict = self::load($this->current) + self::load($this->fallback);
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
    private static function load(string $lang): array
    {
        if (!preg_match('/^[a-z]{2}$/', $lang)) {
            return [];
        }
        $file = __DIR__ . '/Lang/' . $lang . '.php';
        if (!is_file($file)) {
            return [];
        }
        $data = require $file;

        return is_array($data) ? $data : [];
    }
}
