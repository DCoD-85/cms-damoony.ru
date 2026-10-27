<?php

declare(strict_types=1);

namespace Damoony\Ui;

final class Text implements Node
{
    public function __construct(
        private readonly string $value,
        private readonly bool $raw = false,
    ) {
    }

    public static function escaped(string $value): self
    {
        return new self($value, false);
    }

    public static function raw(string $value): self
    {
        return new self($value, true);
    }

    public function tag(): string
    {
        return '';
    }

    public function attrs(): Attrs
    {
        throw new \LogicException('Text has no attributes');
    }

    /** @return list<Node|string> */
    public function children(): array
    {
        return [];
    }

    public function append(Node|string $child): self
    {
        throw new \LogicException('Cannot append to text');
    }

    public function prepend(Node|string $child): self
    {
        throw new \LogicException('Cannot prepend to text');
    }

    public function parent(): ?Element
    {
        return null;
    }

    public function release(): string
    {
        if ($this->raw) {
            return $this->value;
        }

        return htmlspecialchars($this->value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
