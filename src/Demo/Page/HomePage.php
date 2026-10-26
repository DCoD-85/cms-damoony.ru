<?php

declare(strict_types=1);

namespace Damoony\Demo\Page;

use Damoony\Algoritm\Algoritm;
use Damoony\Files\Paths;
use Damoony\Http\Response;
use Damoony\Region\RegionLoader;
use Damoony\Scheme\SchemeRenderer;
use Damoony\Ui\Node;

class HomePage extends Algoritm
{
    public function default(): Node|Response|null
    {
        // Берем Paths из контекста (или создаем для локального запуска)
        $paths = $this->context->service('paths');
        if (!$paths instanceof Paths) {
            $paths = new Paths(dirname(__DIR__, 3));
        }

        $loader = new RegionLoader($paths);
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