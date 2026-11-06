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

# AlliasListCommand.php - убираем array_values внутри массива
patch_file("src/Cli/Commands/AlliasListCommand.php", [
    (
        "                'keys' => array_values($keys),",
        "                'keys' => $keys,"
    )
])

# www/admin/index.php
patch_file("www/admin/index.php", [
    (
        "View::setAvailableLangs($langs)",
        "View::setAvailableLangs(array_values($langs))"
    )
])

print("Done!")