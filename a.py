from pathlib import Path

BASE = Path.cwd()
p = BASE / "src/Core/App/FrontController.php"
src = p.read_text(encoding="utf-8")

OLD = "if ($authOverride instanceof AuthInterface || $authOverride instanceof \\Damoony\\Admin\\AuthInterface) {"
NEW = "if ($authOverride instanceof AuthInterface) {"

if NEW in src:
    print("SKIP: already fixed")
elif OLD in src:
    src = src.replace(OLD, NEW, 1)
    p.write_text(src, encoding="utf-8", newline="")
    print("OK: Removed legacy Admin\\AuthInterface check from FrontController")
else:
    print("FAIL: marker not found")