# ========== 1. FileRouter: 4 замены ==========
path = 'src/Core/Routing/FileRouter.php'
with open(path, 'r', encoding='utf-8', newline='') as f:
    content = f.read().replace('\r\n', '\n')

# 1.1 Удаляем спец-проверку пустого segments
old_root = """        if ($segments === []) {
            return RouteMatch::found('algoritm', '', [], $this->matcher->requiresAuth($rootRules));
        }

        $classChain = 'algoritm';
        $pathRel = '';
        $args = [];

        foreach ($segments as $i => $segment) {"""

new_root = """        $classChain = 'algoritm';
        $pathRel = '';
        $args = [];
        $authRequired = false;

        foreach ($segments as $i => $segment) {"""
assert old_root in content, "1.1 root-check not found"
content = content.replace(old_root, new_root, 1)

# 1.2 После загрузки $rules — накапливаем auth
old_rules = """            if ($rules === null) {
                return RouteMatch::notFound();
            }

            $matched = $this->matcher->matchSegment($segment, $rules);"""

new_rules = """            if ($rules === null) {
                return RouteMatch::notFound();
            }

            $authRequired = $authRequired || $this->matcher->requiresAuth($rules);

            $matched = $this->matcher->matchSegment($segment, $rules);"""
assert old_rules in content, "1.2 rules-load not found"
content = content.replace(old_rules, new_rules, 1)

# 1.3 `continue` → `break` для пустого сегмента
old_empty = """            // Если пустая строка (слеш на конце) - просто пропускаем
            if ($target === '') {
                continue;
            }"""

new_empty = """            // Пустой сегмент ('') в available = стоп, вызываем default() текущего узла
            if ($target === '') {
                break;
            }"""
assert old_empty in content, "1.3 empty-segment not found"
content = content.replace(old_empty, new_empty, 1)

# 1.4 Leaf: учитываем auth + добавляем fallback для админки
old_leaf = """            $candidatePhp = $base . '/' . $pathRel . '/' . $classChain . '.php';
            if (!is_file($candidatePhp)) {
                return RouteMatch::notFound();
            }
        }

        $leafRules = $this->loadRules($base . '/' . $pathRel . '/' . $classChain . '.rules.json') ?? [];
        $auth = $this->matcher->requiresAuth($leafRules);

        return RouteMatch::found($classChain, $pathRel, $args, $auth);"""

new_leaf = """            $candidatePhp = $base . '/' . $pathRel . '/' . $classChain . '.php';
            if (!is_file($candidatePhp)) {
                if ($adminRole !== null) {
                    return RouteMatch::found($classChain, $pathRel, $args, $authRequired);
                }
                return RouteMatch::notFound();
            }
        }

        // Fallback для админки: промах по available → отдаём текущий узел
        if ($matched === null) {
            if ($adminRole !== null) {
                return RouteMatch::found($classChain, $pathRel, $args, $authRequired);
            }
            return RouteMatch::notFound();
        }

        $leafRulesFile = $base . ($pathRel === '' ? '' : '/' . $pathRel) . '/' . $classChain . '.rules.json';
        $leafRules = $this->loadRules($leafRulesFile) ?? [];
        $authRequired = $authRequired || $this->matcher->requiresAuth($leafRules);

        return RouteMatch::found($classChain, $pathRel, $args, $authRequired);"""
assert old_leaf in content, "1.4 leaf not found"
content = content.replace(old_leaf, new_leaf, 1)

with open(path, 'w', encoding='utf-8', newline='\n') as f:
    f.write(content)
print("✓ FileRouter: 4 changes applied")

# ========== 2. dev.md: абзац про накопление auth ==========
dev_path = '_doc/dev.md'
with open(dev_path, 'r', encoding='utf-8', newline='') as f:
    dev = f.read().replace('\r\n', '\n')

old_dev = """## 7. Защита страниц (Auth)

Флаг включается через CLI:"""

new_dev = """## 7. Защита страниц (Auth)

⚠️ **`auth` накапливается по цепочке маршрутов.** Если любой предок требует auth, потомок тоже требует — даже при `auth: false` в своём `.rules.json`. Это защита от обхода: нельзя открыть `/admin/ru/login`, если `/admin` помечен как `auth: true`. Явный `auth: false` работает только если **вся** цепочка до него — без auth.

Флаг включается через CLI:"""
assert old_dev in dev, "dev.md §7 not found"
dev = dev.replace(old_dev, new_dev, 1)

with open(dev_path, 'w', encoding='utf-8', newline='\n') as f:
    f.write(dev)
print("✓ dev.md §7 updated")

print("\nГотово. Проверь синтаксис: php -l src/Core/Routing/FileRouter.php")
