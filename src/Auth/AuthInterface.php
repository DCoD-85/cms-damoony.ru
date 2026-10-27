<?php

declare(strict_types=1);

namespace Damoony\Auth;

interface AuthInterface
{
    public function isAuthenticated(): bool;

    /** @return array<string, mixed>|null */
    public function user(): ?array;

    public function login(string $login, string $password): bool;

    public function logout(): void;
}
