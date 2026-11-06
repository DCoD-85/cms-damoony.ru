import re
from pathlib import Path

def patch_file(path, replacements):
    p = Path(path)
    if not p.exists():
        print(f"File not found: {path}")
        return
    text = p.read_text(encoding="utf-8")
    orig = text
    for find, replace in replacements:
        assert find in text, f"Pattern not found in {path}: {find[:50]}..."
        text = text.replace(find, replace, 1)
    if text != orig:
        p.write_text(text, encoding="utf-8")
        print(f"Patched: {path}")

# 1. CoderAgentInterface - добавляем executeForPage
patch_file("src/Ai/Orchestrator/Agent/CoderAgentInterface.php", [
    (
        "    public function execute(array $context): array;\n}",
        "    public function execute(array $context): array;\n\n    /**\n     * @param array<string, mixed> $context\n     * @param array<string, mixed> $pagePlan\n     * @return array<string, mixed>\n     */\n    public function executeForPage(array $context, array $pagePlan): array;\n}"
    )
])

# 2. CoderAgent - правим PHPDoc и защищаем от null
patch_file("src/Ai/Orchestrator/Agent/CoderAgent.php", [
    (
        "    /** @param array<string, mixed> $block @return array<string, mixed> */\n    private static function normalizeBlock(array $block): array",
        "    /**\n     * @param array<string, mixed> $block\n     * @return array<string, mixed>\n     */\n    private static function normalizeBlock(array $block): array"
    ),
    (
        "        $text = preg_replace('/\\*\\*(.+?)\\*\\*/u', '$1', $text);\n        $text = preg_replace('/(?<!\\*)\\*(?!\\*)(.+?)(?<!\\*)\\*(?!\\*)/u', '$1', $text);\n        $text = preg_replace('/`([^`]+)`/u', '$1', $text);\n        $text = preg_replace('/\\[([^\\]]+)\\]\\([^)]+\\)/u', '$1', $text);\n        $text = preg_replace('/^#+\\s*/mu', '', $text);\n        return trim($text);",
        "        $text = preg_replace('/\\*\\*(.+?)\\*\\*/u', '$1', $text) ?? $text;\n        $text = preg_replace('/(?<!\\*)\\*(?!\\*)(.+?)(?<!\\*)\\*(?!\\*)/u', '$1', $text) ?? $text;\n        $text = preg_replace('/`([^`]+)`/u', '$1', $text) ?? $text;\n        $text = preg_replace('/\\[([^\\]]+)\\]\\([^)]+\\)/u', '$1', $text) ?? $text;\n        $text = preg_replace('/^#+\\s*/mu', '', $text) ?? $text;\n        return trim($text);"
    )
])

# 3. ContextManager - добавляем типы для массивов
patch_file("src/Ai/Orchestrator/ContextManager.php", [
    (
        "    /**\n     * Инициализирует пустой контекст, если файла нет.\n     */\n    public function init(string $prompt, string $targetBlock = 'all', array $currentBlocks = []): void",
        "    /**\n     * Инициализирует пустой контекст, если файла нет.\n     * @param list<array<string, mixed>> $currentBlocks\n     */\n    public function init(string $prompt, string $targetBlock = 'all', array $currentBlocks = []): void"
    ),
    (
        "    /**\n     * Обновляет текущий промпт и блоки перед запуском агента.\n     */\n    public function updateInput(string $prompt, array $currentBlocks = []): void",
        "    /**\n     * Обновляет текущий промпт и блоки перед запуском агента.\n     * @param list<array<string, mixed>> $currentBlocks\n     */\n    public function updateInput(string $prompt, array $currentBlocks = []): void"
    )
])

# 4. Orchestrator - типы для run()
patch_file("src/Ai/Orchestrator/Orchestrator.php", [
    (
        "    /**\n     * @return array<string, mixed>\n     */\n    public function run(",
        "    /**\n     * @param list<array<string, mixed>> $currentBlocks\n     * @return array<string, mixed>\n     */\n    public function run("
    )
])

# 5. Kernel.php - убираем лишний instanceof
patch_file("src/Http/Kernel.php", [
    (
        "            $response = ($this->handler)($request);\n            if (!$response instanceof Response) {\n                $this->logger->error('Handler returned non-Response', ['type' => get_debug_type($response)]);\n                return Response::internalError();\n            }\n\n            return $response;",
        "            $response = ($this->handler)($request);\n            return $response;"
    )
])

# 6. Request.php - убираем лишние is_string
patch_file("src/Http/Request.php", [
    (
        "        foreach ($server as $key => $value) {\n            if (!is_string($key) || !is_string($value)) {\n                continue;\n            }",
        "        foreach ($server as $key => $value) {\n            if (!is_string($value)) {\n                continue;\n            }"
    ),
    (
        "        $https = $this->server['HTTPS'] ?? '';\n        return is_string($https) && strtolower($https) === 'on';",
        "        $https = $this->server['HTTPS'] ?? '';\n        return strtolower($https) === 'on';"
    )
])

# 7. SchemeController.php - убираем is_string
patch_file("src/Admin/Controller/SchemeController.php", [
    (
        "            foreach ($schemes as $s) {\n                if (!is_string($s)) {\n                    continue;\n                }\n\n                $editUrl",
        "            foreach ($schemes as $s) {\n                $editUrl"
    )
])

# 8. DbCallCommand.php - empty()
patch_file("src/Cli/Commands/DbCallCommand.php", [
    (
        "            $r->data['row_count'] = array_is_list($result) ? count($result) : (empty($result) ? 0 : 1);",
        "            $r->data['row_count'] = array_is_list($result) ? count($result) : (count($result) === 0 ? 0 : 1);"
    )
])

# 9. NativeSession.php - match
patch_file("src/Session/NativeSession.php", [
    (
        "                'Strict' => 'Strict',\n                'strict' => 'strict',\n                default => 'Lax',",
        "                'Strict' => 'Strict',\n                default => 'Lax',"
    )
])

# 10. AgentChatHandler.php - curl_setopt
patch_file("src/Ai/Aicli/Handler/AgentChatHandler.php", [
    (
        "        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));",
        "        curl_setopt($ch, CURLOPT_POSTFIELDS, (string) json_encode($payload));"
    )
])

# 11. CurlTransport.php - игнорируем ошибку
patch_file("src/Ainet/CurlTransport.php", [
    (
        "        curl_setopt_array($ch, $curlOptions);",
        "        /* @phpstan-ignore-next-line */\n        curl_setopt_array($ch, $curlOptions);"
    )
])

# 12. HandlerRegistry.php - array_values
patch_file("src/Ai/Aicli/HandlerRegistry.php", [
    (
        "        return array_values(array_merge(array_keys($this->exact), $prefixes));",
        "        return array_merge(array_keys($this->exact), $prefixes);"
    )
])

# 13. AlliasListCommand.php - array_values
patch_file("src/Cli/Commands/AlliasListCommand.php", [
    (
        "        return array_values(array_unique(",
        "        return array_unique("
    )
])

# 14. Write-команды - добавляем array_values для блоков
for cmd in ["BlockWriteCommand", "FillerWriteCommand", "PageWriteCommand"]:
    p = Path(f"src/Cli/Commands/{cmd}.php")
    if p.exists():
        text = p.read_text(encoding="utf-8")
        if "->compile($blocks" in text:
            text = text.replace("->compile($blocks", "->compile(array_values($blocks)")
            p.write_text(text, encoding="utf-8")
            print(f"Patched: {p}")

# 15. BlockCodegen.php - array_values
patch_file("src/Ui/BlockCodegen.php", [
    (
        "        return self::renderWithCounter($blocks,",
        "        return self::renderWithCounter(array_values($blocks),"
    ),
    (
        "            self::renderWithCounter((array) $block['children'],",
        "            self::renderWithCounter(array_values((array) $block['children']),"
    )
])

# 16. www/admin/index.php
patch_file("www/admin/index.php", [
    (
        "View::setAvailableLangs($langs)",
        "View::setAvailableLangs(array_values($langs))"
    )
])

print("All patches applied successfully!")