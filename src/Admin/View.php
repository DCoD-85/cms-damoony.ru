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

        // Подключаем CSS из файла
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

    private function header(): Element
    {
        $header = Builder::div('admin-header');
        
        $brand = Builder::div('brand');
        $brand->append(Builder::a('/admin/', 'logo')->content('Damoony'));
        $brand->append(Text::raw(' <span class="sub">/ admin</span>'));
        $header->append($brand);

        $nav = Builder::el('nav');
        $nav->attrs()->classAdd('admin-nav');
        $nav->append(Builder::a('/admin/?p=domain', 'nav-link')->content('Домены'));
        $nav->append(Builder::a('/admin/?p=stub', 'nav-link')->content('Заглушки'));
        $nav->append(Builder::a('/admin/?p=security', 'nav-link')->content('Безопасность'));
        $nav->append(Builder::a('/admin/?p=settings', 'nav-link')->content('Настройки'));
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