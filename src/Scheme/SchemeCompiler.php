<?php

declare(strict_types=1);

namespace Damoony\Scheme;

final class SchemeCompiler
{
    /**
     * @param array<string, mixed> $data
     */
    public function compile(array $data): string
    {
        $code = "<?php\n\nreturn " . var_export($data, true) . ";\n";

        // Меняем array() на [] для современного вида
        $code = str_replace(['array (', ')'], ['[', ']'], $code);

        return $code;
    }
}
