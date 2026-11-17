<?php
declare(strict_types=1);
namespace {{namespace}};
use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class blocksglobal_top implements RegionInterface
{
    public function name(): string { return 'top'; }
    public function enabled(): bool { return true; }

    public function render(RegionContext $context): Node
    {
        $wrap = Builder::div('dt_inner');
        $wrap->append(Builder::p()->content($context->lang('block.top')));

        $siteConfig = $context->service('site_config');

        $langs = ['ru', 'en'];
        if ($siteConfig instanceof \Damoony\Core\Settings\SiteConfig) {
            $alias = $siteConfig->project($context->domain->real)?->alias($context->domain->allias);
            $configured = $alias?->langs ?? [];
            if ($configured !== []) { $langs = $configured; }
        }
        
        $t = $context->service('i18n');
        $currentPath = $context->request->path;
        $pathSegments = explode('/', trim($currentPath, '/'));
        $pathLang = $pathSegments[0] ?? '';
        $currentLang = in_array($pathLang, $langs, true)
            ? $pathLang
            : ($t instanceof \Damoony\Core\I18n\Translator ? $t->current() : $context->domain->language);
        $pathWithoutLang = $currentPath;
        if (str_starts_with($currentPath, '/' . $currentLang)) {
            $pathWithoutLang = substr($currentPath, strlen('/' . $currentLang));
        }
        if ($pathWithoutLang === '') { $pathWithoutLang = '/'; }

        $nav = Builder::div('dt_top_nav');
        $nav->append(Builder::div('dt_logo')->content($context->lang('name')));

        $mainMenu = Builder::ul('dt_main_menu');
        $mainMenu->append(Builder::li()->append(Builder::a('/' . $currentLang . '/')->content($context->lang('menu.home'))));
        $mainMenu->append(Builder::li()->append(Builder::a('/' . $currentLang . '/about/')->content($context->lang('menu.about'))));
        $nav->append($mainMenu);

        $authMenu = Builder::ul('dt_auth_menu');
        $auth = $context->service('auth');
        if ($auth instanceof \Damoony\Data\Auth\AuthInterface && $auth->isAuthenticated()) {
            $user = $auth->user();
            $name = (string) ($user['login'] ?? $context->lang('profile.title'));
            $authMenu->append(Builder::li()->append(Builder::a('/' . $currentLang . '/profile/')->content($name)));
            $authMenu->append(Builder::li()->append(Builder::a('/' . $currentLang . '/logout/')->content($context->lang('menu.logout'))));
        } else {
            $authMenu->append(Builder::li()->append(Builder::a('/' . $currentLang . '/login/')->content($context->lang('menu.login'))));
            $authMenu->append(Builder::li()->append(Builder::a('/' . $currentLang . '/registration/')->content($context->lang('menu.register'))));
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
            $allowedSkins = array_values(array_filter($allowedSkins, static fn (string $d): bool => is_dir($aliasSkinDir . $d)));
            sort($allowedSkins);
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
