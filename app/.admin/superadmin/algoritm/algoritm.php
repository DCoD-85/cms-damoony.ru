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
use Damoony\Files\Paths;
use Damoony\Scheme\SchemeLoader;
use Damoony\Scheme\SchemeRenderer;

final class algoritm extends Page
{
    public function default(): Node|Response|null
    {
        $domain = $this->context->domain->domain;
        $alias = $this->context->domain->allias;
        $role = $this->context->service('admin_role') ?? 'superadmin';

        // 1. Собираем меню
        $menu = Builder::ul('admin-menu');
        $menu->append(Builder::li()->append(Builder::a('/admin/', 'active')->content('Дашборд')));
        $menu->append(Builder::li()->append(Builder::a('/admin/?p=domain')->content('Домены')));
        $menu->append(Builder::li()->append(Builder::a('/admin/?p=profile')->content('Профиль')));
        $menu->append(Builder::li()->append(Builder::a('/admin/?p=logout')->content('Выход')));
        $this->setContent('menu', $menu);

        // 2. Заголовок
        $this->setContent('title', Builder::h(1)->content('Панель управления'));

        // 3. Контент
        $content = Builder::div('');
        
        $welcome = new Card('Панель управления');
        $welcome->add(Builder::p()->content("Вы вошли как: {$role}"));
        $welcome->add(Builder::p()->content("Текущий контекст: {$domain} (/{$alias})"));
        
        $actions = Builder::div('admin-actions');
        $btn = new Link('/admin/pages', 'Управление страницами');
        $btn->attrs()->classAdd('ui-btn');
        $actions->append($btn);
        $welcome->add($actions);
        $content->append($welcome);

        $table = new Table();
        $thead = new Thead();
        $tr_head = new Tr();
        $tr_head->add(new Th('Домен'));
        $tr_head->add(new Th('Статус'));
        $thead->add($tr_head);
        
        $tbody = new Tbody();
        $tr_body = new Tr();
        $tr_body->add((new Td())->content($domain));
        $tr_body->add((new Td())->content('Активен'));
        $tbody->add($tr_body);
        
        $table->add($thead);
        $table->add($tbody);
        $content->append($table);

        $this->setContent('center', $content);

        // 4. Рендерим Схему
        $paths = $this->context->service('paths');
        if (!$paths instanceof Paths) {
            return Response::internalError('Paths service missing');
        }

        $loader = new SchemeLoader($paths, null, $role);
        $manifest = $loader->load($this->context->domain, 'admin');

        $renderer = new SchemeRenderer();

        return $renderer->render($manifest, $this->regions(), $this->context);
    }
}
