<?php

declare(strict_types=1);

namespace Damoony\Region;

use Damoony\Domain\DomainContext;
use Damoony\Http\Request;
use Damoony\I18n\Translator;
use Damoony\Ui\Meta;

final class RegionContext
{
    public readonly Meta $meta;

    /** @param array<string, mixed> $services */
    public function __construct(
        public readonly Request $request,
        public readonly DomainContext $domain,
        ?Meta $meta = null,
        public readonly array $services = [],
    ) {
        $this->meta = $meta ?? new Meta();
    }

    public function service(string $name): mixed
    {
        return $this->services[$name] ?? null;
    }

    /** @param array<string, string|int> $params */
    public function lang(string $key, array $params = []): string
    {
        $t = $this->service('i18n');
        if ($t instanceof Translator) {
            return $t->t('site.' . $key, $params);
        }
        return $key;
    }

    public function isAuthenticated(): bool
    {
        $auth = $this->service('auth');
        return $auth instanceof \Damoony\Auth\AuthInterface && $auth->isAuthenticated();
    }

    public function user(): mixed
    {
        $auth = $this->service('auth');
        return $auth instanceof \Damoony\Auth\AuthInterface ? $auth->user() : null;
    }

    public function isPost(): bool
    {
        return $this->request->isPost();
    }

    public function isAjax(): bool
    {
        return $this->request->isAjax();
    }
}
