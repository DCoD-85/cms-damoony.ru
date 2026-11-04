<?php

declare(strict_types=1);

namespace Damoony\Ai\Aicli\Handler;

use Damoony\Ai\Aicli\AbstractHandler;
use Damoony\Ai\Aicli\AicliResult;

final class AgentChatHandler extends AbstractHandler
{
    private const SYSTEM_PROMPT = 'Ты — встроенный AI-конструктор сайтов в системе Damoony. Ты сам проектируешь и генерируешь сайты.
ЗАПРЕЩЕНО предлагать пользователю Wix, Tilda, WordPress или другие CMS. Ты сам являешься платформой.
Твоя цель — собрать ТЗ для сайта.
СТРОГИЕ ПРАВИЛА:
1. ЗАПРЕЩЕНО задавать больше одного вопроса за раз! Спросил про стиль — жди ответа.
2. Если пользователь отвечает коротко ("Да", "Нет", "На твой вкус"), прими это как ответ и переходи к следующей теме.
3. Если пользователь просит сбросить ("Удали", "Сброс"), ответь: "Хорошо, я сбросил все настройки. Давайте начнем сначала. Какой сайт вы хотите создать?"
4. Когда соберешь достаточно информации (минимум 3-4 ответа), напиши точную фразу: "Отлично! Я готов создать сайт. Напишите «Создавай», чтобы запустить генерацию."';

    public function commands(): array
    {
        return ['agent:chat'];
    }

    public function handle(string $command, array $args, string $role): AicliResult
    {
        $history = $this->arrayArg($args, 'history');
        $agentConfig = $this->loadAgentConfig();

        $messages = [
            ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
        ];
        foreach ($history as $msg) {
            if (is_array($msg) && isset($msg['role'], $msg['content'])) {
                $messages[] = ['role' => (string) $msg['role'], 'content' => (string) $msg['content']];
            }
        }

        $provider = is_string($agentConfig['llm_provider'] ?? null)
            ? $agentConfig['llm_provider']
            : 'local';

        [$url, $model, $apiKey, $isOpenAI] = $this->resolveProvider($provider, $agentConfig);

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'stream' => false,
        ];

        $headers = ['Content-Type: application/json'];
        if ($isOpenAI) {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('curl_init failed');
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_HEADER, true);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($err !== '') {
            throw new \RuntimeException("LLM error: {$err}");
        }

        $rawHeaders = substr((string) $response, 0, $headerSize);
        $rawBody = substr((string) $response, $headerSize);

        $decoded = json_decode($rawBody, true);

        if (isset($decoded['error'])) {
            $errMsg = is_array($decoded['error'])
                ? ($decoded['error']['message'] ?? json_encode($decoded['error']))
                : (string) $decoded['error'];
            throw new \RuntimeException('LLM error: ' . $errMsg);
        }

        if (isset($decoded['choices'][0]['message']['content'])) {
            $reply = $decoded['choices'][0]['message']['content'];
        } elseif (isset($decoded['message']['content'])) {
            $reply = $decoded['message']['content'];
        } else {
            throw new \RuntimeException(
                "Неизвестный ответ от LLM. HTTP {$httpCode}. Headers: "
                . substr($rawHeaders, 0, 200)
                . ' Body: ' . substr($rawBody, 0, 500),
            );
        }

        return AicliResult::ok(['reply' => (string) $reply]);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadAgentConfig(): array
    {
        $file = $this->root . '/config/agent.config';
        if (!is_file($file)) {
            return [];
        }
        $raw = file_get_contents($file);
        if ($raw === false) {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $cfg
     *
     * @return array{0: string, 1: string, 2: string, 3: bool} [url, model, apiKey, isOpenAI]
     */
    private function resolveProvider(string $provider, array $cfg): array
    {
        if ($provider === 'deepseek') {
            $apiKey = (string) ($cfg['deepseek']['api_key'] ?? '');
            $model = (string) ($cfg['deepseek']['model'] ?? 'deepseek-chat');
            $baseUrl = (string) ($cfg['deepseek']['base_url'] ?? 'https://api.deepseek.com/v1');
            if ($apiKey === '') {
                throw new \RuntimeException('DeepSeek API key missing in agent.config');
            }

            return [rtrim($baseUrl, '/') . '/chat/completions', $model, $apiKey, true];
        }

        if ($provider === 'groq') {
            $apiKey = (string) ($cfg['groq']['api_key'] ?? '');
            $model = (string) ($cfg['groq']['model'] ?? 'llama-3.3-70b-versatile');
            $baseUrl = (string) ($cfg['groq']['base_url'] ?? 'https://api.groq.com/openai/v1');
            if ($apiKey === '') {
                throw new \RuntimeException('Groq API key missing in agent.config');
            }

            return [rtrim($baseUrl, '/') . '/chat/completions', $model, $apiKey, true];
        }

        if ($provider === 'yandex') {
            $apiKey = (string) ($cfg['yandex']['api_key'] ?? '');
            $folderId = (string) ($cfg['yandex']['folder_id'] ?? '');
            $modelName = (string) ($cfg['yandex']['model'] ?? 'deepseek-v4-flash');
            $baseUrl = (string) ($cfg['yandex']['base_url'] ?? 'https://llm.api.cloud.yandex.net/v1');
            if ($apiKey === '' || $folderId === '') {
                throw new \RuntimeException('Yandex API key or folder_id missing in agent.config');
            }
            $model = "gpt://{$folderId}/{$modelName}";

            return [rtrim($baseUrl, '/') . '/chat/completions', $model, $apiKey, true];
        }

        if ($provider === 'cloud_ru') {
            $apiKey = (string) ($cfg['cloud_ru']['api_key'] ?? '');
            $model = (string) ($cfg['cloud_ru']['model'] ?? 'GigaChat-Lightning');
            $baseUrl = (string) ($cfg['cloud_ru']['base_url'] ?? 'https://foundation-models.api.cloud.ru/v1');
            if ($apiKey === '') {
                throw new \RuntimeException('Cloud.ru API key missing in agent.config');
            }

            return [rtrim($baseUrl, '/') . '/chat/completions', $model, $apiKey, true];
        }

        $model = (string) ($cfg['llm_model'] ?? 'qwen2.5-coder:7b');

        return ['http://localhost:11434/api/chat', $model, '', false];
    }
}
