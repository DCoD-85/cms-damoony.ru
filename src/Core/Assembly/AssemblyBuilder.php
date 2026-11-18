<?php

declare(strict_types=1);

namespace Damoony\Core\Assembly;

use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use RuntimeException;

final class AssemblyBuilder
{
    public function __construct(private readonly Paths $paths)
    {
    }

    /**
     * @param array<string, string> $vars
     */
    public function build(string $assembly, string $domain, array $vars): void
    {
        $manifest = AssemblyManifest::fromDirectory($this->paths, $assembly);
        $srcBase = $this->paths->root() . '/stubs/assemblies/' . $assembly;

        $vars['domain'] = $domain;
        $vars['real'] ??= $domain;
        $vars['alias'] ??= 'www';

        $aliases = $manifest->aliases();
        if (!in_array($vars['alias'], $aliases, true)) {
            $aliases[] = $vars['alias'];
        }

        // 1. Копируем app/ (общие каталоги + каталоги алиасов)
        $this->copyTree($srcBase . '/app', $this->paths->domain($vars['real']), $vars);

        // 2. Копируем media/<alias>/ в media/<real>/<alias>/; legacy media/ -> основной алиас
        $perAlias = false;
        foreach ($aliases as $alias) {
            $srcAliasMedia = $srcBase . '/media/' . $alias;
            if (!is_dir($srcAliasMedia)) {
                continue;
            }

            $perAlias = true;
            $aliasVars = $vars;
            $aliasVars['alias'] = $alias;
            $this->copyTree($srcAliasMedia, $this->paths->media($vars['real'], $alias), $aliasVars);
        }

        if (!$perAlias) {
            $this->copyTree($srcBase . '/media', $this->paths->media($vars['real'], $vars['alias']), $vars);
        }

        // 3. Копируем shared/
        $this->copyTree($srcBase . '/shared', $this->paths->mediaRoot() . '/shared', $vars);
    }

    /**
     * @param array<string, string> $vars
     */
    private function copyTree(string $src, string $dst, array $vars): void
    {
        if (!is_dir($src)) {
            return;
        }
        if (!is_dir($dst)) {
            Fs::mkdir($dst);
        }

        $items = scandir($src);
        if ($items === false) {
            throw new RuntimeException("Cannot scan dir: {$src}");
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $srcPath = $src . '/' . $item;
            $dstPath = $dst . '/' . $item;

            if (is_dir($srcPath)) {
                $this->copyTree($srcPath, $dstPath, $vars);
            } elseif (is_file($srcPath)) {
                $content = (string) file_get_contents($srcPath);
                // Заменяем плейсхолдеры ({{domain}}, {{namespace}} и т.д.)
                foreach ($vars as $k => $v) {
                    $content = str_replace('{{' . $k . '}}', $v, $content);
                }
                $content = str_replace('{{timestamp}}', (string) time(), $content);
                Fs::writeAtomic($dstPath, $content);
            }
        }
    }
}
