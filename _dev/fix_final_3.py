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

# 1. Фиксим Risky-тесты (переносим атрибуты на методы)
patch_file("_dev/tests/Unit/Core/ApplicationTest.php", [
    (r"(public function testAccessors\(\): void)", r"#[\\PHPUnit\\Framework\\Attributes\\RunInSeparateProcess]\n    #[\\PHPUnit\\Framework\\Attributes\\PreserveGlobalState(false)]\n    \1"),
    (r"(public function testBootIsIdempotent\(\): void)", r"#[\\PHPUnit\\Framework\\Attributes\\RunInSeparateProcess]\n    #[\\PHPUnit\\Framework\\Attributes\\PreserveGlobalState(false)]\n    \1")
])

patch_file("_dev/tests/Unit/Core/ErrorHandlerTest.php", [
    (r"(public function testHandleErrorThrowsWhenReported\(\): void)", r"#[\\PHPUnit\\Framework\\Attributes\\RunInSeparateProcess]\n    #[\\PHPUnit\\Framework\\Attributes\\PreserveGlobalState(false)]\n    \1"),
    (r"(public function testHandleErrorReturnsFalseWhenSuppressed\(\): void)", r"#[\\PHPUnit\\Framework\\Attributes\\RunInSeparateProcess]\n    #[\\PHPUnit\\Framework\\Attributes\\PreserveGlobalState(false)]\n    \1"),
    (r"(public function testHandleShutdownIgnoresNonFatal\(\): void)", r"#[\\PHPUnit\\Framework\\Attributes\\RunInSeparateProcess]\n    #[\\PHPUnit\\Framework\\Attributes\\PreserveGlobalState(false)]\n    \1")
])

# 2. Фиксим PHPStan (добавляем строгий PHPDoc прямо перед компиляцией)
patch_file("src/Cli/Commands/BlockWriteCommand.php", [
    (r"(\$compiler->compile\(\$namespace, \$className, \$name, \$blocks\);)", r"/** @var list<array<string, mixed>> \$blocks */\n        \1")
])
patch_file("src/Cli/Commands/FillerWriteCommand.php", [
    (r"(\$compiler->compile\(\$namespace, \$className, \$name, \$blocks\);)", r"/** @var list<array<string, mixed>> \$blocks */\n        \1")
])
patch_file("src/Cli/Commands/PageWriteCommand.php", [
    (r"(\$compiler->compile\(\$namespace, \$className, \$blocks\);)", r"/** @var list<array<string, mixed>> \$blocks */\n        \1")
])

print("Done!")