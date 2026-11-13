import shutil
from pathlib import Path

BASE = Path.cwd()
TRASH = BASE / "_trash_v3"
TRASH.mkdir(exist_ok=True)

# 1. Старые папки алиасов (v3)
v3_dirs = [".alliasglobal", ".alliasscheme", ".alliaslocal", ".dataprovider"]

for app_dir in (BASE / "app").iterdir():
    if app_dir.is_dir() and not app_dir.name.startswith("."):
        # Ищем как в корне домена, так и в алиасах
        for root, dirs, files in app_dir.walk():
            for d in list(dirs):
                if d in v3_dirs:
                    src = root / d
                    dst = TRASH / f"{app_dir.name}_{root.name}_{d}"
                    if dst.exists(): shutil.rmtree(dst)
                    shutil.move(str(src), str(dst))
                    print(f"Moved to trash: {src}")

            # Ищем старые *.data.php
            for f in files:
                if f.endswith(".data.php"):
                    src = root / f
                    dst = TRASH / f"{app_dir.name}_{f}"
                    shutil.move(str(src), str(dst))
                    print(f"Moved to trash: {src}")

# 2. Удаляем старую админку (src/Admin), если она осталась
old_admin = BASE / "src" / "Admin"
if old_admin.exists():
    dst = TRASH / "src_Admin"
    if dst.exists(): shutil.rmtree(dst)
    shutil.move(str(old_admin), str(dst))
    print(f"Moved to trash: {old_admin}")

print("Cleanup complete. Files are in _trash_v3/")