<?php

namespace App\Controllers\Api\Owner;

use App\Controllers\Api\BaseApiController;
use App\Helpers\TenantHelper;
use App\Models\TenantModel;

class SettingsController extends BaseApiController
{
    public function show()
    {
        return $this->ok($this->withoutSecrets((new TenantModel())->find(TenantHelper::requireTenantId())));
    }

    public function update()
    {
        $rules = [
            'name'            => 'permit_empty|min_length[3]|max_length[150]',
            'email'           => 'permit_empty|valid_email',
            'whatsapp'        => 'permit_empty|max_length[20]',
            'payment_gateway' => 'permit_empty|in_list[midtrans,xendit]',
        ];

        if (! $this->validate($rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $data = $this->request->getJSON(true) ?? $this->request->getRawInput();
        $update = array_intersect_key($data, array_flip([
            'name', 'address', 'whatsapp', 'email',
            'bank_name', 'bank_account_number', 'bank_account_name', 'qris_static_code',
            'payment_gateway', 'whatsapp_token',
        ]));

        // payment_gateway_config arrives as a JSON object (its shape
        // depends on which gateway) — stringify before the model encrypts
        // it as a single TEXT column.
        if (isset($data['payment_gateway_config']) && is_array($data['payment_gateway_config'])) {
            $update['payment_gateway_config'] = json_encode($data['payment_gateway_config']);
        }

        $tenantModel = new TenantModel();
        $tenantModel->update(TenantHelper::requireTenantId(), $update);

        return $this->ok(
            $this->withoutSecrets($tenantModel->find(TenantHelper::requireTenantId())),
            'Pengaturan berhasil disimpan.'
        );
    }

    /**
     * Max 2MB, image types only — same style of check as the payment-proof
     * upload (Stage 5), just a smaller cap since a logo is a small brand
     * asset, not a scanned document.
     */
    public function uploadLogo()
    {
        $file = $this->request->getFile('logo');

        if (! $file || ! $file->isValid()) {
            return $this->fail('File logo wajib diunggah.', 422);
        }

        if (! in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'], true)) {
            return $this->fail('Format logo harus JPG, PNG, WEBP, atau SVG.', 422);
        }

        if ($file->getSize() > 2048 * 1024) {
            return $this->fail('Ukuran logo maksimal 2MB.', 422);
        }

        $tenantModel = new TenantModel();
        $tenantId    = TenantHelper::requireTenantId();
        $tenant      = $tenantModel->find($tenantId);

        // Remove the previous file so changing branding doesn't quietly
        // accumulate orphaned uploads under public/ over time.
        $this->deleteLogoFile($tenant['logo'] ?? null);

        $newName = $file->getRandomName();
        $file->move(FCPATH . 'uploads/logos', $newName);
        $relativePath = 'uploads/logos/' . $newName;

        $tenantModel->update($tenantId, ['logo' => $relativePath]);

        return $this->ok(['logo' => $relativePath], 'Logo berhasil diunggah.');
    }

    public function deleteLogo()
    {
        $tenantModel = new TenantModel();
        $tenantId    = TenantHelper::requireTenantId();
        $tenant      = $tenantModel->find($tenantId);

        $this->deleteLogoFile($tenant['logo'] ?? null);
        $tenantModel->update($tenantId, ['logo' => null]);

        return $this->ok(null, 'Logo berhasil dihapus.');
    }

    private function deleteLogoFile(?string $relativePath): void
    {
        if (empty($relativePath)) {
            return;
        }

        $path = FCPATH . $relativePath;
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function withoutSecrets(array $tenant): array
    {
        $tenant['payment_gateway_configured'] = ! empty($tenant['payment_gateway_config']);
        $tenant['whatsapp_configured']        = ! empty($tenant['whatsapp_token']);
        unset($tenant['payment_gateway_config'], $tenant['whatsapp_token']);

        return $tenant;
    }
}
