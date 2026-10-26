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
	    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $tag = (string) ($data['tag'] ?? 'div');
        $el = new self($tag, (bool) ($data['selfClosing'] ?? false));

        if (!empty($data['class'])) {
            $el->attrs()->classAdd((string) $data['class']);
        }
        if (!empty($data['attrs']) && is_array($data['attrs'])) {
            foreach ($data['attrs'] as $k => $v) {
                if (is_string($k) && is_string($v)) {
                    $el->attrs()->add($k, $v);
                }
            }
        }
        
        if (isset($data['slot'])) {
            $el->append(new Slot((string) $data['slot']));
        }

        if (!empty($data['children']) && is_array($data['children'])) {
            foreach ($data['children'] as $child) {
                if (is_array($child)) {
                    $el->append(self::fromArray($child));
                }
            }
        }

        return $el;
    }

    /**
     * @return list<Slot>
     */
    public function findSlots(): array
    {
        $slots = [];
        foreach ($this->children as $child) {
            if ($child instanceof Slot) {
                $slots[] = $child;
            } elseif ($child instanceof Element) {
                $slots = array_merge($slots, $child->findSlots());
            }
        }
        return $slots;
    }

    public function replaceChild(Node $old, Node $new): self
    {
        $newChildren = [];
        foreach ($this->children as $child) {
            if ($child === $old) {
                $newChildren[] = $new;
            } else {
                $newChildren[] = $child;
            }
        }
        $this->children = $newChildren;
        return $this;
    }
}