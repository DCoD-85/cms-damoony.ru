<?php

declare(strict_types=1);

namespace Damoony\Ui;

abstract class Component
{
    abstract public function build(): Node;

    public function release(): string
    {
        return $this->build()->release();
    }
}