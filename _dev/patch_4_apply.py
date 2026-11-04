import pathlib

p = pathlib.Path('src/Cli/Commands/SessionApplyCommand.php')
src = p.read_text(encoding='utf-8')

old = """        if ($adminSess !== '' && (string) ($meta['admin_session_id'] ?? '') !== $adminSess) {
            $r->error('Access denied: admin session mismatch');
            return $r;
        }
"""
new = """        if ($adminSess === '') {
            $r->error('Access denied: not authenticated');
            return $r;
        }
        // Примечание: мы намеренно не сравниваем admin_session_id строго,
        // так как PHP session_id может меняться при релогине/перезагрузке.
        // Безопасность обеспечивается неугадываемым tab_id (64 бита).
"""
assert old in src, "Pattern not found in SessionApplyCommand.php"
assert src.count(old) == 1, "Pattern not unique"
p.write_text(src.replace(old, new, 1), encoding='utf-8')
print("OK: SessionApplyCommand admin_session_id check relaxed.")
