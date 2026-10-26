<?php

declare(strict_types=1);

namespace Damoony\Scheme;

use Damoony\Domain\DomainContext;
use Damoony\Files\Paths;
use RuntimeException;

final class SchemeLoader
{
    public function __construct(
        private readonly Paths $paths,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function load(DomainContext $domain, string $schemeName = 'www'): array
    {
        $file = $this->paths->domain($domain->real) . '/.alliasscheme/' . $schemeName . '.data.php';

        if (!is_file($file)) {
            // Fallback, если схемы нет
            return [
                'tag' => 'div',
                'class' => 'layout',
                'children' => [
                    ['tag' => 'main', 'slot' => 'center'],
                ],
            ];
        }

        $data = require $file;
        if (!is_array($data)) {
            throw new RuntimeException('Invalid scheme format: ' . $file);
        }

        return $data;
    }
}