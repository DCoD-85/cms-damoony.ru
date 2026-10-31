<?php

declare(strict_types=1);

namespace Damoony\Page;

final class PageCompiler
{
    /**
     * @param list<array{type: string, content: string}> $blocks
     */
    public function compile(string $namespace, string $classChain, array $blocks): string
    {
        $code = "<?php\n\ndeclare(strict_types=1);\n\n";
        $code .= "namespace {$namespace};\n\n";
        $code .= "use Damoony\\Algoritm\\Page;\n";
        $code .= "use Damoony\\Ui\\Builder;\n";
        $code .= "use Damoony\\Ui\\Node;\n";
        $code .= "use Damoony\\Http\\Response;\n\n";
        $code .= "final class {$classChain} extends Page\n{\n";
        $code .= "    public function default(): Node|Response|null\n    {\n";
        $code .= "        \$wrap = Builder::div('page');\n";

        foreach ($blocks as $block) {
            $type = $block['type'] ?? 'text';
            $content = $block['content'] ?? '';
            // Безопасная генерация через var_export
            $safeContent = var_export($content, true);

            if ($type === 'h1') {
                $code .= "        \$wrap->append(Builder::h(1)->content({$safeContent}));\n";
            } elseif ($type === 'h2') {
                $code .= "        \$wrap->append(Builder::h(2)->content({$safeContent}));\n";
            } elseif ($type === 'img') {
                $code .= "        \$wrap->append(Builder::img({$safeContent}, 'Image'));\n";
            } else {
                // По умолчанию текст
                $code .= "        \$wrap->append(Builder::p()->content({$safeContent}));\n";
            }
        }

        $code .= "        return \$wrap;\n";
        $code .= "    }\n}\n";

        return $code;
    }
}