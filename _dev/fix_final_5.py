import re
from pathlib import Path

def patch_file(path, replacements):
    p = Path(path)
    if not p.exists(): return
    text = p.read_text(encoding="utf-8")
    for find, replace in replacements:
        text = re.sub(find, replace, text)
    p.write_text(text, encoding="utf-8")
    print(f"Patched: {path}")

# 1. PHPStan (оборачиваем $blocks в array_values)
patch_file("src/Cli/Commands/BlockWriteCommand.php", [
    (r"(\$compiler->compile\(\$namespace, \$className, \$name, )(\$blocks)\);", r"\1array_values(\2));")
])
patch_file("src/Cli/Commands/FillerWriteCommand.php", [
    (r"(\$compiler->compile\(\$namespace, \$className, \$name, )(\$blocks)\);", r"\1array_values(\2));")
])
patch_file("src/Cli/Commands/PageWriteCommand.php", [
    (r"(\$compiler->compile\(\$namespace, \$className, )(\$blocks)\);", r"\1array_values(\2));")
])

# 2. OrchestratorTest (фейковый интервьюер должен возвращать build, чтобы конвейер не останавливался)
patch_file("_dev/tests/Unit/Ai/Orchestrator/OrchestratorTest.php", [
    (r"return \['action' => 'chat', 'message' => 'test'\];", "return ['action' => 'build', 'message' => 'test'];")
])

# 3. Risky-тесты (убираем RunInSeparateProcess и ставим WithoutErrorHandler)
patch_file("_dev/tests/Unit/Core/ApplicationTest.php", [
    (r"#\[\\PHPUnit\\Framework\\Attributes\\RunInSeparateProcess\]\n\s*#\[\\PHPUnit\\Framework\\Attributes\\PreserveGlobalState\(false\)\]\n\s*", ""),
    (r"public function testAccessors\(\): void", "#[\\PHPUnit\\Framework\\Attributes\\WithoutErrorHandler]\n    public function testAccessors(): void"),
    (r"public function testBootIsIdempotent\(\): void", "#[\\PHPUnit\\Framework\\Attributes\\WithoutErrorHandler]\n    public function testBootIsIdempotent(): void")
])

patch_file("_dev/tests/Unit/Core/ErrorHandlerTest.php", [
    (r"#\[\\PHPUnit\\Framework\\Attributes\\RunInSeparateProcess\]\n\s*#\[\\PHPUnit\\Framework\\Attributes\\PreserveGlobalState\(false\)\]\n\s*", ""),
    (r"public function testHandleErrorThrowsWhenReported\(\): void", "#[\\PHPUnit\\Framework\\Attributes\\WithoutErrorHandler]\n    public function testHandleErrorThrowsWhenReported(): void"),
    (r"public function testHandleErrorReturnsFalseWhenSuppressed\(\): void", "#[\\PHPUnit\\Framework\\Attributes\\WithoutErrorHandler]\n    public function testHandleErrorReturnsFalseWhenSuppressed(): void"),
    (r"public function testHandleShutdownIgnoresNonFatal\(\): void", "#[\\PHPUnit\\Framework\\Attributes\\WithoutErrorHandler]\n    public function testHandleShutdownIgnoresNonFatal(): void")
])

print("Done!")