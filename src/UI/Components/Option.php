<?php

declare(strict_types=1);

namespace Damoony\UI\Components;

use Damoony\UI\Element;

class Option extends Element
{
    public function __construct(string $value, string $text, bool $selected = false)
    {
        parent::__construct('option');
        $this->attrs()->add('value', $value);
        if ($selected) {
            $this->attrs()->add('selected', 'selected');
        }
        $this->content($text);
    }
}
