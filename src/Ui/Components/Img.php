<?php

declare(strict_types=1);

namespace Damoony\UI\Components;

use Damoony\UI\Element;

class Img extends Element
{
    public function __construct(string $src, string $alt = '')
    {
        parent::__construct('img', true);
        $this->attrs()->add('src', $src);
        if ($alt !== '') {
            $this->attrs()->add('alt', $alt);
        }
        $this->attrs()->classAdd('ui-img');
    }
}
