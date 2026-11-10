<?php

declare(strict_types=1);

namespace Damoony\UI\Components;

use Damoony\UI\Element;
use Damoony\UI\Node;

class Td extends Element
{
    public function __construct()
    {
        parent::__construct('td');
        $this->attrs()->classAdd('ui-td');
    }
    public function add(Node $child): self
    {
        $this->append($child);
        return $this;
    }
}
