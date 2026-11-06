<?php
declare(strict_types=1);
namespace Damoony\Ui\Components;
use Damoony\Ui\Element;

class Input extends Element
{
    public function __construct(string $type = 'text', string $name = '', string $value = '')
    {
        parent::__construct('input', true);
        $this->attrs()->add('type', $type);
        if ($name !== '') $this->attrs()->add('name', $name);
        if ($value !== '') $this->attrs()->add('value', $value);
        $this->attrs()->classAdd('ui-input');
    }
}
