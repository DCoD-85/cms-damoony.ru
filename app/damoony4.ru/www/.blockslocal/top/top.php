<?php
declare(strict_types=1);
namespace damoony4_ru;
use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class blockslocal_www_top implements RegionInterface
{
    public function name(): string { return 'top'; }
    public function enabled(): bool { return true; }

    public function render(RegionContext $context): Node
    {
        $site = $context->service('site');
        $t = $context->service('i18n');
        $currentLang = $t instanceof \Damoony\Core\I18n\Translator ? $t->current() : $context->domain->language;
        
        $auth = $context->service('auth');
        $isAuth = $auth instanceof \Damoony\Data\Auth\AuthInterface && $auth->isAuthenticated();

        $wrap = Builder::div('dt_inner');
        $wrap->append(Builder::p()->content($context->lang('block.top')));

        $nav = Builder::div('dt_top_nav');
        $nav->append(Builder::div('dt_logo')->content('Damoony'));

        $menu = Builder::ul('dt_main_menu');
        $menu->append(Builder::li()->append(Builder::a('/' . $currentLang . '/')->content($context->lang('menu.home'))));
        $menu->append(Builder::li()->append(Builder::a('/' . $currentLang . '/about/')->content($context->lang('menu.about'))));
        $menu->append(Builder::li()->append(Builder::a('/' . $currentLang . '/tests/')->content($context->lang('menu.tests'))));
        $nav->append($menu);

        $authMenu = Builder::ul('dt_auth_menu');
        if (!$isAuth) {
            $authMenu->append(Builder::li()->append(Builder::a('/' . $currentLang . '/login/')->content($context->lang('menu.login'))));
        } else {
            $user = $auth->user();
            $login = $user['login'] ?? '';
            $authMenu->append(Builder::li()->append(Builder::a('/' . $currentLang . '/profile/')->content($login ?: $context->lang('menu.profile'))));
            $authMenu->append(Builder::li()->append(Builder::a('/' . $currentLang . '/logout/')->content($context->lang('menu.logout'))));
        }
        $nav->append($authMenu);
        $wrap->append($nav);

        $switchers = Builder::div('dt_switchers');

        $langs = ['ru', 'en'];
        if ($site instanceof \Damoony\Core\Config) {
            $configured = $site->getByKey(['alliases_langs', $context->domain->real, $context->domain->allias], []);
            if (is_array($configured) && $configured !== []) { $langs = $configured; }
        }
        $currentPath = $context->request->path;
        $pathWithoutLang = $currentPath;
        if (str_starts_with($currentPath, '/' . $currentLang)) {
            $pathWithoutLang = substr($currentPath, strlen('/' . $currentLang));
        }
        if ($pathWithoutLang === '') { $pathWithoutLang = '/'; }

        $langSwitcher = Builder::div('lang-switcher');
        $langSwitcher->append(Builder::span('label')->content('Lang: '));
        foreach ($langs as $l) {
            $url = '/' . $l . ($pathWithoutLang === '/' ? '/' : $pathWithoutLang);
            $a = Builder::a($url)->content(strtoupper($l));
            if ($l === $currentLang) { $a->attrs()->classAdd('active'); }
            $langSwitcher->append($a);
        }
        $switchers->append($langSwitcher);

        $skins = [];
        $activeSkin = $context->domain->skin;
        if ($site instanceof \Damoony\Core\Config) {
            $skins = $site->getByKey(['alliases_skins_allowed', $context->domain->real, $context->domain->allias], []);
            if (!is_array($skins)) $skins = [];
        }
        if ($skins !== []) {
            $skinSwitcher = Builder::div('skin-switcher');
            $skinSwitcher->append(Builder::span('label')->content('Skin: '));
            foreach ($skins as $s) {
                $url = '/' . $currentLang . '/skin/' . $s . '/';
                $a = Builder::a($url)->content(ucfirst($s));
                if ($s === $activeSkin) { $a->attrs()->classAdd('active'); }
                $skinSwitcher->append($a);
            }
            $switchers->append($skinSwitcher);
        }

        $wrap->append($switchers);
        return $wrap;
    }
}