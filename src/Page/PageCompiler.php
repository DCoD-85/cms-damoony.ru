<?php

declare(strict_types=1);

namespace Damoony\Page;

final class PageCompiler
{
    /**
     * @param list<array{type: string, content?: string, items?: list<string>, url?: string, class?: string, id?: string, style?: string, alt?: string}> $blocks
     */
    public function compile(string $namespace, string $classChain, array $blocks, bool $withSchema = false): string
    {
        $code = "<?php\n\ndeclare(strict_types=1);\n\n";
        $code .= "namespace {$namespace};\n\n";
        $code .= "use Damoony\\Algoritm\\Page;\n";
        $code .= "use Damoony\\Ui\\Builder;\n";
        $code .= "use Damoony\\Ui\\Node;\n";
        $code .= "use Damoony\\Http\\Response;\n";

        if ($withSchema) {
            $code .= "use Damoony\\Files\\Paths;\n";
            $code .= "use Damoony\\Scheme\\SchemeLoader;\n";
            $code .= "use Damoony\\Scheme\\SchemeRenderer;\n";
        }

        $code .= "\nfinal class {$classChain} extends Page\n{\n";
        $code .= "    public function default(): Node|Response|null\n    {\n";
        $code .= "        \$content = Builder::div('page-content');\n\n";

        foreach ($blocks as $block) {
            $type = $block['type'];
            $content = $block['content'] ?? '';
            $url = $block['url'] ?? '#';
            $class = $block['class'] ?? '';
            $id = $block['id'] ?? '';
            $style = $block['style'] ?? '';
            $alt = $block['alt'] ?? 'Image';
            $items = $block['items'] ?? [];

            if ($type === 'button' && $class === '') {
                $class = 'btn';
            }

            $attrsCode = '';
            if ($class !== '') {
                $attrsCode .= '        $el->attrs()->classAdd(' . var_export($class, true) . ");\n";
            }
            if ($id !== '') {
                $attrsCode .= "        \$el->attrs()->add('id', " . var_export($id, true) . ");\n";
            }
            if ($style !== '') {
                $attrsCode .= '        $el->attrs()->styleAdd(' . var_export($style, true) . ");\n";
            }

            if ($type === 'h1') {
                $code .= '        $el = Builder::h(1)->content(' . var_export($content, true) . ");\n";
                $code .= $attrsCode;
                $code .= "        \$content->append(\$el);\n\n";
            } elseif ($type === 'h2') {
                $code .= '        $el = Builder::h(2)->content(' . var_export($content, true) . ");\n";
                $code .= $attrsCode;
                $code .= "        \$content->append(\$el);\n\n";
            } elseif ($type === 'h3') {
                $code .= '        $el = Builder::h(3)->content(' . var_export($content, true) . ");\n";
                $code .= $attrsCode;
                $code .= "        \$content->append(\$el);\n\n";
            } elseif ($type === 'text') {
                $code .= '        $el = Builder::p()->content(' . var_export($content, true) . ");\n";
                $code .= $attrsCode;
                $code .= "        \$content->append(\$el);\n\n";
            } elseif ($type === 'img') {
                $code .= '        $el = Builder::img(' . var_export($content, true) . ', ' . var_export($alt, true) . ");\n";
                $code .= $attrsCode;
                $code .= "        \$content->append(\$el);\n\n";
            } elseif ($type === 'button' || $type === 'link') {
                $code .= '        $el = Builder::a(' . var_export($url, true) . ')->content(' . var_export($content, true) . ");\n";
                $code .= $attrsCode;
                $code .= "        \$content->append(Builder::p()->append(\$el));\n\n";
            } elseif ($type === 'ul') {
                $code .= "        \$el = Builder::ul();\n";
                foreach ($items as $item) {
                    $code .= '        $el->append(Builder::li()->content(' . var_export($item, true) . "));\n";
                }
                $code .= $attrsCode;
                $code .= "        \$content->append(\$el);\n\n";
            }
        }

        if ($withSchema) {
            $code .= "        \$this->setContent('center', \$content);\n\n";
            $code .= "        \$paths = \$this->context->service('paths');\n";
            $code .= "        if (!\$paths instanceof Paths) {\n";
            $code .= "            return Response::internalError('Paths service missing');\n";
            $code .= "        }\n\n";
            $code .= "        \$loader = new SchemeLoader(\$paths);\n";
            $code .= "        \$manifest = \$loader->load(\$this->context->domain, \$this->context->domain->scheme);\n";
            $code .= "        \$renderer = new SchemeRenderer();\n\n";
            $code .= "        return \$renderer->render(\$manifest, \$this->regions(), \$this->context);\n";
        } else {
            $code .= "        return \$content;\n";
        }

        $code .= "    }\n}\n";

        return $code;
    }
}
