# -*- coding: utf-8 -*-
# Read-only: атрибуция красного cs-fixer. Пути нормализуются от корня репо.
import json
import os
from pathlib import Path

FORBIDDEN = (
    "src/Core/", "src/Ai/Roles.php", "src/Ai/Navigator.php",
    "src/UI/Page/PageCompiler.php", "src/UI/Page/PagePath.php",
    "src/UI/Page/PageRules.php", "src/UI/Region/BlockCompiler.php",
    "src/Ainet/AinetFacade.php", "bin/aicli.php", "bin/ainet.php",
    "bin/dbcli.php", "www/content.php", "composer.lock",
)
MINE = {
    "src/Cli/Commands/PageNewCommand.php",
    "src/Cli/Commands/PageWriteCommand.php",
    "_doc/start_new_audit.md", "_doc/visuo_showcase.md",
}

def norm(raw: str) -> str:
    return os.path.normpath(raw.replace("\\", "/")).replace("\\", "/")

d = json.loads(Path("_dev/cs.json").read_text(encoding="utf-8"))
files = d.get("files", [])
counts, diffs, norules = {}, [], []

for f in files:
    raw = f["name"].replace("\\", "/")
    n = norm(raw)
    tag = "MINE" if n in MINE else ("FORBIDDEN" if n.startswith(FORBIDDEN) else "OLD")
    counts[tag] = counts.get(tag, 0) + 1
    fixers = f.get("appliedFixers") or []
    if not fixers:
        norules.append(n)
    print(f"{tag:<10} {n}")
    if raw != n:
        print(f"           (raw: {raw})")
    print(f"           -> {','.join(fixers) if fixers else '<нет данных>'}")
    if f.get("diff"):
        diffs.append(f"=== {n} ===\n{f['diff']}")

if diffs:
    with open("_dev/cs_diffs.txt", "w", encoding="utf-8", newline="") as fh:
        fh.write("\n".join(diffs))
    print(f"\nDIFFS: _dev/cs_diffs.txt ({len(diffs)} файлов)")

print("\nTOTAL:", len(files), counts)
if norules:
    print(f"WARN: без имён правил {len(norules)} файлов — фикс вслепую не применяем")
if counts.get("FORBIDDEN"):
    print("STOP: запрещённые файлы — эскалация владельца (§6/§9)")
