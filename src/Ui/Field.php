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
    private bool $checked = false;
    private bool $multiple = false;
    private int $rows = 3;
    private int $cols = 40;
    private string $accept = '';

    /** @var array<string, string> */
    private array $options = [];

    /** @var list<string> */
    private array $selected = [];

    private string $placeholderOption = '';

    public function __construct(string $name, string $type = 'text')
    {
        $this->name = $name;
        $this->type = $type;
    }

    public static function text(string $name): self { return new self($name, 'text'); }
    public static function password(string $name): self { return new self($name, 'password'); }
	public static function datetime(string $name): self { return new self($name, 'datetime-local'); }
    public static function email(string $name): self { return new self($name, 'email'); }
    public static function hidden(string $name): self { return new self($name, 'hidden'); }
    public static function file(string $name): self { return new self($name, 'file'); }
    public static function textarea(string $name): self { return new self($name, 'textarea'); }
    public static function select(string $name): self { return new self($name, 'select'); }
    public static function checkbox(string $name): self { return new self($name, 'checkbox'); }
    public static function radio(string $name): self { return new self($name, 'radio'); }

    public function value(string $value): self { $this->value = $value; return $this; }
    public function placeholder(string $text): self { $this->placeholder = $text; return $this; }
    public function label(string $text): self { $this->label = $text; return $this; }
    public function hint(string $text): self { $this->hint = $text; return $this; }
    public function error(string $text): self { $this->error = $text; return $this; }
    public function required(bool $state = true): self { $this->required = $state; return $this; }
    public function readonly(bool $state = true): self { $this->readonly = $state; return $this; }
    public function disabled(bool $state = true): self { $this->disabled = $state; return $this; }
    public function checked(bool $state = true): self { $this->checked = $state; return $this; }
    public function multiple(bool $state = true): self { $this->multiple = $state; return $this; }
    public function rows(int $rows): self { $this->rows = $rows; return $this; }
    public function cols(int $cols): self { $this->cols = $cols; return $this; }
    public function accept(string $accept): self { $this->accept = $accept; return $this; }

    /** @param array<string, string> $options */
    public function options(array $options): self
    {
        $this->options = $options;
        return $this;
    }

    public function selected(string ...$values): self
    {
        $this->selected = array_values($values);
        return $this;
    }

    public function placeholderOption(string $text): self
    {
        $this->placeholderOption = $text;
        return $this;
    }

    public function build(): Node
    {
        if ($this->type === 'hidden') {
            $input = new Element('input', true);
            $input->attrs()->add('type', 'hidden');
            $input->attrs()->add('name', $this->name);
            $input->attrs()->add('value', $this->value, true);
            return $input;
        }

        $wrap = Builder::div('field field-' . $this->type);

        $isChoice = in_array($this->type, ['checkbox', 'radio'], true);

        if ($this->label !== '' && !$isChoice) {
            $labelEl = new Element('label');
            $labelEl->attrs()->add('for', 'field_' . $this->name);
            $labelEl->content($this->label);
            if ($this->required) {
                $labelEl->append(Text::raw(' <span class="required">*</span>'));
            }
            $wrap->append($labelEl);
        }

        $input = match ($this->type) {
            'textarea' => $this->buildTextarea(),
            'select' => $this->buildSelect(),
            default => $this->buildInput(),
        };

        if ($isChoice) {
            $row = Builder::div('field-choice');
            $row->append($input);
            if ($this->label !== '') {
                $lbl = new Element('label');
                $lbl->attrs()->add('for', (string) $input->attrs()->get('id'));
                $lbl->content($this->label);
                $row->append($lbl);
            }
            $wrap->append($row);
        } else {
            $wrap->append($input);
        }

        if ($this->hint !== '') {
            $wrap->append(Builder::div('hint')->content($this->hint));
        }
        if ($this->error !== '') {
            $wrap->append(Builder::div('error')->content($this->error));
        }

        return $wrap;
    }

    private function buildInput(): Element
    {
        $input = new Element('input', true);
        $input->attrs()->add('type', $this->type);

        $id = 'field_' . $this->name;
        if (in_array($this->type, ['checkbox', 'radio'], true) && $this->value !== '') {
            $id .= '_' . $this->value;
        }
        $input->attrs()->add('id', $id);
        $input->attrs()->add('name', $this->name);

        if (in_array($this->type, ['checkbox', 'radio'], true)) {
            if ($this->value !== '') {
                $input->attrs()->add('value', $this->value);
            }
            if ($this->checked) {
                $input->attrs()->add('checked', 'checked');
            }
        } else {
            $input->attrs()->add('value', $this->value, true);
            if ($this->placeholder !== '') {
                $input->attrs()->add('placeholder', $this->placeholder);
            }
        }

        if ($this->type === 'file' && $this->accept !== '') {
            $input->attrs()->add('accept', $this->accept);
        }

        if ($this->required) { $input->attrs()->add('required', 'required'); }
        if ($this->readonly) { $input->attrs()->add('readonly', 'readonly'); }
        if ($this->disabled) { $input->attrs()->add('disabled', 'disabled'); }

        return $input;
    }

    private function buildTextarea(): Element
    {
        $ta = new Element('textarea');
        $ta->attrs()->add('id', 'field_' . $this->name);
        $ta->attrs()->add('name', $this->name);
        $ta->attrs()->add('rows', (string) $this->rows);
        $ta->attrs()->add('cols', (string) $this->cols);
        if ($this->placeholder !== '') {
            $ta->attrs()->add('placeholder', $this->placeholder);
        }
        if ($this->required) { $ta->attrs()->add('required', 'required'); }
        if ($this->readonly) { $ta->attrs()->add('readonly', 'readonly'); }
        if ($this->disabled) { $ta->attrs()->add('disabled', 'disabled'); }
        $ta->content($this->value);
        return $ta;
    }

    private function buildSelect(): Element
    {
        $sel = new Element('select');
        $sel->attrs()->add('id', 'field_' . $this->name);
        $sel->attrs()->add('name', $this->name . ($this->multiple ? '[]' : ''));
        if ($this->multiple) { $sel->attrs()->add('multiple', 'multiple'); }
        if ($this->required) { $sel->attrs()->add('required', 'required'); }
        if ($this->disabled) { $sel->attrs()->add('disabled', 'disabled'); }

        if ($this->placeholderOption !== '') {
            $opt = new Element('option');
            $opt->attrs()->add('value', '', true);
            $opt->content($this->placeholderOption);
            $sel->append($opt);
        }

        foreach ($this->options as $value => $label) {
            $opt = new Element('option');
            $opt->attrs()->add('value', (string) $value);
            if (in_array((string) $value, $this->selected, true)) {
                $opt->attrs()->add('selected', 'selected');
            }
            $opt->content($label);
            $sel->append($opt);
        }

        return $sel;
    }
}