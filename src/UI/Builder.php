<?php

declare(strict_types=1);

namespace Damoony\UI;

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

    public static function nav(string $class = ''): Element
    {
        $el = new Element('nav');
        if ($class !== '') {
            $el->attrs()->classAdd($class);
        }
        return $el;
    }

    public static function form(string $action = '', string $method = 'post', string $class = ''): Element
    {
        $el = new Element('form');
        if ($action !== '') {
            $el->attrs()->add('action', $action, true);
        }
        $el->attrs()->add('method', $method);
        if ($class !== '') {
            $el->attrs()->classAdd($class);
        }
        return $el;
    }

    public static function table(string $class = ''): Element
    {
        $el = new Element('table');
        if ($class !== '') {
            $el->attrs()->classAdd($class);
        }
        return $el;
    }

    public static function thead(string $class = ''): Element
    {
        $el = new Element('thead');
        if ($class !== '') {
            $el->attrs()->classAdd($class);
        }
        return $el;
    }

    public static function tbody(string $class = ''): Element
    {
        $el = new Element('tbody');
        if ($class !== '') {
            $el->attrs()->classAdd($class);
        }
        return $el;
    }

    public static function tr(string $class = ''): Element
    {
        $el = new Element('tr');
        if ($class !== '') {
            $el->attrs()->classAdd($class);
        }
        return $el;
    }

    public static function th(string $class = ''): Element
    {
        $el = new Element('th');
        if ($class !== '') {
            $el->attrs()->classAdd($class);
        }
        return $el;
    }

    public static function td(string $class = ''): Element
    {
        $el = new Element('td');
        if ($class !== '') {
            $el->attrs()->classAdd($class);
        }
        return $el;
    }

    public static function select(string $name = '', string $class = ''): Element
    {
        $el = new Element('select');
        if ($name !== '') {
            $el->attrs()->add('name', $name);
        }
        if ($class !== '') {
            $el->attrs()->classAdd($class);
        }
        return $el;
    }

    public static function option(string $value = '', bool $selected = false): Element
    {
        $el = new Element('option');
        if ($value !== '') {
            $el->attrs()->add('value', $value, true);
        }
        if ($selected) {
            $el->attrs()->add('selected', 'selected');
        }
        return $el;
    }

    public static function textarea(string $name = '', string $value = '', string $class = ''): Element
    {
        $el = new Element('textarea');
        if ($name !== '') {
            $el->attrs()->add('name', $name);
        }
        if ($class !== '') {
            $el->attrs()->classAdd($class);
        }
        if ($value !== '') {
            $el->content($value);
        }
        return $el;
    }

    public static function label(string $for = '', string $class = ''): Element
    {
        $el = new Element('label');
        if ($for !== '') {
            $el->attrs()->add('for', $for);
        }
        if ($class !== '') {
            $el->attrs()->classAdd($class);
        }
        return $el;
    }
}
