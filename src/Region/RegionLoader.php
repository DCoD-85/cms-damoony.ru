<?php

declare(strict_types=1);

namespace Damoony\Region;

use Damoony\Core\Config;
use Damoony\Domain\DomainContext;
use Damoony\Files\Paths;

final class RegionLoader
{
    public function __construct(
        private readonly Paths $paths,
        private readonly ?Config $site = null,
    ) {
    }

    public function load(DomainContext $domain): RegionRegistry
    {
        $registry = new RegionRegistry();
        $baseDir = $this->paths->domain($domain->real) . '/.alliasglobal';

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

            // Проверяем конфиг (по умолчанию true)
            $isFillerEnabled = true;
            if ($this->site !== null) {
                $isFillerEnabled = (bool) $this->site->getByKey(['fillers', $domain->real, $domain->allias, $item], true);
            }

            // Загружаем базовый
            if (is_file($baseFile)) {
                require_once $baseFile;
                $baseClass = $domain->namespace . '\\alliasglobal_' . $item;
                if (class_exists($baseClass, false) && is_subclass_of($baseClass, RegionInterface::class)) {
                    $registry->add(new $baseClass(), true);
                }
            }

            // Загружаем филлер
            if ($isFillerEnabled && is_file($fillerFile)) {
                require_once $fillerFile;
                $fillerClass = $domain->namespace . '\\alliasglobal_' . $item . '_' . $domain->allias;
                if (class_exists($fillerClass, false) && is_subclass_of($fillerClass, RegionInterface::class)) {
                    $registry->add(new $fillerClass(), false);
                }
            }

            // Проверяем глобальное отключение из конфига
            if ($this->site !== null) {
                $isRegionDisabled = $this->site->getByKey(['regions', $domain->real, $domain->allias, $item], true) === false;
                if ($isRegionDisabled) {
                    $registry->disable($item);
                }
            }
        }

        return $registry;
    }
}
