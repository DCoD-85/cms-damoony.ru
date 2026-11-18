<?php

declare(strict_types=1);

namespace Damoony\UI\Region;

use Damoony\Core\Config;
use Damoony\Core\Domain\DomainContext;
use Damoony\Core\Files\Paths;

final class RegionLoader
{
    private readonly FillerState $fillerState;

    public function __construct(
        private readonly Paths $paths,
        private readonly ?Config $site = null,
        ?FillerState $fillerState = null,
    ) {
        $this->fillerState = $fillerState ?? new FillerState($paths);
    }

    public function load(DomainContext $domain): RegionRegistry
    {
        $registry = new RegionRegistry();

        $baseDir = $this->paths->domain($domain->real) . '/.blocksglobal';
        $namespace = $domain->namespace;

        // Site-режим: local + global base; legacy filler — fallback.
        $localDir = $this->paths->allias($domain->real, $domain->allias) . '/.blockslocal';

        $names = [];
        if (is_dir($baseDir)) {
            foreach (scandir($baseDir) ?: [] as $e) {
                if ($e !== '.' && $e !== '..' && is_dir($baseDir . '/' . $e)) {
                    $names[$e] = true;
                }
            }
        }
        if (is_dir($localDir)) {
            foreach (scandir($localDir) ?: [] as $e) {
                if ($e !== '.' && $e !== '..' && is_dir($localDir . '/' . $e)) {
                    $names[$e] = true;
                }
            }
        }
        if ($names === []) {
            return $registry;
        }
        $items = array_keys($names);
        sort($items);

        foreach ($items as $item) {
            $domainBlock = $baseDir . '/' . $item;
            $localBlock = $localDir . '/' . $item;

            // Base (глобальный блок) — .blocksglobal/{item}/{item}.php
            $baseFile = null;
            if (is_file($domainBlock . '/' . $item . '.php')) {
                $baseFile = $domainBlock . '/' . $item . '.php';
            }

            // Local (new model): .blockslocal/{item}/{item}.php, класс blockslocal_{alias}_{item}
            $localFile = null;
            $localClass = null;
            if (is_file($localBlock . '/' . $item . '.php')) {
                $localFile = $localBlock . '/' . $item . '.php';
                $localClass = $namespace . '\\blockslocal_' . $domain->allias . '_' . $item;
            }

            // Legacy filler: .blocksglobal/{item}/{item}_{alias}.php
            $legacyFile = null;
            $legacyClass = null;
            if (is_file($domainBlock . '/' . $item . '_' . $domain->allias . '.php')) {
                $legacyFile = $domainBlock . '/' . $item . '_' . $domain->allias . '.php';
                $legacyClass = $namespace . '\\blocksglobal_' . $item . '_' . $domain->allias;
            }

            // Источник истины — .blockslocal/{item}/{item}.rules.json (FillerState).
            $isFillerEnabled = $this->fillerState->isEnabled($domain->real, $domain->allias, $item);

            if ($baseFile !== null) {
                $baseClass = $namespace . '\\blocksglobal_' . $item;
                if (!class_exists($baseClass, false)) {
                    require_once $baseFile;
                }
                if (class_exists($baseClass, false) && is_subclass_of($baseClass, RegionInterface::class)) {
                    $registry->add(new $baseClass(), true);
                }
            }

            // Приоритет: local (new) → legacy. Регистрируется как non-base (override).
            if ($isFillerEnabled) {
                if ($localFile !== null && $localClass !== null) {
                    if (!class_exists($localClass, false)) {
                        require_once $localFile;
                    }
                    if (class_exists($localClass, false) && is_subclass_of($localClass, RegionInterface::class)) {
                        $registry->add(new $localClass(), false);
                    }
                } elseif ($legacyFile !== null && $legacyClass !== null) {
                    if (!class_exists($legacyClass, false)) {
                        require_once $legacyFile;
                    }
                    if (class_exists($legacyClass, false) && is_subclass_of($legacyClass, RegionInterface::class)) {
                        $registry->add(new $legacyClass(), false);
                    }
                }
            }

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
