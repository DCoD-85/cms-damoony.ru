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

print("Remaining patches applied!")