<?php

declare(strict_types=1);

namespace Damoony\Security;

use Damoony\Session\SessionInterface;

final class Csrf
{
    public function __construct(
        private readonly SessionInterface $session,
        private readonly string $sessionKey = '_csrf',
        private readonly string $fieldName = '_csrf',
    ) {
    }

    public function token(): string
    {
        $token = $this->session->get($this->sessionKey);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->set($this->sessionKey, $token);
        }

        return $token;
    }

    public function fieldName(): string
    {
        return $this->fieldName;
    }

    public function field(): string
    {
        return sprintf(
            '<input type="hidden" name="%s" value="%s" />',
            htmlspecialchars($this->fieldName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($this->token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        );
    }

    /** @param array<string, mixed> $post */
    public function check(array $post): bool
    {
        $token = $this->session->get($this->sessionKey);
        if (!is_string($token) || $token === '') {
            return false;
        }

        $provided = $post[$this->fieldName] ?? null;
        if (!is_string($provided) || $provided === '') {
            return false;
        }

        return hash_equals($token, $provided);
    }
}
