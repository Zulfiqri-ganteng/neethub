<?php

namespace App\Controllers\Api\Customer;

use App\Controllers\Api\BaseApiController;
use App\Helpers\AuthHelper;
use App\Repositories\CustomerRepository;
use App\Repositories\PaymentRepository;

class PaymentController extends BaseApiController
{
    public function index()
    {
        $customer = (new CustomerRepository())->findByUserId(AuthHelper::id());

        if (! $customer) {
            return $this->notFound('Data pelanggan tidak ditemukan untuk akun ini.');
        }

        [$page, $perPage] = $this->paginationParams();
        $filters = array_filter(['status' => $this->request->getGet('status')]);

        return $this->paginated((new PaymentRepository())->paginateForCustomer($customer['id'], $filters, $page, $perPage));
    }
}
