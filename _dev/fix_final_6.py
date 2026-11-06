from pathlib import Path

def patch_file(path, replacements):
    p = Path(path)
    if not p.exists(): return
    text = p.read_text(encoding="utf-8")
    for find, replace in replacements:
        text = text.replace(find, replace)
    p.write_text(text, encoding="utf-8")
    print(f"Patched: {path}")

# Убираем старые атрибуты (если остались) и ставим новые на методы
patch_file("_dev/tests/Unit/Core/ApplicationTest.php", [
    ("#[\\PHPUnit\\Framework\\Attributes\\RunInSeparateProcess]\n#[\\PHPUnit\\Framework\\Attributes\\PreserveGlobalState(false)]\n", ""),
    ("public function testAccessors(): void", "#[\\PHPUnit\\Framework\\Attributes\\WithoutErrorHandler]\n    public function testAccessors(): void"),
    ("public function testBootIsIdempotent(): void", "#[\\PHPUnit\\Framework\\Attributes\\WithoutErrorHandler]\n    public function testBootIsIdempotent(): void")
])

patch_file("_dev/tests/Unit/Core/ErrorHandlerTest.php", [
    ("#[\\PHPUnit\\Framework\\Attributes\\RunInSeparateProcess]\n#[\\PHPUnit\\Framework\\Attributes\\PreserveGlobalState(false)]\n", ""),
    ("public function testHandleErrorThrowsWhenReported(): void", "#[\\PHPUnit\\Framework\\Attributes\\WithoutErrorHandler]\n    public function testHandleErrorThrowsWhenReported(): void"),
    ("public function testHandleErrorReturnsFalseWhenSuppressed(): void", "#[\\PHPUnit\\Framework\\Attributes\\WithoutErrorHandler]\n    public function testHandleErrorReturnsFalseWhenSuppressed(): void"),
    ("public function testHandleShutdownIgnoresNonFatal(): void", "#[\\PHPUnit\\Framework\\Attributes\\WithoutErrorHandler]\n    public function testHandleShutdownIgnoresNonFatal(): void")
])

print("Done!")