<?php

declare(strict_types=1);

namespace Damoony\Cli;

final class Renderer
{
    public function render(Result $r): string
    {
        $out = '';

        foreach ($r->steps as $step) {
            $out .= ($step['ok'] ? '✓ ' : '✗ ') . $step['message'];
            if ($step['detail'] !== '') {
                $out .= ' — ' . $step['detail'];
            }
            $out .= PHP_EOL;
        }

        if ($r->created !== []) {
            $out .= PHP_EOL . 'Создано:' . PHP_EOL;
            foreach ($r->created as $file) {
                $out .= '  + ' . $file . PHP_EOL;
            }
        }

        if ($r->warnings !== []) {
            $out .= PHP_EOL . 'Предупреждения:' . PHP_EOL;
            foreach ($r->warnings as $w) {
                $out .= '  ! ' . $w . PHP_EOL;
            }
        }

        if ($r->errors !== []) {
            $out .= PHP_EOL . 'Ошибки:' . PHP_EOL;
            foreach ($r->errors as $e) {
                $out .= '  ✗ ' . $e . PHP_EOL;
            }
        }

        $out .= PHP_EOL . ($r->ok ? 'OK' : 'FAILED') . PHP_EOL;

        return $out;
    }
}
