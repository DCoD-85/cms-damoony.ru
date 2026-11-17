<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Next\Support\ContentLayout;
use Damoony\Cli\Next\Support\TargetResolver;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use RuntimeException;

/**
 * content:store для схемы domains_app.
 *
 * MIME-whitelist, лимит 25 MB, минимум 200x200 (кроме SVG), дедупликация sha256.
 */
final class ContentStoreCommand implements CommandInterface
{
    private const MIME_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
    ];

    private const MAX_SIZE = 26214400;

    public function __construct(
        private readonly Paths $paths,
        private readonly TargetResolver $resolver,
        private readonly ContentLayout $layout,
    ) {
    }

    public function name(): string
    {
        return 'content:store';
    }

    public function description(): string
    {
        return 'Записать байты в /content/ (MIME, размеры, дедуп)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        try {
            $target = $this->resolver->resolve(
                (string) ($args['domain'] ?? ''),
                (string) ($args['allias'] ?? ''),
            );
        } catch (RuntimeException $e) {
            $r->error($e->getMessage());

            return $r;
        }

        $category = (string) ($args['category'] ?? 'general');
        if (preg_match(ContentLayout::CATEGORY_RE, $category) !== 1) {
            $r->error('Некорректная category (a-z0-9_-, 1-32)');

            return $r;
        }

        $mime = strtolower((string) ($args['mime'] ?? ''));
        if (!isset(self::MIME_EXT[$mime])) {
            $r->error(sprintf(
                "Недопустимый MIME '%s' (allowed: %s)",
                $mime,
                implode(', ', array_keys(self::MIME_EXT)),
            ));

            return $r;
        }

        // Политика: домен должен быть разрешён в agent.config.allowed_domains.
        if (!$this->domainAllowed($target->projectKey())) {
            $r->error(sprintf(
                "Проект '%s' не в config/agent.config.allowed_domains",
                $target->projectKey(),
            ));

            return $r;
        }

        $bytes = $this->bytes($args, $r);
        if ($bytes === null) {
            return $r;
        }

        if (strlen($bytes) > self::MAX_SIZE) {
            $r->error('Файл больше ' . self::MAX_SIZE . ' байт');

            return $r;
        }

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
                $r->error(sprintf('Изображение слишком маленькое (%dx%d, минимум 200x200)', $width, $height));

                return $r;
            }
        }

        $hash = substr(hash('sha256', $bytes), 0, 16);
        $ext = self::MIME_EXT[$mime];

        $dir = $this->layout->categoryDir($target->folder(), $target->aliasName(), $category);
        $file = $dir . '/' . $hash . '.' . $ext;
        $metaFile = $dir . '/' . $hash . '.meta.json';

        if (is_file($file)) {
            $r->file(Fs::rel($file, $this->paths->root()));
            $r->warn('deduplicated');

            $r->data = [
                'project' => $target->projectKey(),
                'allias' => $target->aliasName(),
                'category' => $category,
                'hash' => $hash,
                'url' => $this->layout->url($target->folder(), $target->aliasName(), $category, $hash, $ext),
                'deduplicated' => true,
            ];

            $context->step('Контент уже существует (дедуплицировано)', true, $hash);

            return $r;
        }

        if ($context->isDryRun()) {
            $context->step(sprintf('Dry-run: контент %s будет сохранён', $hash));
            $r->data = ['hash' => $hash, 'category' => $category, 'dry_run' => true];

            return $r;
        }

        Fs::mkdir($dir);
        Fs::writeAtomic($file, $bytes);
        $r->file(Fs::rel($file, $this->paths->root()));

        $this->layout->writeMeta($metaFile, [
            'source_url' => (string) ($args['source_url'] ?? ''),
            'alt' => (string) ($args['alt'] ?? ''),
            'size' => strlen($bytes),
            'mime' => $mime,
            'width' => $width,
            'height' => $height,
            'hash' => $hash,
            'downloaded_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'page_refs' => [],
        ]);
        $r->file(Fs::rel($metaFile, $this->paths->root()));

        $r->data = [
            'project' => $target->projectKey(),
            'allias' => $target->aliasName(),
            'category' => $category,
            'hash' => $hash,
            'mime' => $mime,
            'size' => strlen($bytes),
            'width' => $width,
            'height' => $height,
            'url' => $this->layout->url($target->folder(), $target->aliasName(), $category, $hash, $ext),
        ];

        $context->step('Контент сохранён', true, $hash);

        return $r;
    }

    /**
     * @param array<string, string> $args
     */
    private function bytes(array $args, Result $r): ?string
    {
        $dataFile = (string) ($args['data_file'] ?? '');
        $dataB64 = (string) ($args['data_b64'] ?? '');

        if ($dataFile !== '') {
            if (!is_file($dataFile)) {
                $r->error('Файл не найден: ' . $dataFile);

                return null;
            }
            $bytes = @file_get_contents($dataFile);
            if ($bytes === false || $bytes === '') {
                $r->error('Не удалось прочитать data_file');

                return null;
            }

            return $bytes;
        }

        if ($dataB64 !== '') {
            $bytes = base64_decode($dataB64, true);
            if ($bytes === false) {
                $r->error('Некорректный base64');

                return null;
            }

            return $bytes;
        }

        $r->error('Укажите --data_b64 или --data_file');

        return null;
    }

    private function domainAllowed(string $projectKey): bool
    {
        $file = $this->paths->root() . '/config/agent.config';
        if (!is_file($file)) {
            return false;
        }

        $config = Config::fromFile($file)->all();
        $allowed = $config['allowed_domains'] ?? null;

        return is_array($allowed) && in_array($projectKey, $allowed, true);
    }
}
