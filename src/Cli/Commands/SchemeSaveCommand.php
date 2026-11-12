<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Commands\Concerns\EnsuresSessionPermission;
use Damoony\Cli\Commands\Concerns\ResolvesRealDomain;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\UI\Scheme\SchemeCompiler;

final class SchemeSaveCommand implements CommandInterface
{
    use EnsuresSessionPermission;
    use ResolvesRealDomain;

    /** HTML tags allowed in scheme (builder-safe subset; no script/iframe/style/form). */
    private const SAFE_TAGS = [
        'div', 'span', 'section', 'article', 'header', 'footer', 'main', 'aside', 'nav',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'ul', 'ol', 'li',
        'a', 'img', 'button', 'figure', 'figcaption', 'blockquote', 'hr', 'br',
    ];

    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'scheme:save';
    }

    public function description(): string
    {
        return 'Сохранить визуальную схему (JSON → editor.json + compile php)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();
        $domain = $args['domain'] ?? '';
        $name = $args['name'] ?? '';
        $sessionId = trim((string) ($args['session'] ?? ''));

        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || str_contains($domain, '..')) {
            $r->error('Некорректный домен');
            return $r;
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $name) || str_contains($name, '..')) {
            $r->error('Некорректное имя схемы');
            return $r;
        }

        // JSON: --scheme_json (plain) или --scheme_json_b64 (для AI)
        if (isset($args['scheme_json_b64'])) {
            $decoded = base64_decode((string) $args['scheme_json_b64'], true);
            if ($decoded === false) {
                $r->error('Некорректный base64');
                return $r;
            }
            $json = $decoded;
        } else {
            $json = (string) ($args['scheme_json'] ?? '');
        }

        if ($json === '') {
            $r->error('Укажите --scheme_json или --scheme_json_b64');
            return $r;
        }

        $scheme = json_decode($json, true);
        if (!is_array($scheme)) {
            $r->error('Некорректный JSON (ожидается объект схемы)');
            return $r;
        }

        try {
            $realDomain = $this->resolveRealDomain($this->paths, $domain);
        } catch (\RuntimeException $e) {
            $r->error($e->getMessage());
            return $r;
        }

        try {
            $this->ensureSessionPermission($this->paths, $sessionId, $realDomain, 'scheme:save');
        } catch (\RuntimeException $e) {
            $r->error($e->getMessage());
            return $r;
        }

        $schemesDir = $this->paths->domain($realDomain) . '/.schemesglobal';
        $editorFile = $schemesDir . '/' . $name . '.editor.json';
        $phpFile = $schemesDir . '/' . $name . '.php';

        if (!is_file($editorFile)) {
            $r->error('editor.json не найден: ' . $name . '. Сначала создайте схему (scheme:new).');
            return $r;
        }

        // 1. Обновляем editor.json — заменяем только scheme, fields не трогаем
        $editorData = json_decode((string) file_get_contents($editorFile), true);
        if (!is_array($editorData)) {
            $r->error('editor.json повреждён');
            return $r;
        }
        $editorData['scheme'] = $scheme;

        Fs::writeAtomic(
            $editorFile,
            json_encode($editorData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n",
        );
        $r->file(Fs::rel($editorFile, $this->paths->root()));

        // 2. Компилируем php
        $phpData = [
            'tag' => 'div',
            'class' => 'layout',
            'children' => $this->mapSchemeToPhp(is_array($scheme['children'] ?? null) ? $scheme['children'] : []),
            'scripts' => is_array($scheme['scripts'] ?? null) ? $scheme['scripts'] : [],
            'meta' => is_array($scheme['meta'] ?? null) ? $scheme['meta'] : [],
        ];

        $compiler = new SchemeCompiler();
        Fs::writeAtomic($phpFile, $compiler->compile($phpData));
        $r->file(Fs::rel($phpFile, $this->paths->root()));

        $context->step('Схема сохранена', true, $name);

        return $r;
    }

    /**
     * @param array<int, array<string, mixed>> $children
     *
     * @return array<int, array<string, mixed>>
     */
    private function mapSchemeToPhp(array $children): array
    {
        $result = [];
        foreach ($children as $child) {

            if (($child['type'] ?? '') === 'slot') {
                $slotName = (string) ($child['name'] ?? '');
                if (!preg_match('/^[a-z][a-z0-9_]*$/', $slotName)) {
                    continue; // skip malformed slot
                }
                $result[] = [
                    'tag' => 'div',
                    'slot' => $slotName,
                ];
            } elseif (($child['type'] ?? '') === 'element' || isset($child['tag'])) {
                $tag = (string) ($child['tag'] ?? 'div');
                if (!in_array($tag, self::SAFE_TAGS, true)) {
                    continue; // skip script/iframe/style/object/embed/link/meta/form/input
                }
                $element = [
                    'tag' => $tag,
                ];
                if (isset($child['class'])) {
                    $element['class'] = (string) $child['class'];
                }
                if (isset($child['slot'])) {
                    $element['slot'] = (string) $child['slot'];
                }
                if (isset($child['children']) && is_array($child['children'])) {
                    $element['children'] = $this->mapSchemeToPhp($child['children']);
                }
                $result[] = $element;
            }
        }
        return $result;
    }
}
