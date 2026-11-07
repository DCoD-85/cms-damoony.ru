<?php

declare(strict_types=1);

namespace Damoony\Admin\Pages\superadmin;

use Damoony\Algoritm\Page;
use Damoony\Http\Response;
use Damoony\Ui\Node;
use Damoony\Ui\Builder;
use Damoony\Ui\Components\Card;
use Damoony\Files\Paths;
use Damoony\Scheme\SchemeLoader;
use Damoony\Scheme\SchemeRenderer;

final class algoritm_lang_domains extends Page
{
    public function default(): Node|Response|null
    {
        $lang = $this->args['lang'] ?? 'ru';
        $role = $this->context->service('admin_role') ?? 'superadmin';
        $t = $this->context->service('i18n');

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
        
        $content = new Card($t->t('admin.tab.domains'));
        $content->add(Builder::p()->content("Здесь будет список доменов."));
        $this->setContent('center', $content);

        $paths = $this->context->service('paths');
        $loader = new SchemeLoader($paths, null, $role);
        $manifest = $loader->load($this->context->domain, 'admin');

        return (new SchemeRenderer())->render($manifest, $this->regions(), $this->context);
    }
}
