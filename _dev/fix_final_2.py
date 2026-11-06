from pathlib import Path

def patch_file(path, replacements):
    p = Path(path)
    if not p.exists():
        return
    text = p.read_text(encoding="utf-8")
    for find, replace in replacements:
        if find in text:
            text = text.replace(find, replace)
    p.write_text(text, encoding="utf-8")
    print(f"Patched: {path}")

# 1. Убираем ошибочные атрибуты с класса в тестах
patch_file("_dev/tests/Unit/Core/ApplicationTest.php", [
    ("#[\\PHPUnit\\Framework\\Attributes\\RunInSeparateProcess]\n#[\\PHPUnit\\Framework\\Attributes\\PreserveGlobalState(false)]\nfinal class ApplicationTest", "final class ApplicationTest")
])
patch_file("_dev/tests/Unit/Core/ErrorHandlerTest.php", [
    ("#[\\PHPUnit\\Framework\\Attributes\\RunInSeparateProcess]\n#[\\PHPUnit\\Framework\\Attributes\\PreserveGlobalState(false)]\nfinal class ErrorHandlerTest", "final class ErrorHandlerTest")
])

# 2. Жесткий каст для компиляторов (PHPStan это понимает)
for cmd in ["BlockWriteCommand", "FillerWriteCommand", "PageWriteCommand"]:
    p = Path(f"src/Cli/Commands/{cmd}.php")
    if p.exists():
        text = p.read_text(encoding="utf-8")
        # Убираем наш неудачный PHPDoc и ставим array_values
        text = text.replace("/** @var list<array<string, mixed>> $blocks */\n        $compiler->compile($blocks", "$compiler->compile(array_values($blocks)")
        p.write_text(text, encoding="utf-8")
        print(f"Patched: {p}")

print("Done!")