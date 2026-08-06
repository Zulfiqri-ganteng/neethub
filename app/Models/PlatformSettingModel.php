<?php

namespace App\Models;

use App\Helpers\CryptoHelper;
use CodeIgniter\Model;

/**
 * Always exactly one row (id=1, seeded by its migration). Super Admin
 * pricing/grace-period/SMTP config — see CreatePlatformSettings +
 * AddSmtpToPlatformSettings migrations.
 */
class PlatformSettingModel extends Model
{
    protected $table            = 'platform_settings';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';

    protected $allowedFields = [
        'monthly_fee', 'trial_days', 'grace_days',
        'bank_name', 'bank_account_number', 'bank_account_name',
        'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_crypto', 'smtp_from_email', 'smtp_from_name',
        'google_client_id',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'monthly_fee' => 'required|decimal',
        'trial_days'  => 'required|integer|greater_than[0]',
        'grace_days'  => 'required|integer|greater_than_equal_to[0]',
    ];

    protected $beforeInsert = ['encryptSecrets'];
    protected $beforeUpdate = ['encryptSecrets'];

    /**
     * Same "encrypted at rest" treatment as tenant whatsapp_token/
     * payment_gateway_config (TenantModel::encryptSecrets).
     */
    protected function encryptSecrets(array $data): array
    {
        if (isset($data['data']['smtp_pass']) && is_string($data['data']['smtp_pass'])) {
            $data['data']['smtp_pass'] = CryptoHelper::encrypt($data['data']['smtp_pass']);
        }

        return $data;
    }

    public function current(): array
    {
        return $this->find(1) ?? [
            'id' => 1, 'monthly_fee' => 100000, 'trial_days' => 30, 'grace_days' => 3,
        ];
    }

    /**
     * Same row as current(), but with smtp_pass decrypted — for internal use
     * by the mail-sending code, never returned directly from an API
     * response (see PlatformSettingController::withoutSecrets).
     */
    public function currentWithDecryptedSmtpPass(): array
    {
        $settings = $this->current();
        if (! empty($settings['smtp_pass'])) {
            $settings['smtp_pass'] = CryptoHelper::decrypt($settings['smtp_pass']);
        }

        return $settings;
    }
}
