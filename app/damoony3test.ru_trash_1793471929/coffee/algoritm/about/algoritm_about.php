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
        $wrap = Builder::div('page');
        $wrap->append(Builder::h(1)->content('Welcome to Damoony Coffee'));
        $wrap->append(Builder::h(2)->content('The best beans in town'));
        $wrap->append(Builder::img('https://images.unsplash.com/photo-1497935586351-b67a49e012bf?auto=format&fit=crop&w=800&q=80', 'Image'));
        $list = Builder::ul();
        $list->append(Builder::li()->content('Organic Beans'));
        $list->append(Builder::li()->content('Fast Delivery'));
        $list->append(Builder::li()->content('Cozy Atmosphere'));
        $wrap->append($list);
        $wrap->append(Builder::a('/shop', 'btn')->content('Order Now'));
        return $wrap;
    }
}
