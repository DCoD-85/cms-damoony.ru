<?php

declare(strict_types=1);

namespace Damoony\Ai\Orchestrator\Agent;

use Damoony\Ai\Aicli\AicliClient;
use Damoony\Ai\Aicli\Handler\AgentChatHandler;

final class ArchitectAgent implements AgentInterface, ArchitectAgentInterface
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
Ты — Архитектор веб-сайта (Senior Web Architect).
Твоя задача: спроектировать структуру страниц и распределить контент по слотам на основе запроса пользователя и текущего состояния сайта.
Ты НЕ пишешь код, ты выдаешь только план в формате JSON.

ПРАВИЛА:
1. Анализируй доступные таблицы БД (db_tables). Если есть таблица подходящая под запрос (например, news, projects), предложи динамическую страницу.
2. Для динамических страниц используй роутинг (например, path: "news/([0-9]+)", available: "[0-9]+").
3. Распределяй контент по слотам: top (шапка), center (основной контент), bottom (подвал).
4. Выдай СТРОГО валидный JSON без markdown-оберток (без ```json).

ФОРМАТ ОТВЕТА:
{
  "routing_tree": [
    {"page_name": "home", "path": "", "available": ".*"},
    {"page_name": "news_item", "path": "news/([0-9]+)", "available": "[0-9]+"}
  ],
  "slot_assignment": {
    "top": "navigation_menu",
    "center": ["h1", "text", "img", "form"],
    "bottom": "footer_text"
  },
  "reasoning": "Краткое обоснование плана"
}
PROMPT;

    private AgentChatHandler $chatHandler;

    public function __construct(string $root)
    {
        $client = new AicliClient($root);
        $this->chatHandler = new AgentChatHandler($client, $root);
    }

    public function execute(array $context): array
    {
        $userPrompt = $context['input']['prompt'] ?? '';
        $tools = $context['tools_context'] ?? [];

        // Формируем задание для LLM
        $llmInput = "Запрос пользователя: {$userPrompt}\n\n"
            . "Доступные таблицы БД: " . json_encode($tools['db_tables'] ?? []) . "\n"
            . "Текущие ошибки структуры: " . json_encode($tools['structure_errors'] ?? []) . "\n\n"
            . "Спроектируй структуру.";

        $history = [
            ['role' => 'user', 'content' => $llmInput]
        ];

        $result = $this->chatHandler->handle('agent:chat', [
            'system_prompt' => self::SYSTEM_PROMPT,
            'history' => $history,
        ], 'architect');

        if (!$result->ok) {
            throw new \RuntimeException('Architect LLM failed: ' . $result->error);
        }

        $reply = (string) ($result->data['reply'] ?? '');
        
        // Очищаем от markdown-оберток, если LLM их добавил
        $reply = (string) preg_replace('/^```json\s*|\s*```$/m', '', trim($reply));
        
        $parsed = json_decode($reply, true);
        if (!is_array($parsed)) {
            throw new \RuntimeException('Architect returned invalid JSON: ' . substr($reply, 0, 300));
        }

        return $parsed;
    }
}
