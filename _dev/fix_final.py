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
        text = text.replace(find, replace) # replace all occurrences
    if text != orig:
        p.write_text(text, encoding="utf-8")
        print(f"Patched: {path}")

# 1. OrchestratorTest - добавляем мок AicliClient
patch_file("_dev/tests/Unit/Ai/Orchestrator/OrchestratorTest.php", [
    (
        "$orchestrator = new Orchestrator($cm, $fake, $fakeInterviewer, $fakeArchitect, $fakeCoder);",
        "$fakeAicli = $this->createMock(\\Damoony\\Ai\\Aicli\\AicliClient::class);\n        $orchestrator = new Orchestrator($cm, $fake, $fakeInterviewer, $fakeArchitect, $fakeCoder, $fakeAicli);"
    )
])

# 2. Изоляция тестов для PHPUnit 11 (убирает Risky)
patch_file("_dev/tests/Unit/Core/ApplicationTest.php", [
    (
        "final class ApplicationTest extends TestCase",
        "#[\\PHPUnit\\Framework\\Attributes\\RunInSeparateProcess]\n#[\\PHPUnit\\Framework\\Attributes\\PreserveGlobalState(false)]\nfinal class ApplicationTest extends TestCase"
    )
])
patch_file("_dev/tests/Unit/Core/ErrorHandlerTest.php", [
    (
        "final class ErrorHandlerTest extends TestCase",
        "#[\\PHPUnit\\Framework\\Attributes\\RunInSeparateProcess]\n#[\\PHPUnit\\Framework\\Attributes\\PreserveGlobalState(false)]\nfinal class ErrorHandlerTest extends TestCase"
    )
])

# 3. BlockCodegen:217 - типизируем $children
patch_file("src/Ui/BlockCodegen.php", [
    (
        "if (is_array($children) && $children !== []) {\n                $code .= self::renderWithCounter($children, $var, $contextVar, $counter, $indent);",
        "if (is_array($children) && $children !== []) {\n                /** @var list<array<string, mixed>> $children */\n                $code .= self::renderWithCounter($children, $var, $contextVar, $counter, $indent);"
    )
])

# 4. ai_edit.php:151 - типизируем $current
patch_file("www/admin/ai_edit.php", [
    (
        "$orchResult = $orchestrator->run($tabId, $realDomain, $alias, $prompt, $targetBlock, $current);",
        "/** @var list<array<string, mixed>> $current */\n        $orchResult = $orchestrator->run($tabId, $realDomain, $alias, $prompt, $targetBlock, $current);"
    )
])

# 5. SchemeSaveCommand:137 - удаляем лишнюю проверку is_array
patch_file("src/Cli/Commands/SchemeSaveCommand.php", [
    (
        "foreach ($children as $child) {\n            if (!is_array($child)) {\n                continue;\n            }\n",
        "foreach ($children as $child) {\n"
    )
])

# 6. DbCallCommand:163 - убираем всегда ложное сравнение
patch_file("src/Cli/Commands/DbCallCommand.php", [
    (
        "$r->data['row_count'] = array_is_list($result) ? count($result) : (count($result) === 0 ? 0 : 1);",
        "$r->data['row_count'] = array_is_list($result) ? count($result) : 1;"
    )
])

# 7. Write-команды - откатываем array_values и добавляем PHPDoc
for cmd in ["BlockWriteCommand", "FillerWriteCommand", "PageWriteCommand"]:
    p = Path(f"src/Cli/Commands/{cmd}.php")
    if p.exists():
        text = p.read_text(encoding="utf-8")
        # Возвращаем как было
        text = text.replace("->compile(array_values($blocks)", "->compile($blocks")
        # Добавляем PHPDoc перед вызовом
        text = text.replace("$compiler->compile($blocks", "/** @var list<array<string, mixed>> $blocks */\n        $compiler->compile($blocks")
        p.write_text(text, encoding="utf-8")
        print(f"Patched: {p}")

print("All final patches applied!")