<?php

namespace App\Controllers\Api\Owner;

use App\Controllers\Api\BaseApiController;
use App\Helpers\TenantHelper;
use App\Models\ActivityModel;
use App\Models\CustomerModel;
use App\Models\InvoiceModel;
use App\Models\NotificationModel;

class BroadcastController extends BaseApiController
{
    /**
     * Body: { "target": "all"|"active"|"overdue"|"custom", "message": "...",
     *         "customer_ids": [...] (required when target=custom) }
     */
    public function whatsapp()
    {
        $rules = [
            'target'  => 'required|in_list[all,active,overdue,custom]',
            'message' => 'required|min_length[3]',
        ];

        if (! $this->validate($rules)) {
            return $this->validationError($this->validator->getErrors());
        }

        $data          = $this->request->getJSON(true) ?? $this->request->getPost();
        $customerIds   = $this->resolveTargetCustomerIds($data['target'], $data['customer_ids'] ?? []);
        $notifications = new NotificationModel();

        foreach ($customerIds as $customerId) {
            $notifications->queue('whatsapp', 'broadcast', 'customer', $customerId, [
                'message' => $data['message'],
            ], TenantHelper::id());
        }

        (new ActivityModel())->log(
            'broadcast_sent',
            TenantHelper::id(),
            null,
            'Broadcast WhatsApp ke ' . count($customerIds) . " pelanggan (target: {$data['target']})"
        );

        return $this->ok(['queued' => count($customerIds)], count($customerIds) . ' pesan broadcast berhasil diantrikan.');
    }

    /**
     * @return int[]
     */
    private function resolveTargetCustomerIds(string $target, array $customIds): array
    {
        $tenantId      = TenantHelper::requireTenantId();
        $customerModel = new CustomerModel();

        if ($target === 'custom') {
            return $customerModel->where('tenant_id', $tenantId)->whereIn('id', $customIds)->findColumn('id') ?? [];
        }

        if ($target === 'active') {
            return $customerModel->where('tenant_id', $tenantId)->where('status', 'active')->findColumn('id') ?? [];
        }

        if ($target === 'overdue') {
            $ids = (new InvoiceModel())
                ->where('tenant_id', $tenantId)
                ->where('status', 'overdue')
                ->findColumn('customer_id') ?? [];

            return array_values(array_unique($ids));
        }

        return $customerModel->where('tenant_id', $tenantId)->findColumn('id') ?? [];
    }
}
