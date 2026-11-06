from pathlib import Path

def patch_file(path, replacements):
    p = Path(path)
    if not p.exists(): return
    text = p.read_text(encoding="utf-8")
    for find, replace in replacements:
        if find in text:
            text = text.replace(find, replace)
    p.write_text(text, encoding="utf-8")
    print(f"Patched: {path}")

# 1. FillerWriteCommand
patch_file("src/Cli/Commands/FillerWriteCommand.php", [
    (
        "$phpCode = $compiler->compile($namespace, $className, $block, $blocks);",
        "$phpCode = $compiler->compile($namespace, $className, $block, array_values($blocks));"
    )
])

# 2. PageWriteCommand
patch_file("src/Cli/Commands/PageWriteCommand.php", [
    (
        "$php = $compiler->compile($namespace . '\\\\' . $allias, $page->classChain, $blocks, $withSchema);",
        "$php = $compiler->compile($namespace . '\\\\' . $allias, $page->classChain, array_values($blocks), $withSchema);"
    )
])

print("Done!")