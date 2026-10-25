<?php

declare(strict_types=1);

namespace Damoony\Ui;

final class Document
{
    private readonly Element $html;
    private readonly Element $head;
    private readonly Element $body;

    public function __construct(
        private readonly string $lang = 'ru-ru',
    ) {
        $this->html = new Element('html');
        $this->html->attrs()->add('lang', $lang);

        $this->head = new Element('head');
        $this->body = new Element('body');

        $this->html->append($this->head)->append($this->body);

        $charset = new Element('meta', true);
        $charset->attrs()->add('charset', 'utf-8');
        $this->head->append($charset);
    }

    public function html(): Element
    {
        return $this->html;
    }

    public function head(): Element
    {
        return $this->head;
    }

    public function body(): Element
    {
        return $this->body;
    }

    public function lang(): string
    {
        return $this->lang;
    }

    public function setLang(string $lang): self
    {
        $this->html->attrs()->add('lang', $lang);
        return $this;
    }

    public function title(string $title): self
    {
        $titleEl = new Element('title');
        $titleEl->content($title);
        $this->head->append($titleEl);
        return $this;
    }

    public function release(): string
    {
        return "<!DOCTYPE html>\n" . $this->html->release();
    }
}