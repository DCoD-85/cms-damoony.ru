<?php

declare(strict_types=1);

namespace Damoony\Core\App;

use Damoony\Core\Domain\DomainContext;
use Damoony\Core\Http\Request;

final class RequestContext
{
    public function __construct(
        public readonly Request $request,
        public readonly DomainContext $domain,
        public readonly bool $isAdmin,
        public readonly ?string $adminRole,
        public readonly string $lang,
        public readonly string $langStrategy, // 'path' | 'query' | 'cookie'
        public readonly string $basePath,     // '' для virtual host, '/admin' для path
        public readonly string $host,
        public readonly string $currentPath,  // чистый путь без языка и /admin (для switchLang)
    ) {
    }
}
