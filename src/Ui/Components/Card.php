<?php
declare(strict_types=1);
namespace Damoony\Ui\Components;
use Damoony\Ui\Element;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;

class Card extends Element
{
    private Element $body;
    public function __construct(string $title = '')
    {
        parent::__construct('div');
        $this->attrs()->classAdd('ui-card');
        
        if ($title !== '') {
            $header = Builder::div('ui-card-header')->content($title);
            parent::append($header);
        }
        
        $this->body = Builder::div('ui-card-body');
        parent::append($this->body);
    }
    public function add(Node $child): self
    {
        $this->body->append($child);
        return $this;
    }
}
