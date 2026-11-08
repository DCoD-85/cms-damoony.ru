<?php

declare(strict_types=1);

namespace damoony_ru\www;

use Damoony\Db\DataProvider\BasicCrudProvider;

final class ProductsProvider extends BasicCrudProvider
{
    public function name(): string
    {
        return 'products';
    }

    public function table(): string
    {
        return 'products';
    }

    protected function writable(): array
    {
        return ['title', 'price', 'description', 'status', 'slug'];
    }
}
