<?php

declare(strict_types=1);

namespace Damoony\Page;

final class PageCompiler
{
    /**
     * @param array<string, array{status: string, blocks?: list<array{type: string, content: string}>}> $slots
     */
    public function compile(string $namespace, string $allias, string $className, array $slots): string
    {
        $code = "<?php\n\ndeclare(strict_types=1);\n\n";
        $code .= "namespace {$namespace}\\{$allias};\n\n";
        $code .= "use Damoony\\Algoritm\\Algoritm;\n";
        $code .= "use Damoony\\Http\\Response;\n";
        $code .= "use Damoony\\Ui\\Builder;\n";
        $code .= "use Damoony\\Ui\\Node;\n";
        $code .= "use Damoony\\Files\\Paths;\n";
        $code .= "use Damoony\\Scheme\\SchemeLoader;\n";
        $code .= "use Damoony\\Scheme\\SchemeRenderer;\n\n";
        $code .= "final class {$className} extends Algoritm\n{\n";
        $code .= "    public function default(): Node|Response|null\n    {\n";

        foreach ($slots as $slotName => $config) {
            if (!preg_match('/^[a-z][a-z0-9_]*$/', $slotName)) {
                continue;
            }

            $status = $config['status'];
            $blocks = $config['blocks'] ?? [];

            if ($status === 'disabled') {
                $code .= "        \$this->regions()->disable('{$slotName}');\n";
            } elseif ($status === 'global') {
                $code .= "        \$this->regions()->useGlobal('{$slotName}');\n";
            } elseif ($status === 'local') {
                $varName = 'container_' . $slotName;
                $code .= "        \${$varName} = Builder::div('page-slot-{$slotName}');\n";
                foreach ($blocks as $block) {
                    $type = $block['type'];
                    $content = var_export($block['content'], true);

                    if ($type === 'h1') {
                        $code .= "        \${$varName}->append(Builder::h(1)->content({$content}));\n";
                    } elseif ($type === 'h2') {
                        $code .= "        \${$varName}->append(Builder::h(2)->content({$content}));\n";
                    } elseif ($type === 'text') {
                        $code .= "        \${$varName}->append(Builder::p()->content({$content}));\n";
                    } elseif ($type === 'img') {
                        $code .= "        \${$varName}->append(Builder::img({$content}, 'Image'));\n";
                    }
                }
                $code .= "        \$this->setContent('{$slotName}', \${$varName});\n";
            }
        }

        $code .= "\n        \$paths = \$this->context->service('paths');\n";
        $code .= "        if (!\$paths instanceof Paths) {\n";
        $code .= "            \$paths = new Paths(dirname(__DIR__, 5));\n";
        $code .= "        }\n";
        $code .= "        \$schemeLoader = new SchemeLoader(\$paths);\n";
        $code .= "        \$manifest = \$schemeLoader->load(\$this->context->domain, \$this->context->domain->scheme);\n";
        $code .= "        \$renderer = new SchemeRenderer();\n";
        $code .= "        return \$renderer->render(\$manifest, \$this->regions(), \$this->context);\n";

        $code .= "    }\n}\n";

        return $code;
    }
}
