# -*- coding: utf-8 -*-
# Read-only аудит состояния CLI-sweep. Файлы не изменяются.
from pathlib import Path
import re

CMD = Path("src/Cli/Commands")
assert CMD.is_dir(), f"нет каталога {CMD}"

MUT = re.compile(
    r"(New|Write|Remove|Edit|Toggle|Save|Apply|Repair|Clean|Clear|Migrate|"
    r"Restore|Bind|Unbind|Add|Sync|Gc|Store|Copy|Passwd)Command\.php$"
)

rows = []
for p in sorted(CMD.glob("*.php")):
    s = p.read_text(encoding="utf-8", errors="replace")
    rows.append({
        "name": p.stem,
        "abs":  "abstract class" in s,
        "R":    "ResolvesRealDomain" in s,
        "H":    "$host" in s,
        "D":    ("->data" in s) or ("data(" in s),
        "Y":    "isDryRun(" in s,
        "M":    bool(MUT.search(p.name)),
    })

def m(v):
    return "+" if v else "."

print(f"{'COMMAND':<34} R H D Y M")
print("-" * 46)
for r in rows:
    print(f"{r['name']:<34} {m(r['R'])} {m(r['H'])} {m(r['D'])} {m(r['Y'])} {m(r['M'])}")

live = [r for r in rows if not r["abs"]]
print("-" * 46)
print(f"files={len(rows)}  abstract={len(rows)-len(live)}  live={len(live)}")
print(f"R=ResolvesRealDomain  H=$host  D=data  Y=isDryRun  M=mutating(by name)")

print("\n=== Y: читают --dry-run ===")
for r in live:
    if r["Y"]:
        print(" ", r["name"])

print("\n=== RISK: мутирующие БЕЗ --dry-run ===")
risk = [r["name"] for r in live if r["M"] and not r["Y"]]
for n in risk:
    print(" ", n)
print(f"  total={len(risk)}")

print("\n=== без сигнала data ===")
nod = [r["name"] for r in live if not r["D"]]
for n in nod:
    print(" ", n)
print(f"  total={len(nod)}")

print("\n=== без ResolvesRealDomain ===")
nor = [r["name"] for r in live if not r["R"]]
for n in nor:
    print(" ", n)
print(f"  total={len(nor)}")
