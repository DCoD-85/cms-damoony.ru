<?php

declare(strict_types=1);

namespace Damoony\Ui;

final class Form extends Component
{
    /** @var list<Component|Node> */
    private array $children = [];
    private string $action = '';
    private string $method = 'POST';
    private string $enctype = 'application/x-www-form-urlencoded';
    private string $csrfField = '';
    private string $submitText = 'Отправить';
    private bool $showSubmit = true;

    public static function create(string $action = '', string $method = 'POST'): self
    {
        $form = new self();
        $form->action = $action;
        $form->method = strtoupper($method);
        return $form;
    }

    public function method(string $method): self
    {
        $this->method = strtoupper($method);
        return $this;
    }

    public function action(string $action): self
    {
        $this->action = $action;
        return $this;
    }

    public function enctype(string $enctype): self
    {
        $this->enctype = $enctype;
        return $this;
    }

    public function csrf(string $csrfHtml): self
    {
        $this->csrfField = $csrfHtml;
        return $this;
    }

    public function submit(string $text): self
    {
        $this->submitText = $text;
        return $this;
    }

    public function hideSubmit(): self
    {
        $this->showSubmit = false;
        return $this;
    }

    public function add(Component|Node $child): self
    {
        $this->children[] = $child;
        return $this;
    }

    public function build(): Node
    {
        $form = new Element('form');
        $form->attrs()->add('method', $this->method);
        if ($this->action !== '') {
            $form->attrs()->add('action', $this->action);
        }
        if ($this->enctype !== 'application/x-www-form-urlencoded') {
            $form->attrs()->add('enctype', $this->enctype);
        }
        $form->attrs()->classAdd('form');

        if ($this->csrfField !== '') {
            $form->append(Text::raw($this->csrfField));
        }

        foreach ($this->children as $child) {
            $form->append($child instanceof Component ? $child->build() : $child);
        }

        if ($this->showSubmit) {
            $btn = new Element('button');
            $btn->attrs()->add('type', 'submit');
            $btn->attrs()->classAdd('btn btn-primary');
            $btn->content($this->submitText);
            $form->append($btn);
        }

        return $form;
    }
}
