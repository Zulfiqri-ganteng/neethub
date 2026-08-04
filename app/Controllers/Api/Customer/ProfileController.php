<?php

namespace App\Controllers\Api\Customer;

use App\Controllers\Api\BaseApiController;
use App\Helpers\AuthHelper;
use App\Models\UserModel;
use App\Repositories\CustomerRepository;

class ProfileController extends BaseApiController
{
    public function show()
    {
        $user = (new UserModel())->find(AuthHelper::id());
        unset($user['password']);

        $customer = (new CustomerRepository())->findByUserId(AuthHelper::id());

        return $this->ok(['user' => $user, 'customer' => $customer]);
    }

    public function update()
    {
        $rules = [
            'name'     => 'permit_empty|min_length[2]|max_length[150]',
            'phone'    => 'permit_empty|max_length[20]',
            'password' => 'permit_empty|min_length[8]',
            'address'  => 'permit_empty',
            'whatsapp' => 'permit_empty|max_length[20]',
        ];

        if (! $this->validate($rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $data = $this->request->getJSON(true) ?? $this->request->getRawInput();

        $userModel = new UserModel();
        $userData  = array_intersect_key($data, array_flip(['name', 'phone', 'password']));
        if ($userData !== []) {
            $userModel->update(AuthHelper::id(), $userData);
        }

        $customerData = array_intersect_key($data, array_flip(['address', 'whatsapp']));
        $customer     = (new CustomerRepository())->findByUserId(AuthHelper::id());
        if ($customer && $customerData !== []) {
            (new CustomerRepository())->update((int) $customer['id'], $customerData);
        }

        $user = $userModel->find(AuthHelper::id());
        unset($user['password']);

        return $this->ok(['user' => $user, 'customer' => (new CustomerRepository())->findByUserId(AuthHelper::id())], 'Profil berhasil diperbarui.');
    }
}
