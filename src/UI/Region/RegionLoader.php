<?php

declare(strict_types=1);

namespace Damoony\UI\Region;

use Damoony\Core\Config;
use Damoony\Core\Domain\DomainContext;
use Damoony\Core\Files\Paths;

final class RegionLoader
{
    public function __construct(
        private readonly Paths $paths,
        private readonly ?Config $site = null,
        private readonly ?string $adminRole = null,
    ) {
    }

    public function load(DomainContext $domain): RegionRegistry
    {
        $registry = new RegionRegistry();

        $roleDir = null;
        if ($this->adminRole !== null) {
            $baseDir = rtrim($this->paths->root(), '/') . '/app/.admin/.blocksglobal';
            $roleDir = rtrim($this->paths->root(), '/') . '/app/.admin/' . $this->adminRole . '/.blocksglobal';
            $namespace = 'Damoony\\Admin\\Pages\\' . $this->adminRole;
        } else {
            $baseDir = $this->paths->domain($domain->real) . '/.blocksglobal';
            $namespace = $domain->namespace;
        }

        // Site-режим: local (new) + global base; legacy filler — fallback.
        // Admin-режим: только baseDir/roleDir (без local — не применимо).
        $localDir = $this->adminRole === null ? $this->paths->allias($domain->real, $domain->allias) . '/.blockslocal' : null;

        $names = [];
        if (is_dir($baseDir)) {
            foreach (scandir($baseDir) ?: [] as $e) {
                if ($e !== '.' && $e !== '..' && is_dir($baseDir . '/' . $e)) {
                    $names[$e] = true;
                }
            }
        }
        if ($roleDir !== null && is_dir($roleDir)) {
            foreach (scandir($roleDir) ?: [] as $e) {
                if ($e !== '.' && $e !== '..' && is_dir($roleDir . '/' . $e)) {
                    $names[$e] = true;
                }
            }
        }
        if ($localDir !== null && is_dir($localDir)) {
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
            $roleBlock = $roleDir !== null ? $roleDir . '/' . $item : null;
            $localBlock = $localDir !== null ? $localDir . '/' . $item : null;

            // Base (глобальный блок) — .blocksglobal/{item}/{item}.php
            $baseFile = null;
            if ($roleBlock !== null && is_file($roleBlock . '/' . $item . '.php')) {
                $baseFile = $roleBlock . '/' . $item . '.php';
            } elseif (is_file($domainBlock . '/' . $item . '.php')) {
                $baseFile = $domainBlock . '/' . $item . '.php';
            }

            // Local (new model): .blockslocal/{item}/{item}.php, класс blockslocal_{alias}_{item}
            $localFile = null;
            $localClass = null;
            if ($localBlock !== null && is_file($localBlock . '/' . $item . '.php')) {
                $localFile = $localBlock . '/' . $item . '.php';
                $localClass = $namespace . '\\blockslocal_' . $domain->allias . '_' . $item;
            }

            // Legacy filler: .blocksglobal/{item}/{item}_{alias}.php
            $legacyFile = null;
            $legacyClass = null;
            if ($roleBlock !== null && is_file($roleBlock . '/' . $item . '_' . $domain->allias . '.php')) {
                $legacyFile = $roleBlock . '/' . $item . '_' . $domain->allias . '.php';
                $legacyClass = $namespace . '\\blocksglobal_' . $item . '_' . $domain->allias;
            } elseif (is_file($domainBlock . '/' . $item . '_' . $domain->allias . '.php')) {
                $legacyFile = $domainBlock . '/' . $item . '_' . $domain->allias . '.php';
                $legacyClass = $namespace . '\\blocksglobal_' . $item . '_' . $domain->allias;
            }

            $isFillerEnabled = true;
            if ($this->site !== null) {
                $isFillerEnabled = (bool) $this->site->getByKey(['fillers', $domain->real, $domain->allias, $item], true);
            }

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
