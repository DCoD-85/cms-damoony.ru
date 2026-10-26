<?php

declare(strict_types=1);

namespace Damoony\Admin;

use Damoony\Ui\Builder;
use Damoony\Ui\Document;
use Damoony\Ui\Element;

final class View
{
    public function __construct(
        private readonly string $title = 'Damoony Admin',
        private readonly string $lang = 'ru-ru',
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $content, array $data = []): string
    {
        $doc = new Document();
        $doc->lang($this->lang);
        $doc->title((string) ($data['title'] ?? $this->title));

        $style = new Element('style');
        $style->content(self::css());
        $doc->head()->append($style);

        $doc->body()->append($this->header());
        $main = Builder::div('admin-main');
        $main->append(\Damoony\Ui\Text::raw($content));
        $doc->body()->append($main);

        return $doc->release();
    }

    private function header(): Element
    {
        $header = Builder::div('admin-header');
        $header->append(Builder::a('/admin/', 'brand')->content('Damoony Admin'));

        if ($this->authWidget !== null) {
            $header->append($this->authWidget);
        }

        return $header;
    }

    private ?Element $authWidget = null;

    public function setAuthWidget(?Element $widget): self
    {
        $this->authWidget = $widget;
        return $this;
    }

    public static function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function css(): string
    {
        return <<<'CSS'
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #f5f5f7; color: #1d1d1f; }
        .admin-header { display: flex; align-items: center; justify-content: space-between; padding: 12px 24px; background: #1d1d1f; color: #fff; }
        .admin-header .brand { color: #fff; text-decoration: none; font-weight: 600; }
        .admin-header a { color: #fff; margin-left: 16px; text-decoration: none; }
        .admin-header a:hover { text-decoration: underline; }
        .admin-main { padding: 24px; max-width: 960px; margin: 0 auto; }
        .admin-card { background: #fff; border-radius: 8px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
        .admin-card h1 { margin-top: 0; font-size: 22px; }
        .admin-form { display: grid; gap: 12px; max-width: 360px; }
        .admin-form label { font-weight: 500; font-size: 14px; }
        .admin-form input { padding: 8px 10px; border: 1px solid #d2d2d7; border-radius: 6px; font-size: 14px; }
        .admin-form button { padding: 10px 16px; background: #0071e3; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; }
        .admin-form button:hover { background: #0062c4; }
        .error { color: #c00; padding: 10px 12px; background: #fdecea; border-radius: 6px; margin-bottom: 12px; }
        .success { color: #0a7f3f; padding: 10px 12px; background: #e7f6ec; border-radius: 6px; margin-bottom: 12px; }
        CSS;
    }
}