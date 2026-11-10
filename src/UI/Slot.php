<?php

declare(strict_types=1);

namespace Damoony\UI;

use Damoony\Core\Env;

final class Slot implements Node
{
    public function __construct(private readonly string $name)
    {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function tag(): string
    {
        return 'slot';
    }

    public function attrs(): Attrs
    {
        throw new \LogicException('Slot has no attributes');
    }

    /** @return list<Node|string> */
    public function children(): array
    {
        return [];
    }

    public function append(Node|string $child): self
    {
        throw new \LogicException('Cannot append to slot');
    }

    public function prepend(Node|string $child): self
    {
        throw new \LogicException('Cannot prepend to slot');
    }

    public function parent(): ?Element
    {
        return null;
    }

    public function release(): string
    {
        // В dev — видимый маркер (баг схемы: слот не заменён регионом).
        // В prod — пусто (пользователь не должен видеть служебные комментарии).
        if (Env::fromServer()->isDev()) {
            return '<!-- UNRESOLVED SLOT: ' . htmlspecialchars($this->name, ENT_QUOTES) . ' -->';
        }

        return '';
    }
}
