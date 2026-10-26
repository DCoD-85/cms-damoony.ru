<?php

declare(strict_types=1);

namespace Damoony\Ui;

final class Document
{
    private readonly Element $html;
    private readonly Element $head;
    private readonly Element $body;
    public readonly Meta $meta;

    public function __construct(?Meta $meta = null)
    {
        $this->meta = $meta ?? new Meta();

        $this->html = new Element('html');
        $this->head = new Element('head');
        $this->body = new Element('body');

        $this->html->append($this->head)->append($this->body);

        $charset = new Element('meta', true);
        $charset->attrs()->add('charset', 'utf-8');
        $this->head->append($charset);
    }

    public function lang(string $lang): self
    {
        $this->html->attrs()->add('lang', $lang);
        return $this;
    }

    public function title(string $title): self
    {
        $this->meta->setTitle($title);
        return $this;
    }

    public function meta(): Meta
    {
        return $this->meta;
    }

    public function html(): Element { return $this->html; }
    public function head(): Element { return $this->head; }
    public function body(): Element { return $this->body; }

    public function release(): string
    {
        $metaRaw = $this->meta->render();
        if ($metaRaw !== '') {
            $this->head->append(Text::raw($metaRaw));
        }

        return "<!DOCTYPE html>\n" . $this->html->release();
    }
}