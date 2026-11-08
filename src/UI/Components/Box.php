<?php

declare(strict_types=1);

namespace Damoony\UI\Components;

use Damoony\UI\Element;
use Damoony\UI\Node;

class Box extends Element
{
    public function __construct(string $class = '')
    {
        parent::__construct('div');
        $this->attrs()->classAdd('ui-box');
        if ($class !== '') {
            $this->attrs()->classAdd($class);
        }
    }
    public function add(Node $child): self
    {
        $this->append($child);
        return $this;
    }
}
