<?php

declare(strict_types=1);

namespace damoony3test_ru\coffee;

use Damoony\Algoritm\Page;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;
use Damoony\Http\Response;

final class algoritm_about extends Page
{
    public function default(): Node|Response|null
    {
        $content = Builder::div('page-content');

        $el = Builder::h(1)->content('About Us');
        $el->attrs()->classAdd('hero-title');
        $el->attrs()->styleAdd('color: #6b3e26; font-size: 2.5em; text-align: center;');
        $content->append($el);

        $el = Builder::p()->content('Founded in 2024 with a passion for great coffee.');
        $el->attrs()->styleAdd('text-align: center; font-size: 1.2em;');
        $content->append($el);

        $el = Builder::a('/menu')->content('Our Menu');
        $el->attrs()->classAdd('nav-link');
        $el->attrs()->styleAdd('display:inline-block; margin-right:20px; padding:10px; background:#6b3e26; color:#fff; text-decoration:none;');
        $content->append(Builder::p()->append($el));

        $el = Builder::a('/contacts')->content('Contact Us');
        $el->attrs()->classAdd('nav-link');
        $el->attrs()->styleAdd('display:inline-block; padding:10px; background:#6b3e26; color:#fff; text-decoration:none;');
        $content->append(Builder::p()->append($el));

        return $content;
    }
}
