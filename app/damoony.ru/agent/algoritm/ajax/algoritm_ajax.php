<?php

declare(strict_types=1);

namespace damoony_ru\agent;

use Damoony\Algoritm\Page;
use Damoony\Ui\Node;
use Damoony\Http\Response;

final class algoritm_ajax extends Page
{
    public function default(): Node|Response|null
    {
        $action = $_POST['action'] ?? '';

        if ($action === 'chat') {
            return $this->handleChat();
        } elseif ($action === 'generate') {
            return $this->handleGenerate();
        } elseif ($action === 'status') {
            return $this->handleStatus();
        } elseif ($action === 'cancel') {
            return $this->handleCancel();
        }

        return Response::json(['ok' => false, 'error' => 'Invalid action'], 400);
    }

    private function handleChat(): Response
    {
        $historyJson = (string)($_POST['history'] ?? '[]');
        $history = json_decode($historyJson, true) ?: [];
        
        if (empty($history)) {
            return Response::json(['ok' => false, 'error' => 'History is empty'], 400);
        }

        $payload = json_encode([
            'command' => 'agent:chat',
            'role' => '*',
            'args' => ['history' => $history]
        ]);

        $output = $this->runCli($payload);
        $result = json_decode($output, true);
        
        if (!is_array($result)) {
            return Response::json(['ok' => false, 'error' => 'CLI Output: ' . strip_tags($output)], 500);
        }

        return Response::json($result);
    }

    private function handleGenerate(): Response
    {
        $historyJson = (string)($_POST['history'] ?? '[]');
        $history = json_decode($historyJson, true) ?: [];
        
        $brief = "";
        foreach ($history as $msg) {
            $role = $msg['role'] === 'user' ? 'Клиент' : 'Дизайнер';
            $brief .= "{$role}: {$msg['content']}\n";
        }

        // Читаем конфиг, чтобы узнать домен фабрики
        $agentConfigFile = dirname(__DIR__, 5) . '/config/agent.config';
        $cfg = is_file($agentConfigFile) ? json_decode(file_get_contents($agentConfigFile), true) : [];
        
        // Домен берем из конфига. Если там пусто — вытаскиваем из текущего хоста (убирая "agent.")
        $domain = $cfg['target_domain'] ?? '';
        if ($domain === '') {
            $host = $_SERVER['HTTP_HOST'] ?? 'damoony3test.ru';
            $domain = str_replace('agent.', '', $host);
        }

        $allias = 'auto'; // Python сам придумает алиас на основе брифа
        $sessionId = 'gen_' . rand(1000, 9999);

        $varDir = dirname(__DIR__, 5) . '/var';
        $briefFile = $varDir . '/' . $sessionId . '_brief.txt';
        $logFile = $varDir . '/' . $sessionId . '_brain.log';
        
        file_put_contents($briefFile, $brief);
        file_put_contents($logFile, ''); // Очищаем лог

        // Добавляем домен в agent.config
        $agentConfigFile = dirname(__DIR__, 5) . '/config/agent.config';
        if (file_exists($agentConfigFile)) {
            $cfg = json_decode(file_get_contents($agentConfigFile), true);
            if (!in_array($domain, $cfg['allowed_domains'])) {
                $cfg['allowed_domains'][] = $domain;
                file_put_contents($agentConfigFile, json_encode($cfg, JSON_PRETTY_PRINT));
            }
        }

        // КРОСС-ПЛАТФОРМЕННЫЙ ЗАПУСК PYTHON
        $python = $cfg['python_path'] ?? 'python';
        $rootDir = dirname(__DIR__, 5);
        
        $brainCmd = escapeshellarg($python) . " -u -m ai_agent.brain {$domain} {$allias} @" . escapeshellarg($briefFile) . " --session={$sessionId} > " . escapeshellarg($logFile) . " 2>&1";

        if (PHP_OS_FAMILY === 'Windows') {
            // Для Windows используем bat-файл
            $batFile = $varDir . '/' . $sessionId . '_run.bat';
            $batContent = "@echo off\r\n";
            $batContent .= "cd /d \"{$rootDir}\"\r\n";
            $batContent .= "set PYTHONPATH={$rootDir}\r\n";
            $batContent .= "{$brainCmd}\r\n";
            file_put_contents($batFile, $batContent);
            
            $cmd = "cmd /c start /B \"\" \"" . $batFile . "\"";
            pclose(popen($cmd, 'r'));
        } else {
            // Для Linux/Mac используем nohup
            $cmd = "cd " . escapeshellarg($rootDir) . " && nohup {$brainCmd} &";
            exec($cmd);
        }

        return Response::json(['ok' => true, 'session_id' => $sessionId]);
    }

    private function handleStatus(): Response
    {
        $sessionId = $_POST['session_id'] ?? '';
        if ($sessionId === '') {
            return Response::json(['ok' => false, 'error' => 'No session ID']);
        }

        $varDir = dirname(__DIR__, 5) . '/var';
        $logFile = $varDir . '/' . $sessionId . '_brain.log';

        if (!file_exists($logFile)) {
            return Response::json(['ok' => true, 'done' => false, 'status_text' => 'Запуск агента...']);
        }

        if (filesize($logFile) === 0 && (time() - filemtime($logFile) > 180)) {
            return Response::json(['ok' => false, 'error' => 'Модель не отвечает (возможно, не хватило RAM). Попробуйте qwen2.5-coder:7b']);
        }

        $log = (string)file_get_contents($logFile);

        if (str_contains($log, 'Fatal error') || str_contains($log, 'Aborting session')) {
            if (str_contains($log, 'Отменено пользователем')) {
                return Response::json(['ok' => false, 'error' => 'Генерация отменена пользователем']);
            }
            
            // Ищем конкретную ошибку от Wizard
            if (str_contains($log, 'уже занят')) {
                return Response::json(['ok' => false, 'error' => 'Этот алиас уже занят. Попробуйте выбрать другое имя для сайта.']);
            }
            
            return Response::json(['ok' => false, 'error' => 'Ошибка при генерации (см. лог)']);
        }

        if (str_contains($log, 'Done! Total time:')) {
            $link = '';
            if (preg_match('/SITE_URL:\s*(http[^\s]+)/', $log, $matches)) {
                $link = $matches[1];
            } else {
                $link = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/';
            }
            return Response::json([
                'ok' => true, 
                'done' => true, 
                'link' => $link
            ]);
        }

        $lines = explode("\n", trim($log));
        $lastStatus = 'Инициализация...';
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^\[\d\/5\]/', $line) || str_starts_with($line, '-> Writing:') || str_starts_with($line, '[=>]') || str_starts_with($line, '[*]')) {
                $lastStatus = preg_replace('/\s*\[Time\].*/i', '', $line);
                $lastStatus = str_replace([
                    '[1/5] Wizard started', 
                    '[2/5] Architect is planning the structure...',
                    '[*] Generating global widgets...',
                    '[3/5] Generating content (Markdown mode)...',
                    '[4/5] Designer is creating main.css...',
                    '[5/5] Designer is styling top.css...',
                    '[=>] All drafts collected. Committing to filesystem...',
                    '-> Writing:'
                ], [
                    'Инициализация...',
                    'Проектирование структуры...',
                    'Генерация виджетов...',
                    'Генерация контента...',
                    'Создание стилей (main.css)...',
                    'Настройка навигации (top.css)...',
                    'Сборка файлов на диске...',
                    'Написание текста для:'
                ], $lastStatus);
            }
        }

        return Response::json(['ok' => true, 'done' => false, 'status_text' => $lastStatus]);
    }

    private function handleCancel(): Response
    {
        $sessionId = $_POST['session_id'] ?? '';
        if ($sessionId === '') {
            return Response::json(['ok' => false, 'error' => 'No session ID']);
        }

        $varDir = dirname(__DIR__, 5) . '/var';
        $cancelFile = $varDir . '/' . $sessionId . '.cancel';
        
        file_put_contents($cancelFile, '1');
        
        return Response::json(['ok' => true, 'message' => 'Отмена запрошена']);
    }

    private function runCli(string $payload): string
    {
        // 1. Пытаемся взять путь из конфига
        $agentConfigFile = dirname(__DIR__, 5) . '/config/agent.config';
        $cfg = is_file($agentConfigFile) ? json_decode(file_get_contents($agentConfigFile), true) : [];
        $phpBin = trim($cfg['php_path'] ?? '');

        // 2. Если в конфиге пусто — определяем автоматически
        if ($phpBin === '') {
            $phpBin = PHP_BINARY;
            // Если это веб-сервер (Apache/FPM), берем просто системный 'php'
            if (str_contains(strtolower($phpBin), 'httpd.exe') || 
                str_contains(strtolower($phpBin), 'apache') || 
                str_contains(strtolower($phpBin), 'fpm') || 
                empty($phpBin)) {
                $phpBin = 'php';
            }
        }

        $cliPath = dirname(__DIR__, 5) . '/bin/aicli.php';
        $cmd = escapeshellarg($phpBin) . ' ' . escapeshellarg($cliPath) . ' 2>&1';
        
        $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) {
            return '{"ok":false,"error":"Failed to start CLI"}';
        }

        fwrite($pipes[0], $payload);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);

        return $output;
    }
}