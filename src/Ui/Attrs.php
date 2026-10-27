<?php

declare(strict_types=1);

namespace Damoony\Ui;

final class Attrs
{
    /** @var array<string, string> */
    private array $attributes = [];

    /** @var list<string> */
    private array $classes = [];

    /** @var array<string, string> */
    private array $styles = [];

    /** @var array<string, string> */
    private array $data = [];

    public function add(string $name, string $value, bool $empty = false): self
    {
        if ($value === '' && !$empty) {
            return $this;
        }
        $this->attributes[$name] = $value;
        return $this;
    }

    public function get(string $name): ?string
    {
        return $this->attributes[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->attributes);
    }

    public function remove(string $name): self
    {
        unset($this->attributes[$name]);
        return $this;
    }

    public function classAdd(string ...$names): self
    {
        foreach ($names as $chunk) {
            foreach (preg_split('/\s+/', trim($chunk)) ?: [] as $cls) {
                if ($cls !== '' && !in_array($cls, $this->classes, true)) {
                    $this->classes[] = $cls;
                }
            }
        }
        return $this;
    }

    public function classRemove(string $name): self
    {
        $this->classes = array_values(array_filter($this->classes, fn ($c) => $c !== $name));
        return $this;
    }

    /** @return list<string> */
    public function classes(): array
    {
        return $this->classes;
    }

    public function styleAdd(string $pair): self
    {
        foreach (explode(';', $pair) as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '' || !str_contains($chunk, ':')) {
                continue;
            }
            [$k, $v] = explode(':', $chunk, 2);
            $k = trim($k);
            $v = trim($v);
            if ($k !== '') {
                $this->styles[$k] = $v;
            }
        }
        return $this;
    }

    public function styleRemove(string $name): self
    {
        unset($this->styles[$name]);
        return $this;
    }

    /** @return array<string, string> */
    public function styles(): array
    {
        return $this->styles;
    }

    public function dataAdd(string $name, mixed $value): self
    {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->data[$name] = $encoded === false ? '' : $encoded;
        return $this;
    }

    public function dataRemove(string $name): self
    {
        unset($this->data[$name]);
        return $this;
    }

    /** @return array<string, string> */
    public function data(): array
    {
        return $this->data;
    }

    public function release(): string
    {
        $parts = [];

        if ($this->classes !== []) {
            $parts[] = 'class="' . $this->esc(implode(' ', $this->classes)) . '"';
        }

        if ($this->styles !== []) {
            $css = '';
            foreach ($this->styles as $k => $v) {
                $css .= $k . ':' . $v . ';';
            }
            $parts[] = 'style="' . $this->esc($css) . '"';
        }

        foreach ($this->attributes as $name => $value) {
            $parts[] = $name . '="' . $this->esc($value) . '"';
        }

        foreach ($this->data as $name => $value) {
            $parts[] = 'data-' . $name . '="' . $this->esc($value) . '"';
        }

        return $parts === [] ? '' : ' ' . implode(' ', $parts);
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
