from pathlib import Path
import json

BASE = Path.cwd()

paths = {
    "asm": {
        "lang": BASE / "stubs/assemblies/developer/app/www/algoritm/lang",
        "top": BASE / "stubs/assemblies/developer/app/www/.blockslocal/top/top.php",
    },
    "proj": {
        "lang": BASE / "app/damoony4.ru/www/algoritm/lang",
        "top": BASE / "app/damoony4.ru/www/.blockslocal/top/top.php",
    }
}

# ============================================================
# 1. Правим правила роутинга (parent rules)
# ============================================================
for key in ['asm', 'proj']:
    lang_dir = paths[key]["lang"]
    
    # 1.1. algoritm_lang.rules.json -> добавляем папку skin
    rules_file = lang_dir / "algoritm_lang.rules.json"
    if rules_file.exists():
        rules = json.loads(rules_file.read_text(encoding="utf-8"))
        rules['available']['skin'] = '^skin$'
        rules_file.write_text(json.dumps(rules, indent=4, ensure_ascii=False), encoding="utf-8")
        print(f"OK: {key} parent rules updated")

    # 1.2. algoritm_lang_skin.rules.json -> внутри папки skin
    skin_dir = lang_dir / "skin"
    skin_dir.mkdir(parents=True, exist_ok=True)
    
    skin_rules = {
        "available": {
            "skin": "^([a-z0-9_]+)$"
        },
        "auth": False
    }
    (skin_dir / "algoritm_lang_skin.rules.json").write_text(json.dumps(skin_rules, indent=4), encoding="utf-8")
    print(f"OK: {key} child rules updated")

# ============================================================
# 2. Возвращаем правильные URL в top.php
# ============================================================
for key in ['asm', 'proj']:
    top_file = paths[key]["top"]
    if not top_file.exists():
        print(f"FAIL: {top_file} not found")
        continue
        
    src = top_file.read_text(encoding="utf-8")
    
    # Ищем строку с генерацией URL скина и меняем на /skin/name/
    # У нас там сейчас может быть $url = '/' . $currentLang . '/' . $s . '/';
    import re
    # Ищем любую строку, где $url = ...
    pattern = re.compile(r"\$url = '/' \. \$currentLang \. '/.*?' \. \$s \. '/';")
    
    NEW_URL = "$url = '/' . $currentLang . '/skin/' . $s . '/';"
    
    if pattern.search(src):
        src = pattern.sub(NEW_URL, src)
        top_file.write_text(src, encoding="utf-8", newline="")
        print(f"OK: {key} top.php URL fixed to /skin/")
    else:
        print(f"FAIL: URL marker not found in {top_file}")