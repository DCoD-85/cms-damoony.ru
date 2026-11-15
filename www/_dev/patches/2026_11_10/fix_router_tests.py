path = 'src/Core/Routing/FileRouter.php'
with open(path, 'r', encoding='utf-8', newline='') as f:
    content = f.read().replace('\r\n', '\n')

# 1. Пустой путь → синтетический [''] для матчинга '' в available
old_init = """        $classChain = 'algoritm';
        $pathRel = '';
        $args = [];
        $authRequired = false;

        foreach ($segments as $i => $segment) {"""

new_init = """        $classChain = 'algoritm';
        $pathRel = '';
        $args = [];
        $authRequired = false;

        // Пустой путь (/) → синтетический сегмент '', чтобы сработал '' в available
        $segments = $segments === [] ? [''] : $segments;

        foreach ($segments as $i => $segment) {"""
assert old_init in content, "1. init not found"
content = content.replace(old_init, new_init, 1)

# 2. Удаляем dead code (after-loop $matched === null — PHPStan прав)
old_dead = """        // Fallback для админки: промах по available → отдаём текущий узел
        if ($matched === null) {
            if ($adminRole !== null) {
                return RouteMatch::found($classChain, $pathRel, $args, $authRequired);
            }
            return RouteMatch::notFound();
        }

        $leafRulesFile"""
new_dead = """        $leafRulesFile"""
assert old_dead in content, "2. dead code not found"
content = content.replace(old_dead, new_dead, 1)

with open(path, 'w', encoding='utf-8', newline='\n') as f:
    f.write(content)
print("✓ FileRouter fixed: root matches via '', dead code removed")
