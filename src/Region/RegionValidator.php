<?php

declare(strict_types=1);

namespace Damoony\Region;

final class RegionValidator
{
    /**
     * @param list<array<string, mixed>> $blocks
     *
     * @return list<string> список ключей ошибок (без перевода)
     */
    public function validate(array $blocks): array
    {
        $errors = [];
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
}
