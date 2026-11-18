<?php

declare(strict_types=1);

namespace Damoony\Admin\Policy;

/**
 * Права ролей и проверка scope. Только человеческие админы.
 */
final class AdminPolicy
{
    public const SERVER_VIEW = 'server.view';
    public const SERVER_MANAGE = 'server.manage';
    public const DOMAIN_VIEW = 'domain.view';
    public const DOMAIN_MANAGE = 'domain.manage';
    public const ALIAS_VIEW = 'alias.view';
    public const ALIAS_MANAGE = 'alias.manage';
    public const CONTENT_EDIT = 'content.edit';
    public const ACCOUNTS_MANAGE = 'accounts.manage';
    public const AUDIT_VIEW = 'audit.view';
    public const OPS_VIEW = 'ops.view';

    /** @return list<string> */
    public static function capabilitiesFor(string $role): array
    {
        return match ($role) {
            'superadmin' => [
                self::SERVER_VIEW, self::SERVER_MANAGE,
                self::DOMAIN_VIEW, self::DOMAIN_MANAGE,
                self::ALIAS_VIEW, self::ALIAS_MANAGE,
                self::CONTENT_EDIT, self::ACCOUNTS_MANAGE,
                self::AUDIT_VIEW, self::OPS_VIEW,
            ],
            'domainadmin' => [
                self::DOMAIN_VIEW, self::DOMAIN_MANAGE,
                self::ALIAS_VIEW, self::ALIAS_MANAGE,
                self::CONTENT_EDIT, self::AUDIT_VIEW,
            ],
            default => [],
        };
    }

    /**
     * @param array{role: string, scope: string, account: array<string, mixed>} $session
     */
    public static function allows(array $session, string $capability, string $domain = '', string $alias = ''): bool
    {
        if (!in_array($capability, self::capabilitiesFor((string) $session['role']), true)) {
            return false;
        }
        if ((string) $session['role'] === 'superadmin') {
            return true;
        }

        $scope = strtolower(trim((string) $session['scope']));
        if ($scope === '') {
            return false;
        }

        $domain = strtolower(trim($domain));
        if ($domain === '') {
            return false;
        }

        return $scope === $domain || str_ends_with($domain, '.' . $scope);
    }
}
