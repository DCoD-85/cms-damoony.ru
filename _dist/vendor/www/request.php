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
    <title>Damoony Engine — <?= $isUpdate ? 'Обновление' : 'Установка' ?></title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f4f4f4; padding: 30px 20px; margin: 0; }
        .container { max-width: 720px; margin: 0 auto; }
        .box { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 20px; }
        h1 { margin-top: 0; }
        h2 { margin-top: 0; font-size: 1.3em; color: #333; }
        input { width: 100%; padding: 10px; margin-bottom: 10px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; }
        button { background: #007bff; color: #fff; border: 0; padding: 12px 20px; cursor: pointer; width: 100%; border-radius: 4px; font-size: 15px; }
        button:hover { background: #0056b3; }
        code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; font-size: 13px; color: #c0392b; }
        pre { background: #2d2d2d; color: #f0f0f0; padding: 12px; border-radius: 4px; overflow-x: auto; font-size: 13px; }
        pre code { background: none; color: inherit; padding: 0; }
        ol { padding-left: 20px; line-height: 1.7; }
        .info { background: #e7f3ff; border-left: 4px solid #007bff; padding: 12px 15px; border-radius: 4px; font-size: 14px; }
        .warn { background: #fff4e5; border-left: 4px solid #e67e22; padding: 12px 15px; border-radius: 4px; font-size: 14px; }
        .muted { color: #888; font-size: 0.9em; }
        .update-link { display: block; text-align: center; margin-top: 15px; color: #007bff; text-decoration: none; }
        .update-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="container">

    <!-- Форма -->
    <div class="box">
        <h1><?= $isUpdate ? 'Обновить Damoony Engine' : 'Получить Damoony Engine' ?></h1>
        
        <?php if ($isUpdate): ?>
            <p class="warn">Режим обновления: ядро и <code>app/</code> будут перегенерированы из свежих шаблонов. <b>База данных не затрагивается.</b></p>
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

        <?php if (!$isUpdate): ?>
            <a href="?mode=update" class="update-link">Уже установлен Damoony? Скачать скрипт обновления (update.sh) →</a>
        <?php else: ?>
            <a href="request.php" class="update-link">← Вернуться к установке</a>
        <?php endif; ?>
    </div>

    <!-- Информация по установке -->
    <?php if (!$isUpdate): ?>
    <div class="box">
        <h2>📦 Установка с нуля</h2>
        <p class="muted">Скрипт автоматически скачает движок, создаст БД, настроит конфиги и соберёт сайт.</p>

        <p><b>Шаг 1.</b> Заполни форму выше и скачай <code>install-вашдомен.sh</code>.</p>
        <p><b>Шаг 2.</b> Загрузи файл на сервер в папку сайта:</p>
        <pre><code>scp install-вашдомен.sh root@ваш-сервер:/var/www/ваш-домен/</code></pre>

        <p><b>Шаг 3.</b> Запусти установку:</p>
        <pre><code>cd /var/www/ваш-домен/
bash install-вашдомен.sh</code></pre>

        <p><b>Шаг 4.</b> Введи данные MySQL root (единственное, что нужно вводить вручную).</p>
        <p><b>Шаг 5.</b> После завершения открой <code>https://ваш-домен/ru/</code>.</p>

        <div class="info">
            <b>Данные администратора</b> будут сохранены в файле <code>CREDENTIALS.txt</code> в корне проекта. 
            Пароль генерируется автоматически (12 символов).
        </div>

        <p class="muted" style="margin-top: 15px;"><b>Требования:</b> PHP 8.5+, MySQL/MariaDB, curl, unzip. Скрипт проверяет их автоматически.</p>
    </div>
    <?php endif; ?>

    <!-- Информация по обновлению -->
    <?php if ($isUpdate): ?>
    <div class="box">
        <h2>🔄 Обновление движка</h2>
        <p class="muted">Обновляет ядро, шаблоны и пересобирает <code>app/</code>. Не трогает <code>config/</code> и базу данных.</p>

        <p><b>Шаг 1.</b> Заполни форму выше и скачай <code>update-вашдомен.sh</code>.</p>
        <p><b>Шаг 2.</b> Загрузи файл на сервер в папку сайта:</p>
        <pre><code>scp update-вашдомен.sh root@ваш-сервер:/var/www/ваш-домен/</code></pre>

        <p><b>Шаг 3.</b> Запусти обновление:</p>
        <pre><code>cd /var/www/ваш-домен/
bash update-вашдомен.sh</code></pre>

        <div class="warn">
            <b>Что произойдёт:</b>
            <ol style="margin: 8px 0 0 0;">
                <li>Скачается свежий <code>core-v4.zip</code></li>
                <li>Обновятся <code>src/</code>, <code>stubs/</code>, <code>vendor/</code></li>
                <li><code>app/</code> будет перегенерирован из новых шаблонов</li>
                <li>Применятся новые миграции БД (если есть)</li>
                <li>PHP-FPM перезагрузится</li>
            </ol>
        </div>

        <div class="info">
            <b>Сохранение данных:</b> конфиги (<code>config/</code>), база данных, ваш контент в <code>app/</code> (если вы его не генерировали) — <b>не пострадают</b>.
        </div>

        <p class="muted" style="margin-top: 15px;"><b>Откат:</b> если что-то пойдёт не так, всегда можно сделать <code>git checkout</code> или восстановить из <code>.sess_*</code> бэкапа.</p>
    </div>
    <?php endif; ?>

</div>
</body>
</html>