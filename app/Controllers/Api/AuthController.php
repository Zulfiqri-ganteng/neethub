<?php

namespace App\Controllers\Api;

use App\Helpers\AuthHelper;
use App\Helpers\ValidationHelper;
use App\Models\ActivityModel;
use App\Models\RefreshTokenModel;
use App\Models\RoleModel;
use App\Models\TenantModel;
use App\Models\UserModel;

class AuthController extends BaseApiController
{
    /**
     * Self-registration: a new internet provider signs up and becomes the
     * "owner" of a brand-new, fully isolated tenant.
     */
    public function register()
    {
        $rules = ValidationHelper::registerRules();

        if (! $this->validate($rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $data      = $this->request->getJSON(true) ?? $this->request->getPost();
        $tenantModel = new TenantModel();
        $userModel   = new UserModel();
        $roleModel   = new RoleModel();

        $ownerRole = $roleModel->findBySlug('owner');
        if (! $ownerRole) {
            return $this->fail('Konfigurasi role belum lengkap. Hubungi Super Admin.', 500);
        }

        $tenantId = $tenantModel->insert([
            'name'          => $data['tenant_name'],
            'slug'          => $this->uniqueSlug($tenantModel, $data['tenant_name']),
            'email'         => $data['email'],
            'whatsapp'      => $data['phone'] ?? null,
            'plan'          => 'trial',
            'status'        => 'active',
            'trial_ends_at' => date('Y-m-d H:i:s', strtotime('+14 days')),
        ]);

        $userId = $userModel->insert([
            'tenant_id' => $tenantId,
            'role_id'   => $ownerRole['id'],
            'name'      => $data['owner_name'],
            'email'     => $data['email'],
            'phone'     => $data['phone'] ?? null,
            'password'  => $data['password'],
            'is_active' => 1,
        ]);

        (new ActivityModel())->log(
            'register',
            $tenantId,
            $userId,
            "Tenant baru terdaftar: {$data['tenant_name']}",
            $this->request->getIPAddress(),
            $this->request->getUserAgent()->getAgentString()
        );

        $tokens = $this->issueTokens((int) $userId, $tenantId ? (int) $tenantId : null, 'owner', $data['email']);

        $user = $userModel->findWithRole((int) $userId);
        unset($user['password']);

        return $this->created([
            'tenant' => $tenantModel->find($tenantId),
            'user'   => $user,
            ...$tokens,
        ], 'Registrasi berhasil. Selamat datang di NetHub!');
    }

    public function login()
    {
        if (! $this->validate(ValidationHelper::loginRules())) {
            return $this->validationError($this->validator->getErrors());
        }

        $data = $this->request->getJSON(true) ?? $this->request->getPost();
        $userModel = new UserModel();
        $user = $userModel->findByEmailWithRole($data['email']);

        if (! $user || ! AuthHelper::verifyPassword($data['password'], $user['password'])) {
            return $this->unauthorized('Email atau password salah.');
        }

        if ((int) $user['is_active'] === 0) {
            return $this->forbidden('Akun Anda dinonaktifkan. Hubungi administrator.');
        }

        if ($user['role_slug'] !== 'super_admin' && $user['tenant_id'] !== null) {
            $tenant = (new TenantModel())->find($user['tenant_id']);
            if (! $tenant || $tenant['status'] !== 'active') {
                return $this->forbidden('Akun tenant Anda sedang di-suspend. Hubungi Super Admin.');
            }
        }

        $userModel->update($user['id'], ['last_login_at' => date('Y-m-d H:i:s')]);

        (new ActivityModel())->log(
            'login',
            $user['tenant_id'] !== null ? (int) $user['tenant_id'] : null,
            (int) $user['id'],
            'Login berhasil',
            $this->request->getIPAddress(),
            $this->request->getUserAgent()->getAgentString()
        );

        $tokens = $this->issueTokens(
            (int) $user['id'],
            $user['tenant_id'] !== null ? (int) $user['tenant_id'] : null,
            $user['role_slug'],
            $user['email']
        );

        unset($user['password']);

        return $this->ok([
            'user' => $user,
            ...$tokens,
        ], 'Login berhasil.');
    }

    public function refresh()
    {
        if (! $this->validate(ValidationHelper::refreshRules())) {
            return $this->validationError($this->validator->getErrors());
        }

        $data          = $this->request->getJSON(true) ?? $this->request->getPost();
        $refreshToken  = $data['refresh_token'];
        $decoded       = AuthHelper::decodeToken($refreshToken);

        if (! $decoded || ($decoded['type'] ?? null) !== 'refresh') {
            return $this->unauthorized('Refresh token tidak valid.');
        }

        $refreshTokenModel = new RefreshTokenModel();
        $stored = $refreshTokenModel->findActiveByToken($refreshToken);

        if (! $stored || (int) $stored['user_id'] !== (int) $decoded['sub']) {
            return $this->unauthorized('Refresh token tidak valid atau sudah dicabut.');
        }

        $userModel = new UserModel();
        $user = $userModel->findWithRole((int) $decoded['sub']);

        if (! $user || (int) $user['is_active'] === 0) {
            return $this->unauthorized('Akun tidak aktif.');
        }

        // Rotate: revoke the used refresh token and issue a fresh pair.
        $refreshTokenModel->revoke($stored['id']);

        $tokens = $this->issueTokens(
            (int) $user['id'],
            $user['tenant_id'] !== null ? (int) $user['tenant_id'] : null,
            $user['role_slug'],
            $user['email']
        );

        return $this->ok($tokens, 'Token berhasil diperbarui.');
    }

    public function logout()
    {
        $data = $this->request->getJSON(true) ?? $this->request->getPost();
        $refreshTokenModel = new RefreshTokenModel();

        if (! empty($data['refresh_token'])) {
            $stored = $refreshTokenModel->findActiveByToken($data['refresh_token']);
            if ($stored) {
                $refreshTokenModel->revoke($stored['id']);
            }
        } else {
            $refreshTokenModel->revokeAllForUser(AuthHelper::id());
        }

        (new ActivityModel())->log(
            'logout',
            AuthHelper::tenantId(),
            AuthHelper::id(),
            'Logout',
            $this->request->getIPAddress(),
            $this->request->getUserAgent()->getAgentString()
        );

        return $this->ok(null, 'Logout berhasil.');
    }

    private function issueTokens(int $userId, ?int $tenantId, string $roleSlug, string $email): array
    {
        $accessToken  = AuthHelper::generateAccessToken([
            'userId'   => $userId,
            'tenantId' => $tenantId,
            'roleSlug' => $roleSlug,
            'email'    => $email,
        ]);
        $refreshToken = AuthHelper::generateRefreshToken($userId);

        $refreshTokenModel = new RefreshTokenModel();
        $refreshTokenModel->insert([
            'user_id'    => $userId,
            'token_hash' => $refreshTokenModel->hashToken($refreshToken),
            'expires_at' => date('Y-m-d H:i:s', time() + AuthHelper::refreshExpirySeconds()),
            'ip_address' => $this->request->getIPAddress(),
            'user_agent' => $this->request->getUserAgent()->getAgentString(),
        ]);

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type'    => 'Bearer',
            'expires_in'    => AuthHelper::accessExpirySeconds(),
        ];
    }

    private function uniqueSlug(TenantModel $tenantModel, string $name): string
    {
        $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
        $slug = $base;
        $suffix = 1;

        while ($tenantModel->where('slug', $slug)->first()) {
            $slug = $base . '-' . (++$suffix);
        }

        return $slug;
    }
}
