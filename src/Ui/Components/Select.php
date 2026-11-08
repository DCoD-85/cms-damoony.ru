<?php

declare(strict_types=1);

namespace Damoony\UI\Components;

use Damoony\UI\Element;
use Damoony\UI\Node;

class Select extends Element
{
    public function __construct(string $name = '')
    {
        parent::__construct('select');
        $this->attrs()->classAdd('ui-select');
        if ($name !== '') {
            $this->attrs()->add('name', $name);
        }
    }

    public function add(Node $option): self
    {
        $this->append($option);
        return $this;
    }
}
