<?php

declare(strict_types=1);

namespace Damoony\UI\Region;

use Damoony\Core\Domain\DomainContext;
use Damoony\Core\Http\Request;
use Damoony\Core\I18n\Translator;
use Damoony\UI\Meta;

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

    /** @param array<string, string|int> $params */
    public function langPlural(string $key, int|float $count, array $params = []): string
    {
        $t = $this->service('i18n');
        if ($t instanceof Translator) {
            return $t->tPlural('site.' . $key, $count, $params);
        }
        return $key;
    }

    public function isAuthenticated(): bool
    {
        $auth = $this->service('auth');
        return $auth instanceof \Damoony\Data\Auth\AuthInterface && $auth->isAuthenticated();
    }

    public function user(): mixed
    {
        $auth = $this->service('auth');
        return $auth instanceof \Damoony\Data\Auth\AuthInterface ? $auth->user() : null;
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
