<?php
$isUpdate = ($_GET['mode'] ?? '') === 'update' || ($_POST['mode'] ?? '') === 'update';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $domain = trim($_POST['domain'] ?? '');
    $name   = trim($_POST['name'] ?? '');
    $email  = trim($_POST['email'] ?? '');

    if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain)) {
        die('Некорректный домен');
    }

    $key = 'KEY-' . strtoupper(bin2hex(random_bytes(8)));

    $dataDir = __DIR__ . '/../data';
    @mkdir($dataDir, 0755, true);
    $licFile = $dataDir . '/licenses.json';
    $licenses = is_file($licFile) ? json_decode(file_get_contents($licFile), true) : [];
    $licenses[] = [
        'key' => $key,
        'domain' => $domain,
        'name' => $name,
        'email' => $email,
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s'),
    ];
    file_put_contents($licFile, json_encode($licenses, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $tplFile = __DIR__ . '/stubs/install.sh.tpl';
    if (!is_file($tplFile)) {
        die('Шаблон install.sh.tpl не найден');
    }
    $tpl = file_get_contents($tplFile);
    $install = str_replace(['{{DOMAIN}}', '{{KEY}}'], [$domain, $key], $tpl);

    $filename = ($isUpdate ? 'update-' : 'install-') . $domain . '.sh';

    header('Content-Type: application/x-sh');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $install;
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Damoony — <?= $isUpdate ? 'Обновление' : 'Запрос установки' ?></title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f4f4f4; padding: 50px; }
        .box { background: #fff; padding: 30px; border-radius: 8px; max-width: 500px; margin: 0 auto; }
        input { width: 100%; padding: 10px; margin-bottom: 10px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        button { background: #007bff; color: #fff; border: 0; padding: 12px 20px; cursor: pointer; width: 100%; border-radius: 4px; }
        .hint { color: #888; font-size: 0.9em; margin-top: 15px; }
        .hint a { color: #007bff; text-decoration: none; }
        .hint a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="box">
    <h1><?= $isUpdate ? 'Обновить Damoony Engine' : 'Получить Damoony Engine' ?></h1>
    
    <?php if ($isUpdate): ?>
        <p style="color: #e67e22; margin-bottom: 15px;">Режим обновления: скрипт пересоберет <code>app/</code> и обновит ядро, не трогая БД.</p>
    <?php endif; ?>

    <form method="POST">
        <?php if ($isUpdate): ?>
            <input type="hidden" name="mode" value="update">
        <?php endif; ?>
        <input name="domain" placeholder="mydomain.ru" required pattern="[a-z0-9][a-z0-9.-]*\.[a-z]{2,}">
        <input name="name" placeholder="Ваше имя">
        <input name="email" type="email" placeholder="email@example.com">
        <button type="submit"><?= $isUpdate ? 'Скачать update.sh' : 'Скачать install.sh' ?></button>
    </form>
    
    <p class="hint">
        <?php if (!$isUpdate): ?>
            Уже установлен Damoony? <a href="?mode=update">Скачать скрипт обновления (update.sh)</a>
        <?php else: ?>
            <a href="request.php">← Вернуться к установке</a>
        <?php endif; ?>
    </p>
</div>
</body>
</html>