<?php

declare(strict_types=1);

namespace damoony_ru\protonews;

use Damoony\Db\DataProvider\BasicCrudProvider;

final class CategoriesProvider extends BasicCrudProvider
{
    public function name(): string { return 'categories'; }
    public function table(): string { return 'categories'; }
    protected function writable(): array
    {
        return ['name'];
    }
}
