<?php

declare(strict_types=1);

namespace Damoony\Region;

use Damoony\Domain\DomainContext;
use Damoony\Files\Paths;

final class RegionLoader
{
    public function __construct(
        private readonly Paths $paths,
    ) {
    }

    public function load(DomainContext $domain): RegionRegistry
    {
        $registry = new RegionRegistry();
        $baseDir = $this->paths->domain($domain->logic, $domain->real) . '/alliasglobal';

        if (!is_dir($baseDir)) {
            return $registry;
        }

        $items = scandir($baseDir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || !is_dir($baseDir . '/' . $item)) {
                continue;
            }

            $baseFile = $baseDir . '/' . $item . '/' . $item . '.php';
            $fillerFile = $baseDir . '/' . $item . '/' . $item . '_' . $domain->allias . '.php';

            $fileToLoad = null;
            $className = '';

            // Проверяем филлер (top_visuo)
            if (is_file($fillerFile)) {
                $fileToLoad = $fillerFile;
                $className = 'alliasglobal_' . $item . '_' . $domain->allias;
            } 
            // Если филлера нет, берем базовый (top)
            elseif (is_file($baseFile)) {
                $fileToLoad = $baseFile;
                $className = 'alliasglobal_' . $item;
            }

            if ($fileToLoad !== null) {
                require_once $fileToLoad;
                $fullClass = $domain->namespace . '\\' . $className;

                if (class_exists($fullClass, false) && is_subclass_of($fullClass, RegionInterface::class)) {
                    /** @var RegionInterface $region */
                    $region = new $fullClass();
                    $registry->add($region);
                }
            }
        }

        return $registry;
    }
}