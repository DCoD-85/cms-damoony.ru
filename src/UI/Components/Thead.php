<?php

declare(strict_types=1);

namespace Damoony\UI\Components;

use Damoony\UI\Element;
use Damoony\UI\Node;

class Thead extends Element
{
    public function __construct()
    {
        parent::__construct('thead');
        $this->attrs()->classAdd('ui-thead');
    }
    public function add(Node $child): self
    {
        $this->append($child);
        return $this;
    }
}
