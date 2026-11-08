<?php

declare(strict_types=1);

namespace Damoony\UI\Components;

use Damoony\UI\Element;
use Damoony\UI\Node;

class Tr extends Element
{
    public function __construct()
    {
        parent::__construct('tr');
    }
    public function add(Node $child): self
    {
        $this->append($child);
        return $this;
    }
}
