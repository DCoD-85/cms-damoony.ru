<?php

declare(strict_types=1);

namespace Damoony\Ui\Components;

use Damoony\Ui\Builder;
use Damoony\Ui\Element;

class LangSwitcher extends Element
{
    public function __construct(string $currentLang, string $label = 'Language: ')
    {
        parent::__construct('div');
        $this->attrs()->classAdd('lang-switcher');

        $this->append(Builder::span('lang-label')->content($label));

        $currentUri = $_SERVER['REQUEST_URI'] ?? '/admin/';

        foreach (['ru', 'en', 'zh'] as $lang) {
            $newUri = preg_replace('#^/admin/[a-z]{2}#', '/admin/' . $lang, $currentUri);

            if ($newUri === $currentUri && !str_starts_with($currentUri, '/admin/' . $lang)) {
                $newUri = "/admin/{$lang}/dashboard";
            }

            $a = Builder::a($newUri);
            $a->content(strtoupper($lang));
            $a->attrs()->add('data-update', 'all');

            if ($lang === $currentLang) {
                $a->attrs()->classAdd('active');
            }
            $this->append($a);
        }
    }
}
