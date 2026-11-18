<?php

declare(strict_types=1);

namespace Damoony\UI\Components;

use Damoony\UI\Element;
use Damoony\UI\Node;

class Th extends Element
{
    public function __construct(Node|string $content = '')
    {
        parent::__construct('th');
        $this->attrs()->classAdd('ui-th');
        if ($content !== '') {
            is_string($content) ? $this->content($content) : $this->append($content);
        }
    }
    protected function accepts(Node|string $child): bool
    {
        if (is_string($child)) {
            return true;
        }

        return !($child instanceof Table
            || $child instanceof Tbody
            || $child instanceof Thead
            || $child instanceof Tr
            || $child instanceof Td
            || $child instanceof Th);
    }
}
