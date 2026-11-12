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
        $wrap = Builder::div('site-local-top');
        $wrap->append(Builder::p()->content($context->lang('block.top')));
        $site = $context->service('site');
        $langs = ['ru', 'en'];
        if ($site instanceof \Damoony\Core\Config) {
            $configured = $site->getByKey(['alliases_langs', $context->domain->real, $context->domain->allias], []);
            if (is_array($configured) && $configured !== []) { $langs = $configured; }
            else { $configured = $site->getByKey(['domains_langs', $context->domain->real], ['ru', 'en']); if (is_array($configured) && $configured !== []) { $langs = $configured; } }
        }
        $t = $context->service('i18n');
        $currentLang = $t instanceof \Damoony\Core\I18n\Translator ? $t->current() : $context->domain->language;
        $currentPath = $context->request->path;
        $pathWithoutLang = $currentPath;
        if (str_starts_with($currentPath, '/' . $currentLang)) {
            $pathWithoutLang = substr($currentPath, strlen('/' . $currentLang));
        }
        if ($pathWithoutLang === '') {
            $pathWithoutLang = '/';
        }
        $switcher = Builder::div('lang-switcher');
        foreach ($langs as $l) {
            $url = '/' . $l . ($pathWithoutLang === '/' ? '/' : $pathWithoutLang);
            $a = Builder::a($url)->content(strtoupper($l));
            if ($l === $currentLang) { $a->attrs()->classAdd('active'); }
            $switcher->append($a);
        }
        $wrap->append($switcher);
        return $wrap;
    }
}
