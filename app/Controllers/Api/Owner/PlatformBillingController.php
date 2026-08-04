<?php

namespace App\Controllers\Api\Owner;

use App\Controllers\Api\BaseApiController;
use App\Helpers\TenantHelper;
use App\Models\PlatformInvoiceModel;
use App\Models\PlatformSettingModel;
use App\Services\PlatformBillingService;
use RuntimeException;

/**
 * The tenant's own view of what THEY owe the platform (Stage 13) — separate
 * from InvoiceController, which is what their customers owe them.
 */
class PlatformBillingController extends BaseApiController
{
    public function index()
    {
        $tenantId = TenantHelper::requireTenantId();

        return $this->ok([
            'invoices' => (new PlatformInvoiceModel())->forTenant($tenantId),
            'bank'     => array_intersect_key(
                (new PlatformSettingModel())->current(),
                array_flip(['bank_name', 'bank_account_number', 'bank_account_name'])
            ),
        ]);
    }

    /**
     * Submits manual-transfer proof for one of the tenant's own unpaid
     * platform invoices. Always ends up `pending` — a Super Admin verifies
     * it (PlatformPaymentController::verify), same as a customer's transfer
     * proof is verified by the Owner.
     */
    public function pay($id = null)
    {
        $file = $this->request->getFile('proof');

        if (! $file || ! $file->isValid()) {
            return $this->fail('Bukti transfer wajib diunggah.', 422);
        }

        if (! in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return $this->fail('Format bukti transfer harus JPG, PNG, atau WEBP.', 422);
        }

        if ($file->getSize() > 5120 * 1024) {
            return $this->fail('Ukuran bukti transfer maksimal 5MB.', 422);
        }

        // Outside the public webroot, same reasoning/pattern as customer
        // transfer proofs (PaymentController::proof) — a bank receipt isn't
        // meant to be reachable by guessing a URL.
        $newName = $file->getRandomName();
        $file->move(WRITEPATH . 'uploads/platform_payment_proofs', $newName);
        $relativePath = 'platform_payment_proofs/' . $newName;

        try {
            $payment = (new PlatformBillingService())->submitManualPayment(TenantHelper::requireTenantId(), (int) $id, $relativePath);
        } catch (RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->created($payment, 'Bukti pembayaran berhasil diunggah, menunggu verifikasi.');
    }
}
