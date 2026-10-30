<?php

declare(strict_types=1);

namespace Damoony\Region;

final class RegionValidator
{
    /** @var list<string> */
    private const PHP_BLOCK_TYPES = ['custom', 'if', 'else', 'endif'];

    public function __construct(private readonly bool $allowPhpBlocks = false)
    {
    }

    /**
     * @param list<array<string, mixed>> $blocks
     *
     * @return list<string> список ключей ошибок (без перевода)
     */
    public function validate(array $blocks): array
    {
        $errors = [];

        if (!$this->allowPhpBlocks && $this->hasPhpBlocks($blocks)) {
            $errors[] = 'admin.editor.error_php_blocks_disabled';

            return $errors;
        }

        $depth = 0;

        foreach ($blocks as $block) {
            $type = (string)($block['type'] ?? '');

            if ($type === 'if') {
                $condition = trim((string)($block['condition'] ?? ''));
                if ($condition === '') {
                    $errors[] = 'admin.editor.error_if_empty';
                }
                $depth++;
            } elseif ($type === 'endif') {
                $depth--;
                if ($depth < 0) {
                    $errors[] = 'admin.editor.error_endif_orphan';
                    $depth = 0;
                }
            } elseif ($type === 'else') {
                if ($depth <= 0) {
                    $errors[] = 'admin.editor.error_else_orphan';
                }
            }
        }

        if ($depth > 0) {
            $errors[] = 'admin.editor.error_if_unclosed';
        }

        return $errors;
    }

    /** @param list<array<string, mixed>> $blocks */
    private function hasPhpBlocks(array $blocks): bool
    {
        foreach ($blocks as $block) {
            if (in_array($block['type'] ?? '', self::PHP_BLOCK_TYPES, true)) {
                return true;
            }
        }

        return false;
    }
}
