<?php

declare(strict_types=1);

namespace Damoony\Region;

final class RegionCompiler
{
    /** @var list<string> */
    private const PHP_BLOCK_TYPES = ['custom', 'if', 'else', 'endif'];

    public function __construct(private readonly bool $allowPhpBlocks = false)
    {
    }

    /**
     * @param list<array<string, mixed>> $blocks
     */
    public function compile(string $namespace, string $blockName, array $blocks, string $classSuffix = ''): string
    {
        if (!$this->allowPhpBlocks && $this->hasPhpBlocks($blocks)) {
            throw new \RuntimeException(
                'PHP blocks (custom/if/else/endif) are disabled. ' .
                'Set security.allow_php_blocks=true in engine.config to enable.',
            );
        }

        $className = 'alliasglobal_' . $blockName . $classSuffix;

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

        $indent = '        '; // 8 пробелов

        foreach ($blocks as $block) {
            $type = $block['type'] ?? 'text';
            $content = $block['content'] ?? '';

            if ($type === 'custom') {
                $userCode = (string)($block['code'] ?? '');
                if ($userCode !== '') {
                    foreach (explode("\n", $userCode) as $line) {
                        $code .= $indent . $line . "\n";
                    }
                }
            } elseif ($type === 'if') {
                $condition = (string)($block['condition'] ?? '');
                $code .= $indent . "if ({$condition}) {\n";
                $indent .= '    ';
            } elseif ($type === 'else') {
                $indent = substr($indent, 0, -4);
                $code .= $indent . "} else {\n";
                $indent .= '    ';
            } elseif ($type === 'endif') {
                $indent = substr($indent, 0, -4);
                $code .= $indent . "}\n";
            } elseif ($type === 'h1') {
                $code .= $indent . '$wrap->append(Builder::h(1)->content(' . var_export($content, true) . "));\n";
            } elseif ($type === 'h2') {
                $code .= $indent . '$wrap->append(Builder::h(2)->content(' . var_export($content, true) . "));\n";
            } elseif ($type === 'text') {
                $code .= $indent . '$wrap->append(Builder::p()->content(' . var_export($content, true) . "));\n";
            } elseif ($type === 'img') {
                $code .= $indent . '$wrap->append(Builder::img(' . var_export($content, true) . ", 'Image'));\n";
            }
        }

        $code .= "        return \$wrap;\n";
        $code .= "    }\n}\n";

        return $code;
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
