<?php

declare(strict_types=1);

namespace Damoony\Ui\Components;

use Damoony\Ui\Element;

class Toggle extends Element
{
    public function __construct(string $name, bool $checked = false)
    {
        parent::__construct('label');
        $this->attrs()->classAdd('ui-toggle');

        $input = new Element('input', true);
        $input->attrs()->add('type', 'checkbox');
        $input->attrs()->add('name', $name);
        if ($checked) {
            $input->attrs()->add('checked', 'checked');
        }

        $slider = new Element('span');
        $slider->attrs()->classAdd('ui-slider');

        $this->append($input);
        $this->append($slider);
    }
}
