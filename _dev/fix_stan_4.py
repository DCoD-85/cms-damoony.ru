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

# Правим переменную $children
patch_file("src/Ui/BlockCodegen.php", [
    (
        "$code .= self::renderWithCounter($children, $var, $contextVar, $counter, $indent);",
        "$code .= self::renderWithCounter(array_values($children), $var, $contextVar, $counter, $indent);"
    )
])

print("Done!")