<?php

declare(strict_types=1);

namespace Damoony\Ai\Orchestrator;

use Damoony\Files\Paths;

final class ContextManager
{
    private string $file;

    public function __construct(Paths $paths, string $sessionId, string $realDomain)
    {
        $sessionDir = $paths->sessionDir($sessionId, $realDomain);
        $this->file = $sessionDir . '/context.json';
    }

    /**
     * Инициализирует пустой контекст, если файла нет.
     */
    public function init(string $prompt, string $targetBlock = 'all', array $currentBlocks = []): void
    {
        if (is_file($this->file)) {
            return;
        }

        $context = [
            'meta' => [
                'status' => 'init',
                'iteration' => 1,
                'max_iterations' => 3,
                'target_block' => $targetBlock,
            ],
            'input' => [
                'prompt' => $prompt,
                'current_blocks' => $currentBlocks,
                'existing_schema' => [],
            ],
            'tools_context' => [], // Данные из db:schema, structure:check
            'agent_outputs' => [
                'architect' => null,
                'writer' => null,
                'coder' => null,
                'critic' => null,
            ],
        ];

        $this->save($context);
    }

    /**
     * @return array<string, mixed>
     */
    public function read(): array
    {
        if (!is_file($this->file)) {
            throw new \RuntimeException('Context file not found: ' . $this->file);
        }
        $raw = file_get_contents($this->file);
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Invalid context JSON');
        }
        return $decoded;
    }


    /**
     * Добавляет сообщение в историю диалога.
     * @param string $role
     * @param string $content
     */
    public function addHistory(string $role, string $content): void
    {
        $ctx = $this->read();
        if (!isset($ctx['input']['history'])) {
            $ctx['input']['history'] = [];
        }
        $ctx['input']['history'][] = ['role' => $role, 'content' => $content];
        $this->save($ctx);
    }


    /**
     * Обновляет текущий промпт и блоки перед запуском агента.
     */
    public function updateInput(string $prompt, array $currentBlocks = []): void
    {
        $ctx = $this->read();
        $ctx['input']['prompt'] = $prompt;
        $ctx['input']['current_blocks'] = $currentBlocks;
        $this->save($ctx);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function save(array $context): void
    {
        $dir = dirname($this->file);
        if (!is_dir($dir)) {
            mkdir($dir, 0o775, true);
        }
        file_put_contents(
            $this->file,
            json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Обновляет конкретную секцию контекста.
     * @param array<string, mixed> $data
     */
    public function updateAgentOutput(string $agent, array $data): void
    {
        $context = $this->read();
        $context['agent_outputs'][$agent] = $data;
        $this->save($context);
    }
}
