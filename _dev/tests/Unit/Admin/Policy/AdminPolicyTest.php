<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Admin\Policy;

use Damoony\Admin\Policy\AdminPolicy;
use PHPUnit\Framework\TestCase;

final class AdminPolicyTest extends TestCase
{
    /** @param array<string, mixed> $extra */
    private function session(string $role, string $scope = '', array $extra = []): array
    {
        return ['role' => $role, 'scope' => $scope, 'account' => ['id' => 1], 'session_id' => 1] + $extra;
    }

    public function testSuperadminHasAllCapabilities(): void
    {
        $session = $this->session('superadmin', 'damoony.ru');

        self::assertTrue(AdminPolicy::allows($session, AdminPolicy::SERVER_MANAGE));
        self::assertTrue(AdminPolicy::allows($session, AdminPolicy::ACCOUNTS_MANAGE));
        self::assertTrue(AdminPolicy::allows($session, AdminPolicy::DOMAIN_MANAGE, 'other.ru'));
    }

    public function testDomainadminIsLimitedToOwnDomain(): void
    {
        $session = $this->session('domainadmin', 'docs.damoony.ru');

        self::assertTrue(AdminPolicy::allows($session, AdminPolicy::DOMAIN_VIEW, 'docs.damoony.ru'));
        self::assertTrue(AdminPolicy::allows($session, AdminPolicy::ALIAS_MANAGE, 'docs.damoony.ru'));
        self::assertTrue(AdminPolicy::allows($session, AdminPolicy::CONTENT_EDIT, 'docs.damoony.ru'));
        self::assertFalse(AdminPolicy::allows($session, AdminPolicy::DOMAIN_VIEW, 'other.ru'));
        self::assertFalse(AdminPolicy::allows($session, AdminPolicy::SERVER_MANAGE, 'docs.damoony.ru'));
        self::assertFalse(AdminPolicy::allows($session, AdminPolicy::ACCOUNTS_MANAGE, 'docs.damoony.ru'));
        self::assertFalse(AdminPolicy::allows($session, AdminPolicy::OPS_VIEW, 'docs.damoony.ru'));
    }

    public function testDomainadminWithoutScopeIsDenied(): void
    {
        $session = $this->session('domainadmin');

        self::assertFalse(AdminPolicy::allows($session, AdminPolicy::DOMAIN_VIEW, 'damoony.ru'));
    }

    public function testUnknownRoleIsDenied(): void
    {
        self::assertFalse(AdminPolicy::allows($this->session('assistant'), AdminPolicy::CONTENT_EDIT));
    }
}
