<?php

declare(strict_types=1);

namespace Damoony\Routing;

final class RouteMatch
{
    /**
     * @param array<string, string> $args
     */
    public function __construct(
        public readonly bool $found,
        public readonly ?string $classChain = null,
        public readonly ?string $pathRel = null,
        public readonly array $args = [],
        public readonly int $code = 404,
        public readonly bool $auth = false,
        public readonly bool $guest = false,
    ) {
    }

    /** @param array<string, string> $args */
    public static function found(string $classChain, string $pathRel, array $args = [], bool $auth = false, bool $guest = false): self
    {
        return new self(true, $classChain, $pathRel, $args, 200, $auth, $guest);
    }

    public static function notFound(int $code = 404): self
    {
        return new self(false, null, null, [], $code);
    }

    public static function forbidden(): self
    {
        return new self(false, null, null, [], 403);
    }
}
