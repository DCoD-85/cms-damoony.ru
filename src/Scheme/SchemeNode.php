<?php

declare(strict_types=1);

namespace Damoony\Scheme;

final class SchemeNode
{
    /**
     * @param list<SchemeNode> $children
     */
    public function __construct(
        public readonly string $tag = 'div',
        public readonly string $class = '',
        public readonly string $layout = 'vertical',
        public readonly bool $enabled = true,
        public readonly bool $return = false,
        public readonly array $children = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $children = [];
        foreach ((array) ($data['children'] ?? []) as $child) {
            if (is_array($child)) {
                $children[] = self::fromArray($child);
            }
        }

        return new self(
            tag: (string) ($data['tag'] ?? 'div'),
            class: (string) ($data['class'] ?? ''),
            layout: (string) ($data['layout'] ?? 'vertical'),
            enabled: (bool) ($data['enabled'] ?? true),
            return: (bool) ($data['return'] ?? false),
            children: $children,
        );
    }
}