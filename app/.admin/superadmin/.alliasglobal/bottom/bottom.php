<?php

declare(strict_types=1);

namespace Damoony\Admin\Pages\superadmin;

use Damoony\Region\RegionContext;
use Damoony\Region\RegionInterface;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;

final class alliasglobal_bottom implements RegionInterface
{
    public function name(): string { return 'bottom'; }
    public function enabled(): bool { return true; }

    public function render(RegionContext $context): Node
    {
        // Пока пустой футер, сюда можно будет добавить статус версии или время
        return Builder::div('admin-bottom-inner')->content('Damoony Engine v3.1');
    }
}
