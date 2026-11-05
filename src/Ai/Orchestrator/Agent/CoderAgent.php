<?php

declare(strict_types=1);

namespace Damoony\Ai\Orchestrator\Agent;

use Damoony\Ai\Aicli\AicliClient;
use Damoony\Ai\Aicli\Handler\AgentChatHandler;

final class CoderAgent implements AgentInterface, CoderAgentInterface
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
Ты — Верстальщик (Coder Agent).
Тебе дают описание одной конкретной страницы. Ты генерируешь JSON-массив блоков для её центральной части.

ПРАВИЛА:
1. Формат простого блока: {"type": "h1", "content": "Текст"}.
2. Формат группы: {"type": "section", "children": [{"type": "h2", "content": "..."}, {"type": "text", "content": "..."}]}.
3. Типы: h1, h2, h3, text, p, link, button, section, div.
4. Для section/div используй "children" (массив), а НЕ "content".
5. ВАЖНО: в content — только PLAIN TEXT. НИКАКОГО markdown: без **, *, #, _, `, [текст](url). Пиши обычными словами.
6. Наполняй блоки осмысленным русским текстом по теме страницы. Никакого бреда!
7. Минимум 5-8 блоков на страницу.
8. Выдай СТРОГО валидный JSON-массив без markdown-оберток.
PROMPT;

    private AgentChatHandler $chatHandler;

    public function __construct(string $root)
    {
        $client = new AicliClient($root);
        $this->chatHandler = new AgentChatHandler($client, $root);
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $pagePlan
     * @return array{blocks: array<int, array<string, mixed>>}
     */
    public function executeForPage(array $context, array $pagePlan): array
    {
        $pageTitle = (string) ($pagePlan['title'] ?? 'Страница');
        $pageBrief = (string) ($pagePlan['brief'] ?? '');
        $sections = $pagePlan['sections'] ?? ['h1', 'text', 'section'];

        $llmInput = "Страница: {$pageTitle}\n"
            . "Описание: {$pageBrief}\n"
            . "Планируемые секции: " . json_encode($sections) . "\n\n"
            . "Сгенерируй JSON-массив блоков для этой страницы. Plain text, без markdown.";

        $result = $this->chatHandler->handle('agent:chat', [
            'system_prompt' => self::SYSTEM_PROMPT,
            'history' => [['role' => 'user', 'content' => $llmInput]],
        ], 'designer');

        if (!$result->ok) {
            throw new \RuntimeException('Coder LLM failed: ' . $result->error);
        }

        $reply = (string) ($result->data['reply'] ?? '');
        $reply = (string) preg_replace('/^```json\s*|\s*```$/m', '', trim($reply));

        $parsed = json_decode($reply, true);
        if (!is_array($parsed)) {
            throw new \RuntimeException('Coder returned invalid JSON: ' . substr($reply, 0, 500));
        }

        if (isset($parsed['blocks']) && is_array($parsed['blocks'])) {
            $parsed = $parsed['blocks'];
        }
        if (isset($parsed['type']) && is_string($parsed['type'])) {
            $parsed = [$parsed];
        }

        $normalized = [];
        foreach ($parsed as $block) {
            if (is_array($block)) {
                $normalized[] = self::normalizeBlock($block);
            }
        }

        return ['blocks' => $normalized];
    }

    /**
     * @param array<string, mixed> $context
     */
    public function execute(array $context): array
    {
        $plan = $context['agent_outputs']['architect'] ?? [];
        $firstPage = $plan['pages'][0] ?? [
            'title' => 'Страница',
            'brief' => (string) ($context['input']['brief'] ?? ''),
            'sections' => ['h1', 'text', 'section'],
        ];
        return $this->executeForPage($context, $firstPage);
    }

    /** @param array<string, mixed> $block @return array<string, mixed> */
    private static function normalizeBlock(array $block): array
    {
        if (!isset($block['type']) || !is_string($block['type'])) {
            throw new \RuntimeException('Coder returned block without type');
        }
        if (isset($block['content']) && is_array($block['content'])) {
            $block['children'] = $block['content'];
            unset($block['content']);
        }
        // Очистка markdown из content
        if (isset($block['content']) && is_string($block['content'])) {
            $block['content'] = self::stripMarkdown($block['content']);
        }
        if (isset($block['children']) && is_array($block['children'])) {
            $nc = [];
            foreach ($block['children'] as $child) {
                if (is_array($child)) {
                    $nc[] = self::normalizeBlock($child);
                }
            }
            $block['children'] = $nc;
        }
        return $block;
    }

    private static function stripMarkdown(string $text): string
    {
        // Снимаем **жирный**, *курсив*, #заголовки, `код`, [текст](url)
        $text = preg_replace('/\*\*(.+?)\*\*/u', '$1', $text);
        $text = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/u', '$1', $text);
        $text = preg_replace('/`([^`]+)`/u', '$1', $text);
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/u', '$1', $text);
        $text = preg_replace('/^#+\s*/mu', '', $text);
        return trim($text);
    }
}
