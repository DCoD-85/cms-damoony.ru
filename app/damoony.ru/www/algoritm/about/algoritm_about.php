<?php

declare(strict_types=1);

namespace damoony_ru\www;

use Damoony\Algoritm\Algoritm;
use Damoony\Http\Response;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;
use Damoony\Files\Paths;
use Damoony\Scheme\SchemeLoader;
use Damoony\Scheme\SchemeRenderer;

final class algoritm_about extends Algoritm
{
    public function default(): Node|Response|null
    {
        $this->regions()->useGlobal('top');
        $this->regions()->useGlobal('test_v');
        $this->regions()->useGlobal('left');
        $this->regions()->useGlobal('center');
        $this->regions()->useGlobal('right');
        $this->regions()->useGlobal('bottom');
        $this->regions()->useGlobal('footer');
        $this->regions()->useGlobal('policy');

        $paths = $this->context->service('paths');
        if (!$paths instanceof Paths) {
            $paths = new Paths(dirname(__DIR__, 5));
        }
        $schemeLoader = new SchemeLoader($paths);
        $manifest = $schemeLoader->load($this->context->domain, $this->context->domain->scheme);
        $renderer = new SchemeRenderer();
        return $renderer->render($manifest, $this->regions(), $this->context);
    }
}
