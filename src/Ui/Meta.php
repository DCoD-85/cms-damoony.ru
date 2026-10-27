<?php

declare(strict_types=1);

namespace Damoony\Ui;

final class Meta
{
    private string $title = '';
    /** @var array<string, string> */
    private array $css = [];
    /** @var array<string, string> */
    private array $js = [];
    /** @var array<string, array<string, string>> */
    private array $metaTags = [];

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function addCss(string $url, string $id = ''): self
    {
        $key = $id !== '' ? $id : $url;
        $this->css[$key] = $url;
        return $this;
    }

    public function addJs(string $url, string $id = ''): self
    {
        $key = $id !== '' ? $id : $url;
        $this->js[$key] = $url;
        return $this;
    }

    public function addMeta(string $name, string $content): self
    {
        $this->metaTags[$name] = ['name' => $name, 'content' => $content];
        return $this;
    }

    public function render(): string
    {
        $html = '';

        if ($this->title !== '') {
            $html .= '<title>' . htmlspecialchars($this->title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</title>' . "\n";
        }

        foreach ($this->metaTags as $tag) {
            $html .= '<meta name="' . htmlspecialchars($tag['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" content="' . htmlspecialchars($tag['content'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" />' . "\n";
        }

        foreach ($this->css as $url) {
            $html .= '<link rel="stylesheet" href="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" />' . "\n";
        }

        foreach ($this->js as $url) {
            $html .= '<script src="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></script>' . "\n";
        }

        return $html;
    }
}
