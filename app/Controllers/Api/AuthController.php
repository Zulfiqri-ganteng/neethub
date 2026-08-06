<?php

namespace App\Controllers\Api;

use App\Helpers\AuthHelper;
use App\Helpers\ValidationHelper;
use App\Models\ActivityModel;
use App\Models\BiometricCredentialModel;
use App\Models\PasswordResetModel;
use App\Models\PlatformSettingModel;
use App\Models\RefreshTokenModel;
use App\Models\RoleModel;
use App\Models\TenantModel;
use App\Models\UserModel;
use App\Notifications\NotificationSenderFactory;
use App\Services\NotificationTemplates;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use RuntimeException;

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

        // Stage 13: trial length is Super Admin-configurable
        // (platform_settings.trial_days), not a hardcoded constant — was
        // still hardcoded to 14 here from before that feature existed.
        $trialDays = (new PlatformSettingModel())->current()['trial_days'] ?? 30;

        $tenantId = $tenantModel->insert([
            'name'          => $data['tenant_name'],
            'slug'          => $this->uniqueSlug($tenantModel, $data['tenant_name']),
            'email'         => $data['email'],
            'whatsapp'      => $data['phone'] ?? null,
            'plan'          => 'trial',
            'status'        => 'active',
            'trial_ends_at' => date('Y-m-d H:i:s', strtotime("+{$trialDays} days")),
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

    /**
     * Public, unauthenticated — the app calls this BEFORE showing the
     * "Masuk/Daftar dengan Google" button, since Login/Register are
     * pre-auth screens with no way to hit the jwtauth-gated `/brand`
     * endpoint's pattern. `client_id` is a Google Web Client ID, not a
     * secret — safe to return as-is (same reasoning `google_client_id`
     * itself is never encrypted at rest in platform_settings).
     */
    public function googleConfig()
    {
        $clientId = (new PlatformSettingModel())->current()['google_client_id'] ?? null;

        return $this->ok([
            'enabled'   => ! empty($clientId),
            'client_id' => $clientId,
        ]);
    }

    /**
     * One endpoint for both "Masuk dengan Google" and "Daftar dengan
     * Google" — which one happens is decided by whether the token's email
     * already has an account, not by a client-supplied mode flag (so the
     * app can't be tricked into re-registering an existing email, and a
     * user who forgot they already signed up just gets logged in instead
     * of a confusing "email already exists" error).
     *
     * Google only ever gives us name + email, never a business name or
     * phone — so a genuinely new email can't be registered in one call.
     * The first call (just `id_token`) gets back `needs_registration: true`
     * plus Google's own name/email so the app can show a short "lengkapi
     * data usaha" form, then resubmit the SAME id_token with `tenant_name`
     * (+ optional `owner_name`/`phone`) filled in. Google ID tokens are
     * short-lived (~1 hour) but that's easily enough time for this
     * two-step round trip.
     */
    public function google()
    {
        if (! $this->validate(ValidationHelper::googleAuthRules())) {
            return $this->validationError($this->validator->getErrors());
        }

        $data     = $this->request->getJSON(true) ?? $this->request->getPost();
        $clientId = (new PlatformSettingModel())->current()['google_client_id'] ?? null;

        if (empty($clientId)) {
            return $this->fail('Login Google belum diaktifkan oleh penyedia layanan.', 422);
        }

        try {
            $payload = $this->verifyGoogleIdToken($data['id_token'], $clientId);
        } catch (RuntimeException $e) {
            return $this->unauthorized($e->getMessage());
        }

        $email     = $payload['email'];
        $userModel = new UserModel();
        $user      = $userModel->findByEmailWithRole($email);

        if ($user) {
            $eligibilityError = $this->assertUserCanLogin($user);
            if ($eligibilityError) {
                return $eligibilityError;
            }

            return $this->completeLogin($user, 'Login berhasil.', 'Login berhasil (Google)');
        }

        if (empty($data['tenant_name'])) {
            return $this->ok([
                'needs_registration' => true,
                'google'             => ['name' => $payload['name'] ?? '', 'email' => $email],
            ], 'Akun belum terdaftar. Lengkapi data usaha untuk mendaftar.');
        }

        return $this->registerViaGoogle($email, $payload['name'] ?? '', $data);
    }

    /**
     * Same shape as register() — a brand-new tenant + owner — just sourced
     * from a verified Google identity instead of a submitted password. The
     * account still gets a real (random, unknown-to-anyone) password hash
     * rather than a null/blank one, so it satisfies UserModel's normal
     * `password required` validation and stays consistent with "every user
     * row has a password" — if this owner ever wants password login too,
     * "Lupa Password" already works for any account regardless of how it
     * was created.
     */
    private function registerViaGoogle(string $email, string $googleName, array $data): ResponseInterface
    {
        $tenantModel = new TenantModel();
        $userModel   = new UserModel();
        $roleModel   = new RoleModel();

        $ownerRole = $roleModel->findBySlug('owner');
        if (! $ownerRole) {
            return $this->fail('Konfigurasi role belum lengkap. Hubungi Super Admin.', 500);
        }

        $trialDays = (new PlatformSettingModel())->current()['trial_days'] ?? 30;

        $tenantId = $tenantModel->insert([
            'name'          => $data['tenant_name'],
            'slug'          => $this->uniqueSlug($tenantModel, $data['tenant_name']),
            'email'         => $email,
            'whatsapp'      => $data['phone'] ?? null,
            'plan'          => 'trial',
            'status'        => 'active',
            'trial_ends_at' => date('Y-m-d H:i:s', strtotime("+{$trialDays} days")),
        ]);

        $userId = $userModel->insert([
            'tenant_id' => $tenantId,
            'role_id'   => $ownerRole['id'],
            'name'      => $data['owner_name'] ?? ($googleName !== '' ? $googleName : 'Owner'),
            'email'     => $email,
            'phone'     => $data['phone'] ?? null,
            'password'  => bin2hex(random_bytes(32)),
            'is_active' => 1,
        ]);

        (new ActivityModel())->log(
            'register',
            $tenantId,
            $userId,
            "Tenant baru terdaftar via Google: {$data['tenant_name']}",
            $this->request->getIPAddress(),
            $this->request->getUserAgent()->getAgentString()
        );

        $tokens = $this->issueTokens((int) $userId, $tenantId ? (int) $tenantId : null, 'owner', $email);

        $user = $userModel->findWithRole((int) $userId);
        unset($user['password']);

        return $this->created([
            'tenant' => $tenantModel->find($tenantId),
            'user'   => $user,
            ...$tokens,
        ], 'Registrasi berhasil. Selamat datang di NetHub!');
    }

    /**
     * Verifies the ID token against Google's tokeninfo endpoint — a simple
     * HTTP call rather than local JWKS-based verification, same "direct
     * HTTP call, no SDK" convention as FonnteWhatsAppSender/Midtrans/Xendit
     * in this codebase, and Google's own docs list this endpoint as an
     * acceptable (if slightly slower) alternative to local verification.
     * Checks `aud` against this platform's configured Client ID so a token
     * meant for a completely different Google app can't be replayed here.
     *
     * @return array<string, mixed> the token's decoded claims (sub, email,
     *   email_verified, name, ...)
     * @throws RuntimeException with a user-facing message on any failure.
     */
    private function verifyGoogleIdToken(string $idToken, string $expectedClientId): array
    {
        $client   = Services::curlrequest();
        $response = $client->get('https://oauth2.googleapis.com/tokeninfo', [
            'query'       => ['id_token' => $idToken],
            'http_errors' => false,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException('Token Google tidak valid atau sudah kedaluwarsa.');
        }

        $payload = json_decode($response->getBody(), true) ?? [];

        if (($payload['aud'] ?? null) !== $expectedClientId) {
            throw new RuntimeException('Token Google tidak valid untuk aplikasi ini.');
        }

        if (($payload['email_verified'] ?? 'false') !== 'true') {
            throw new RuntimeException('Email Google Anda belum terverifikasi.');
        }

        if (empty($payload['email'])) {
            throw new RuntimeException('Google tidak mengirimkan alamat email.');
        }

        return $payload;
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

        $eligibilityError = $this->assertUserCanLogin($user);
        if ($eligibilityError) {
            return $eligibilityError;
        }

        return $this->completeLogin($user, 'Login berhasil');
    }

    /**
     * Same account-status checks `login()` and `biometricLogin()` must both
     * enforce (an inactive account, a suspended tenant, or a tenant that
     * turned the customer portal off shouldn't get in through *either*
     * door) — kept in one place so they can't silently drift apart.
     *
     * @return ResponseInterface|null null if the user is allowed to log in.
     */
    private function assertUserCanLogin(array $user): ?ResponseInterface
    {
        if ((int) $user['is_active'] === 0) {
            return $this->forbidden('Akun Anda dinonaktifkan. Hubungi administrator.');
        }

        if ($user['role_slug'] !== 'super_admin' && $user['tenant_id'] !== null) {
            $tenant = (new TenantModel())->find($user['tenant_id']);
            if (! $tenant || $tenant['status'] !== 'active') {
                return $this->forbidden('Akun tenant Anda sedang di-suspend. Hubungi Super Admin.');
            }

            // A tenant can turn the customer portal off after pelanggan
            // accounts already exist (Stage 13) — don't leave those accounts
            // able to log in as if nothing changed.
            if ($user['role_slug'] === 'pelanggan' && empty($tenant['customer_portal_enabled'])) {
                return $this->forbidden('Login pelanggan sedang dinonaktifkan oleh penyedia layanan Anda. Hubungi mereka langsung.');
            }
        }

        return null;
    }

    /**
     * Shared tail end of every successful login (password or biometric):
     * bump `last_login_at`, log the activity, issue a fresh token pair.
     */
    private function completeLogin(array $user, string $message, string $activityNote = 'Login berhasil'): ResponseInterface
    {
        $userModel = new UserModel();
        $userModel->update($user['id'], ['last_login_at' => date('Y-m-d H:i:s')]);

        (new ActivityModel())->log(
            'login',
            $user['tenant_id'] !== null ? (int) $user['tenant_id'] : null,
            (int) $user['id'],
            $activityNote,
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
        ], $message);
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

    /**
     * Works for every role — owner/admin/teknisi/kasir/pelanggan/super_admin
     * all log in through this same shared endpoint, so one flow covers all
     * of them. Always returns the same generic success message whether or
     * not the email exists, to avoid leaking which emails are registered.
     *
     * Sends the email SYNCHRONOUSLY within this request — deliberately not
     * through the notifications queue/cron (unlike account_created), so the
     * user gets the email within seconds instead of waiting on the next
     * `notifications:send` cron tick. A send failure (bad/missing SMTP
     * config) is logged server-side but never surfaces to the caller — the
     * generic response must stay generic either way; use the Super Admin
     * settings page's "Test SMTP" button to actually diagnose delivery
     * problems, not this endpoint.
     */
    public function forgotPassword()
    {
        if (! $this->validate(ValidationHelper::forgotPasswordRules())) {
            return $this->validationError($this->validator->getErrors());
        }

        $data = $this->request->getJSON(true) ?? $this->request->getPost();
        $user = (new UserModel())->findByEmail($data['email']);

        if ($user && (int) $user['is_active'] === 1) {
            $token = bin2hex(random_bytes(32));

            (new PasswordResetModel())->insert([
                'email'      => $user['email'],
                'token_hash' => hash('sha256', $token),
                'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            ]);

            try {
                $rendered = (new NotificationTemplates())->passwordReset(['token' => $token, 'email' => $user['email']]);
                NotificationSenderFactory::email()->send($user['email'], $rendered['subject'], $rendered['message']);
            } catch (\Throwable $e) {
                log_message('error', 'Gagal kirim email reset password ke {email}: {error}', [
                    'email' => $user['email'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->ok(null, 'Jika email terdaftar, link reset password telah dikirim. Cek email Anda dalam beberapa menit.');
    }

    public function resetPassword()
    {
        if (! $this->validate(ValidationHelper::resetPasswordRules())) {
            return $this->validationError($this->validator->getErrors());
        }

        $data  = $this->request->getJSON(true) ?? $this->request->getPost();
        $reset = (new PasswordResetModel())->findValid($data['email'], $data['token']);

        if (! $reset) {
            return $this->fail('Link reset password tidak valid atau sudah kedaluwarsa.', 422);
        }

        $userModel = new UserModel();
        $user      = $userModel->findByEmail($data['email']);

        if (! $user) {
            return $this->fail('Link reset password tidak valid atau sudah kedaluwarsa.', 422);
        }

        $userModel->update($user['id'], ['password' => $data['password']]);
        (new PasswordResetModel())->markUsed((int) $reset['id']);

        // Force re-login everywhere — a leaked-then-reset password
        // shouldn't leave old sessions valid.
        (new RefreshTokenModel())->revokeAllForUser((int) $user['id']);

        (new ActivityModel())->log(
            'password_reset',
            $user['tenant_id'] !== null ? (int) $user['tenant_id'] : null,
            (int) $user['id'],
            'Password direset via lupa password'
        );

        return $this->ok(null, 'Password berhasil direset. Silakan masuk dengan password baru Anda.');
    }

    /**
     * Register (or re-register) this device's biometric-gated public key
     * against the currently logged-in user. Requires a normal Bearer token
     * from a real password login — you can't turn fingerprint login on
     * without having just proven you know the password, by design.
     */
    public function biometricRegister()
    {
        if (! $this->validate(ValidationHelper::biometricRegisterRules())) {
            return $this->validationError($this->validator->getErrors());
        }

        $data = $this->request->getJSON(true) ?? $this->request->getPost();

        (new BiometricCredentialModel())->upsertForUser(
            AuthHelper::id(),
            $data['device_id'],
            $data['device_name'] ?? null,
            $data['public_key']
        );

        return $this->ok(null, 'Login sidik jari berhasil diaktifkan.');
    }

    /**
     * Step 1 of passwordless biometric login (no auth — this is what lets
     * the app get back in without a password at all): issue a one-time
     * challenge for this device to sign with its private key. Deliberately
     * doesn't reveal *why* a device isn't eligible (never registered vs.
     * revoked vs. wrong device) — same anti-enumeration reasoning as
     * `forgotPassword()`.
     */
    public function biometricChallenge()
    {
        if (! $this->validate(ValidationHelper::biometricChallengeRules())) {
            return $this->validationError($this->validator->getErrors());
        }

        $data       = $this->request->getJSON(true) ?? $this->request->getPost();
        $credModel  = new BiometricCredentialModel();
        $credential = $credModel->findActiveByDeviceId($data['device_id']);

        if (! $credential) {
            return $this->unauthorized('Login sidik jari tidak tersedia untuk perangkat ini. Silakan masuk dengan password.');
        }

        $challenge = bin2hex(random_bytes(32));
        $credModel->setChallenge((int) $credential['id'], $challenge, 120);

        return $this->ok(['challenge' => $challenge], 'Tantangan berhasil dibuat.');
    }

    /**
     * Step 2: verify the signed challenge against the stored public key.
     * On success this behaves exactly like a normal login — same account
     * eligibility checks, same fresh token pair — it just replaces "type
     * your password" with "prove you hold the private key" as the factor.
     */
    public function biometricLogin()
    {
        if (! $this->validate(ValidationHelper::biometricLoginRules())) {
            return $this->validationError($this->validator->getErrors());
        }

        $data       = $this->request->getJSON(true) ?? $this->request->getPost();
        $credModel  = new BiometricCredentialModel();
        $credential = $credModel->findActiveByDeviceId($data['device_id']);

        if (! $credential) {
            return $this->unauthorized('Login sidik jari tidak tersedia untuk perangkat ini. Silakan masuk dengan password.');
        }

        if (empty($credential['challenge']) || strtotime($credential['challenge_expires_at']) < time()) {
            return $this->unauthorized('Tantangan sudah kedaluwarsa. Coba lagi.');
        }

        $publicKey = openssl_pkey_get_public($credential['public_key']);
        $signature = base64_decode($data['signature'], true);
        $verified  = $publicKey && $signature !== false
            && openssl_verify($credential['challenge'], $signature, $publicKey, OPENSSL_ALGO_SHA256) === 1;

        // Single-use regardless of outcome — a failed attempt shouldn't
        // leave a challenge sitting around for a retry to reuse.
        $credModel->clearChallenge((int) $credential['id']);

        if (! $verified) {
            return $this->unauthorized('Verifikasi sidik jari gagal.');
        }

        $userModel = new UserModel();
        $user      = $userModel->findWithRole((int) $credential['user_id']);

        if (! $user) {
            return $this->unauthorized('Akun tidak ditemukan.');
        }

        $eligibilityError = $this->assertUserCanLogin($user);
        if ($eligibilityError) {
            return $eligibilityError;
        }

        $credModel->markUsed((int) $credential['id']);

        return $this->completeLogin($user, 'Login berhasil.', 'Login berhasil (sidik jari)');
    }

    /**
     * Turns fingerprint login back off for this device — called when the
     * user explicitly disables it from the app. Deliberately independent of
     * a normal `logout()`/refresh-token lifecycle: the whole point of this
     * credential is that it survives a regular logout and gets a *fresh*
     * token pair on its own, so logging out must NOT implicitly revoke it.
     */
    public function biometricRevoke()
    {
        if (! $this->validate(ValidationHelper::biometricRevokeRules())) {
            return $this->validationError($this->validator->getErrors());
        }

        $data = $this->request->getJSON(true) ?? $this->request->getPost();
        (new BiometricCredentialModel())->revokeForUser(AuthHelper::id(), $data['device_id']);

        return $this->ok(null, 'Login sidik jari berhasil dinonaktifkan.');
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
