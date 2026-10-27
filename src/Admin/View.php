<?php

declare(strict_types=1);

namespace Damoony\Admin;

use Damoony\I18n\Translator;
use Damoony\Ui\Builder;
use Damoony\Ui\Document;
use Damoony\Ui\Element;
use Damoony\Ui\Node;
use Damoony\Ui\Text;

final class View
{
    private static ?Translator $i18n = null;
    private static bool $isLoggedIn = false;
    /** @var list<string> */
    private static array $availableLangs = ['ru'];

    public function __construct(
        private readonly string $title = 'Damoony Admin',
        private readonly string $lang = 'ru',
    ) {
    }

    public static function setLoggedIn(bool $state): void
    {
        self::$isLoggedIn = $state;
    }

    public static function setLang(?Translator $lang): void
    {
        self::$i18n = $lang;
    }

    /** @param list<string> $langs */
    public static function setAvailableLangs(array $langs): void
    {
        if ($langs !== []) {
            self::$availableLangs = $langs;
        }
    }

    public static function currentLang(): string
    {
        return self::$i18n?->current() ?? 'ru';
    }

    /** @param array<string, string|int> $params */
    public static function t(string $key, array $params = []): string
    {
        if (self::$i18n === null) {
            return $key;
        }

        return self::$i18n->t('admin.' . $key, $params);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string|Node $content, array $data = []): string
    {
        $doc = new Document();
        $doc->lang($this->lang);
        $doc->title((string) ($data['title'] ?? $this->title));

        $css = new Element('link', true);
        $css->attrs()->add('rel', 'stylesheet');
        $css->attrs()->add('href', '/media/admin/admin.css');
        $doc->head()->append($css);

        $doc->body()->append($this->header());

        $main = Builder::div('admin-main');
        $main->append($content instanceof Node ? $content : Text::raw($content));
        $doc->body()->append($main);

        return $doc->release();
    }

    public static function domainTabs(string $domain, string $active): Element
    {
        $tabs = Builder::div('nav-tabs');

        $items = [
            'settings' => ['tab.settings', '/admin/?p=domain:edit&domain=' . urlencode($domain)],
            'bindings' => ['tab.bindings', '/admin/?p=domain:bindings&domain=' . urlencode($domain)],
            'allias' => ['tab.alliases', '/admin/?p=allias&domain=' . urlencode($domain)],
            'blocks' => ['tab.blocks', '/admin/?p=block&domain=' . urlencode($domain)],
            'schemes' => ['tab.schemes', '/admin/?p=scheme&domain=' . urlencode($domain)],
            'db' => ['tab.db', '/admin/?p=domain:db&domain=' . urlencode($domain)],
        ];

        foreach ($items as $key => $item) {
            [$labelKey, $url] = $item;
            $a = Builder::a($url, 'nav-tab');
            if ($key === $active) {
                $a->attrs()->classAdd('active');
            }
            $a->content(self::t($labelKey));
            $tabs->append($a);
        }

        return $tabs;
    }

    public static function alliasTabs(string $domain, string $allias, string $active): Element
    {
        $tabs = Builder::div('nav-tabs');
        $baseUrl = '/admin/?domain=' . urlencode($domain) . '&allias=' . urlencode($allias);

        $items = [
            'settings' => ['tab.allias_settings', '/admin/?p=allias:edit' . $baseUrl],
            'blocks' => ['tab.fillers', '/admin/?p=filler' . $baseUrl],
            'pages' => ['tab.pages', '/admin/?p=page' . $baseUrl],
            'schemes' => ['tab.allias_schemes', '/admin/?p=allias_scheme' . $baseUrl],
            'db' => ['tab.allias_db', '/admin/?p=allias:db' . $baseUrl],
        ];

        foreach ($items as $key => $item) {
            [$labelKey, $url] = $item;
            $a = Builder::a($url, 'nav-tab');
            if ($key === $active) {
                $a->attrs()->classAdd('active');
            }
            $a->content(self::t($labelKey));
            $tabs->append($a);
        }

        return $tabs;
    }

    private function header(): Element
    {
        $header = Builder::div('admin-header');

        $brand = Builder::div('brand');
        $brand->append(Builder::a('/admin/', 'logo')->content('Damoony'));
        $brand->append(Text::raw(' <span class="sub">' . self::esc(self::t('brand.sub')) . '</span>'));
        $header->append($brand);

        if (self::$isLoggedIn) {
            $p = $_GET['p'] ?? 'dashboard';

            $nav = Builder::el('nav');
            $nav->attrs()->classAdd('admin-nav');

            $navItems = [
                'domain' => ['nav.domains', '/admin/?p=domain'],
                'stub' => ['nav.stubs', '/admin/?p=stub'],
                'security' => ['nav.security', '/admin/?p=security'],
                'settings' => ['nav.settings', '/admin/?p=settings'],
            ];
            foreach ($navItems as $prefix => $item) {
                [$labelKey, $url] = $item;
                $cls = 'nav-link' . (str_starts_with((string) $p, $prefix) ? ' active' : '');
                $nav->append(Builder::a($url, $cls)->content(self::t($labelKey)));
            }
            $header->append($nav);
        }

        $rightPanel = Builder::div('user-panel');

        // Динамический переключатель языков
        $lang = self::currentLang();
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/admin/');
        $query = [];
        $qs = parse_url($uri, PHP_URL_QUERY);
        if (is_string($qs)) {
            parse_str($qs, $query);
        }
        unset($query['lang']);

        $langContainer = Builder::div();
        $langContainer->attrs()->styleAdd('display:flex; gap:12px; align-items:center; margin-right:16px;');

        foreach (self::$availableLangs as $code) {
            $label = strtoupper($code);
            $query['lang'] = $code;
            $href = '/admin/?' . http_build_query($query);

            if ($lang === $code) {
                $activeLang = Builder::span()->content($label);
                $activeLang->attrs()->styleAdd('color:#fff; font-weight:bold; font-size:12px;');
                $langContainer->append($activeLang);
            } else {
                $langLink = Builder::a($href)->content($label);
                $langLink->attrs()->styleAdd('color:#8a93a8; text-decoration:none; font-size:12px;');
                $langContainer->append($langLink);
            }
        }
        $rightPanel->append($langContainer);

        if (self::$isLoggedIn) {
            $dropdown = Builder::div('user-dropdown');
            $trigger = Builder::div('user-trigger');
            $trigger->append(Builder::div('user-avatar')->content('A'));
            $trigger->append(Builder::span('user-name')->content('admin'));
            $dropdown->append($trigger);

            $menu = Builder::div('dropdown-menu');
            $menu->append(Builder::a('/admin/?p=profile', '')->content(self::t('profile.my_profile')));
            $menu->append(Builder::a('/admin/?p=logout', '')->content(self::t('nav.logout')));
            $dropdown->append($menu);

            $rightPanel->append($dropdown);
        }

        $header->append($rightPanel);

        return $header;
    }

    public static function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
