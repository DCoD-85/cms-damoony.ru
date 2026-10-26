<?php

declare(strict_types=1);

namespace Damoony\Demo\Page;

use Damoony\Algoritm\Algoritm;
use Damoony\Core\Config;
use Damoony\Files\Paths;
use Damoony\Http\Response;
use Damoony\Region\RegionLoader;
use Damoony\Scheme\SchemeLoader;
use Damoony\Scheme\SchemeRenderer;
use Damoony\Ui\Node;

class HomePage extends Algoritm
{
    public function default(): Node|Response|null
    {
        $paths = $this->context->service('paths');
        if (!$paths instanceof Paths) {
            $paths = new Paths(dirname(__DIR__, 3));
        }

        $site = $this->context->service('site');
        if (!$site instanceof Config) {
            $site = null;
        }

        $regionLoader = new RegionLoader($paths, $site);
        $regions = $regionLoader->load($this->context->domain);

        $schemeLoader = new SchemeLoader($paths);
        $manifest = $schemeLoader->load($this->context->domain, $this->context->domain->scheme);

        $renderer = new SchemeRenderer($regions);

        return $renderer->render($manifest, $this->context);
    }
}