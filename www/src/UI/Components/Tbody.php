<?php

declare(strict_types=1);

namespace Damoony\UI\Components;

use Damoony\UI\Element;
use Damoony\UI\Node;

class Tbody extends Element
{
    public function __construct()
    {
        parent::__construct('tbody');
        $this->attrs()->classAdd('ui-tbody');
    }
    public function add(Node $child): self
    {
        $this->append($child);
        return $this;
    }
}
