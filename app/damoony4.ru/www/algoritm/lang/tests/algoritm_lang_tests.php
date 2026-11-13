<?php
declare(strict_types=1);
namespace damoony4_ru\www;
use Damoony\UI\Algoritm\Page;
use Damoony\UI\Builder;
use Damoony\UI\Node;

final class algoritm_lang_tests extends Page
{
    protected function content(): Node|null
    {
        return Builder::div('dt_tests')->content('Добро пожаловать в раздел тестов движка!');
    }
}