<?php

declare(strict_types=1);

namespace Damoony\Ui\Components;

use Damoony\Ui\Element;
use Damoony\Ui\Node;

class Td extends Element
{
    public function __construct()
    {
        parent::__construct('td');
    }
    public function add(Node $child): self
    {
        $this->append($child);
        return $this;
    }
}
