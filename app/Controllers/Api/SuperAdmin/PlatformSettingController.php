<?php

namespace App\Controllers\Api\SuperAdmin;

use App\Controllers\Api\BaseApiController;
use App\Models\PlatformSettingModel;

class PlatformSettingController extends BaseApiController
{
    public function show()
    {
        return $this->ok((new PlatformSettingModel())->current());
    }

    public function update()
    {
        $rules = [
            'monthly_fee' => 'permit_empty|decimal|greater_than[0]',
            'trial_days'  => 'permit_empty|integer|greater_than[0]',
            'grace_days'  => 'permit_empty|integer|greater_than_equal_to[0]',
        ];

        if (! $this->validate($rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $data  = $this->request->getJSON(true) ?? $this->request->getRawInput();
        $model = new PlatformSettingModel();

        $update = array_intersect_key($data, array_flip([
            'monthly_fee', 'trial_days', 'grace_days', 'bank_name', 'bank_account_number', 'bank_account_name',
        ]));

        $model->update(1, $update);

        return $this->ok($model->current(), 'Pengaturan platform berhasil disimpan.');
    }
}
