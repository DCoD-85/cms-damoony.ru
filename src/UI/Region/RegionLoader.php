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
        if ($names === []) {
            return $registry;
        }
        $items = array_keys($names);
        sort($items);

        foreach ($items as $item) {
            $domainBlock = $baseDir . '/' . $item;
            $roleBlock = $roleDir !== null ? $roleDir . '/' . $item : null;

            $baseFile = null;
            if ($roleBlock !== null && is_file($roleBlock . '/' . $item . '.php')) {
                $baseFile = $roleBlock . '/' . $item . '.php';
            } elseif (is_file($domainBlock . '/' . $item . '.php')) {
                $baseFile = $domainBlock . '/' . $item . '.php';
            }

            $fillerFile = null;
            if ($roleBlock !== null && is_file($roleBlock . '/' . $item . '_' . $domain->allias . '.php')) {
                $fillerFile = $roleBlock . '/' . $item . '_' . $domain->allias . '.php';
            } elseif (is_file($domainBlock . '/' . $item . '_' . $domain->allias . '.php')) {
                $fillerFile = $domainBlock . '/' . $item . '_' . $domain->allias . '.php';
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

            if ($isFillerEnabled && $fillerFile !== null) {
                $fillerClass = $namespace . '\\blocksglobal_' . $item . '_' . $domain->allias;
                if (!class_exists($fillerClass, false)) {
                    require_once $fillerFile;
                }
                if (class_exists($fillerClass, false) && is_subclass_of($fillerClass, RegionInterface::class)) {
                    $registry->add(new $fillerClass(), false);
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
