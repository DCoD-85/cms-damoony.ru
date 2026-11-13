<?php
declare(strict_types=1);
namespace damoony4_ru\www;
use Damoony\UI\Algoritm\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class algoritm_lang_tests_scheme extends Page
{
    protected function content(): Node|null
    {
        $this->context->meta->addCss('/media/damoony4.ru/www/css/custom.css');
        $this->useScheme('custom_scheme');
        return Builder::div()->content('Эта страница использует кастомную схему без сайдбаров!');
    }
}