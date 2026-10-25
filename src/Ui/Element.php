<?php

declare(strict_types=1);

namespace Damoony\Ui;

final class Element implements Node
{
    /** @var list<Node|string> */
    private array $children = [];

    private ?Element $parent = null;

    private readonly Attrs $attrs;

    public function __construct(
        private readonly string $tag,
        private readonly bool $selfClosing = false,
    ) {
        $this->attrs = new Attrs();
    }

    public function tag(): string
    {
        return $this->tag;
    }

    public function attrs(): Attrs
    {
        return $this->attrs;
    }

    /** @return list<Node|string> */
    public function children(): array
    {
        return $this->children;
    }

    public function append(Node|string $child): self
    {
        if ($child instanceof Element) {
            $child->parent = $this;
        }
        $this->children[] = $child;
        return $this;
    }

    public function prepend(Node|string $child): self
    {
        if ($child instanceof Element) {
            $child->parent = $this;
        }
        array_unshift($this->children, $child);
        return $this;
    }

    public function parent(): ?Element
    {
        return $this->parent;
    }

    public function clear(): self
    {
        $this->children = [];
        return $this;
    }

    public function content(string $text): self
    {
        $this->children[] = $text;
        return $this;
    }

    public function release(): string
    {
        $open = '<' . $this->tag . $this->attrs->release();

        if ($this->selfClosing) {
            return $open . ' />';
        }

        $open .= '>';

        $inner = '';
        foreach ($this->children as $child) {
            $inner .= $child instanceof Node ? $child->release() : $this->esc((string) $child);
        }

        return $open . $inner . '</' . $this->tag . '>';
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}