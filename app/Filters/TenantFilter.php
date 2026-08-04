<?php

namespace App\Filters;

use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use App\Models\TenantModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Runs after JwtAuthFilter. Confirms the authenticated user's tenant still
 * exists and is active — blocks access the moment a tenant is suspended,
 * even mid-session. Super admins have no tenant and skip this check.
 */
class TenantFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (AuthHelper::isSuperAdmin()) {
            return null;
        }

        $tenantId = AuthHelper::tenantId();

        if ($tenantId === null) {
            return $this->reject('Tenant tidak ditemukan untuk akun ini.');
        }

        $tenant = (new TenantModel())->find($tenantId);

        if (! $tenant || $tenant['status'] !== 'active') {
            return $this->reject('Akun tenant Anda sedang di-suspend. Hubungi Super Admin.', 403);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }

    private function reject(string $message, int $status = 403): ResponseInterface
    {
        return service('response')
            ->setStatusCode($status)
            ->setJSON(ResponseHelper::error($message));
    }
}
