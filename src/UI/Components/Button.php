<?php

declare(strict_types=1);

namespace Damoony\UI\Components;

use Damoony\UI\Element;
use Damoony\UI\Node;

class Button extends Element
{
    public function __construct(string $text = '', string $type = 'submit')
    {
        parent::__construct('button');
        $this->attrs()->add('type', $type);
        $this->attrs()->classAdd('ui-btn');
        if ($text !== '') {
            $this->content($text);
        }
    }
    protected function accepts(Node|string $child): bool
    {
        return is_string($child);
    }
}
