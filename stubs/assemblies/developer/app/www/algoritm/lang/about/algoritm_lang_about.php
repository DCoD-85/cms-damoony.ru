<?php
declare(strict_types=1);
namespace {{namespace}}\{{alias}};
use Damoony\UI\Algoritm\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class algoritm_lang_about extends Page
{
    protected function content(): Node|null
    {
        return Builder::div('dt_about')->content('Это сборка Developer: полный стек, БД, i18n и CRUD-тесты.');
    }
}