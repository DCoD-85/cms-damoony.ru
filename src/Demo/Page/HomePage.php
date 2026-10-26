<?php

declare(strict_types=1);

namespace Damoony\Demo\Page;

use Damoony\Algoritm\Algoritm;
use Damoony\Http\Response;
use Damoony\Region\RegionRegistry;
use Damoony\Scheme\SchemeRenderer;
use Damoony\Ui\Node;
use Damoony\Demo\Region\HelloRegion;
use Damoony\Demo\Region\FooterRegion;

class HomePage extends Algoritm
{
    public function default(): Node|Response|null
    {
        $regions = new RegionRegistry();
        $regions->add(new HelloRegion())->add(new FooterRegion());

        $manifest = [
            'tag' => 'div',
            'class' => 'layout',
            'children' => [
                ['tag' => 'section', 'slot' => 'hello'], // заменили class на slot
                ['tag' => 'footer', 'slot' => 'footer'],
            ],
        ];

        $renderer = new SchemeRenderer($regions);

        return $renderer->render($manifest, $this->context);
    }
}