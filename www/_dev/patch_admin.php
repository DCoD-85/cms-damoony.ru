from pathlib import Path
import subprocess

p = Path("www/admin/index.php")
text = p.read_text(encoding="utf-8")

# 1. Внедряем AppFactory для доступа к контейнеру (нужен для FrontController)
find_init = "$paths = new Paths($root);"
replace_init = """
    $app = \\Damoony\\App\\AppFactory::create($root);
    $container = $app->container();
    $pages = $container->get(\\Damoony\\App\\PageHandlerInterface::class);
    assert($pages instanceof \\Damoony\\App\\PageHandlerInterface);
    $paths = new Paths($root);
"""
assert find_init in text, "Init pattern not found"
text = text.replace(find_init, replace_init, 1)

# 2. Меняем $handler (Strangler Fig)
find_handler = """    $handler = static fn (Request $r) => $stack->dispatch(
        $r,
        static fn (Request $r2) => $router->dispatch($r2),
    );"""
replace_handler = """    $handler = static function (Request $r) use ($stack, $router, $auth, $container, $pages) {
        return $stack->dispatch($r, static function (Request $r2) use ($router, $auth, $container, $pages) {
            // Если не авторизован — отдаем старому роутеру (он покажет логин)
            if (!$auth->isLoggedIn()) {
                return $router->dispatch($r2);
            }

            // Получаем контекст из сессии или ставим дефолт (superadmin)
            $ctxDomain = $_SESSION['admin_context']['domain'] ?? 'damoony.ru';
            $ctxAlias  = $_SESSION['admin_context']['alias'] ?? 'www';
            $ctxRole   = $_SESSION['admin_context']['role'] ?? 'superadmin';

            // Создаем DomainContext через резолвер
            $resolver = $container->get(\\Damoony\\Domain\\DomainResolverInterface::class);
            $domainContext = $resolver->resolve($ctxDomain);
            if ($ctxAlias !== $domainContext->allias) {
                $domainContext = $domainContext->withAllias($ctxAlias);
            }

            // Пытаемся найти новый файл в app/.admin/
            try {
                $path = $r2->path;
                // Если зашли просто на /admin/ — считаем это дашбордом
                if ($path === '/admin' || $path === '/admin/') {
                    $path = '/admin/dashboard';
                }
                
                // Отрезаем /admin от пути
                if (str_starts_with($path, '/admin')) {
                    $path = substr($path, 6);
                }
                if ($path === '') {
                    $path = '/';
                }

                $adminRequest = $r2->withPath($path);
                $fc = new \\Damoony\\App\\FrontController($container, $pages);
                
                $response = $fc->handle($adminRequest, null, null, $ctxRole, $domainContext);
                
                // Если новый роутер нашел файл, отдаем его
                if ($response->status !== 404) {
                    return $response;
                }
            } catch (\\Throwable $e) {
                // В случае ошибки нового роутера падаем в старый
            }

            // Если файла нет — отдаем старому роутеру
            return $router->dispatch($r2);
        });
    };"""
assert find_handler in text, "Handler pattern not found"
text = text.replace(find_handler, replace_handler, 1)

p.write_text(text, encoding="utf-8")
print("Patched: www/admin/index.php")