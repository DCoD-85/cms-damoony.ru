<?php

declare(strict_types=1);

namespace Damoony\Admin\Pages\superadmin;

use Damoony\Admin\AuthInterface;
use Damoony\UI\Builder;
use Damoony\UI\Components\LangSwitcher;
use Damoony\UI\Node;
use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;

final class blocksglobal_top implements RegionInterface
{
    public function name(): string
    {
        return 'top';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function render(RegionContext $context): Node
    {
        $lang = (string) ($context->domain->allias ?: 'ru');
        $auth = $context->service('auth');
        $isAuth = $auth instanceof AuthInterface && $auth->isLoggedIn();

        $wrap = Builder::div('admin-top-inner');

        $t = $context->service('i18n');
        $tr = $t instanceof \Damoony\Core\I18n\Translator ? $t : null;
        $tx = static fn (string $k, string $d): string => $tr ? $tr->t($k) : $d;

        $url = $context->service('url');
        $routing = $context->service('routing');

        $menu = Builder::ul('admin-menu');
        if (!$isAuth) {
            $menu->append(Builder::li()->append(
                Builder::a($url->internal('/login'))->content($tx('admin.nav.login', 'Login'))
            ));
        } else {
            $menu->append(Builder::li()->append(
                Builder::a($url->internal('/'))->content($tx('admin.nav.home', 'Home'))
            ));
            $menu->append(Builder::li()->append(
                Builder::a($url->internal('/domains'))->content($tx('admin.nav.domains', 'Domains'))
            ));
            $menu->append(Builder::li()->append(
                Builder::a($url->internal('/profile'))->content($tx('admin.nav.profile', 'Profile'))
            ));
            $menu->append(Builder::li()->append(
                Builder::a($url->internal('/profile/logout'))->content($tx('admin.nav.logout', 'Logout'))
            ));
        }
        $wrap->append($menu);

        $label = $tx('admin.dash.lang', 'Language');
        $switcher = Builder::div('lang-switcher');
        $switcher->append(Builder::span('lang-label')->content($label));
        foreach ($routing->domain->supportedLanguages as $l) {
            $a = Builder::a($url->switchLang($l))->content(strtoupper($l));
            $a->attrs()->add('data-update', 'all');
            if ($l === $routing->lang) {
                $a->attrs()->classAdd('active');
            }
            $switcher->append($a);
        }
        $wrap->append($switcher);

        return $wrap;
    }
}
