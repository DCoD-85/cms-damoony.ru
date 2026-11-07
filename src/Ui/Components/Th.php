<?php

declare(strict_types=1);

namespace Damoony\Ui\Components;

use Damoony\Ui\Element;

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
