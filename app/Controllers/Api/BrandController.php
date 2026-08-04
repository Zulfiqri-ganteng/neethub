<?php

namespace App\Controllers\Api;

use App\Helpers\AuthHelper;
use App\Models\TenantModel;

/**
 * Lightweight, cross-role branding lookup (Stage 13 phase 5) — `{name,
 * logo}` only, called by nethub-auth.js on every authenticated page so the
 * sidebar/topbar reflects whichever tenant is actually logged in instead of
 * a hardcoded "NetHub". Deliberately its own top-level endpoint rather than
 * reusing Owner\SettingsController::show — that one requires
 * `permission:settings.manage`, which only the `owner` role has (not admin/
 * teknisi/kasir/pelanggan), so it would 403 on every page load for everyone
 * else. This one just needs `jwtauth` (any authenticated role, no tenant
 * filter — super_admin has no tenant and must still get a sane default).
 */
class BrandController extends BaseApiController
{
    public function index()
    {
        $tenantId = AuthHelper::tenantId();

        if ($tenantId === null) {
            return $this->ok(['name' => 'NetHub', 'logo' => null]);
        }

        $tenant = (new TenantModel())->find($tenantId);

        return $this->ok([
            'name' => $tenant['name'] ?? 'NetHub',
            'logo' => $tenant['logo'] ?? null,
        ]);
    }
}
