<?php

declare(strict_types=1);

namespace Damoony\Ai\Orchestrator\Agent;

use Damoony\Cli\Ai\AicliClient;
use Damoony\Cli\Ai\Handler\AgentChatHandler;

final class ArchitectAgent implements AgentInterface, ArchitectAgentInterface
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
        Ты — Архитектор сайта (Senior Web Architect). На вход получаешь бриф и составляешь ПЛАН.

        ЗАДАЧА: разбить сайт на страницы и для каждой описать, какие секции/блоки будут в центре.

        ФОРМАТ ОТВЕТА (строго JSON):
        {
          "pages": [
            {
              "name": "home",
              "path": "",
              "parent": null,
              "title": "Главная",
              "brief": "Что должно быть на этой странице — 2-3 предложения",
              "sections": ["h1", "text", "section", "text"]
            },
            {
              "name": "menu",
              "path": "menu",
              "parent": null,
              "title": "Меню",
              "brief": "Страница с меню кофейни",
              "sections": ["h1", "text", "section", "section"]
            }
          ]
        }

        ПРАВИЛА:
        1. Если в брифе указано N страниц — создай ровно N страниц.
        2. Для каждой страницы в "sections" — перечисли 4-8 блоков.
        3. Поле "name" — латиница snake_case (home, about, menu, contacts).
        4. Поле "path" — URL без слэша на старте ("" для главной, "about", "menu").
        5. Поле "parent" — null (плоская структура) или имя родителя (для вложенности).
        6. Выдай СТРОГО валидный JSON без markdown-оберток.
        PROMPT;

    private AgentChatHandler $chatHandler;

    public function __construct(string $root)
    {
        $client = new AicliClient($root);
        $this->chatHandler = new AgentChatHandler($client, $root);
    }

    public function execute(array $context): array
    {
        $userPrompt = (string) ($context['input']['prompt'] ?? '');
        $brief = (string) ($context['input']['brief'] ?? '');
        $tools = $context['tools_context'] ?? [];

        $llmInput = "Бриф от Интервьюера:\n{$brief}\n\n"
            . "Исходный запрос: {$userPrompt}\n\n"
            . 'Доступные таблицы БД: ' . json_encode($tools['db_tables'] ?? []) . "\n"
            . 'Составь подробный план страниц.';

        $result = $this->chatHandler->handle('agent:chat', [
            'system_prompt' => self::SYSTEM_PROMPT,
            'history' => [['role' => 'user', 'content' => $llmInput]],
        ], 'architect');

        if (!$result->ok) {
            throw new \RuntimeException('Architect LLM failed: ' . $result->error);
        }

        $reply = (string) ($result->data['reply'] ?? '');
        $reply = (string) preg_replace('/^```json\s*|\s*```$/m', '', trim($reply));

        $parsed = json_decode($reply, true);
        if (!is_array($parsed) || !isset($parsed['pages']) || !is_array($parsed['pages'])) {
            throw new \RuntimeException(
                'Architect returned invalid JSON. Expected {"pages": [...]}, got: ' . substr($reply, 0, 500),
            );
        }

        return $parsed;
    }
}
