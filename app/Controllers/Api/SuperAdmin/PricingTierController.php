<?php

namespace App\Controllers\Api\SuperAdmin;

use App\Controllers\Api\BaseApiController;
use App\Models\PlatformPricingTierModel;

/**
 * CRUD for the customer-count-based pricing tiers a platform invoice's
 * amount is resolved against (see PlatformBillingService::generateForTenant
 * and PlatformPricingTierModel's docblock). No ActivityModel logging here —
 * matches PlatformSettingController's own convention of not logging
 * platform-level config changes (there's no tenant_id to attribute this to,
 * unlike every other logged action in the app).
 */
class PricingTierController extends BaseApiController
{
    public function index()
    {
        return $this->ok((new PlatformPricingTierModel())->allSorted());
    }

    public function create()
    {
        $rules = [
            'min_customers' => 'required|integer|greater_than_equal_to[0]',
            'max_customers' => 'permit_empty|integer|greater_than_equal_to[0]',
            'monthly_fee'   => 'required|decimal|greater_than[0]',
        ];

        if (! $this->validate($rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $data = $this->request->getJSON(true) ?? $this->request->getPost();
        $min  = (int) $data['min_customers'];
        $max  = isset($data['max_customers']) && $data['max_customers'] !== '' ? (int) $data['max_customers'] : null;

        if ($max !== null && $max < $min) {
            return $this->fail('Batas maksimal harus lebih besar dari batas minimal.', 422);
        }

        $model = new PlatformPricingTierModel();
        if ($model->overlaps($min, $max)) {
            return $this->fail('Rentang tier ini tumpang tindih dengan tier yang sudah ada.', 422);
        }

        $id = $model->insert(['min_customers' => $min, 'max_customers' => $max, 'monthly_fee' => $data['monthly_fee']]);

        return $this->created($model->find($id), 'Tier harga berhasil dibuat.');
    }

    public function update($id = null)
    {
        $model = new PlatformPricingTierModel();
        $tier  = $model->find((int) $id);
        if (! $tier) {
            return $this->notFound('Tier tidak ditemukan.');
        }

        $rules = [
            'min_customers' => 'permit_empty|integer|greater_than_equal_to[0]',
            'max_customers' => 'permit_empty|integer|greater_than_equal_to[0]',
            'monthly_fee'   => 'permit_empty|decimal|greater_than[0]',
        ];

        if (! $this->validate($rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $data = $this->request->getJSON(true) ?? $this->request->getRawInput();
        $min  = array_key_exists('min_customers', $data) ? (int) $data['min_customers'] : (int) $tier['min_customers'];
        $max  = array_key_exists('max_customers', $data)
            ? ($data['max_customers'] === '' || $data['max_customers'] === null ? null : (int) $data['max_customers'])
            : ($tier['max_customers'] !== null ? (int) $tier['max_customers'] : null);

        if ($max !== null && $max < $min) {
            return $this->fail('Batas maksimal harus lebih besar dari batas minimal.', 422);
        }

        if ($model->overlaps($min, $max, (int) $id)) {
            return $this->fail('Rentang tier ini tumpang tindih dengan tier yang sudah ada.', 422);
        }

        $update = ['min_customers' => $min, 'max_customers' => $max];
        if (isset($data['monthly_fee'])) {
            $update['monthly_fee'] = $data['monthly_fee'];
        }

        $model->update((int) $id, $update);

        return $this->ok($model->find((int) $id), 'Tier harga berhasil diperbarui.');
    }

    public function delete($id = null)
    {
        $model = new PlatformPricingTierModel();
        if (! $model->find((int) $id)) {
            return $this->notFound('Tier tidak ditemukan.');
        }

        $model->delete((int) $id);

        return $this->ok(null, 'Tier harga berhasil dihapus.');
    }
}
