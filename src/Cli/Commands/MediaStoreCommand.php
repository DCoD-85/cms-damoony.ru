<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Files\Fs;
use Damoony\Files\Paths;

final class MediaStoreCommand implements CommandInterface
{
    private const MIME_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
    ];

    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'media:store';
    }

    public function description(): string
    {
        return 'Записать медиа (base64) в www/media/<domain>/<allias>/<category>/';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = (string) ($args['domain'] ?? '');
        $allias = (string) ($args['allias'] ?? '');
        $category = (string) ($args['category'] ?? 'general');
        $dataB64 = (string) ($args['data_b64'] ?? '');
        $mime = strtolower((string) ($args['mime'] ?? ''));
        $alt = (string) ($args['alt'] ?? '');

        // 1. Валидация
        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || str_contains($domain, '..')) {
            $r->error('Некорректный домен');
            return $r;
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $allias)) {
            $r->error('Некорректный алиас');
            return $r;
        }
        if (!preg_match('/^[a-z0-9_-]{1,32}$/', $category)) {
            $r->error('Некорректная category (a-z0-9_-, 1-32)');
            return $r;
        }
        if ($dataB64 === '') {
            $r->error('Укажите --data_b64 (base64 содержимого)');
            return $r;
        }
        $bytes = base64_decode($dataB64, true);
        if ($bytes === false) {
            $r->error('Некорректный base64');
            return $r;
        }
        if ($bytes === '') {
            $r->error('Пустые байты');
            return $r;
        }

        // 2. MIME whitelist
        if (!isset(self::MIME_EXT[$mime])) {
            $r->error("Недопустимый MIME '{$mime}' (allowed: " . implode(', ', array_keys(self::MIME_EXT)) . ')');
            return $r;
        }

        // 3. Домен в agent.config.allowed_domains
        if (!$this->isAllowedDomain($domain)) {
            $r->error("Домен '{$domain}' не в config/agent.config.allowed_domains");
            return $r;
        }

        // 4. Лимит размера (10 MB по умолчанию)
        $maxSize = 10485760;
        if (strlen($bytes) > $maxSize) {
            $r->error('Файл больше ' . $maxSize . ' байт');
            return $r;
        }

        // 5. Реальные размеры картинки (для не-SVG)
        $width = 0;
        $height = 0;
        if ($mime !== 'image/svg+xml') {
            $info = @getimagesizefromstring($bytes);
            if ($info === false) {
                $r->error('Не удалось прочитать размеры изображения (не картинка или повреждено)');
                return $r;
            }
            $width = (int) $info[0];
            $height = (int) $info[1];
            if ($width < 200 || $height < 200) {
                $r->error("Изображение слишком маленькое ({$width}x{$height}, минимум 200x200)");
                return $r;
            }
        }

        // 6. Хэш и путь
        $hash = substr(hash('sha256', $bytes), 0, 16);
        $ext = self::MIME_EXT[$mime];
        $mediaDir = $this->paths->media($domain, $allias) . '/' . $category;
        $file = $mediaDir . '/' . $hash . '.' . $ext;
        $metaFile = $mediaDir . '/' . $hash . '.meta.json';

        // 7. Дедупликация
        if (is_file($file)) {
            $rel = Fs::rel($file, $this->paths->root());
            $r->file($rel);
            $context->step('Медиа уже существует (дедуплицировано)', true, $hash);
            $r->warn('deduplicated');

            return $r;
        }

        // 8. Запись
        Fs::mkdir($mediaDir);
        Fs::writeAtomic($file, $bytes);
        $r->file(Fs::rel($file, $this->paths->root()));

        $meta = [
            'source_url' => (string) ($args['source_url'] ?? ''),
            'alt' => $alt,
            'size' => strlen($bytes),
            'mime' => $mime,
            'width' => $width,
            'height' => $height,
            'hash' => $hash,
            'downloaded_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ];
        Fs::writeAtomic(
            $metaFile,
            json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        );
        $r->file(Fs::rel($metaFile, $this->paths->root()));

        $context->step('Медиа сохранено', true, $hash);

        return $r;
    }

    private function isAllowedDomain(string $domain): bool
    {
        $file = $this->paths->root() . '/config/agent.config';
        if (!is_file($file)) {
            return false;
        }
        $raw = @file_get_contents($file);
        if ($raw === false) {
            return false;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['allowed_domains']) || !is_array($decoded['allowed_domains'])) {
            return false;
        }

        return in_array($domain, $decoded['allowed_domains'], true);
    }
}
