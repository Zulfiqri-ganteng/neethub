<?php

namespace App\Controllers\Api\SuperAdmin;

use App\Controllers\Api\BaseApiController;
use App\Helpers\AuthHelper;
use App\Models\PlatformPaymentModel;
use App\Services\PlatformBillingService;
use RuntimeException;

class PlatformPaymentController extends BaseApiController
{
    public function pending()
    {
        return $this->ok((new PlatformPaymentModel())->pending());
    }

    /**
     * Same "outside the webroot, streamed through an authenticated
     * endpoint" pattern as Owner\PaymentController::proof.
     */
    public function proof($id = null)
    {
        $payment = (new PlatformPaymentModel())->find((int) $id);

        if (! $payment || empty($payment['proof_image'])) {
            return $this->notFound('Bukti pembayaran tidak ditemukan.');
        }

        $path = WRITEPATH . 'uploads/' . $payment['proof_image'];

        if (! is_file($path)) {
            return $this->notFound('File bukti pembayaran tidak ditemukan.');
        }

        return $this->response->download($path, null, true)->inline();
    }

    /**
     * Body: { "approve": true|false, "notes": "..." (optional) }
     *
     * `approve` validated manually, not via in_list[] — same reasoning as
     * Owner\PaymentController::verify (in_list[] doesn't reliably match a
     * real JSON boolean vs a JSON/form string).
     */
    public function verify($id = null)
    {
        $data = $this->request->getJSON(true) ?? $this->request->getPost();

        if (! array_key_exists('approve', $data)) {
            return $this->validationError(['approve' => 'The approve field is required.']);
        }

        $approve = filter_var($data['approve'], FILTER_VALIDATE_BOOLEAN);
        $notes   = trim((string) ($data['notes'] ?? ''));

        try {
            $result = (new PlatformBillingService())->verifyPayment((int) $id, AuthHelper::id(), $approve, $notes !== '' ? $notes : null);
        } catch (RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->ok($result, $approve ? 'Pembayaran platform diverifikasi.' : 'Pembayaran platform ditolak.');
    }
}
