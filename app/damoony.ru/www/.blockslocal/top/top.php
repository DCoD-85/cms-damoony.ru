<?php
declare(strict_types=1);
namespace damoony_ru;
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
        $wrap = Builder::div('dt_inner');
        $wrap->append(Builder::p()->content($context->lang('block.top')));

        $site = $context->service('site');
        
        $langs = ['ru', 'en'];
        if ($site instanceof \Damoony\Core\Config) {
            $configured = $site->getByKey(['alliases', $context->domain->real, $context->domain->allias, 'langs'], []);
            if (is_array($configured) && $configured !== []) { $langs = $configured; }
        }
        
        $t = $context->service('i18n');
        $currentLang = $t instanceof \Damoony\Core\I18n\Translator ? $t->current() : $context->domain->language;
        $currentPath = $context->request->path;
        $pathWithoutLang = $currentPath;
        if (str_starts_with($currentPath, '/' . $currentLang)) {
            $pathWithoutLang = substr($currentPath, strlen('/' . $currentLang));
        }
        if ($pathWithoutLang === '') { $pathWithoutLang = '/'; }

        $nav = Builder::div('dt_top_nav');
        $nav->append(Builder::div('dt_logo')->content('Damoony'));
        
        $mainMenu = Builder::ul('dt_main_menu');
        $mainMenu->append(Builder::li()->append(Builder::a('/' . $currentLang . '/')->content($context->lang('menu.home'))));
        $mainMenu->append(Builder::li()->append(Builder::a('/' . $currentLang . '/about/')->content($context->lang('menu.about'))));
        $mainMenu->append(Builder::li()->append(Builder::a('/' . $currentLang . '/tests/')->content($context->lang('menu.tests'))));
        $nav->append($mainMenu);

        $authMenu = Builder::ul('dt_auth_menu');
        $auth = $context->service('auth');
        if ($auth instanceof \Damoony\Data\Auth\AuthInterface && $auth->isAuthenticated()) {
            $user = $auth->user();
            $name = $user['login'] ?? 'Профиль';
            $authMenu->append(Builder::li()->append(Builder::a('/' . $currentLang . '/profile/')->content($name)));
            $authMenu->append(Builder::li()->append(Builder::a('/' . $currentLang . '/logout/')->content('Выйти')));
        } else {
            $authMenu->append(Builder::li()->append(Builder::a('/' . $currentLang . '/login/')->content($context->lang('menu.login'))));
        }
        $nav->append($authMenu);
        
        $wrap->append($nav);

        $switchers = Builder::div('dt_switchers');

        // Языки
        $langSwitcher = Builder::div('lang-switcher');
        $langSwitcher->append(Builder::span('label')->content('Lang: '));
        foreach ($langs as $l) {
            $url = '/' . $l . ($pathWithoutLang === '/' ? '/' : $pathWithoutLang);
            $a = Builder::a($url)->content(strtoupper($l));
            if ($l === $currentLang) { $a->attrs()->classAdd('active'); }
            $langSwitcher->append($a);
        }
        $switchers->append($langSwitcher);

        // Скины (ЧИСТЫЕ ссылки, без ?return=)
        $paths = $context->service('paths');
        $aliasSkinDir = $paths instanceof \Damoony\Core\Files\Paths ? $paths->media($context->domain->real, $context->domain->allias) . '/skin/' : '';
        $allowedSkins = is_dir($aliasSkinDir) ? array_values(array_diff(scandir($aliasSkinDir), ['.', '..'])) : [];
        if (is_array($allowedSkins)) {
            $allowedSkins = array_filter($allowedSkins, fn($d) => is_dir($aliasSkinDir . $d));
        }
        
        $currentSkin = $context->domain->skin;
        if (count($allowedSkins) > 1) {
            $skinSwitcher = Builder::div('skin-switcher');
            $skinSwitcher->append(Builder::span('label')->content('Skin: '));
            foreach ($allowedSkins as $s) {
                $url = '/' . $currentLang . '/skin/' . $s . '/';
                $a = Builder::a($url)->content(ucfirst($s));
                if ($s === $currentSkin) { $a->attrs()->classAdd('active'); }
                $skinSwitcher->append($a);
            }
            $switchers->append($skinSwitcher);
        }

        $wrap->append($switchers);
        return $wrap;
    }
}