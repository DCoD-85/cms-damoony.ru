<?php

declare(strict_types=1);

namespace Damoony\UI\Components;

use Damoony\UI\Builder;
use Damoony\UI\Element;
use Damoony\UI\Node;

class Form extends Element
{
    private Element $formNode;

    public function __construct(string $class = '')
    {
        parent::__construct('div');
        $this->attrs()->classAdd('ui-form-wrapper');
        $this->attrs()->classAdd('form_box');
        if ($class !== '') {
            $this->attrs()->classAdd($class);
        }

        $this->formNode = Builder::el('form');
        $this->formNode->attrs()->classAdd('ui-form');
        $this->formNode->attrs()->classAdd('form_form');
        parent::append($this->formNode);
    }

    public function add(Node $child): self
    {
        $this->formNode->append($child);
        return $this;
    }

    public function action(string $url): self
    {
        $this->formNode->attrs()->add('action', $url);
        return $this;
    }

    public function method(string $method = 'POST'): self
    {
        $this->formNode->attrs()->add('method', strtoupper($method));
        return $this;
    }

    public function csrf(\Damoony\Core\Security\Csrf $csrf): self
    {
        $this->add(new Input('hidden', $csrf->fieldName(), $csrf->token()));
        return $this;
    }
}
