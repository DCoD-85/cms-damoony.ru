<?php

declare(strict_types=1);

namespace Damoony\Admin\Content;

use Damoony\Cli\Support\ContentRefs;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\ProjectSettings;
use Damoony\UI\Page\PageCompiler;
use Damoony\UI\Page\PagePath;
use RuntimeException;

/**
 * Чтение/запись страниц через безопасные JSON-блоки.
 * Никакого shell и произвольного PHP из web-редактора.
 */
final class ContentService
{
    public function __construct(private readonly Paths $paths)
    {
    }

    /**
     * @return list<array{path: string, title: string, url: string, editable: bool}>
     */
    public function pages(ProjectSettings $project, string $alias, string $lang): array
    {
        $base = rtrim($this->paths->algoritm($project->folder, $alias), '/');
        if (!is_dir($base)) {
            return [];
        }

        $pages = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
            $dir = dirname($relative);
            $dir = $dir === '.' ? '' : $dir;
            if ($dir === '') {
                continue;
            }
            $editor = $base . '/' . $dir . '/' . pathinfo($relative, PATHINFO_FILENAME) . '.editor.json';
            $editable = false;
            if (is_file($editor)) {
                $decoded = json_decode((string) file_get_contents($editor), true);
                $editable = is_array($decoded) && $decoded !== [];
            }
            $pages[] = [
                'path' => $dir,
                'title' => '/' . $dir . '/',
                'url' => '/' . $lang . '/' . $dir . '/',
                'editable' => $editable,
            ];
        }

        usort($pages, static fn (array $a, array $b): int => strcmp($a['path'], $b['path']));

        return $pages;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function read(ProjectSettings $project, string $alias, string $path): array
    {
        $page = $this->page($project, $alias, $path);
        if ($path === '' || !is_file($page->editorFile)) {
            throw new RuntimeException('У страницы нет editor.json — редактирование только через CLI');
        }

        $raw = file_get_contents($page->editorFile);
        $data = json_decode((string) $raw, true);
        if (!is_array($data) || !array_is_list($data)) {
            throw new RuntimeException('editor.json не является массивом блоков');
        }

        return $data;
    }

    /**
     * @param list<array<string, mixed>> $blocks
     */
    public function save(ProjectSettings $project, string $alias, string $path, array $blocks): void
    {
        $page = $this->page($project, $alias, $path);
        if ($path === '' || !is_file($page->phpFile) || !is_file($page->editorFile)) {
            throw new RuntimeException('Страница недоступна для редактирования');
        }

        $namespace = $project->namespace . '\\' . $alias;
        /** @var list<array{type: string, content?: string, items?: mixed, url?: string, class?: string, id?: string, style?: string, alt?: string}> $compiled */
        $compiled = $blocks;
        $php = new PageCompiler()->compile($namespace, $page->classChain, $compiled);
        $old = (string) file_get_contents($page->phpFile);

        Fs::writeAtomic($page->phpFile, $php);
        Fs::writeAtomic(
            $page->editorFile,
            json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n",
        );
        new ContentRefs($this->paths)->sync($project->folder, $alias, $path, $old, $php);
    }

    public function readSource(ProjectSettings $project, string $alias, string $path): string
    {
        $page = $this->page($project, $alias, $path);
        if ($path === '' || !is_file($page->phpFile)) {
            throw new RuntimeException('Страница не найдена');
        }

        return (string) file_get_contents($page->phpFile);
    }

    public function saveSource(ProjectSettings $project, string $alias, string $path, string $code): void
    {
        $page = $this->page($project, $alias, $path);
        if ($path === '' || !is_file($page->phpFile)) {
            throw new RuntimeException('Страница не найдена');
        }
        if (!str_starts_with(ltrim($code), '<?php')) {
            throw new RuntimeException('PHP-файл должен начинаться с <?php');
        }
        try {
            $tokens = token_get_all($code, TOKEN_PARSE);
            if ($tokens === []) {
                throw new \ParseError('пустой файл');
            }
        } catch (\ParseError $e) {
            throw new RuntimeException('Ошибка синтаксиса PHP: ' . $e->getMessage());
        }

        $backupDir = $this->paths->root() . '/var/backups/content';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0o775, true);
        }
        $backup = $backupDir . '/' . $page->classChain . '-' . date('Ymd-His') . '.php.bak';
        copy($page->phpFile, $backup);

        $old = (string) file_get_contents($page->phpFile);
        Fs::writeAtomic($page->phpFile, $code);
        new ContentRefs($this->paths)->sync($project->folder, $alias, $path, $old, $code);
    }

    /**
     * @return array{types: list<string>, fields: list<string>, methods: list<array{provider: string, name: string, params: list<string>}>}
     */
    public function hints(ProjectSettings $project, string $alias): array
    {
        $providers = ['user', 'news', 'files', 'audit', 'form_submission'];
        $dirs = [
            $this->paths->appRoot() . '/' . $project->folder . '/.dbprovider',
            $this->paths->appRoot() . '/' . $project->folder . '/' . $alias . '/.dbprovider',
        ];
        foreach ($dirs as $dir) {
            foreach (glob($dir . '/*Provider.php') ?: [] as $file) {
                $providers[] = lcfirst(basename($file, 'Provider.php'));
            }
        }

        $methods = [];
        foreach (array_unique($providers) as $provider) {
            $class = \Damoony\Data\Db\DataProvider\ProviderResolver::resolveClass($provider, $project->folder, $alias, $this->paths);
            if ($class === null || !class_exists($class)) {
                continue;
            }
            $ref = new \ReflectionClass($class);
            foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $declarer = $method->getDeclaringClass()->getName();
                if (!in_array($declarer, [$ref->getName(), \Damoony\Data\Db\DataProvider\BasicCrudProvider::class], true)) {
                    continue;
                }
                if (in_array($method->getName(), ['table', 'physicalTable', 'domain', 'alias'], true)) {
                    continue;
                }
                $params = [];
                foreach ($method->getParameters() as $parameter) {
                    $type = $parameter->getType();
                    $params[] = $parameter->getName() . ($type instanceof \ReflectionNamedType ? ':' . $type->getName() : '');
                }
                $methods[] = ['provider' => $provider, 'name' => $method->getName(), 'params' => $params];
            }
        }

        return [
            'types' => ['h1', 'h2', 'h3', 'text', 'p', 'img', 'link', 'button', 'ul', 'nav', 'section', 'div', 'table'],
            'fields' => ['type', 'content', 'class', 'id', 'style', 'url', 'alt', 'items', 'button_type', 'children', 'headers', 'rows'],
            'methods' => $methods,
        ];
    }

    /**
     * @param array{tmp_name?: string, error?: int, size?: int, name?: string} $file
     *
     * @return array{url: string, file: string, width: int, height: int}
     */
    public function uploadMedia(ProjectSettings $project, string $alias, array $file, string $category = 'upload', string $alt = ''): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Ошибка загрузки файла');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Файл не загружен');
        }
        if (preg_match('/^[a-z0-9_-]{1,32}$/', $category) !== 1) {
            throw new RuntimeException('Некорректная категория');
        }
        if ((int) ($file['size'] ?? 0) > 25 * 1024 * 1024) {
            throw new RuntimeException('Файл больше 25 MB');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = strtolower((string) $finfo->file($tmp));
        $map = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
        if (!isset($map[$mime])) {
            throw new RuntimeException('Разрешены PNG, JPEG, GIF, WebP');
        }
        $bytes = (string) file_get_contents($tmp);
        $info = @getimagesizefromstring($bytes);
        if ($info === false || $info[0] < 200 || $info[1] < 200) {
            throw new RuntimeException('Изображение минимум 200×200');
        }

        $hash = substr(hash('sha256', $bytes), 0, 16);
        $ext = $map[$mime];
        $dir = $this->paths->content($project->folder, $alias) . '/' . $category;
        if (!is_dir($dir)) {
            mkdir($dir, 0o775, true);
        }
        $filePath = $dir . '/' . $hash . '.' . $ext;
        Fs::writeAtomic($filePath, $bytes);
        Fs::writeAtomic($dir . '/' . $hash . '.meta.json', json_encode([
            'source_url' => '',
            'alt' => $alt,
            'size' => strlen($bytes),
            'mime' => $mime,
            'width' => (int) $info[0],
            'height' => (int) $info[1],
            'hash' => $hash,
            'downloaded_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'page_refs' => [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");

        return [
            'url' => '/content/' . $project->folder . '/' . $alias . '/' . $category . '/' . $hash . '.' . $ext,
            'file' => $hash . '.' . $ext,
            'width' => (int) $info[0],
            'height' => (int) $info[1],
        ];
    }

    /**
     * @return list<array{url: string, absolute: string, category: string, file: string, size: int, alt: string}>
     */
    public function media(ProjectSettings $project, string $alias): array
    {
        $base = rtrim($this->paths->content($project->folder, $alias), '/');
        if (!is_dir($base)) {
            return [];
        }

        $hosts = $project->hostNames();
        $host = $hosts[0] ?? '';
        if ($alias !== 'www' && $host !== '') {
            $host = $alias . '.' . $host;
        }

        $items = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }
            $ext = strtolower($file->getExtension());
            if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'], true)) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
            $category = dirname($relative);
            if ($category === '.') {
                $category = '';
            }
            $hash = pathinfo($relative, PATHINFO_FILENAME);
            $metaFile = dirname($file->getPathname()) . '/' . $hash . '.meta.json';
            $meta = is_file($metaFile) ? json_decode((string) file_get_contents($metaFile), true) : [];
            $alt = is_array($meta) ? (string) ($meta['alt'] ?? '') : '';
            $pathPart = $category === '' ? $relative : $category . '/' . $relative;
            $url = '/content/' . $project->folder . '/' . $alias . '/' . $pathPart;
            $items[] = [
                'url' => $url,
                'absolute' => $host === '' ? $url : 'http://' . $host . $url,
                'category' => $category,
                'file' => $relative,
                'size' => (int) $file->getSize(),
                'alt' => $alt,
            ];
        }

        usort($items, static fn (array $a, array $b): int => strcmp($a['file'], $b['file']));

        return $items;
    }

    public function previewUrl(ProjectSettings $project, string $alias, string $path, string $lang): string
    {
        $hosts = $project->hostNames();
        $host = $hosts[0] ?? '';
        if ($alias !== 'www' && $host !== '') {
            $host = $alias . '.' . $host;
        }

        return ($host === '' ? '' : 'http://' . $host) . '/' . $lang . '/' . ($path === '' ? '' : $path . '/');
    }

    private function page(ProjectSettings $project, string $alias, string $path): PagePath
    {
        $path = trim($path, '/');
        $parts = $path === '' ? [] : explode('/', $path);
        $name = $parts === [] ? '' : (string) array_pop($parts);
        $parent = implode('/', $parts);

        return PagePath::resolve($this->paths, $project->folder, $alias, $name, $parent);
    }
}
