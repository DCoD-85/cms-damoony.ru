<?php

declare(strict_types=1);

namespace Damoony\Ai\Orchestrator;

use Damoony\Ai\Orchestrator\Agent\ArchitectAgentInterface;
use Damoony\Ai\Orchestrator\Agent\CoderAgentInterface;
use Damoony\Ai\Orchestrator\Agent\InterviewerAgentInterface;

final class Orchestrator
{
    public function __construct(
        private readonly ContextManager $context,
        private readonly ToolsCollectorInterface $tools,
        private readonly InterviewerAgentInterface $interviewer,
        private readonly ArchitectAgentInterface $architect,
        private readonly CoderAgentInterface $coder,
        private readonly \Damoony\Ai\Aicli\AicliClient $aicli,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $currentBlocks
     *
     * @return array<string, mixed>
     */
    public function run(
        string $sessionId,
        string $realDomain,
        string $alias,
        string $prompt,
        string $targetBlock = 'all',
        array $currentBlocks = [],
    ): array {
        $this->context->init($prompt, $targetBlock, $currentBlocks);
        $this->context->updateInput($prompt, $currentBlocks);
        $ctx = $this->context->read();

        // 1. Сбор инструментов
        $toolCtx = $this->tools->collect($realDomain, $alias);
        $ctx['tools_context'] = $toolCtx;
        $ctx['meta']['status'] = $toolCtx['errors'] === [] ? 'tools_collected' : 'tools_failed';
        $this->context->save($ctx);

        if ($toolCtx['errors'] !== []) {
            return $ctx;
        }

        // 2. Интервьюер (Чат или Бриф)
        try {
            // Добавляем сообщение пользователя в историю ДО вызова агента
            $this->context->addHistory('user', $prompt);
            $ctx = $this->context->read();

            $interview = $this->interviewer->execute($ctx);
            $this->context->updateAgentOutput('interviewer', $interview);

            // Добавляем ответ агента в историю
            if (isset($interview['message'])) {
                $this->context->addHistory('assistant', $interview['message']);
            }

            $ctx = $this->context->read();

            // Сохраняем накопленный бриф
            $ctx['input']['brief'] = $interview['brief'] ?? ($ctx['input']['brief'] ?? '');

            if (($interview['action'] ?? 'chat') === 'chat') {
                $ctx['meta']['status'] = 'chat_done';
                $this->context->save($ctx);
                return $ctx; // Останавливаем конвейер
            }

            // Если это команда на генерацию, используем накопленный бриф как промпт
            $ctx['input']['prompt'] = $ctx['input']['brief'] ?? $ctx['input']['prompt'];
            $this->context->save($ctx);
        } catch (\Throwable $e) {
            $ctx['meta']['status'] = 'interviewer_failed';
            $ctx['meta']['errors'][] = $e->getMessage();
            $this->context->save($ctx);
            return $ctx;
        }

        // 3. Архитектор
        try {
            $plan = $this->architect->execute($ctx);
            $this->context->updateAgentOutput('architect', $plan);
            $ctx = $this->context->read();
            $ctx['meta']['status'] = 'architect_done';
            $this->context->save($ctx);
        } catch (\Throwable $e) {
            $ctx['meta']['status'] = 'architect_failed';
            $ctx['meta']['errors'][] = $e->getMessage();
            $this->context->save($ctx);
            return $ctx;
        }

        // 3. Кодер (Верстальщик)
        try {
            $code = $this->coder->execute($ctx);
            $this->context->updateAgentOutput('coder', $code);
            $ctx = $this->context->read();
            $ctx['meta']['status'] = 'coder_done';
            $this->context->save($ctx);
        } catch (\Throwable $e) {
            $ctx['meta']['status'] = 'coder_failed';
            $ctx['meta']['errors'][] = $e->getMessage();
            $this->context->save($ctx);
        }

        return $ctx;
    }

    /**
     * Прямой запуск Архитектора + Кодера (без Интервьюера).
     * Используется по кнопке "Создать сайт по брифу".
     *
     * @return array<string, mixed>
     */
    public function build(
        string $sessionId,
        string $realDomain,
        string $alias,
    ): array {
        $ctx = $this->context->read();

        $brief = (string) ($ctx['input']['brief'] ?? '');
        if ($brief === '') {
            $ctx['meta']['status'] = 'build_failed';
            $ctx['meta']['errors'][] = 'Brief is empty. Chat with the interviewer first.';
            $this->context->save($ctx);
            return $ctx;
        }

        $ctx['input']['prompt'] = $brief;
        $ctx['meta']['status'] = 'build_started';
        $this->context->save($ctx);

        // 1. Архитектор → каркас {pages: [...]}
        try {
            $plan = $this->architect->execute($ctx);
            $this->context->updateAgentOutput('architect', $plan);
            $ctx = $this->context->read();
            $ctx['meta']['status'] = 'architect_done';
            $this->context->save($ctx);
        } catch (\Throwable $e) {
            $ctx['meta']['status'] = 'architect_failed';
            $ctx['meta']['errors'][] = $e->getMessage();
            $this->context->save($ctx);
            return $ctx;
        }

        $pages = $plan['pages'] ?? [];
        if (empty($pages)) {
            $ctx['meta']['status'] = 'build_failed';
            $ctx['meta']['errors'][] = 'Architect returned no pages.';
            $this->context->save($ctx);
            return $ctx;
        }

        // 2. Цикл по страницам
        $progress = [];
        foreach ($pages as $i => $pagePlan) {
            $pageName = (string) ($pagePlan['name'] ?? ('page_' . $i));
            $isRoot = ($pagePlan['path'] ?? '') === '';
            $cliName = $isRoot ? '__root__' : $pageName;

            $progress[] = ['page' => $pageName, 'status' => 'pending'];
            $idx = count($progress) - 1;

            try {
                if (!$isRoot) {
                    $newCmd = 'page:new'
                        . ' --session=' . $sessionId
                        . ' --domain=' . $realDomain
                        . ' --allias=' . $alias
                        . ' --name=' . $pageName;
                    $this->aicli->runJson($newCmd);
                }

                $blocks = $this->coder->executeForPage($ctx, $pagePlan)['blocks'];
                if (empty($blocks)) {
                    throw new \RuntimeException('Coder returned empty blocks for ' . $pageName);
                }

                $tmp = sys_get_temp_dir() . '/block_' . $pageName . '_' . uniqid() . '.json';
                file_put_contents($tmp, json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
                $writeCmd = 'page:write'
                    . ' --session=' . $sessionId
                    . ' --domain=' . $realDomain
                    . ' --allias=' . $alias
                    . ' --name=' . $cliName
                    . ' --json_file=' . escapeshellarg($tmp)
                    . ' --with_schema=1';
                $writeRes = $this->aicli->runJson($writeCmd);
                @unlink($tmp);

                if (!($writeRes['ok'] ?? false)) {
                    throw new \RuntimeException('page:write failed for ' . $pageName . ': ' . ($writeRes['error'] ?? 'unknown'));
                }

                $progress[$idx]['status'] = 'done';
                $progress[$idx]['blocks'] = count($blocks);
            } catch (\Throwable $e) {
                $progress[$idx]['status'] = 'failed';
                $progress[$idx]['error'] = $e->getMessage();
            }

            $ctx = $this->context->read();
            $ctx['meta']['build_progress'] = $progress;
            $this->context->save($ctx);
        }

        $ctx = $this->context->read();
        $failed = array_filter($progress, fn ($p) => $p['status'] === 'failed');
        $ctx['meta']['status'] = empty($failed) ? 'build_done' : 'build_partial';
        $ctx['meta']['pages_total'] = count($pages);
        $ctx['meta']['pages_ok'] = count($progress) - count($failed);
        $this->context->save($ctx);

        return $ctx;
    }

}
