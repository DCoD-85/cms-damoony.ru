<?php

declare(strict_types=1);

namespace Damoony\Core;

use Damoony\Session\SessionInterface;

final class Flash
{
    private const KEY = '_flash_messages';

    public function __construct(
        private readonly SessionInterface $session,
    ) {
    }

    public function add(string $type, string $text): void
    {
        $messages = $this->session->get(self::KEY, []);
        if (!is_array($messages)) {
            $messages = [];
        }
        $messages[] = ['type' => $type, 'text' => $text];
        $this->session->set(self::KEY, $messages);
    }

    public function success(string $text): void
    {
        $this->add('success', $text);
    }
    public function error(string $text): void
    {
        $this->add('error', $text);
    }
    public function warning(string $text): void
    {
        $this->add('warning', $text);
    }
    public function info(string $text): void
    {
        $this->add('info', $text);
    }

    /**
     * @return list<array{type: string, text: string}>
     */
    public function pull(): array
    {
        $messages = $this->session->get(self::KEY, []);
        $this->session->remove(self::KEY);

        /** @var list<array{type: string, text: string}> $result */
        $result = is_array($messages) ? $messages : [];
        return $result;
    }
}
