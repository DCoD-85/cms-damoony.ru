<?php

declare(strict_types=1);

namespace Damoony\Ui\Components;

use Damoony\Ui\Element;
use Damoony\Ui\Node;

class Thead extends Element
{
    public function __construct()
    {
        parent::__construct('thead');
    }
    public function add(Node $child): self
    {
        $this->append($child);
        return $this;
    }
}
