path = 'src/Core/App/PageHandler.php'
with open(path, 'r', encoding='utf-8', newline='') as f:
    content = f.read().replace('\r\n', '\n')

old = """        if ($result instanceof Node) {
            $this->applySkin($domain, $context);

            $doc = new Document($context->meta);"""

new = """        if ($result instanceof Node) {
            // AJAX: отдаём блоки как JSON (без skin/document)
            if ($context->request->isAjax()) {
                return Response::json([
                    'ok' => true,
                    'blocks' => ['center' => $result->release()],
                    'url' => $context->request->path,
                ]);
            }

            $this->applySkin($domain, $context);

            $doc = new Document($context->meta);"""

assert old in content, "Marker not found"
content = content.replace(old, new, 1)

with open(path, 'w', encoding='utf-8', newline='\n') as f:
    f.write(content)
print("✓ PageHandler: AJAX branch for Node")
