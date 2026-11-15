<?php

declare(strict_types=1);

namespace Damoony\UI\Response;

use Damoony\Core\Http\Response;
use Damoony\UI\Node;

final class MinStruct
{
    private bool $ok = true;

    /** @var list<array{type: string, text: string}> */
    private array $messages = [];

    /** @var array<string, string> */
    private array $blocks = [];

    private bool $formReset = false;

    /** @var list<string> */
    private array $formKeep = [];

    private ?string $formRedirect = null;

    private ?int $formRedirectTime = null;

    private bool $reload = false;

    public function ok(bool $value = true): self
    {
        $this->ok = $value;
        return $this;
    }

    public function error(bool $value = true): self
    {
        $this->ok = !$value;
        return $this;
    }

    public function message(string $type, string $text): self
    {
        $this->messages[] = ['type' => $type, 'text' => $text];
        return $this;
    }

    /** @param list<array{type: string, text: string}> $messages */
    public function messages(array $messages): self
    {
        $this->messages = $messages;
        return $this;
    }

    public function block(string $name, string $html): self
    {
        $this->blocks[$name] = $html;
        return $this;
    }

    public function release(?Node $node = null): self
    {
        if ($node !== null) {
            $this->blocks['center'] = $node->release();
        }
        return $this;
    }

    /** @param list<string> $keep */
    public function form(bool $reset = false, array $keep = [], ?string $redirect = null, ?int $redirectTime = null): self
    {
        $this->formReset = $reset;
        $this->formKeep = $keep;
        $this->formRedirect = $redirect;
        $this->formRedirectTime = $redirectTime;
        return $this;
    }

    public function reload(bool $value = true): self
    {
        $this->reload = $value;
        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'messages' => $this->messages,
            'blocks' => $this->blocks,
            'form' => [
                'reset' => $this->formReset,
                'keep' => $this->formKeep,
                'redirect' => $this->formRedirect,
                'redirect_time' => $this->formRedirectTime,
            ],
            'reload' => $this->reload,
        ];
    }

    public function toResponse(int $status = 200): Response
    {
        return Response::json($this->toArray(), $status);
    }
}
