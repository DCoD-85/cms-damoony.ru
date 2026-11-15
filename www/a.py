from pathlib import Path

BASE = Path.cwd()

# 1. Патч algoritm_lang_skin.php (assembly + project)
SKIN_FILES = [
    BASE / "stubs/assemblies/developer/app/www/algoritm/lang/skin/algoritm_lang_skin.php",
    BASE / "app/damoony4.ru/www/algoritm/lang/skin/algoritm_lang_skin.php",
]

OLD_AJAX = """        // Если это AJAX (cliajax), просим JS перезагрузить страницу
        if ($this->isAjax()) {
            return Response::json(['ok' => true, 'reload' => true]);
        }"""

NEW_AJAX = """        // AJAX: меняем скин без перезагрузки страницы
        if ($this->isAjax()) {
            return Response::json([
                'ok' => true,
                'skin_updated' => $skinName,
                'redirect_url' => $path,
                'reload' => true, // fallback для старого JS
            ]);
        }"""

for p in SKIN_FILES:
    if not p.exists(): print(f"SKIP (no file): {p}"); continue
    src = p.read_text(encoding="utf-8")
    if NEW_AJAX in src: print(f"SKIP (already): {p}"); continue
    if OLD_AJAX not in src: print(f"FAIL (marker): {p}"); continue
    p.write_text(src.replace(OLD_AJAX, NEW_AJAX, 1), encoding="utf-8", newline="")
    print(f"OK: {p}")

# 2. Патч cliajax.js (3 копии)
CLIAJAX_FILES = [
    BASE / "stubs/cliajax.js",
    BASE / "stubs/assemblies/developer/shared/scripts/cliajax.js",
    BASE / "www/media/shared/scripts/cliajax.js",
]

OLD_MSG = """        if (data.messages && data.messages.length > 0) {"""

NEW_MSG = """        // Смена скина без перезагрузки
        if (data.skin_updated) {
            document.querySelectorAll('link[rel="stylesheet"]').forEach(function(link) {
                var href = link.getAttribute('href');
                if (href && /\\/skin\\/[a-z0-9_]+\\//.test(href)) {
                    link.setAttribute('href', href.replace(/\\/skin\\/[a-z0-9_]+\\//, '/skin/' + data.skin_updated + '/'));
                }
            });
            if (data.redirect_url) {
                navigateTo(window.location.origin + data.redirect_url, false);
            }
            return true;
        }

        if (data.messages && data.messages.length > 0) {"""

for p in CLIAJAX_FILES:
    if not p.exists(): print(f"SKIP (no file): {p}"); continue
    src = p.read_text(encoding="utf-8")
    if "skin_updated" in src: print(f"SKIP (already): {p}"); continue
    if OLD_MSG not in src: print(f"FAIL (marker): {p}"); continue
    p.write_text(src.replace(OLD_MSG, NEW_MSG, 1), encoding="utf-8", newline="")
    print(f"OK: {p}")