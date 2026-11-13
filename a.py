from pathlib import Path

BASE = Path.cwd()
p = BASE / "_dev/tests/Unit/UI/Algoritm/AdminPageTest.php"

if not p.is_file():
    print("FAIL: file not found")
else:
    src = p.read_text(encoding="utf-8")
    
    OLD = "new DomainContext('test.ru', 'www', 'ru', 'default');"
    NEW = "new DomainContext('test.ru', 'test.ru', 'www', 'default', 'ru', true);"
    
    if NEW in src:
        print("SKIP: already patched")
    elif OLD in src:
        src = src.replace(OLD, NEW, 1)
        p.write_text(src, encoding="utf-8", newline="")
        print("OK: Fixed DomainContext instantiation in AdminPageTest")
    else:
        print("FAIL: marker not found")