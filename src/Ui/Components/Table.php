<?php

declare(strict_types=1);

namespace Damoony\Ui\Components;

use Damoony\Ui\Element;
use Damoony\Ui\Node;

class Table extends Element
{
    public function __construct(string $class = '')
    {
        parent::__construct('table');
        $this->attrs()->classAdd('ui-table');
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
