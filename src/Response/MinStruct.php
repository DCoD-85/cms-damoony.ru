<?php

declare(strict_types=1);

namespace Damoony\Response;

use Damoony\Http\Response;
use Damoony\Ui\Node;

final class MinStruct
{
    private bool $error = false;

    private string $messages = '';

    private ?Node $release = null;

    private bool $formReset = false;

    private ?string $formRedirect = null;

    private ?int $formRedirectTime = null;

    private ?string $referer = null;

    private bool $refererSet = false;

    public function error(bool $value = false): self
    {
        $this->error = $value;
        return $this;
    }

    public function messages(string $html = ''): self
    {
        $this->messages = $html;
        return $this;
    }

    public function release(?Node $node = null): self
    {
        $this->release = $node;
        return $this;
    }

    public function form(bool $reset = false, ?string $redirect = null, ?int $redirectTime = null): self
    {
        $this->formReset = $reset;
        $this->formRedirect = $redirect;
        $this->formRedirectTime = $redirectTime;
        return $this;
    }

    public function referer(?string $referer = null): self
    {
        $this->referer = $referer;
        $this->refererSet = true;
        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $referer = $this->referer;
        if (!$this->refererSet) {
            $referer = isset($_SERVER['HTTP_REFERER']) && is_string($_SERVER['HTTP_REFERER'])
                ? $_SERVER['HTTP_REFERER']
                : null;
        }

        return [
            'error' => $this->error,
            'messages' => $this->messages,
            'release' => $this->release?->release(),
            'form' => [
                'reset' => $this->formReset,
                'redirect' => $this->formRedirect,
                'redirect_time' => $this->formRedirectTime,
            ],
            'referer' => $referer,
        ];
    }

    public function toResponse(int $status = 200): Response
    {
        return Response::json($this->toArray(), $status);
    }
}
