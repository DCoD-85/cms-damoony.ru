<?php
declare(strict_types=1);
namespace {{namespace}};
use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionInterface;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class blockslocal_{{alias}}_top implements RegionInterface
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
            $configured = $site->getByKey(['alliases_langs', $context->domain->real, $context->domain->allias], []);
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

        $switcher = Builder::div('lang-switcher');
        foreach ($langs as $l) {
            $url = '/' . $l . ($pathWithoutLang === '/' ? '/' : $pathWithoutLang);
            $a = Builder::a($url)->content(strtoupper($l));
            if ($l === $currentLang) { $a->attrs()->classAdd('active'); }
            $switcher->append($a);
        }
        $wrap->append($switcher);

        // Skin Switcher
        $skins = [];
        $activeSkin = $context->domain->skin;
        if ($site instanceof \Damoony\Core\Config) {
            $skins = $site->getByKey(['alliases_skins_allowed', $context->domain->real, $context->domain->allias], []);
            if (!is_array($skins)) $skins = [];
        }
        if ($skins !== []) {
            $skinSwitcher = Builder::div('skin-switcher');
            foreach ($skins as $s) {
                $url = '/' . $currentLang . '/skin/' . $s . '/';
                $a = Builder::a($url)->content(ucfirst($s));
                if ($s === $activeSkin) { $a->attrs()->classAdd('active'); }
                $skinSwitcher->append($a);
            }
            $wrap->append($skinSwitcher);
        }

        return $wrap;
    }
}