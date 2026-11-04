<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Ai\Aicli\AicliClient;
use Damoony\Ai\Aicli\Handler\AgentChatHandler;
use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Files\Paths;

final class AiEditPageCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths) {}

    public function name(): string { return 'ai:edit-page'; }
    public function description(): string { return 'Edit page blocks via AI prompt (AIUI)'; }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();
        $domain = $args['domain'] ?? '';
        $alias = $args['alias'] ?? 'www';
        $page = $args['page'] ?? '__root__';
        $prompt = $args['prompt'] ?? '';
        $force = isset($args['force']) && $args['force'] === '1';

        if ($domain === '' || $prompt === '') {
            $r->error('Missing --domain or --prompt');
            return $r;
        }

        // 1. Читаем текущие блоки из editor.json
        $base = $this->paths->algoritm($domain, $alias);
        $jsonFile = ($page === '__root__') 
            ? $base . '/algoritm.editor.json' 
            : $base . '/' . str_replace('_', '/', $page) . '/algoritm_' . $page . '.editor.json';

        $currentBlocks = [];
        if (is_file($jsonFile)) {
            $raw = file_get_contents($jsonFile);
            $decoded = json_decode((string)$raw, true);
            if (is_array($decoded)) {
                $currentBlocks = $decoded;
            }
        }

        // 2. Формируем промпт для LLM
        $llmPrompt = "You are an AI web editor. Current page blocks JSON:\n"
            . json_encode($currentBlocks, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            . "\n\nUser request: {$prompt}\n\n"
            . "Output ONLY the updated JSON array of blocks. Keep existing blocks unless user asked to remove them.\n"
            . "Available types: h1, h2, h3, text, img, link, button, ul, nav, section, div, table, form, input, textarea, select, label.\n"
            . "Output JSON array only, no markdown.";

        // 3. Спрашиваем LLM (используем наш существующий AgentChatHandler)
        $client = new AicliClient(dirname(__DIR__, 3));
        $chatHandler = new AgentChatHandler($client, dirname(__DIR__, 3));
        
        $context->step('Asking AI...', true);
        $chatResult = $chatHandler->handle('agent:chat', [
            'history' => [['role' => 'user', 'content' => $llmPrompt]]
        ], 'designer');

        if (!$chatResult->ok) {
            $r->error('AI failed: ' . $chatResult->error);
            return $r;
        }

        $reply = (string) ($chatResult->data['reply'] ?? '');
        
        // 4. Извлекаем JSON из ответа (простой парсинг)
        $reply = preg_replace('/^```json\s*|\s*```$/m', '', trim($reply));
        $newBlocks = json_decode((string) $reply, true);

        if (!is_array($newBlocks)) {
            $r->error('AI returned invalid JSON. Raw: ' . substr((string) $reply, 0, 200));
            return $r;
        }

        // 5. Сохраняем (если не dry-run)
        $r->data = [
            'old' => $currentBlocks,
            'new' => $newBlocks,
            'diff' => count($newBlocks) - count($currentBlocks) . ' blocks'
        ];

        if ($force) {
            $encoded = base64_encode((string) json_encode($newBlocks, JSON_UNESCAPED_UNICODE));
            
            // Вызываем page:write, чтобы скомпилировать PHP
            $root = $this->paths->root();
            $cliPath = $root . '/bin/cli.php';
            
            $cmd = 'php ' . escapeshellarg($cliPath) . ' page:write --domain=' . escapeshellarg($domain) 
                 . ' --allias=' . escapeshellarg($alias) 
                 . ' --name=' . escapeshellarg($page)
                 . ' --json_b64=' . escapeshellarg($encoded)
                 . ' 2>&1';
            
            $output = shell_exec($cmd);
            
            if (str_contains((string)$output, 'OK')) {
                $context->step("Page recompiled successfully", true);
                $r->data['file'] = $jsonFile;
            } else {
                $r->error('Compilation failed: ' . $output);
                return $r;
            }
        } else {
            $context->step('Dry-run: not saved. Use --force=1 to apply.', true);
        }

        return $r;
    }
}
