<?php

declare(strict_types=1);

use Damoony\Session\NativeSession;
use Damoony\Ui\Builder;

require_once __DIR__ . '/../../vendor/autoload.php';

$session = new NativeSession('ADMINSESSID', ($_SERVER['HTTPS'] ?? '') === 'on');
$session->start();
$adminLogin = $session->get('admin_login');
if (!is_string($adminLogin) || $adminLogin === '') {
    http_response_code(403);
    exit('Forbidden: Admin authentication required.');
}

$domain = 'damoony3.ru';
$alias = 'www';
$realDomain = 'damoony.ru';

$layout = Builder::div('aiui-layout');

$preview = Builder::div('preview-area');
$header = Builder::div('preview-header');
$header->append(Builder::span()->content('🔴 🟡 🟢'));
$header->append(Builder::elA('span', ['id' => 'address'])->content("//www.{$domain}/"));
$header->append(Builder::elA('button', ['id' => 'btn-apply'])->content('Применить'));
$header->append(Builder::elA('span', [
    'id' => 'tab-badge',
    'style' => 'margin-left: auto; font-size: 11px; color: #aaa;',
])->content('tab: ...'));
$preview->append($header);
$preview->append(Builder::elA('iframe', ['id' => 'site-frame', 'src' => "//www.{$domain}/"]));

$chat = Builder::div('chat-area');

$messages = Builder::elA('div', ['class' => 'messages', 'id' => 'messages']);
$messages->append(Builder::div('msg ai')->content('Привет! Я AIUI. Что изменить на странице?'));
$chat->append($messages);

$inputArea = Builder::div('input-area');

// Панель брифа (скрыта по умолчанию)
$briefPanel = Builder::elA('div', ['id' => 'brief-panel', 'style' => 'display:none;']);
$briefPanel->append(Builder::span()->content('📝 Бриф: '));
$briefPanel->append(Builder::elA('span', ['id' => 'brief-text'])->content(''));
$inputArea->append($briefPanel);

$inputRow = Builder::div('input-row');
$inputRow->append(Builder::elA('textarea', ['id' => 'prompt', 'rows' => '2', 'placeholder' => 'Опишите, что нужно сделать...']));
$inputRow->append(Builder::elA('button', ['id' => 'send'])->content('→'));

// Кнопка явной генерации сайта
$createRow = Builder::div('create-row');
$createRow->append(
    Builder::elA('button', ['id' => 'btn-create', 'class' => 'btn-create'])
        ->content('🚀 Создать сайт по брифу'),
);
$inputArea->append($createRow);
$inputArea->append($inputRow);

$chat->append($inputArea);
$layout->append($preview);
$layout->append($chat);

echo "<!DOCTYPE html>\n<html lang=\"ru\">\n<head>\n<meta charset=\"UTF-8\">\n<title>AIUI Editor</title>\n";
echo Builder::elA('link', ['rel' => 'stylesheet', 'href' => "/media/{$realDomain}/agent/skin/default/css/aiui.css?v=5"], true)->release();
echo "\n</head>\n<body>\n";
echo $layout->release();
echo "\n";
echo Builder::elA('script', ['src' => "/media/{$realDomain}/agent/skin/default/js/aiui.js?v=5"])->release();
echo "\n</body>\n</html>";
