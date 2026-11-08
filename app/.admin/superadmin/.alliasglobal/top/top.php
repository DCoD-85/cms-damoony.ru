<?php

declare(strict_types=1);

namespace Damoony\Admin\Pages\superadmin;

use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;
use Damoony\UI\Builder;
use Damoony\UI\Node;
use Damoony\UI\Components\LangSwitcher;

final class alliasglobal_top implements RegionInterface
{
    public function name(): string { return 'top'; }
    public function enabled(): bool { return true; }

    public function render(RegionContext $context): Node
    {
        $t = $context->service('i18n');
        $lang = $t->current();
        $path = $context->request->path;

        // Левая часть: Логотип
        $left = Builder::div('admin-top-left');
        $left->append(Builder::div('admin-logo')->content('Damoony Admin'));

        // Центральная часть: Меню
        $center = Builder::div('admin-top-center');
        $menu = Builder::ul('admin-menu');
        
        $isDash = str_contains($path, '/dashboard');
        $menu->append(Builder::li()->append(Builder::a("/admin/{$lang}/dashboard", $isDash ? 'active' : '')->content($t->t('admin.dash.title'))));
        
        $isDom = str_contains($path, '/domains');
        $menu->append(Builder::li()->append(Builder::a("/admin/{$lang}/domains", $isDom ? 'active' : '')->content($t->t('admin.tab.domains'))));
        
        $isProf = str_contains($path, '/profile');
        $menu->append(Builder::li()->append(Builder::a("/admin/{$lang}/profile", $isProf ? 'active' : '')->content($t->t('admin.profile.title'))));
        
        $center->append($menu);

        // Правая часть: Языки и Выход
        $right = Builder::div('admin-top-right');
        $right->append(new LangSwitcher($lang, $t->t('admin.dash.lang')));
        
        $logout = Builder::a('/admin/logout', 'logout-btn')->content($t->t('admin.logout'));
        $right->append($logout);

        // Собираем шапку
        $container = Builder::div('admin-top-inner');
        $container->append($left);
        $container->append($center);
        $container->append($right);

        return $container;
    }
}
