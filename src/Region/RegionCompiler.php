<?php

declare(strict_types=1);

namespace Damoony\Region;

final class RegionCompiler
{
    /**
     * @param list<array{type: string, content: string, level?: int}> $blocks
     */
    public function compile(string $namespace, string $blockName, array $blocks): string
    {
        $className = 'alliasglobal_' . $blockName;

        $code = "<?php\n\ndeclare(strict_types=1);\n\n";
        $code .= "namespace {$namespace};\n\n";
        $code .= "use Damoony\\Region\\RegionContext;\n";
        $code .= "use Damoony\\Region\\RegionInterface;\n";
        $code .= "use Damoony\\Ui\\Builder;\n";
        $code .= "use Damoony\\Ui\\Node;\n\n";
        $code .= "final class {$className} implements RegionInterface\n{\n";
        $code .= "    public function name(): string\n    {\n        return '{$blockName}';\n    }\n\n";
        $code .= "    public function enabled(): bool\n    {\n        return true;\n    }\n\n";
        $code .= "    public function render(RegionContext \$context): Node\n    {\n";
        $code .= "        \$wrap = Builder::div('{$blockName}-wrap');\n";

        foreach ($blocks as $block) {
            $type = $block['type'];
            $content = addslashes($block['content']);

            if ($type === 'h1') {
                $code .= "        \$wrap->append(Builder::h(1)->content('{$content}'));\n";
            } elseif ($type === 'h2') {
                $code .= "        \$wrap->append(Builder::h(2)->content('{$content}'));\n";
            } elseif ($type === 'text') {
                $code .= "        \$wrap->append(Builder::p()->content('{$content}'));\n";
            } elseif ($type === 'img') {
                $code .= "        \$wrap->append(Builder::img('{$content}', 'Image'));\n";
            }
        }

        $code .= "        return \$wrap;\n";
        $code .= "    }\n}\n";

        return $code;
    }
}
