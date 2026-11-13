<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$step = $_GET['step'] ?? 'key';
$message = '';

if ($step === 'process' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $key = $_POST['license_key'] ?? '';
    $domain = $_SERVER['HTTP_HOST'];
    $ch = curl_init('https://damoony.ru/api/activate.php');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['key' => $key, 'domain' => $domain]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $data = json_decode(curl_exec($ch), true);
    
    if ($data && $data['ok']) {
        $zipUrl = $data['download_url'];
        $zipFile = __DIR__ . '/core.zip';
        $ch = curl_init($zipUrl);
        $fp = fopen($zipFile, 'wb');
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        fclose($fp);
        
        $zip = new ZipArchive;
        if ($zip->open($zipFile) === TRUE) {
            $zip->extractTo(dirname(__DIR__));
            $zip->close();
            unlink($zipFile);
            $step = 'done';
        } else { $message = "Ошибка распаковки."; }
    } else { $message = "Ошибка активации."; }
}
?>
<!DOCTYPE html>
<html><head><title>Установка Damoony</title></head><body>
    <h2>Установка Damoony</h2>
    <?php if ($message) echo "<p style='color:red'>$message</p>"; ?>
    <?php if ($step === 'key'): ?>
        <form method="POST" action="?step=process">
            <input type="text" name="license_key" placeholder="Лицензионный ключ" required>
            <button type="submit">Шаг 1: Скачать ядро</button>
        </form>
    <?php elseif ($step === 'done'): ?>
        <p style="color:green">Ядро скачано! Удалите install.php и выполните настройку через CLI.</p>
    <?php endif; ?>
</body></html>