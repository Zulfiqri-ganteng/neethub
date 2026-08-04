<?php

namespace App\Controllers\Api\SuperAdmin;

use App\Controllers\Api\BaseApiController;
use App\Helpers\AuthHelper;
use App\Models\PlatformSettingModel;
use App\Notifications\SmtpEmailSender;
use RuntimeException;
use Throwable;

class PlatformSettingController extends BaseApiController
{
    public function show()
    {
        return $this->ok($this->withoutSecrets((new PlatformSettingModel())->current()));
    }

    public function update()
    {
        $rules = [
            'monthly_fee' => 'permit_empty|decimal|greater_than[0]',
            'trial_days'  => 'permit_empty|integer|greater_than[0]',
            'grace_days'  => 'permit_empty|integer|greater_than_equal_to[0]',
            'smtp_port'   => 'permit_empty|integer',
        ];

        if (! $this->validate($rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $data  = $this->request->getJSON(true) ?? $this->request->getRawInput();
        $model = new PlatformSettingModel();

        $update = array_intersect_key($data, array_flip([
            'monthly_fee', 'trial_days', 'grace_days', 'bank_name', 'bank_account_number', 'bank_account_name',
            'smtp_host', 'smtp_port', 'smtp_user', 'smtp_crypto', 'smtp_from_email', 'smtp_from_name',
        ]));

        // Only overwrite the stored (encrypted) password if a new one was
        // actually typed — an empty string here means "leave it unchanged",
        // same convention as the router/PPPoE password fields elsewhere.
        if (! empty($data['smtp_pass'])) {
            $update['smtp_pass'] = $data['smtp_pass'];
        }

        $model->update(1, $update);

        return $this->ok($this->withoutSecrets($model->current()), 'Pengaturan platform berhasil disimpan.');
    }

    /**
     * Tests whatever SMTP config is in the request body right now — NOT
     * necessarily what's saved yet, so a Super Admin can verify before
     * committing a typo'd host/port/password. Send failures return their
     * real error message here (unlike forgotPassword, which never leaks
     * send failures to the caller) — this endpoint's entire purpose is
     * debugging delivery, so hiding the real error would defeat it.
     */
    public function testSmtp()
    {
        $rules = [
            'smtp_host' => 'required|string',
            'smtp_port' => 'required|integer',
            'to_email'  => 'permit_empty|valid_email',
        ];

        if (! $this->validate($rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $data = $this->request->getJSON(true) ?? $this->request->getRawInput();

        // A blank password in the test form means "use the already-saved
        // one" (same convention as update()) — otherwise typing a new
        // password just to re-test an already-working account is annoying.
        $smtpPass = $data['smtp_pass'] ?? '';
        if ($smtpPass === '') {
            $smtpPass = (new PlatformSettingModel())->currentWithDecryptedSmtpPass()['smtp_pass'] ?? '';
        }

        $toEmail = $data['to_email'] ?? AuthHelper::email();

        try {
            (new SmtpEmailSender([
                'smtp_host'       => $data['smtp_host'],
                'smtp_port'       => (int) $data['smtp_port'],
                'smtp_user'       => $data['smtp_user'] ?? '',
                'smtp_pass'       => $smtpPass,
                'smtp_crypto'     => $data['smtp_crypto'] ?? 'tls',
                'smtp_from_email' => $data['smtp_from_email'] ?? null,
                'smtp_from_name'  => $data['smtp_from_name'] ?? null,
            ]))->send(
                $toEmail,
                'Test SMTP - NetHub',
                'Ini email percobaan dari pengaturan SMTP NetHub. Kalau Anda menerima ini, konfigurasi SMTP Anda sudah benar.'
            );
        } catch (Throwable $e) {
            return $this->fail('Gagal mengirim: ' . $e->getMessage(), 422);
        }

        return $this->ok(null, "Email percobaan berhasil dikirim ke {$toEmail}.");
    }

    private function withoutSecrets(array $settings): array
    {
        $settings['smtp_configured'] = ! empty($settings['smtp_pass']);
        unset($settings['smtp_pass']);

        return $settings;
    }
}
