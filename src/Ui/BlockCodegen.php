<?php

declare(strict_types=1);

namespace Damoony\Ui;

final class BlockCodegen
{
    /**
     * Генерирует PHP-код, добавляющий блоки в $parentVar.
     *
     * @param list<array<string, mixed>> $blocks
     */
    public static function render(array $blocks, string $parentVar, string $contextVar, int $indent = 8): string
    {
        $counter = 0;
        return self::renderWithCounter($blocks, $parentVar, $contextVar, $counter, $indent);
    }

    /**
     * @param list<array<string, mixed>> $blocks
     */
    private static function renderWithCounter(array $blocks, string $parentVar, string $contextVar, int &$counter, int $indent): string
    {
        $code = '';
        foreach ($blocks as $block) {
            $var = '$el' . (++$counter);
            $code .= self::renderOne($block, $parentVar, $var, $contextVar, $counter, $indent);
        }
        return $code;
    }

    /**
     * @param array<string, mixed> $block
     */
    private static function renderOne(array $block, string $parentVar, string $var, string $contextVar, int &$counter, int $indent): string
    {
        $pad = str_repeat(' ', $indent);
        $type = (string) ($block['type'] ?? '');
        $content = (string) ($block['content'] ?? '');
        $url = (string) ($block['url'] ?? '#');
        $class = (string) ($block['class'] ?? '');
        $id = (string) ($block['id'] ?? '');
        $style = (string) ($block['style'] ?? '');
        $alt = (string) ($block['alt'] ?? 'Image');
        $items = $block['items'] ?? [];
        $children = $block['children'] ?? [];

        if ($type === 'button' && $class === '') {
            $class = 'btn';
        }

        $attrs = '';
        if ($class !== '') {
            $attrs .= $pad . $var . '->attrs()->classAdd(' . var_export($class, true) . ");\n";
        }
        if ($id !== '') {
            $attrs .= $pad . $var . "->attrs()->add('id', " . var_export($id, true) . ");\n";
        }
        if ($style !== '') {
            $attrs .= $pad . $var . '->attrs()->styleAdd(' . var_export($style, true) . ");\n";
        }

        $code = '';

        if (in_array($type, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
            $level = (int) substr($type, 1);
            $code .= $pad . $var . ' = Builder::h(' . $level . ')->append(Text::raw(strip_tags(' . var_export($content, true) . ", '<a><strong>')));\n";
            $code .= $attrs;
            $code .= $pad . $parentVar . '->append(' . $var . ");\n\n";
            return $code;
        }

        if ($type === 'text' || $type === 'p') {
            $code .= $pad . $var . ' = Builder::p()->append(Text::raw(strip_tags(' . var_export($content, true) . ", '<a><strong>')));\n";
            $code .= $attrs;
            $code .= $pad . $parentVar . '->append(' . $var . ");\n\n";
            return $code;
        }

        if ($type === 'img') {
            $code .= $pad . $var . ' = Builder::img(' . var_export(UrlSanitizer::forSrc($content), true) . ', ' . var_export($alt, true) . ");\n";
            $code .= $attrs;
            $code .= $pad . $parentVar . '->append(' . $var . ");\n\n";
            return $code;
        }

        if ($type === 'link') {
            $code .= $pad . $var . ' = Builder::a(' . var_export(UrlSanitizer::forHref($url), true) . ')->append(Text::raw(strip_tags(' . var_export($content, true) . ", '<a><strong>')));\n";
            $code .= $attrs;
            $code .= $pad . $parentVar . '->append(Builder::p()->append(' . $var . "));\n\n";
            return $code;
        }

        if ($type === 'button') {
            $buttonType = (string) ($block['button_type'] ?? 'submit');
            if (!in_array($buttonType, ['submit', 'button', 'reset'], true)) {
                $buttonType = 'submit';
            }
            $code .= $pad . $var . ' = Builder::button(' . var_export($buttonType, true) . ");\n";
            $code .= $attrs;
            $code .= $pad . $var . '->append(Text::raw(strip_tags(' . var_export($content, true) . ", '<a><strong>')));\n";
            $code .= $pad . $parentVar . '->append(' . $var . ");\n\n";
            return $code;
        }

        if ($type === 'ul') {
            $code .= $pad . $var . " = Builder::ul();\n";
            foreach ((array) $items as $item) {
                if (is_string($item)) {
                    $code .= $pad . $var . '->append(Builder::li()->append(Text::raw(strip_tags(' . var_export($item, true) . ", '<a><strong>'))));\n";
                }
            }
            $code .= $attrs;
            $code .= $pad . $parentVar . '->append(' . $var . ");\n\n";
            return $code;
        }

        if ($type === 'nav') {
            $code .= $pad . $var . " = Builder::ul('menu');\n";
            foreach ((array) $items as $item) {
                if (is_array($item) && isset($item['url'], $item['title'])) {
                    $itemUrl = var_export(UrlSanitizer::forHref((string) $item['url']), true);
                    $itemTitle = var_export((string) $item['title'], true);
                    $code .= $pad . $var . "->append(Builder::li()->append(Builder::a({$itemUrl})->append(Text::raw(strip_tags({$itemTitle}, '<a><strong>')))));\n";
                }
            }
            $code .= $attrs;
            $code .= $pad . $parentVar . '->append(' . $var . ");\n\n";
            return $code;
        }

        if ($type === 'section' || $type === 'div') {
            $tag = $type === 'section' ? 'section' : 'div';
            $code .= $pad . $var . ' = Builder::' . $tag . "();\n";
            $code .= $attrs;
            if (is_array($children) && $children !== []) {
                $code .= self::renderWithCounter($children, $var, $contextVar, $counter, $indent);
            }
            $code .= $pad . $parentVar . '->append(' . $var . ");\n\n";
            return $code;
        }


        if ($type === 'table') {
            $headers = $block['headers'] ?? [];
            $rows = $block['rows'] ?? [];

            $code .= $pad . $var . " = Builder::el('table');\n";
            $code .= $attrs;

            if (is_array($headers) && $headers !== []) {
                $thead = '$el' . (++$counter);
                $code .= $pad . $thead . " = Builder::el('thead');\n";

                $trHead = '$el' . (++$counter);
                $code .= $pad . $trHead . " = Builder::el('tr');\n";

                foreach ($headers as $h) {
                    $th = '$el' . (++$counter);
                    $code .= $pad . $th . ' = Builder::el(\'th\')->append(Text::raw(strip_tags(' . var_export((string) $h, true) . ", '<a><strong>')));\n";
                    $code .= $pad . $trHead . '->append(' . $th . ");\n";
                }

                $code .= $pad . $thead . '->append(' . $trHead . ");\n";
                $code .= $pad . $var . '->append(' . $thead . ");\n";
            }

            if (is_array($rows) && $rows !== []) {
                $tbody = '$el' . (++$counter);
                $code .= $pad . $tbody . " = Builder::el('tbody');\n";

                foreach ($rows as $row) {
                    $tr = '$el' . (++$counter);
                    $code .= $pad . $tr . " = Builder::el('tr');\n";

                    foreach ((array) $row as $cell) {
                        $td = '$el' . (++$counter);
                        $code .= $pad . $td . ' = Builder::el(\'td\')->append(Text::raw(strip_tags(' . var_export((string) $cell, true) . ", '<a><strong>')));\n";
                        $code .= $pad . $tr . '->append(' . $td . ");\n";
                    }

                    $code .= $pad . $tbody . '->append(' . $tr . ");\n";
                }

                $code .= $pad . $var . '->append(' . $tbody . ");\n";
            }

            $code .= $pad . $parentVar . '->append(' . $var . ");\n\n";

            return $code;
        }


        if ($type === 'form') {
            $action = $block['action'] ?? '#';
            $method = strtoupper((string)($block['method'] ?? 'POST'));

            $code .= $pad . $var . " = Builder::el('form');\n";
            $code .= $pad . $var . '->attrs()->add(\'action\', ' . var_export(UrlSanitizer::forHref($action), true) . ");\n";
            $code .= $pad . $var . '->attrs()->add(\'method\', ' . var_export($method, true) . ");\n";
            $code .= $attrs;

            if ($method === 'POST') {
                $csrfVar = '$el' . (++$counter);
                $code .= $pad . $csrfVar . " = Builder::input('hidden', '_csrf', '');\n";
                $code .= $pad . $csrfVar . "->attrs()->add('value', " . $contextVar . "->service('csrf')->token());\n";
                $code .= $pad . $var . '->append(' . $csrfVar . ");\n";
            }

            if (is_array($children) && $children !== []) {
                $code .= self::renderWithCounter($children, $var, $contextVar, $counter, $indent);
            }
            $code .= $pad . $parentVar . '->append(' . $var . ");\n\n";
            return $code;
        }

        if ($type === 'label') {
            $for = $block['for'] ?? '';
            $code .= $pad . $var . " = Builder::el('label');\n";
            if ($for !== '') {
                $code .= $pad . $var . '->attrs()->add(\'for\', ' . var_export($for, true) . ");\n";
            }
            $code .= $attrs;
            $code .= $pad . $var . '->append(Text::raw(strip_tags(' . var_export($content, true) . ", '<a><strong>')));\n";
            $code .= $pad . $parentVar . '->append(' . $var . ");\n\n";
            return $code;
        }

        if ($type === 'input') {
            $inputType = $block['input_type'] ?? 'text';
            $name = $block['name'] ?? '';
            $value = $block['value'] ?? '';
            $placeholder = $block['placeholder'] ?? '';
            $required = $block['required'] ?? false;

            $code .= $pad . $var . ' = Builder::input(' . var_export($inputType, true) . ', ' . var_export($name, true) . ', ' . var_export($value, true) . ");\n";
            if ($placeholder !== '') {
                $code .= $pad . $var . '->attrs()->add(\'placeholder\', ' . var_export($placeholder, true) . ");\n";
            }
            if ($required) {
                $code .= $pad . $var . "->attrs()->add('required', 'required');\n";
            }
            $code .= $attrs;
            $code .= $pad . $parentVar . '->append(' . $var . ");\n\n";
            return $code;
        }

        if ($type === 'textarea') {
            $name = $block['name'] ?? '';
            $rows = (int) ($block['rows'] ?? 3);
            $placeholder = $block['placeholder'] ?? '';
            $required = $block['required'] ?? false;

            $code .= $pad . $var . " = Builder::el('textarea');\n";
            if ($name !== '') {
                $code .= $pad . $var . '->attrs()->add(\'name\', ' . var_export($name, true) . ");\n";
            }
            $code .= $pad . $var . '->attrs()->add(\'rows\', ' . var_export((string) $rows, true) . ");\n";
            if ($placeholder !== '') {
                $code .= $pad . $var . '->attrs()->add(\'placeholder\', ' . var_export($placeholder, true) . ");\n";
            }
            if ($required) {
                $code .= $pad . $var . "->attrs()->add('required', 'required');\n";
            }
            $code .= $attrs;
            $code .= $pad . $var . '->append(Text::raw(strip_tags(' . var_export($content, true) . ", '<a><strong>')));\n";
            $code .= $pad . $parentVar . '->append(' . $var . ");\n\n";
            return $code;
        }

        if ($type === 'select') {
            $name = $block['name'] ?? '';
            $options = $block['options'] ?? [];
            $required = $block['required'] ?? false;

            $code .= $pad . $var . " = Builder::el('select');\n";
            if ($name !== '') {
                $code .= $pad . $var . '->attrs()->add(\'name\', ' . var_export($name, true) . ");\n";
            }
            if ($required) {
                $code .= $pad . $var . "->attrs()->add('required', 'required');\n";
            }
            $code .= $attrs;

            foreach ((array) $options as $opt) {
                if (is_array($opt) && isset($opt['value'], $opt['label'])) {
                    $optVar = '$el' . (++$counter);
                    $code .= $pad . $optVar . " = Builder::el('option');\n";
                    $code .= $pad . $optVar . '->attrs()->add(\'value\', ' . var_export((string) $opt['value'], true) . ");\n";
                    $code .= $pad . $optVar . '->append(Text::raw(strip_tags(' . var_export((string) $opt['label'], true) . ", '<a><strong>')));\n";
                    $code .= $pad . $var . '->append(' . $optVar . ");\n";
                }
            }

            $code .= $pad . $parentVar . '->append(' . $var . ");\n\n";
            return $code;
        }

        return '';
    }
}
