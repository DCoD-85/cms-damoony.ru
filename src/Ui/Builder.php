<?php

declare(strict_types=1);

namespace Damoony\Ui;

final class Builder
{
    public static function el(string $tag, bool $selfClosing = false): Element
    {
        return new Element($tag, $selfClosing);
    }

    /** @param array<string, string> $attrs */
    public static function elA(string $tag, array $attrs = [], bool $selfClosing = false): Element
    {
        $el = new Element($tag, $selfClosing);
        foreach ($attrs as $name => $value) {
            $el->attrs()->add($name, $value);
        }
        return $el;
    }

    public static function div(string $class = ''): Element
    {
        $el = new Element('div');
        if ($class !== '') {
            $el->attrs()->classAdd($class);
        }
        return $el;
    }

    public static function span(string $class = ''): Element
    {
        $el = new Element('span');
        if ($class !== '') {
            $el->attrs()->classAdd($class);
        }
        return $el;
    }

    public static function p(string $class = ''): Element
    {
        $el = new Element('p');
        if ($class !== '') {
            $el->attrs()->classAdd($class);
        }
        return $el;
    }

    public static function h(int $level, string $class = ''): Element
    {
        $level = max(1, min(6, $level));
        $el = new Element('h' . $level);
        if ($class !== '') {
            $el->attrs()->classAdd($class);
        }
        return $el;
    }

    public static function a(string $href, string $class = ''): Element
    {
        $el = new Element('a');
        $el->attrs()->add('href', $href);
        if ($class !== '') {
            $el->attrs()->classAdd($class);
        }
        return $el;
    }

    public static function img(string $src, string $alt = ''): Element
    {
        $el = new Element('img', true);
        $el->attrs()->add('src', $src, true);
        if ($alt !== '') {
            $el->attrs()->add('alt', $alt);
        }
        return $el;
    }

    public static function input(string $type = 'text', string $name = '', string $value = ''): Element
    {
        $el = new Element('input', true);
        $el->attrs()->add('type', $type);
        if ($name !== '') {
            $el->attrs()->add('name', $name);
        }
        if ($value !== '') {
            $el->attrs()->add('value', $value, true);
        }
        return $el;
    }

    public static function ul(string $class = ''): Element
    {
        $el = new Element('ul');
        if ($class !== '') {
            $el->attrs()->classAdd($class);
        }
        return $el;
    }

    public static function li(string $class = ''): Element
    {
        $el = new Element('li');
        if ($class !== '') {
            $el->attrs()->classAdd($class);
        }
        return $el;
    }

    public static function button(string $type = 'submit', string $class = ''): Element
    {
        $el = new Element('button');
        $el->attrs()->add('type', $type);
        if ($class !== '') {
            $el->attrs()->classAdd($class);
        }
        return $el;
    }
}