<?php

namespace App\Controllers\Api\Owner;

use App\Controllers\Api\BaseApiController;
use App\Helpers\TenantHelper;
use App\Models\OnlineUserModel;
use App\Repositories\CustomerRepository;
use App\Services\RouterService;
use RuntimeException;

class MonitoringController extends BaseApiController
{
    /**
     * Reads the pre-synced online_users table (kept fresh by the
     * `router:sync` command, roughly every 30s per the spec's caching
     * rule) rather than calling every router live on each request — a
     * dashboard-style "who's online" view shouldn't have its response time
     * depend on how many routers are slow/unreachable right now.
     */
    public function usersOnline()
    {
        $rows = (new OnlineUserModel())
            ->select('online_users.*, customers.name as customer_name, routers.name as router_name')
            ->join('customers', 'customers.id = online_users.customer_id', 'left')
            ->join('routers', 'routers.id = online_users.router_id', 'left')
            ->where('online_users.tenant_id', TenantHelper::requireTenantId())
            ->orderBy('online_users.last_seen_at', 'DESC')
            ->findAll();

        return $this->ok($rows);
    }

    /**
     * Live per-customer traffic snapshot — a single targeted router call,
     * unlike the tenant-wide online list above, so doing it live on demand
     * is fine.
     */
    public function traffic()
    {
        $customerId = $this->request->getGet('customer_id');
        if (! $customerId) {
            return $this->fail('Parameter customer_id wajib diisi.', 422);
        }

        $customer = (new CustomerRepository())->find((int) $customerId);
        if (! $customer) {
            return $this->notFound('Pelanggan tidak ditemukan.');
        }

        try {
            return $this->ok((new RouterService())->trafficForCustomer($customer));
        } catch (RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }
}
