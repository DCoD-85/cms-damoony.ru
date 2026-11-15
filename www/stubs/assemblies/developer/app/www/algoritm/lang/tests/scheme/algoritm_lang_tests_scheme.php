<?php
declare(strict_types=1);
namespace {{namespace}}\{{alias}};
use Damoony\UI\Algoritm\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class algoritm_lang_tests_scheme extends Page
{
    protected function content(): Node|null
    {
        $this->context->meta->addCss('/media/{{real}}/{{alias}}/css/custom.css');
        $this->useScheme('custom_scheme');
        return Builder::div()->content('Эта страница использует кастомную схему без сайдбаров!');
    }
}