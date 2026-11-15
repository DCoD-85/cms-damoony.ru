<?php

declare(strict_types=1);

namespace Damoony\UI\Scheme;

final class SchemeCompiler
{
    /**
     * @param array<string, mixed> $data
     */
    public function compile(array $data): string
    {
        return "<?php\n\nreturn " . var_export($data, true) . ";\n";
    }
}
