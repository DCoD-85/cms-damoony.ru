<?php

declare(strict_types=1);

namespace Damoony\Admin;

interface AuthInterface
{
    public function attempt(string $login, string $password): bool;

    public function isLoggedIn(): bool;

    public function login(): string;

    public function logout(): void;
}
