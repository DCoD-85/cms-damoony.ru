<?php

declare(strict_types=1);

namespace Damoony\Admin;

use Damoony\Ui\Builder;
use Damoony\Ui\Document;
use Damoony\Ui\Element;
use Damoony\Ui\Node;
use Damoony\Ui\Text;

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
    public function render(string|Node $content, array $data = []): string
    {
        $doc = new Document();
        $doc->lang($this->lang);
        $doc->title((string) ($data['title'] ?? $this->title));

        $css = new Element('link', true);
        $css->attrs()->add('rel', 'stylesheet');
        $css->attrs()->add('href', '/media/admin/admin.css');
        $doc->head()->append($css);

        $doc->body()->append($this->header());
        
        $main = Builder::div('admin-main');
        $main->append($content instanceof Node ? $content : Text::raw($content));
        $doc->body()->append($main);

        return $doc->release();
    }

    public static function domainTabs(string $domain, string $active): Element
    {
        $tabs = Builder::div('nav-tabs');
        
        $items = [
            'settings' => ['Настройки', '/admin/?p=domain:edit&domain=' . urlencode($domain)],
            'bindings' => ['Привязанные', '/admin/?p=domain:bindings&domain=' . urlencode($domain)],
            'allias' => ['Алиасы', '/admin/?p=allias&domain=' . urlencode($domain)],
            'blocks' => ['Блоки', '/admin/?p=block&domain=' . urlencode($domain)],
            'schemes' => ['Схемы', '/admin/?p=scheme&domain=' . urlencode($domain)],
            'db' => ['БД домена', '/admin/?p=domain:db&domain=' . urlencode($domain)],
        ];

        foreach ($items as $key => $item) {
            [$label, $url] = $item;
            $a = Builder::a($url, 'nav-tab');
            if ($key === $active) $a->attrs()->classAdd('active');
            $a->content($label);
            $tabs->append($a);
        }

        return $tabs;
    }

    public static function alliasTabs(string $domain, string $allias, string $active): Element
    {
        $tabs = Builder::div('nav-tabs');
        $baseUrl = '/admin/?domain=' . urlencode($domain) . '&allias=' . urlencode($allias);
        
        $items = [
            'settings' => ['Настройки алиаса', '/admin/?p=allias:edit' . $baseUrl],
            'blocks' => ['Блоки (Fillers)', '/admin/?p=filler' . $baseUrl],
            'pages' => ['Страницы', '/admin/?p=page' . $baseUrl],
            'schemes' => ['Схемы алиаса', '/admin/?p=allias_scheme' . $baseUrl],
            'db' => ['БД алиаса', '/admin/?p=allias:db' . $baseUrl],
        ];

        foreach ($items as $key => $item) {
            [$label, $url] = $item;
            $a = Builder::a($url, 'nav-tab');
            if ($key === $active) $a->attrs()->classAdd('active');
            $a->content($label);
            $tabs->append($a);
        }

        return $tabs;
    }

    private function header(): Element
    {
        $header = Builder::div('admin-header');
        
        $brand = Builder::div('brand');
        $brand->append(Builder::a('/admin/', 'logo')->content('Damoony'));
        $brand->append(Text::raw(' <span class="sub">/ admin</span>'));
        $header->append($brand);

        $p = $_GET['p'] ?? '';
        
        $nav = Builder::el('nav');
        $nav->attrs()->classAdd('admin-nav');
        $nav->append(Builder::a('/admin/?p=domain', 'nav-link' . (str_starts_with($p, 'domain') ? ' active' : ''))->content('Домены'));
        $nav->append(Builder::a('/admin/?p=stub', 'nav-link' . (str_starts_with($p, 'stub') ? ' active' : ''))->content('Заглушки'));
        $nav->append(Builder::a('/admin/?p=security', 'nav-link' . (str_starts_with($p, 'security') ? ' active' : ''))->content('Безопасность'));
        $nav->append(Builder::a('/admin/?p=settings', 'nav-link' . (str_starts_with($p, 'settings') ? ' active' : ''))->content('Настройки'));
        $header->append($nav);

        $user = Builder::div('user-panel');
        $user->append(Builder::span('user-name')->content('admin'));
        $user->append(Builder::a('/admin/?p=logout', 'btn-logout')->content('Выйти'));
        $header->append($user);

        return $header;
    }

    public static function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}