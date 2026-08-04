<?php

namespace App\Controllers\Api\Customer;

use App\Controllers\Api\BaseApiController;
use App\Helpers\AuthHelper;
use App\Helpers\TenantHelper;
use App\Models\InvoiceModel;
use App\Models\PackageModel;
use App\Models\TenantModel;
use App\Repositories\CustomerRepository;

class DashboardController extends BaseApiController
{
    public function index()
    {
        $customer = (new CustomerRepository())->findByUserId(AuthHelper::id());

        if (! $customer) {
            return $this->notFound('Data pelanggan tidak ditemukan untuk akun ini.');
        }

        $package = $customer['package_id'] ? (new PackageModel())->find($customer['package_id']) : null;

        $nextDue = (new InvoiceModel())
            ->where('customer_id', $customer['id'])
            ->whereIn('status', ['unpaid', 'overdue'])
            ->orderBy('due_date', 'ASC')
            ->first();

        $tenant = (new TenantModel())->find(TenantHelper::requireTenantId());

        return $this->ok([
            'customer' => [
                'name'          => $customer['name'],
                'customer_code' => $customer['customer_code'],
                'status'        => $customer['status'],
            ],
            'package'  => $package,
            'next_due' => $nextDue,
            // Public-safe subset only — never QRIS/payment-gateway secrets.
            'tenant' => [
                'name'                => $tenant['name'],
                'whatsapp'            => $tenant['whatsapp'],
                'bank_name'           => $tenant['bank_name'],
                'bank_account_number' => $tenant['bank_account_number'],
                'bank_account_name'   => $tenant['bank_account_name'],
            ],
        ]);
    }
}
