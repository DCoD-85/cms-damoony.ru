<?php
declare(strict_types=1);
namespace Damoony\Ui\Components;
use Damoony\Ui\Element;

class Option extends Element
{
    public function __construct(string $value, string $text, bool $selected = false)
    {
        parent::__construct('option');
        $this->attrs()->add('value', $value);
        if ($selected) $this->attrs()->add('selected', 'selected');
        $this->content($text);
    }
}
