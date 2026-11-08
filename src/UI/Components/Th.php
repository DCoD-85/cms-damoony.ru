<?php

declare(strict_types=1);

namespace Damoony\UI\Components;

use Damoony\UI\Element;

class Th extends Element
{
    public function __construct(string $text = '')
    {
        parent::__construct('th');
        if ($text !== '') {
            $this->content($text);
        }
    }
}
