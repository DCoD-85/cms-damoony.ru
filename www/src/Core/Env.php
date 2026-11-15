<?php

declare(strict_types=1);

namespace Damoony\Core;

enum Env: string
{
    case Dev = 'dev';
    case Test = 'test';
    case Staging = 'staging';
    case Prod = 'prod';

    public static function fromServer(): self
    {
        $raw = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: 'prod';
        return self::tryFrom((string) $raw) ?? self::Prod;
    }

    public function isDev(): bool
    {
        return $this === self::Dev;
    }

    public function isTest(): bool
    {
        return $this === self::Test;
    }

    public function isStaging(): bool
    {
        return $this === self::Staging;
    }

    public function isProd(): bool
    {
        return $this === self::Prod;
    }
}
