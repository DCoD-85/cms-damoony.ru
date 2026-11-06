<?php
declare(strict_types=1);
namespace Damoony\Ui\Components;
use Damoony\Ui\Element;

class Textarea extends Element
{
    public function __construct(string $name = '', string $value = '', int $rows = 3)
    {
        parent::__construct('textarea');
        $this->attrs()->classAdd('ui-textarea');
        $this->attrs()->add('rows', (string) $rows);
        if ($name !== '') $this->attrs()->add('name', $name);
        if ($value !== '') $this->content($value);
    }
}
