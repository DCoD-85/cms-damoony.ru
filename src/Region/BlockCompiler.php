<?php

declare(strict_types=1);

namespace Damoony\Region;

use Damoony\Ui\BlockCodegen;

final class BlockCompiler
{
    /**
     * @param list<array{type: string, content?: string, items?: mixed, url?: string, class?: string, id?: string, style?: string, alt?: string}> $blocks
     */
    public function compile(string $namespace, string $className, string $blockName, array $blocks): string
    {
        $code = "<?php\n\ndeclare(strict_types=1);\n\n";
        $code .= "namespace {$namespace};\n\n";
        $code .= "use Damoony\\Region\\RegionContext;\n";
        $code .= "use Damoony\\Region\\RegionInterface;\n";
        $code .= "use Damoony\\Ui\\Builder;\n";
        $code .= "use Damoony\\Ui\\Node;\n";
        $code .= "use Damoony\\Ui\\Text;\n";
        $code .= "use Damoony\\Ui\\UrlSanitizer;\n\n";

        $code .= "final class {$className} implements RegionInterface\n{\n";
        $code .= "    public function name(): string\n    {\n        return '{$blockName}';\n    }\n\n";
        $code .= "    public function enabled(): bool\n    {\n        return true;\n    }\n\n";
        $code .= "    public function render(RegionContext \$context): Node\n    {\n";
        $code .= "        \$cssUrl = '/media/' . \$context->domain->real . '/' . \$context->domain->allias . '/skin/default/css/{$blockName}.css';\n";
        $code .= "        \$context->meta->addCss(\$cssUrl);\n\n";
        $code .= "        \$content = Builder::div('{$blockName}');\n\n";

        $code .= BlockCodegen::render($blocks, '$content', 8);

        $code .= "        return \$content;\n    }\n}\n";

        return $code;
    }
}
