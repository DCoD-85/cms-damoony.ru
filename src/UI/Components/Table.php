<?php

declare(strict_types=1);

namespace Damoony\UI\Components;

use Damoony\UI\Element;
use Damoony\UI\Node;

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
    protected function accepts(Node|string $child): bool
    {
        return $child instanceof Tbody || $child instanceof Thead || $child instanceof Tr;
    }
}
