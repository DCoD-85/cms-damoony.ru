<?php

declare(strict_types=1);

namespace Damoony\Support;

final class Markdown
{
    public static function render(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        $codeBlocks = [];
        $text = preg_replace_callback('/```([a-z]*)\n(.*?)\n```/s', function ($m) use (&$codeBlocks) {
            $placeholder = '___CODE_BLOCK_' . count($codeBlocks) . '___';
            $codeBlocks[$placeholder] = '<pre><code class="language-' . ($m[1] ?: 'text') . '">'
                . htmlspecialchars((string)$m[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</code></pre>';
            return $placeholder;
        }, $text) ?? $text;

        $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $text = preg_replace_callback('/`([^`]+)`/', function ($m) {
            return '<code>' . $m[1] . '</code>';
        }, $text) ?? $text;

        $text = preg_replace('/^###### (.*)$/m', '<h6>$1</h6>', $text) ?? $text;
        $text = preg_replace('/^##### (.*)$/m', '<h5>$1</h5>', $text) ?? $text;
        $text = preg_replace('/^#### (.*)$/m', '<h4>$1</h4>', $text) ?? $text;
        $text = preg_replace('/^### (.*)$/m', '<h3>$1</h3>', $text) ?? $text;
        $text = preg_replace('/^## (.*)$/m', '<h2>$1</h2>', $text) ?? $text;
        $text = preg_replace('/^# (.*)$/m', '<h1>$1</h1>', $text) ?? $text;

        $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text) ?? $text;
        $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text) ?? $text;
        $text = preg_replace('/\[(.*?)\]\((.*?)\)/', '<a href="$2">$1</a>', $text) ?? $text;

        $lines = explode("\n", $text);
        $out = [];
        $inList = false;

        foreach ($lines as $line) {
            if (trim($line) === '') {
                if ($inList) {
                    $out[] = '</ul>';
                    $inList = false;
                }
                continue;
            }

            if (preg_match('/^- (.*)$/', $line, $m)) {
                if (!$inList) {
                    $out[] = '<ul>';
                    $inList = true;
                }
                $out[] = '<li>' . $m[1] . '</li>';
                continue;
            }

            if ($inList) {
                $out[] = '</ul>';
                $inList = false;
            }

            if (str_starts_with($line, '___CODE_BLOCK_') || preg_match('/^<\/?(ul|ol|li|h[1-6]|blockquote|pre)/', $line)) {
                $out[] = $line;
            } else {
                $out[] = '<p>' . $line . '</p>';
            }
        }
        if ($inList) {
            $out[] = '</ul>';
        }
        $text = implode("\n", $out);

        foreach ($codeBlocks as $placeholder => $html) {
            $text = str_replace($placeholder, $html, $text);
        }

        return $text;
    }
}
