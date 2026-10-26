<?php

declare(strict_types=1);

namespace Damoony\Ui;

final class Field extends Component
{
    private string $type = 'text';
    private string $name = '';
    private string $value = '';
    private string $placeholder = '';
    private string $label = '';
    private string $hint = '';
    private string $error = '';
    private bool $required = false;
    private bool $readonly = false;
    private bool $disabled = false;

    public function __construct(string $name, string $type = 'text')
    {
        $this->name = $name;
        $this->type = $type;
    }

    public static function text(string $name): self
    {
        return new self($name, 'text');
    }

    public static function password(string $name): self
    {
        return new self($name, 'password');
    }

    public static function email(string $name): self
    {
        return new self($name, 'email');
    }

    public static function hidden(string $name): self
    {
        return new self($name, 'hidden');
    }

    public function value(string $value): self
    {
        $this->value = $value;
        return $this;
    }

    public function placeholder(string $text): self
    {
        $this->placeholder = $text;
        return $this;
    }

    public function label(string $text): self
    {
        $this->label = $text;
        return $this;
    }

    public function hint(string $text): self
    {
        $this->hint = $text;
        return $this;
    }

    public function error(string $text): self
    {
        $this->error = $text;
        return $this;
    }

    public function required(bool $state = true): self
    {
        $this->required = $state;
        return $this;
    }

    public function readonly(bool $state = true): self
    {
        $this->readonly = $state;
        return $this;
    }

    public function disabled(bool $state = true): self
    {
        $this->disabled = $state;
        return $this;
    }

    public function build(): Node
    {
        $wrap = Builder::div('field field-' . $this->type);

        if ($this->type === 'hidden') {
            $input = new Element('input', true);
            $input->attrs()->add('type', $this->type);
            $input->attrs()->add('name', $this->name);
            $input->attrs()->add('value', $this->value, true);
            return $input;
        }

        if ($this->label !== '') {
            $labelEl = new Element('label');
            $labelEl->attrs()->add('for', 'field_' . $this->name);
            $labelEl->content($this->label);
            if ($this->required) {
                $labelEl->append(Text::raw(' <span class="required">*</span>'));
            }
            $wrap->append($labelEl);
        }

        $input = new Element('input', true);
        $input->attrs()->add('type', $this->type);
        $input->attrs()->add('id', 'field_' . $this->name);
        $input->attrs()->add('name', $this->name);
        $input->attrs()->add('value', $this->value, true);
        if ($this->placeholder !== '') {
            $input->attrs()->add('placeholder', $this->placeholder);
        }
        if ($this->required) {
            $input->attrs()->add('required', 'required');
        }
        if ($this->readonly) {
            $input->attrs()->add('readonly', 'readonly');
        }
        if ($this->disabled) {
            $input->attrs()->add('disabled', 'disabled');
        }
        $wrap->append($input);

        if ($this->hint !== '') {
            $wrap->append(Builder::div('hint')->content($this->hint));
        }
        if ($this->error !== '') {
            $wrap->append(Builder::div('error')->content($this->error));
        }

        return $wrap;
    }
}