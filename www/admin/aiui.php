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
$header->append(Builder::elA('span', ['id' => 'address'])->content("http://www.{$domain}/"));
$header->append(Builder::elA('button', ['id' => 'btn-apply'])->content('Применить'));
$header->append(Builder::elA('span', [
    'id' => 'tab-badge',
    'style' => 'margin-left: auto; font-size: 11px; color: #aaa;',
])->content('tab: ...'));
$preview->append($header);
$preview->append(Builder::elA('iframe', ['id' => 'site-frame', 'src' => "http://www.{$domain}/"]));

$chat = Builder::div('chat-area');

$messages = Builder::elA('div', ['class' => 'messages', 'id' => 'messages']);
$messages->append(Builder::div('msg ai')->content('Привет! Я AIUI. Что изменить на странице?'));
$chat->append($messages);

$inputArea = Builder::div('input-area');

$toolbar = Builder::div('toolbar');
$select = Builder::elA('select', ['id' => 'target-block']);
$select->append(Builder::elA('option', ['value' => 'all'])->content('Вся страница'));
$select->append(Builder::elA('option', ['value' => 'center'])->content('Только CENTER (Контент)'));
$select->append(Builder::elA('option', ['value' => 'top'])->content('Только TOP (Шапка)'));
$select->append(Builder::elA('option', ['value' => 'bottom'])->content('Только BOTTOM (Футер)'));
$toolbar->append($select);

$label = Builder::elA('label');
$label->append(Builder::elA('input', ['type' => 'checkbox', 'id' => 'wireframe-toggle'], true));
$label->append(' Структура');
$toolbar->append($label);

$inputArea->append($toolbar);

$inputRow = Builder::div('input-row');
$inputRow->append(Builder::elA('textarea', ['id' => 'prompt', 'rows' => '2', 'placeholder' => 'Напишите команду...']));
$inputRow->append(Builder::elA('button', ['id' => 'send'])->content('→'));
$inputArea->append($inputRow);

$chat->append($inputArea);
$layout->append($preview);
$layout->append($chat);

echo "<!DOCTYPE html>\n<html lang=\"ru\">\n<head>\n<meta charset=\"UTF-8\">\n<title>AIUI Editor</title>\n";
echo Builder::elA('link', ['rel' => 'stylesheet', 'href' => "/media/{$realDomain}/agent/skin/default/css/aiui.css"], true)->release();
echo "\n</head>\n<body>\n";
echo $layout->release();
echo "\n";
echo Builder::elA('script', ['src' => "/media/{$realDomain}/agent/skin/default/js/aiui.js"])->release();
echo "\n</body>\n</html>";
