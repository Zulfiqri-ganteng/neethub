<?php

namespace App\Filters;

use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use App\Models\RoleModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Runs after JwtAuthFilter. Route filter arg is the required permission
 * slug(s), e.g. 'permission:customers.delete'. Super admin bypasses.
 */
class PermissionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (AuthHelper::isSuperAdmin()) {
            return null;
        }

        if (! $arguments) {
            return null;
        }

        $granted = (new RoleModel())->permissionSlugs(AuthHelper::roleId());
        $missing = array_diff($arguments, $granted);

        if ($missing !== []) {
            return service('response')
                ->setStatusCode(403)
                ->setJSON(ResponseHelper::error('Anda tidak memiliki izin: ' . implode(', ', $missing)));
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
