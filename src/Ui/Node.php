<?php

declare(strict_types=1);

namespace Damoony\UI;

interface Node
{
    public function tag(): string;

    public function attrs(): Attrs;

    /** @return list<Node|string> */
    public function children(): array;

    public function append(Node|string $child): self;

    public function prepend(Node|string $child): self;

    public function parent(): ?Element;

    public function release(): string;
}
