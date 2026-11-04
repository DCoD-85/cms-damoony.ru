<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Ai\Aicli\AicliClient;
use Damoony\Ai\Aicli\Handler\AgentChatHandler;
use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Domain\DomainResolverInterface;
use Damoony\Files\Paths;

final class AiEditPageCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
        private readonly DomainResolverInterface $resolver,
    ) {
    }

    public function name(): string
    {
        return 'ai:edit-page';
    }

    public function description(): string
    {
        return 'Edit page blocks via AI prompt (AIUI)';
    }

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

        // 1. Резолвим домен в real (как это делает PageHandler)
        try {
            $domainContext = $this->resolver->resolve($domain);
            $realDomain = $domainContext->real;
        } catch (\Throwable) {
            $realDomain = $domain; // fallback
        }

        // 2. Читаем текущие блоки из editor.json
        $base = $this->paths->algoritm($realDomain, $alias);
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

        // 3. Формируем промпт для LLM
        $llmPrompt = "You are an AI web editor. You must output a JSON array of block objects.\n"
            . "Current page blocks:\n"
            . json_encode($currentBlocks, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n"
            . "User request: {$prompt}\n\n"
            . "CRITICAL RULES:\n"
            . "1. Output ONLY a valid JSON array.\n"
            . "2. Each element MUST be an object with a 'type' field.\n"
            . "3. Allowed types: h1, h2, h3, text, img, link, button, ul, nav, section, div, table, form, input, textarea, select, label.\n"
            . "4. Keep existing blocks unless the user explicitly asks to delete them.\n\n"
            . "EXAMPLE OF CORRECT FORMAT:\n"
            . "[\n  {\"type\": \"h1\", \"content\": \"Hello\"},\n  {\"type\": \"text\", \"content\": \"World\"}\n]\n\n"
            . 'Output JSON array only. No markdown, no explanations.';

        // 4. Спрашиваем LLM
        $client = new AicliClient(dirname(__DIR__, 3));
        $chatHandler = new AgentChatHandler($client, dirname(__DIR__, 3));

        $context->step('Asking AI...', true);
        $chatResult = $chatHandler->handle('agent:chat', [
            'history' => [['role' => 'user', 'content' => $llmPrompt]],
        ], 'designer');

        if (!$chatResult->ok) {
            $r->error('AI failed: ' . $chatResult->error);
            return $r;
        }

        $reply = (string) ($chatResult->data['reply'] ?? '');

        // 5. Извлекаем JSON
        $reply = (string) preg_replace('/^```json\s*|\s*```$/m', '', trim($reply));
        $newBlocks = json_decode($reply, true);

        if (!is_array($newBlocks)) {
            $r->error('AI returned invalid JSON. Raw: ' . substr((string)$reply, 0, 200));
            return $r;
        }

        // Валидация структуры блоков (защита от того, что ИИ вернет массив строк)
        foreach ($newBlocks as $b) {
            if (!is_array($b) || !isset($b['type']) || !is_string($b['type'])) {
                $r->error('AI returned invalid block structure. Raw reply: ' . substr((string)$reply, 0, 500));
                return $r;
            }
        }

        // 6. Проверяем, изменилось ли что-то
        if ($currentBlocks === $newBlocks) {
            $r->error('AI не внесла изменений. Попробуйте переформулировать запрос.');
            return $r;
        }

        // 7. Сохраняем editor.json и компилируем PHP
        $r->data = [
            'old' => $currentBlocks,
            'new' => $newBlocks,
            'diff' => count($newBlocks) - count($currentBlocks) . ' blocks',
        ];

        if ($force) {
            file_put_contents($jsonFile, json_encode($newBlocks, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            $encoded = base64_encode((string) json_encode($newBlocks, JSON_UNESCAPED_UNICODE));

            $root = $this->paths->root();
            $cliPath = $root . '/bin/cli.php';

            $cmd = 'php ' . escapeshellarg($cliPath) . ' page:write --domain=' . escapeshellarg($realDomain)
                 . ' --allias=' . escapeshellarg($alias)
                 . ' --name=' . escapeshellarg($page)
                 . ' --json_b64=' . escapeshellarg($encoded)
                 . ' 2>&1';

            $output = shell_exec($cmd);

            if (str_contains((string)$output, 'OK')) {
                $context->step('Page recompiled successfully', true);
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
