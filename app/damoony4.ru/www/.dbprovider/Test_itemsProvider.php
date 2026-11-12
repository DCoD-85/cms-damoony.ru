<?php

declare(strict_types=1);

namespace damoony4_ru\www;

use Damoony\Data\Db\DataProvider\BasicCrudProvider;

final class Test_itemsProvider extends BasicCrudProvider
{
    public function name(): string
    {
        return 'test_items';
    }

    public function table(): string
    {
        return 'test_items';
    }

    protected function writable(): array
    {
        return [
            'title', 'body'
        ];
    }
}
