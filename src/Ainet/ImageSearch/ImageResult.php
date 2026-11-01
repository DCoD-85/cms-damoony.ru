<?php

declare(strict_types=1);

namespace Damoony\Ainet\ImageSearch;

/**
 * Результат поиска картинки. Иммутабельный DTO.
 * Используется для ответа AI (image:search) и для image:download (url).
 */
final class ImageResult
{
    public function __construct(
        public readonly string $url,
        public readonly string $previewUrl,
        public readonly int $width,
        public readonly int $height,
        public readonly string $license,
        public readonly string $author,
        public readonly string $source,
        public readonly string $title = '',
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'preview_url' => $this->previewUrl,
            'width' => $this->width,
            'height' => $this->height,
            'license' => $this->license,
            'author' => $this->author,
            'source' => $this->source,
            'title' => $this->title,
        ];
    }
}
