<?php

declare(strict_types=1);

namespace Damoony\Admin\Pages\superadmin;

use Damoony\Algoritm\Page;
use Damoony\Http\Response;
use Damoony\Ui\Node;
use Damoony\Ui\Builder;
use Damoony\Ui\Components\Card;
use Damoony\Ui\Components\Table;
use Damoony\Ui\Components\Thead;
use Damoony\Ui\Components\Tbody;
use Damoony\Ui\Components\Tr;
use Damoony\Ui\Components\Th;
use Damoony\Ui\Components\Td;
use Damoony\Ui\Components\Link;

final class algoritm extends Page
{
    public function default(): Node|Response|null
    {
        // Подключаем стили и скрипты админки
        $this->context->meta->addCss('/media/admin/admin.css', 'admin');
        $this->context->meta->addJs('/media/admin/admin.js', 'admin');

        $domain = $this->context->domain->domain;
        $alias = $this->context->domain->allias;
        $role = $_SESSION['admin_context']['role'] ?? 'superadmin';

        // Карточка приветствия
        $welcome = new Card('Панель управления');
        $welcome->add(Builder::p()->content("Вы вошли как: {$role}"));
        $welcome->add(Builder::p()->content("Текущий контекст: {$domain} (/{$alias})"));
        
        $actions = Builder::div('admin-actions');
        $btn = new Link('/admin/pages', 'Управление страницами');
        $btn->attrs()->classAdd('ui-btn');
        $actions->append($btn);
        $welcome->add($actions);

        // Таблица доменов (просто для примера)
        $table = new Table();
        $thead = new Thead();
        $tr_head = new Tr();
        $tr_head->add(new Th('Домен'));
        $tr_head->add(new Th('Статус'));
        $thead->add($tr_head);
        
        $tbody = new Tbody();
        $tr_body = new Tr();
        $tr_body->add(new Td($domain));
        $tr_body->add(new Td('Активен'));
        $tbody->add($tr_body);
        
        $table->add($thead);
        $table->add($tbody);

        $content = Builder::div('admin-content');
        $content->append($welcome);
        $content->append($table);

        return \Damoony\Admin\Ui\AdminLayout::render('Панель управления', $content);
    }
}
