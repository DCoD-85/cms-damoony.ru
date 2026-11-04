<?php

declare(strict_types=1);

namespace Damoony\Db\DataProvider\Template;

interface TemplateInterface
{
    /**
     * @param array<string, mixed> $args
     */
    public static function compile(array $args): string;
}
