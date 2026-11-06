<?php

declare(strict_types=1);

namespace Damoony\Admin\Ui;

use Damoony\Ui\Builder;
use Damoony\Ui\Element;
use Damoony\Ui\Node;

class AdminLayout
{
    public static function render(string $title, Node $content): Node
    {
        // Внешняя обертка
        $wrapper = Builder::div('admin-wrapper');

        // Сайдбар
        $sidebar = Builder::div('admin-sidebar');
        $sidebar->append(Builder::div('admin-logo')->content('Damoony Admin'));
        
        $menu = Builder::ul('admin-menu');
        // В будущем это меню можно генерировать динамически
        $menu->append(Builder::li()->append(Builder::a('/admin/', 'active')->content('Дашборд')));
        $menu->append(Builder::li()->append(Builder::a('/admin/?p=domain')->content('Домены')));
        $menu->append(Builder::li()->append(Builder::a('/admin/?p=profile')->content('Профиль')));
        $menu->append(Builder::li()->append(Builder::a('/admin/?p=logout')->content('Выход')));
        
        $sidebar->append($menu);

        // Основная часть
        $main = Builder::div('admin-main');
        $header = Builder::div('admin-header');
        $header->append(Builder::h(1)->content($title));
        $main->append($header);

        $contentArea = Builder::div('admin-content');
        $contentArea->append($content);
        $main->append($contentArea);

        // Собираем все вместе
        $wrapper->append($sidebar);
        $wrapper->append($main);

        return $wrapper;
    }
}
