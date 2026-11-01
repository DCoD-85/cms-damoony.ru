<?php

declare(strict_types=1);

namespace Damoony\Page;

use Damoony\Files\Paths;

/**
 * Резолвинг путей страницы по единой модели:
 *
 *   URL           →  PHP-класс                 →  файлы
 *   /             →  algoritm                 →  algoritm/algoritm.{php,rules.json,editor.json}
 *   /page1/       →  algoritm_page1           →  algoritm/page1/algoritm_page1.{php,rules.json,editor.json}
 *   /page1/page2/ →  algoritm_page1_page2     →  algoritm/page1/page2/algoritm_page1_page2.{...}
 */
final class PagePath
{
    public function __construct(
        public readonly string $targetRelPath,
        public readonly string $classChain,
        public readonly string $pageDir,
        public readonly string $phpFile,
        public readonly string $rulesFile,
        public readonly string $editorFile,
        public readonly string $parentRelPath,
        public readonly string $parentClassChain,
        public readonly string $parentDir,
        public readonly string $parentPhpFile,
        public readonly string $parentRulesFile,
    ) {
    }

    public static function resolve(
        Paths $paths,
        string $domain,
        string $allias,
        string $name,
        string $parent = '',
    ): self {
        $baseDir = rtrim($paths->algoritm($domain, $allias), '/');

        $targetRelPath = $parent === '' ? $name : $parent . '/' . $name;
        $classChain = 'algoritm' . ($targetRelPath === '' ? '' : '_' . str_replace('/', '_', $targetRelPath));
        $pageDir = $baseDir . ($targetRelPath === '' ? '' : '/' . $targetRelPath);

        $parentRelPath = $parent;
        $parentClassChain = 'algoritm' . ($parentRelPath === '' ? '' : '_' . str_replace('/', '_', $parentRelPath));
        $parentDir = $baseDir . ($parentRelPath === '' ? '' : '/' . $parentRelPath);

        return new self(
            targetRelPath: $targetRelPath,
            classChain: $classChain,
            pageDir: $pageDir,
            phpFile: $pageDir . '/' . $classChain . '.php',
            rulesFile: $pageDir . '/' . $classChain . '.rules.json',
            editorFile: $pageDir . '/' . $classChain . '.editor.json',
            parentRelPath: $parentRelPath,
            parentClassChain: $parentClassChain,
            parentDir: $parentDir,
            parentPhpFile: $parentDir . '/' . $parentClassChain . '.php',
            parentRulesFile: $parentDir . '/' . $parentClassChain . '.rules.json',
        );
    }

    /** Для root алиаса — не через page:new, только через allias:new. */
    public static function isRoot(string $name, string $parent): bool
    {
        return $name === '' && $parent === '';
    }
}
