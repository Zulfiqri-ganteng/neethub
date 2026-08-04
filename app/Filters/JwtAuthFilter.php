<?php

namespace App\Filters;

use App\Helpers\AuthHelper;
use App\Helpers\ResponseHelper;
use App\Models\UserModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Verifies the Bearer access token, loads the user fresh from the DB (so a
 * deactivated/deleted account or role change takes effect immediately even
 * though the JWT itself hasn't expired), and populates AuthHelper's
 * request-scoped auth context.
 *
 * Route filter args restrict by role slug, e.g. 'jwtauth:owner,admin'.
 */
class JwtAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $header = $request->getHeaderLine('Authorization');

        if (! $header || ! str_starts_with($header, 'Bearer ')) {
            return $this->reject('Token akses tidak ditemukan.');
        }

        $token   = substr($header, 7);
        $decoded = AuthHelper::decodeToken($token);

        if (! $decoded || ($decoded['type'] ?? null) !== 'access') {
            return $this->reject('Token akses tidak valid atau sudah kedaluwarsa.');
        }

        $userModel = new UserModel();
        $user      = $userModel->findWithRole((int) $decoded['sub']);

        if (! $user || (int) $user['is_active'] === 0) {
            return $this->reject('Akun tidak aktif atau tidak ditemukan.');
        }

        if ($arguments && ! in_array($user['role_slug'], $arguments, true)) {
            return $this->reject('Anda tidak memiliki akses ke resource ini.', 403);
        }

        AuthHelper::setContext([
            'userId'   => (int) $user['id'],
            'tenantId' => $user['tenant_id'] !== null ? (int) $user['tenant_id'] : null,
            'roleId'   => (int) $user['role_id'],
            'roleSlug' => $user['role_slug'],
            'email'    => $user['email'],
        ]);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Nothing to do — context is per-request and dies with the process.
    }

    private function reject(string $message, int $status = 401): ResponseInterface
    {
        return service('response')
            ->setStatusCode($status)
            ->setJSON(ResponseHelper::error($message));
    }
}
