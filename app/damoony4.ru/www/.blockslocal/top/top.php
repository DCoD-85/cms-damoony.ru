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
        $wrap->append(Builder::p()->content('это локальный блок top'));
        $site = $context->service('site');
        $langs = ['ru', 'en'];
        if ($site instanceof \Damoony\Core\Config) {
            $configured = $site->getByKey(['alliases_langs', $context->domain->real, $context->domain->allias], []);
            if (is_array($configured) && $configured !== []) { $langs = $configured; }
            else { $configured = $site->getByKey(['domains_langs', $context->domain->real], ['ru', 'en']); if (is_array($configured) && $configured !== []) { $langs = $configured; } }
        }
        $switcher = Builder::div('lang-switcher');
        foreach ($langs as $l) {
            $switcher->append(Builder::a('/' . $l . '/')->content(strtoupper($l)));
        }
        $wrap->append($switcher);
        return $wrap;
    }
}
