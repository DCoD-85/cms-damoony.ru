<?php

declare(strict_types=1);

namespace Damoony\Ui\Components;

use Damoony\Ui\Element;
use Damoony\Ui\Node;

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
