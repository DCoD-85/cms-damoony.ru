<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Cli\Support\SessionGuard;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\UI\Scheme\SchemeCompiler;
use RuntimeException;
use Throwable;

/**
 * scheme:save для схемы domains_app: визуальное дерево JSON -> editor.json + PHP.
 */
final class SchemeSaveCommand implements CommandInterface
{
    private const NAME_RE = '/^[a-z][a-z0-9_]*$/';

    /** Теги, разрешённые в схеме (никаких script/iframe/style/form/input). */
    private const SAFE_TAGS = [
        'div', 'span', 'section', 'article', 'header', 'footer', 'main', 'aside', 'nav',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'ul', 'ol', 'li',
        'a', 'img', 'button', 'figure', 'figcaption', 'blockquote', 'hr', 'br',
    ];

    public function __construct(
        private readonly Paths $paths,
        private readonly SiteConfig $site,
        private readonly SessionGuard $sessionGuard,
    ) {
    }

    public function name(): string
    {
        return 'scheme:save';
    }

    public function description(): string
    {
        return 'Сохранить визуальную схему (JSON -> editor.json + compile php)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $name = (string) ($args['name'] ?? '');
        if (preg_match(self::NAME_RE, $name) !== 1) {
            $r->error('Некорректное имя схемы');

            return $r;
        }

        $scheme = $this->parseScheme($args, $r);
        if ($scheme === null) {
            return $r;
        }

        try {
            $project = $this->site->resolve((string) ($args['domain'] ?? ''));
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $sessionId = trim((string) ($args['session'] ?? ''));

        try {
            $this->sessionGuard->assertAllowed($sessionId, $project->folder, 'scheme:save');
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $schemesDir = $this->paths->schemesGlobal($project->folder);
        $editorFile = $schemesDir . '/' . $name . '.editor.json';
        $phpFile = $schemesDir . '/' . $name . '.php';

        if (!is_file($editorFile)) {
            $r->error('editor.json не найден: ' . $name . '. Сначала создайте схему (scheme:new).');

            return $r;
        }

        $editorData = json_decode((string) file_get_contents($editorFile), true);
        if (!is_array($editorData)) {
            $r->error('editor.json повреждён');

            return $r;
        }

        if ($context->isDryRun()) {
            $context->step('Dry-run: схема ' . $name . ' будет перезаписана');
            $r->data = [
                'project' => $project->key,
                'scheme' => $name,
                'children' => count(is_array($scheme['children'] ?? null) ? $scheme['children'] : []),
                'dry_run' => true,
            ];

            return $r;
        }

        $editorData['scheme'] = $scheme;
        Fs::writeAtomic(
            $editorFile,
            json_encode($editorData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n",
        );
        $r->file(Fs::rel($editorFile, $this->paths->root()));

        $phpData = [
            'tag' => 'div',
            'class' => 'layout',
            'children' => $this->mapSchemeToPhp(is_array($scheme['children'] ?? null) ? $scheme['children'] : []),
            'scripts' => is_array($scheme['scripts'] ?? null) ? $scheme['scripts'] : [],
            'meta' => is_array($scheme['meta'] ?? null) ? $scheme['meta'] : [],
        ];

        try {
            Fs::writeAtomic($phpFile, new SchemeCompiler()->compile($phpData));
        } catch (Throwable $e) {
            $r->error('Не удалось скомпилировать схему: ' . $e->getMessage());

            return $r;
        }
        $r->file(Fs::rel($phpFile, $this->paths->root()));

        $r->data = ['project' => $project->key, 'scheme' => $name];

        $context->step('Схема сохранена', true, $name);

        return $r;
    }

    /**
     * @param array<string, string> $args
     *
     * @return array<string, mixed>|null
     */
    private function parseScheme(array $args, Result $r): ?array
    {
        if (isset($args['scheme_json_b64'])) {
            $decoded = base64_decode((string) $args['scheme_json_b64'], true);
            if ($decoded === false) {
                $r->error('Некорректный base64');

                return null;
            }
            $json = $decoded;
        } else {
            $json = (string) ($args['scheme_json'] ?? '');
        }

        if ($json === '') {
            $r->error('Укажите --scheme_json или --scheme_json_b64');

            return null;
        }

        $scheme = json_decode($json, true);
        if (!is_array($scheme)) {
            $r->error('Некорректный JSON (ожидается объект схемы)');

            return null;
        }

        /** @var array<string, mixed> $scheme */
        return $scheme;
    }

    /**
     * @param array<mixed> $children
     *
     * @return list<array<string, mixed>>
     */
    private function mapSchemeToPhp(array $children): array
    {
        $result = [];

        foreach ($children as $child) {
            if (!is_array($child)) {
                continue;
            }

            $type = (string) ($child['type'] ?? '');

            if ($type === 'slot') {
                $slotName = (string) ($child['name'] ?? '');
                if (preg_match(self::NAME_RE, $slotName) !== 1) {
                    continue;
                }
                $result[] = ['tag' => 'div', 'slot' => $slotName];

                continue;
            }

            if ($type !== 'element' && !isset($child['tag'])) {
                continue;
            }

            $tag = (string) ($child['tag'] ?? 'div');
            if (!in_array($tag, self::SAFE_TAGS, true)) {
                continue;
            }

            $element = ['tag' => $tag];

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

        return $result;
    }
}
