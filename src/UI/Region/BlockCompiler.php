<?php

declare(strict_types=1);

namespace Damoony\UI\Region;

use Damoony\UI\BlockCodegen;

final class BlockCompiler
{
    /**
     * @param list<array{type: string, content?: string, items?: mixed, url?: string, class?: string, id?: string, style?: string, alt?: string}> $blocks
     */
    public function compile(string $namespace, string $className, string $blockName, array $blocks): string
    {
        $code = "<?php\n\ndeclare(strict_types=1);\n\n";
        $code .= "namespace {$namespace};\n\n";
        $code .= "use Damoony\\UI\\Region\\RegionContext;\n";
        $code .= "use Damoony\\UI\\Region\\RegionInterface;\n";
        $code .= "use Damoony\\UI\\Builder;\n";
        $code .= "use Damoony\\UI\\Node;\n";
        $code .= "use Damoony\\UI\\Components\\Button;\n";
        $code .= "use Damoony\\UI\\Components\\Img;\n";
        $code .= "use Damoony\\UI\\Components\\Link;\n";
        $code .= "use Damoony\\UI\\Components\\Input;\n";
        $code .= "use Damoony\\UI\\Components\\Textarea;\n";
        $code .= "use Damoony\\UI\\Components\\Select;\n";
        $code .= "use Damoony\\UI\\Components\\Option;\n";
        $code .= "use Damoony\\UI\\Components\\Label;\n";
        $code .= "use Damoony\\UI\\Components\\Form;\n";
        $code .= "use Damoony\\UI\\Components\\Table;\n";
        $code .= "use Damoony\\UI\\Components\\Thead;\n";
        $code .= "use Damoony\\UI\\Components\\Tbody;\n";
        $code .= "use Damoony\\UI\\Components\\Tr;\n";
        $code .= "use Damoony\\UI\\Components\\Th;\n";
        $code .= "use Damoony\\UI\\Components\\Td;\n";
        $code .= "use Damoony\\UI\\Components\\Button;\n";
        $code .= "use Damoony\\UI\\Components\\Img;\n";
        $code .= "use Damoony\\UI\\Components\\Link;\n";
        $code .= "use Damoony\\UI\\Components\\Input;\n";
        $code .= "use Damoony\\UI\\Components\\Textarea;\n";
        $code .= "use Damoony\\UI\\Components\\Select;\n";
        $code .= "use Damoony\\UI\\Components\\Option;\n";
        $code .= "use Damoony\\UI\\Components\\Label;\n";
        $code .= "use Damoony\\UI\\Components\\Form;\n";
        $code .= "use Damoony\\UI\\Components\\Table;\n";
        $code .= "use Damoony\\UI\\Components\\Thead;\n";
        $code .= "use Damoony\\UI\\Components\\Tbody;\n";
        $code .= "use Damoony\\UI\\Components\\Tr;\n";
        $code .= "use Damoony\\UI\\Components\\Th;\n";
        $code .= "use Damoony\\UI\\Components\\Td;\n";
        $code .= "use Damoony\\UI\\Text;\n";
        $code .= "use Damoony\\UI\\UrlSanitizer;\n\n";

        $code .= "final class {$className} implements RegionInterface\n{\n";
        $code .= "    public function name(): string\n    {\n        return '{$blockName}';\n    }\n\n";
        $code .= "    public function enabled(): bool\n    {\n        return true;\n    }\n\n";
        $code .= "    public function render(RegionContext \$context): Node\n    {\n";
        $code .= "        \$cssUrl = '/media/' . \$context->domain->real . '/' . \$context->domain->allias . '/skin/default/css/{$blockName}.css';\n";
        $code .= "        \$context->meta->addCss(\$cssUrl);\n\n";
        $code .= "        \$content = Builder::div('{$blockName}');\n\n";

        $code .= BlockCodegen::render($blocks, '$content', '$context', 8);

        $code .= "        return \$content;\n    }\n}\n";

        return $code;
    }
}
