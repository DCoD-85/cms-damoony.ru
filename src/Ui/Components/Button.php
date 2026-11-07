<?php

declare(strict_types=1);

namespace Damoony\Ui\Components;

use Damoony\Ui\Element;

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
}
