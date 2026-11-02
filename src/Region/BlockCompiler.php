<?php

declare(strict_types=1);

namespace Damoony\Region;

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
        $code .= "use Damoony\\Ui\\Text;\n\n";

        $code .= "final class {$className} implements RegionInterface\n{\n";
        $code .= "    public function name(): string\n    {\n        return '{$blockName}';\n    }\n\n";
        $code .= "    public function enabled(): bool\n    {\n        return true;\n    }\n\n";
        $code .= "    public function render(RegionContext \$context): Node\n    {\n";
        $code .= "        \$cssUrl = '/media/' . \$context->domain->real . '/' . \$context->domain->allias . '/skin/default/css/{$blockName}.css';\n";
        $code .= "        \$context->meta->addCss(\$cssUrl);\n\n";
        $code .= "        \$content = Builder::div('{$blockName}');\n\n";

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

            if ($type === 'nav') {
                $code .= "        \$el = Builder::ul('menu');\n";
                $navItems = $block['items'] ?? [];
                foreach ($navItems as $item) {
                    if (is_array($item) && isset($item['url'], $item['title'])) {
                        $itemUrl = var_export($item['url'], true);
                        $itemTitle = var_export($item['title'], true);
                        $code .= "        \$el->append(Builder::li()->append(Builder::a({$itemUrl})->append(Text::raw(strip_tags({$itemTitle}, '<a><strong>')))));\n";
                    }
                }
                $code .= $attrsCode;
                $code .= "        \$content->append(\$el);\n\n";
            } elseif ($type === 'h1') {
                $code .= '        $el = Builder::h(1)->append(Text::raw(strip_tags(' . var_export($content, true) . ", '<a><strong>')));\n";
                $code .= $attrsCode;
                $code .= "        \$content->append(\$el);\n\n";
            } elseif ($type === 'h2') {
                $code .= '        $el = Builder::h(2)->append(Text::raw(strip_tags(' . var_export($content, true) . ", '<a><strong>')));\n";
                $code .= $attrsCode;
                $code .= "        \$content->append(\$el);\n\n";
            } elseif ($type === 'h3') {
                $code .= '        $el = Builder::h(3)->append(Text::raw(strip_tags(' . var_export($content, true) . ", '<a><strong>')));\n";
                $code .= $attrsCode;
                $code .= "        \$content->append(\$el);\n\n";
            } elseif ($type === 'text') {
                $code .= '        $el = Builder::p()->append(Text::raw(strip_tags(' . var_export($content, true) . ", '<a><strong>')));\n";
                $code .= $attrsCode;
                $code .= "        \$content->append(\$el);\n\n";
            } elseif ($type === 'img') {
                $code .= '        $el = Builder::img(' . var_export($content, true) . ', ' . var_export($alt, true) . ");\n";
                $code .= $attrsCode;
                $code .= "        \$content->append(\$el);\n\n";
            } elseif ($type === 'button' || $type === 'link') {
                $code .= '        $el = Builder::a(' . var_export($url, true) . ')->append(Text::raw(strip_tags(' . var_export($content, true) . ", '<a><strong>')));\n";
                $code .= $attrsCode;
                $code .= "        \$content->append(Builder::p()->append(\$el));\n\n";
            } elseif ($type === 'ul') {
                $code .= "        \$el = Builder::ul();\n";
                foreach ($items as $item) {
                    $code .= '        $el->append(Builder::li()->append(Text::raw(strip_tags(' . var_export($item, true) . ", '<a><strong>'))));\n";
                }
                $code .= $attrsCode;
                $code .= "        \$content->append(\$el);\n\n";
            }
        }

        $code .= "        return \$content;\n    }\n}\n";

        return $code;
    }
}
