<?php

declare(strict_types=1);

namespace Damoony\Ai\Orchestrator\Agent;

use Damoony\Ai\Aicli\AicliClient;
use Damoony\Ai\Aicli\Handler\AgentChatHandler;

final class InterviewerAgent implements AgentInterface, InterviewerAgentInterface
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
        Ты — дружелюбный менеджер по созданию сайтов (Интервьюер).
        Твоя задача: общаться с клиентом и собирать техническое задание (бриф).
        Ты НЕ генерируешь сайт, ты только общаешься.

        ПРАВИЛА:
        1. Отвечай кратко и по делу, задавай наводящие вопросы.
        2. Всегда возвращай ответ СТРОГО в формате JSON.
        3. Поле "brief" должно быть ОБЫЧНОЙ СТРОКОЙ (не вложенным JSON-объектом), где перечислены требования.
        4. Если клиент просто здоровается, просто поздоровайся в ответ.

        ФОРМАТ ОТВЕТА (строго JSON):
        {
          "message": "Твой ответ пользователю",
          "brief": "Сайт кофейни, темный дизайн, меню"
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
        $userPrompt = trim($context['input']['prompt'] ?? '');
        $currentBrief = $context['input']['brief'] ?? 'Пока нет.';
        $history = $context['input']['history'] ?? [];

        // ЖЕЛЕЗОБЕТОННАЯ ПРОВЕРКА (перехватываем команду до вызова LLM)
        if (mb_stripos($userPrompt, 'создава') !== false ||
            mb_stripos($userPrompt, 'генерир') !== false ||
            mb_stripos($userPrompt, 'примен') !== false ||
            mb_stripos($userPrompt, 'поехали') !== false ||
            mb_stripos($userPrompt, 'старт') !== false) {
            return ['action' => 'build', 'brief' => $currentBrief];
        }

        // Формируем историю для LLM
        $messages = [['role' => 'system', 'content' => self::SYSTEM_PROMPT]];
        foreach ($history as $msg) {
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }
        $messages[] = ['role' => 'user', 'content' => $userPrompt];

        // Скрытая инструкция для LLM
        $messages[] = ['role' => 'system', 'content' => "Текущий бриф: {$currentBrief}. Верни строго JSON: {\"message\": \"...\", \"brief\": \"...\"}"];

        $result = $this->chatHandler->handle('agent:chat', [
            'history' => $messages,
        ], 'reporter');

        if (!$result->ok) {
            throw new \RuntimeException('Interviewer LLM failed: ' . $result->error);
        }

        $reply = trim((string) ($result->data['reply'] ?? ''));

        // Извлекаем JSON
        $parsed = null;
        if (preg_match('/\{.*\}/s', $reply, $matches)) {
            $parsed = json_decode($matches[0], true);
        }

        // Если JSON битый, просто отдаем текст
        if (!is_array($parsed) || !isset($parsed['message'])) {
            return ['action' => 'chat', 'message' => $reply, 'brief' => $currentBrief];
        }

        // Превращаем бриф в строку, если LLM вернула массив
        $newBrief = $parsed['brief'] ?? $currentBrief;
        if (is_array($newBrief)) {
            $newBrief = implode(', ', $newBrief);
        }

        return [
            'action' => 'chat',
            'message' => $parsed['message'],
            'brief' => $newBrief,
        ];
    }
}
