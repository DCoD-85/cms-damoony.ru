<?php

declare(strict_types=1);

namespace Damoony\Page;

final class PageCompiler
{
    /**
     * @param list<array{type: string, content: string, level?: int}> $blocks
     */
    public function compile(string $namespace, string $allias, string $className, array $blocks): string
    {
        $code = "<?php\n\ndeclare(strict_types=1);\n\n";
        $code .= "namespace {$namespace}\\{$allias};\n\n";
        $code .= "use Damoony\\Algoritm\\Algoritm;\n";
        $code .= "use Damoony\\Http\\Response;\n";
        $code .= "use Damoony\\Ui\\Builder;\n";
        $code .= "use Damoony\\Ui\\Node;\n\n";
        $code .= "final class {$className} extends Algoritm\n{\n";
        $code .= "    public function default(): Node|Response|null\n    {\n";
        $code .= "        \$container = Builder::div('page-container');\n";

        foreach ($blocks as $block) {
            $type = $block['type'];
            $content = addslashes($block['content']);

            if ($type === 'h1') {
                $code .= "        \$container->append(Builder::h(1)->content('{$content}'));\n";
            } elseif ($type === 'h2') {
                $code .= "        \$container->append(Builder::h(2)->content('{$content}'));\n";
            } elseif ($type === 'text') {
                $code .= "        \$container->append(Builder::p()->content('{$content}'));\n";
            } elseif ($type === 'img') {
                $code .= "        \$container->append(Builder::img('{$content}', 'Image'));\n";
            }
        }

        $code .= "        return \$container;\n";
        $code .= "    }\n}\n";

        return $code;
    }
}
