<?php

declare(strict_types=1);

namespace Damoony\Support;

final class Markdown
{
    public static function render(string $text): string
    {
        // 1. Нормализация переносов
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // 2. Извлекаем код-блоки (```)
        $codeBlocks = [];
        $text = preg_replace_callback('/```([a-z]*)\n(.*?)\n```/s', function ($m) use (&$codeBlocks) {
            $placeholder = "___CODE_BLOCK_" . count($codeBlocks) . "___";
            $codeBlocks[$placeholder] = '<pre><code class="language-' . ($m[1] ?: 'text') . '">' 
                . htmlspecialchars($m[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') 
                . '</code></pre>';
            return $placeholder;
        }, $text);

        // 3. Экранируем весь остальной HTML
        $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // 4. Инлайн-код (`)
        $text = preg_replace_callback('/`([^`]+)`/', function ($m) {
            return '<code>' . $m[1] . '</code>'; // $m[1] уже экранирован
        }, $text);

        // 5. Заголовки
        $text = preg_replace('/^###### (.*)$/m', '<h6>$1</h6>', $text);
        $text = preg_replace('/^##### (.*)$/m', '<h5>$1</h5>', $text);
        $text = preg_replace('/^#### (.*)$/m', '<h4>$1</h4>', $text);
        $text = preg_replace('/^### (.*)$/m', '<h3>$1</h3>', $text);
        $text = preg_replace('/^## (.*)$/m', '<h2>$1</h2>', $text);
        $text = preg_replace('/^# (.*)$/m', '<h1>$1</h1>', $text);

        // 6. Жирный и курсив
        $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);

        // 7. Ссылки
        $text = preg_replace('/\[(.*?)\]\((.*?)\)/', '<a href="$2">$1</a>', $text);

        // 8. Списки и параграфы (обрабатываем построчно)
        $lines = explode("\n", $text);
        $out = [];
        $inList = false;

        foreach ($lines as $line) {
            // Пропускаем пустые строки
            if (trim($line) === '') {
                if ($inList) {
                    $out[] = '</ul>';
                    $inList = false;
                }
                continue;
            }

            // Списки
            if (preg_match('/^- (.*)$/', $line, $m)) {
                if (!$inList) {
                    $out[] = '<ul>';
                    $inList = true;
                }
                $out[] = '<li>' . $m[1] . '</li>';
                continue;
            }

            // Закрываем список, если начался обычный текст
            if ($inList) {
                $out[] = '</ul>';
                $inList = false;
            }

            // Плейсхолдеры код-блоков не оборачиваем в <p>
            if (str_starts_with($line, '___CODE_BLOCK_')) {
                $out[] = $line;
            } else {
                $out[] = '<p>' . $line . '</p>';
            }
        }
        if ($inList) {
            $out[] = '</ul>';
        }
        $text = implode("\n", $out);

        // 9. Возвращаем код-блоки на место
        foreach ($codeBlocks as $placeholder => $html) {
            $text = str_replace($placeholder, $html, $text);
        }

        return $text;
    }
}