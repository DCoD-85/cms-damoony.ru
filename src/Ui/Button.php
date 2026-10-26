<?php

declare(strict_types=1);

namespace Damoony\Ui;

final class Button extends Component
{
    private string $text = '';
    private string $type = 'submit';
    private string $class = 'btn btn-primary';
    private string $name = '';
    private string $value = '';

    public static function submit(string $text): self
    {
        $btn = new self();
        $btn->text = $text;
        $btn->type = 'submit';
        return $btn;
    }

    public static function button(string $text): self
    {
        $btn = new self();
        $btn->text = $text;
        $btn->type = 'button';
        return $btn;
    }

    public function class(string $class): self
    {
        $this->class = $class;
        return $this;
    }

    public function name(string $name, string $value = ''): self
    {
        $this->name = $name;
        $this->value = $value;
        return $this;
    }

    public function build(): Node
    {
        $btn = new Element('button');
        $btn->attrs()->add('type', $this->type);
        if ($this->class !== '') {
            $btn->attrs()->classAdd($this->class);
        }
        if ($this->name !== '') {
            $btn->attrs()->add('name', $this->name);
            $btn->attrs()->add('value', $this->value);
        }
        $btn->content($this->text);
        return $btn;
    }
}