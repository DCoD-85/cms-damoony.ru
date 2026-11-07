<?php

declare(strict_types=1);

namespace Damoony\Ui\Components;

use Damoony\Ui\Element;
use Damoony\Ui\Node;

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
