<?php

namespace App\Helpers;

/**
 * Thin accessor over the current request's tenant context. The context
 * itself is owned by AuthHelper (set by JwtAuthFilter) — this class exists
 * so Models/Repositories can ask "what tenant am I scoped to" without
 * reaching into auth internals directly.
 */
class TenantHelper
{
    public static function id(): ?int
    {
        return AuthHelper::tenantId();
    }

    public static function isSuperAdmin(): bool
    {
        return AuthHelper::isSuperAdmin();
    }

    /**
     * Throws if there is no tenant in context and the caller is not a
     * super admin. Use at the start of any tenant-scoped repository method
     * to fail loudly instead of silently returning cross-tenant data.
     */
    public static function requireTenantId(): int
    {
        $tenantId = self::id();

        if ($tenantId === null) {
            throw new \RuntimeException('No tenant context available for this request.');
        }

        return $tenantId;
    }
}
