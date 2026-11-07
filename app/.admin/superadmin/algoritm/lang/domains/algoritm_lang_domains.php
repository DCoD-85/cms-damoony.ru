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
use Damoony\Ui\Components\Toggle;
use Damoony\Files\Paths;
use Damoony\Core\Config;
use Damoony\Scheme\SchemeLoader;
use Damoony\Scheme\SchemeRenderer;

final class algoritm_lang_domains extends Page
{
    public function default(): Node|Response|null
    {
        $lang = $this->args['lang'] ?? 'ru';
        $role = $this->context->service('admin_role') ?? 'superadmin';
        $t = $this->context->service('i18n');
        $site = $this->context->service('site');

        // Меню
        $menu = Builder::ul('admin-menu');
        $menu->append(Builder::li()->append(Builder::a("/admin/{$lang}/dashboard")->content($t->t('admin.dash.title'))));
        $menu->append(Builder::li()->append(Builder::a("/admin/{$lang}/domains", 'active')->content($t->t('admin.tab.domains'))));
        $menu->append(Builder::li()->append(Builder::a("/admin/{$lang}/profile")->content($t->t('admin.profile.title'))));
        $menu->append(Builder::li()->append(Builder::a('/admin/logout')->content($t->t('admin.logout'))));
        $this->setContent('menu', $menu);

        // Языки
        $langs = Builder::div('lang-switcher');
        $langs->append(Builder::span('lang-label')->content($t->t('admin.dash.lang') ?? 'Язык: '));
        foreach (['ru', 'en', 'zh'] as $l) {
            $a = Builder::a("/admin/{$l}/domains");
            $a->content(strtoupper($l));
            $a->attrs()->add('data-update', 'all');
            if ($l === $lang) $a->attrs()->classAdd('active');
            $langs->append($a);
        }
        $this->setContent('lang', $langs);

        $this->setContent('title', Builder::h(1)->content($t->t('admin.tab.domains')));

        // Контент
        $content = Builder::div('');
        
        $header = Builder::div('admin-actions');
        $header->append(Builder::h(2)->content($t->t('admin.tab.domains')));
        $btn = new Link("/admin/{$lang}/domains/create", 'Добавить домен');
        $btn->attrs()->classAdd('ui-btn');
        $header->append($btn);
        $content->append($header);

        $table = new Table();
        $thead = new Thead();
        $tr_head = new Tr();
        $tr_head->add(new Th('Домен (Real)'));
        $tr_head->add(new Th('Статус'));
        $tr_head->add(new Th('Действия'));
        $thead->add($tr_head);
        
        $tbody = new Tbody();
        
        $domains = [];
        if ($site instanceof Config) {
            $domainsMap = $site->getByKey(['domains_real'], []);
            if (is_array($domainsMap)) {
                $domains = array_unique(array_values($domainsMap));
            }
        }

        if (empty($domains)) {
            $tr_body = new Tr();
            $tr_body->add((new Td())->attrs()->add('colspan', '3')->content('Домены не найдены.'));
            $tbody->add($tr_body);
        } else {
            foreach ($domains as $domain) {
                if ($domain === '') continue;
                
                $isActive = false;
                if ($site instanceof Config) {
                    $availMap = $site->getByKey(['domains_available'], []);
                    $isActive = is_array($availMap) ? (bool)($availMap[$domain] ?? false) : false;
                }
                
                $tr = new Tr();
                $tr->add((new Td())->content($domain));
                
                // Красивый переключатель с data-атрибутами для JS
                $toggle = new Toggle('domain_active', $isActive);
                $input = $toggle->children()[0];
                if ($input instanceof \Damoony\Ui\Element) {
                    $input->attrs()->add('data-domain', $domain);
                    $input->attrs()->add('data-lang', $lang);
                }
                $tr->add((new Td())->add($toggle));
                
                // Кнопки действий
                $actions = Builder::div('action-buttons');
                $editBtn = new Link("/admin/{$lang}/domain/{$domain}", 'Настроить');
                $editBtn->attrs()->classAdd('ui-btn')->classAdd('ui-btn-sm');
                $actions->append($editBtn);
                
                $tr->add((new Td())->add($actions));
                $tbody->add($tr);
            }
        }
        
        $table->add($thead);
        $table->add($tbody);
        $content->append($table);

        $this->setContent('center', $content);

        $paths = $this->context->service('paths');
        $loader = new SchemeLoader($paths, null, $role);
        $manifest = $loader->load($this->context->domain, 'admin');

        return (new SchemeRenderer())->render($manifest, $this->regions(), $this->context);
    }
}
