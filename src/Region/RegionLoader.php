<?php

declare(strict_types=1);

namespace Damoony\Region;

use Damoony\Domain\DomainContext;
use Damoony\Files\Paths;
use RuntimeException;

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

            $className = 'alliasglobal_' . $item;
            $file = $baseDir . '/' . $item . '/' . $item . '.php';

            if (!is_file($file)) {
                continue;
            }

            require_once $file;

            // Дописываем неймспейс домена (например, damoony_ru\alliasglobal_top)
			$fullClass = $domain->namespace . '\\' . $className;

            if (class_exists($fullClass, false) && is_subclass_of($fullClass, RegionInterface::class)) {
                /** @var RegionInterface $region */
                $region = new $fullClass();
                $registry->add($region);
            }
        }

        return $registry;
    }
}