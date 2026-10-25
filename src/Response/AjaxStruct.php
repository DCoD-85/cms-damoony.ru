<?php

declare(strict_types=1);

namespace Damoony\Response;

use Damoony\Http\Response;

final class AjaxStruct
{
    private string $address = '';

    private string $header = '';

    /** @var array<string, string> */
    private array $layers = [
        'top' => '',
        'left' => '',
        'center' => '',
        'right' => '',
        'bottom' => '',
    ];

    private bool $error = false;

    public function address(string $address): self
    {
        $this->address = $address;
        return $this;
    }

    public function header(string $html): self
    {
        $this->header = $html;
        return $this;
    }

    public function layer(string $name, string $html): self
    {
        if (array_key_exists($name, $this->layers)) {
            $this->layers[$name] = $html;
        }
        return $this;
    }

    public function center(string $html): self
    {
        return $this->layer('center', $html);
    }

    public function error(bool $value = false): self
    {
        $this->error = $value;
        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $layers = [];
        foreach ($this->layers as $name => $html) {
            $layers[$name] = ['layer' => 'dt_' . $name, 'html' => $html];
        }

        return [
            'error' => $this->error,
            'address' => $this->address,
            'header' => $this->header,
            'layers' => $layers,
        ];
    }

    public function toResponse(int $status = 200): Response
    {
        return Response::json($this->toArray(), $status);
    }
}