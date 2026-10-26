<?php

declare(strict_types=1);

namespace Damoony\Demo\Page;

use Damoony\Algoritm\Algoritm;
use Damoony\Http\Response;
use Damoony\Region\RegionLoader;
use Damoony\Scheme\SchemeRenderer;
use Damoony\Ui\Node;

class HomePage extends Algoritm
{
    public function default(): Node|Response|null
    {
        // Используем загрузчик регионов (он сам найдет hello и footer в папке alliasglobal)
        $loader = new RegionLoader(new \Damoony\Files\Paths(dirname(__DIR__, 4)));
        $regions = $loader->load($this->context->domain);

        $manifest = [
            'tag' => 'div',
            'class' => 'layout',
            'children' => [
                ['tag' => 'section', 'slot' => 'hello'],
                ['tag' => 'footer', 'slot' => 'footer'],
            ],
        ];

        $renderer = new SchemeRenderer($regions);

        return $renderer->render($manifest, $this->context);
    }
}