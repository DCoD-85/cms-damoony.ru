import io

with io.open('_doc/dev.md', 'r', encoding='utf-8') as f:
    dev = f.read()

with io.open('_dev/dev_addendum.md', 'r', encoding='utf-8') as f:
    addendum = f.read()

marker = '\n## См. также'
assert marker in dev, 'dev.md: marker not found'
dev = dev.replace(marker, addendum + marker, 1)

with io.open('_doc/dev.md', 'w', encoding='utf-8', newline='\n') as f:
    f.write(dev)
print('OK dev.md §12 added')

with io.open('_doc/arch.md', 'r', encoding='utf-8') as f:
    arch = f.read()

pairs = [
    (
        '6. **PageHandler::applySkin:** читает `$_COOKIE` напрямую, не через сервис.',
        '6. **PageHandler::applySkin:** читает `$_COOKIE` напрямую. План: удалить, SkinCookieMiddleware — единственный источник. См. dev.md §12 (шаг D).'
    ),
    (
        '15. **Скрытые дефолты:** `Slot::release()` возвращает пустую строку (слот без региона молча исчезает).',
        '15. **Скрытые дефолты:** `Slot::release()` возвращает пустую строку. План: маркер в dev, пусто в prod. См. dev.md §12 (шаг E).'
    ),
]

for old, new in pairs:
    assert old in arch, 'arch.md: marker not found: ' + old[:40]
    arch = arch.replace(old, new, 1)

with io.open('_doc/arch.md', 'w', encoding='utf-8', newline='\n') as f:
    f.write(arch)
print('OK arch.md P2.6, P2.15 updated')
