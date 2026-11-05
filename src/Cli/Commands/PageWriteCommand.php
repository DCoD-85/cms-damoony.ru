<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Files\Fs;
use Damoony\Files\Paths;
use Damoony\Page\PageCompiler;
use Damoony\Page\PageNamespace;
use Damoony\Page\PagePath;

final class PageWriteCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'page:write';
    }

    public function description(): string
    {
        return 'Записать контент (JSON) в существующую страницу';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = $args['domain'] ?? '';
        $allias = $args['allias'] ?? '';
        $name = $args['name'] ?? '';
        $parent = trim((string) ($args['parent'] ?? ''), '/');
        $sessionId = trim((string) ($args['session'] ?? ''));

        // 1. Валидация
        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || str_contains($domain, '..')) {
            $r->error('Некорректный домен');
            return $r;
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $allias)) {
            $r->error('Некорректный алиас');
            return $r;
        }

        // 2. __root__ — главная страница
        if ($name === '__root__') {
            if ($parent !== '') {
                $r->error('--parent несовместим с __root__');
                return $r;
            }
            $name = '';
        } elseif ($name === '') {
            $r->error('Укажите --name (или __root__ для главной страницы)');
            return $r;
        } elseif (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            $r->error('Некорректное имя страницы (a-z, 0-9, _; начинается с буквы)');
            return $r;
        }

        if ($parent !== '' && !preg_match('#^[a-z][a-z0-9_]*(/[a-z][a-z0-9_]*)*$#', $parent)) {
            $r->error('Некорректный путь родительской страницы');
            return $r;
        }

        if ($sessionId !== '') {
            if (!preg_match('/^[a-f0-9]{8,32}$/', $sessionId)) {
                $r->error('Некорректный --session (8-32 hex символов)');
                return $r;
            }
            if (!is_dir($this->paths->sessionDir($sessionId, $domain))) {
                $r->error('Сессия не найдена: ' . $sessionId);
                return $r;
            }
        }

        // 3. JSON
        if (isset($args['json_file'])) {
            $file = (string) $args['json_file'];
            if (!is_file($file)) {
                $r->error('Файл json_file не найден');
                return $r;
            }
            $json = (string) file_get_contents($file);
        } elseif (isset($args['json_b64'])) {
            $decoded = base64_decode((string) $args['json_b64'], true);
            if ($decoded === false) {
                $r->error('Некорректный base64');
                return $r;
            }
            $json = $decoded;
        } else {
            $json = (string) ($args['json'] ?? '[]');
        }

        $blocks = json_decode($json, true);
        if (!is_array($blocks)) {
            $r->error('Некорректный JSON (ожидается массив блоков)');
            return $r;
        }

        // 4. Резолвинг
        $page = PagePath::resolve(
            $this->paths,
            $domain,
            $allias,
            $name,
            $parent,
            $sessionId !== '' ? $sessionId : null,
        );

        if (!is_file($page->phpFile)) {
            $r->error('Страница не найдена. Сначала используйте page:new');
            return $r;
        }

        // 5. Namespace из site.config
        $siteFile = $this->paths->siteConfig();
        $site = is_file($siteFile) ? Config::fromFile($siteFile)->all() : [];
        $namespace = PageNamespace::fromSite($site, $domain);

        // 6. Компиляция и запись
        $withSchema = isset($args['with_schema']) && $args['with_schema'] === '1';
        $compiler = new PageCompiler();
        $php = $compiler->compile($namespace . '\\' . $allias, $page->classChain, $blocks, $withSchema);

        // 7. Читаем старый .php ДО перезаписи (для diff page_refs)
        $oldPhp = (string) @file_get_contents($page->phpFile);

        Fs::writeAtomic($page->phpFile, $php);
        Fs::writeAtomic(
            $page->editorFile,
            json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n",
        );
        $r->file(Fs::rel($page->phpFile, $this->paths->root()));

        // 8. Обновить page_refs в meta.json всех /content/... картинок (add new, remove stale)
        $pageKey = $name !== '' ? $name : '__root__';
        $this->updateContentRefs($domain, $allias, $pageKey, $oldPhp, $php);

        $context->step('Контент страницы обновлен', true, $pageKey);

        return $r;
    }

    /**
     * Синхронизирует page_refs: страница упоминается в meta.json тех картинок,
     * на которые она ссылается (по URL /content/<domain>/<allias>/<category>/<hash>.*).
     *
     * - newHashes − oldHashes: добавить pageKey
     * - oldHashes − newHashes: удалить pageKey
     */
    private function updateContentRefs(
        string $domain,
        string $allias,
        string $pageKey,
        string $oldPhp,
        string $newPhp,
    ): void {
        $oldRefs = $this->extractContentHashes($oldPhp, $domain, $allias);
        $newRefs = $this->extractContentHashes($newPhp, $domain, $allias);

        $oldKeys = array_map(static fn (array $r): string => $r['category'] . '/' . $r['hash'], $oldRefs);
        $newKeys = array_map(static fn (array $r): string => $r['category'] . '/' . $r['hash'], $newRefs);

        foreach ($newRefs as $i => $ref) {
            if (!in_array($newKeys[$i], $oldKeys, true)) {
                $this->applyPageRef($domain, $allias, $ref['category'], $ref['hash'], $pageKey, 'add');
            }
        }

        foreach ($oldRefs as $i => $ref) {
            if (!in_array($oldKeys[$i], $newKeys, true)) {
                $this->applyPageRef($domain, $allias, $ref['category'], $ref['hash'], $pageKey, 'remove');
            }
        }
    }

    /**
     * @return list<array{category: string, hash: string}>
     */
    private function extractContentHashes(string $php, string $domain, string $allias): array
    {
        $pattern = '#/content/'
            . preg_quote($domain, '#')
            . '/'
            . preg_quote($allias, '#')
            . '/([a-z0-9_-]{1,32})/([a-f0-9]{16})\.[a-z]+#';

        if (preg_match_all($pattern, $php, $m, PREG_SET_ORDER) === false) {
            return [];
        }

        $out = [];
        $seen = [];
        foreach ($m as $row) {
            $key = $row[1] . '/' . $row[2];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = ['category' => $row[1], 'hash' => $row[2]];
        }

        return $out;
    }

    private function applyPageRef(
        string $domain,
        string $allias,
        string $category,
        string $hash,
        string $pageKey,
        string $mode,
    ): void {
        $metaFile = $this->paths->content($domain, $allias)
            . '/' . $category . '/' . $hash . '.meta.json';

        if (!is_file($metaFile)) {
            return; // картинка не из content:store — пропускаем
        }

        $raw = @file_get_contents($metaFile);
        if ($raw === false) {
            return;
        }
        $meta = json_decode($raw, true);
        if (!is_array($meta)) {
            return;
        }

        $refs = is_array($meta['page_refs'] ?? null) ? $meta['page_refs'] : [];
        $refs = array_values(array_filter($refs, 'is_string'));

        if ($mode === 'add') {
            if (!in_array($pageKey, $refs, true)) {
                $refs[] = $pageKey;
            }
        } elseif ($mode === 'remove') {
            $refs = array_values(array_filter($refs, static fn (string $k): bool => $k !== $pageKey));
        }

        $meta['page_refs'] = $refs;

        Fs::writeAtomic(
            $metaFile,
            json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        );
    }
}
