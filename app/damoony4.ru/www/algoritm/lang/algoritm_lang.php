<?php
declare(strict_types=1);
namespace damoony4_ru\www;
use Damoony\UI\Algoritm\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class algoritm_lang extends Page
{
    protected function content(): Node|null
    {
        return Builder::div('dt_home')->content('Добро пожаловать на главную страницу Developer!');
    }
}