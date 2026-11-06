<?php
declare(strict_types=1);
namespace Damoony\Ui\Components;
use Damoony\Ui\Element;

class Link extends Element
{
    public function __construct(string $href, string $text = '')
    {
        parent::__construct('a');
        $this->attrs()->add('href', $href);
        $this->attrs()->classAdd('ui-link');
        if ($text !== '') $this->content($text);
    }
}
