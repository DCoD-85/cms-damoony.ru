import re, pathlib, sys

def patch(path: str, old: str, new: str, label: str) -> None:
    p = pathlib.Path(path)
    src = p.read_text(encoding='utf-8')
    assert old in src, f"[{label}] pattern not found in {path}"
    assert src.count(old) == 1, f"[{label}] pattern not unique in {path}"
    p.write_text(src.replace(old, new, 1), encoding='utf-8')
    print(f"OK: {label}")

# --- Patch 1: hide exception details (P0) ---
patch(
    'www/admin/aipreview.php',
    "    echo 'Not Found: ' . $e->getMessage(); // Скрываем детали",
    "    echo 'Not Found'; // Скрываем детали",
    'aipreview: hide exception details',
)

# --- Patch 2a: FrontController::handle signature ---
patch(
    'src/App/FrontController.php',
    "    public function handle(Request $request, ?string $sessionId = null): Response\n    {\n        $resolver = $this->container->get(DomainResolverInterface::class);\n        assert($resolver instanceof DomainResolverInterface);\n\n        $host = (string) ($request->header('host') ?? $request->server['SERVER_NAME'] ?? '');\n        $domain = $resolver->resolve($host);\n",
    "    public function handle(\n        Request $request,\n        ?string $sessionId = null,\n        ?string $aliasOverride = null,\n    ): Response {\n        $resolver = $this->container->get(DomainResolverInterface::class);\n        assert($resolver instanceof DomainResolverInterface);\n\n        $host = (string) ($request->header('host') ?? $request->server['SERVER_NAME'] ?? '');\n        $domain = $resolver->resolve($host);\n\n        if ($aliasOverride !== null && $aliasOverride !== '' && $aliasOverride !== $domain->allias) {\n            $domain = $domain->withAllias($aliasOverride);\n        }\n",
    'FrontController: accept aliasOverride',
)

# --- Patch 2b: pass session_id into RegionContext services ---
patch(
    'src/App/FrontController.php',
    "        $context = new RegionContext($request, $domain, $meta, [\n            'paths' => $paths,\n            'site' => $site,\n            'auth' => $auth,\n            'csrf' => $csrf,\n            'i18n' => $translator,\n        ]);",
    "        $context = new RegionContext($request, $domain, $meta, [\n            'paths' => $paths,\n            'site' => $site,\n            'auth' => $auth,\n            'csrf' => $csrf,\n            'i18n' => $translator,\n            'session_id' => $sessionId,\n        ]);",
    'FrontController: pass session_id to RegionContext',
)

print("All patches applied.")
