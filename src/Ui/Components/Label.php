<?php
declare(strict_types=1);
namespace Damoony\Ui\Components;
use Damoony\Ui\Element;

class Label extends Element
{
    public function __construct(string $text = '', string $for = '')
    {
        parent::__construct('label');
        $this->attrs()->classAdd('ui-label');
        if ($for !== '') $this->attrs()->add('for', $for);
        if ($text !== '') $this->content($text);
    }
}
