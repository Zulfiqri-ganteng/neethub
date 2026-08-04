<?php

namespace App\Controllers\Api\Owner;

use App\Controllers\Api\BaseApiController;
use App\Helpers\AuthHelper;
use App\Repositories\PaymentRepository;
use App\Services\PaymentService;
use RuntimeException;

class PaymentController extends BaseApiController
{
    private PaymentRepository $payments;
    private PaymentService $paymentService;

    public function __construct()
    {
        $this->payments       = new PaymentRepository();
        $this->paymentService = new PaymentService();
    }

    public function index()
    {
        [$page, $perPage] = $this->paginationParams();

        $filters = array_filter([
            'status'         => $this->request->getGet('status'),
            'payment_method' => $this->request->getGet('payment_method'),
            'customer_id'    => $this->request->getGet('customer_id'),
        ]);

        return $this->paginated($this->payments->paginate($filters, $page, $perPage));
    }

    public function show($id = null)
    {
        $payment = $this->payments->find((int) $id);

        if (! $payment) {
            return $this->notFound('Pembayaran tidak ditemukan.');
        }

        return $this->ok($payment);
    }

    /**
     * Streams the uploaded transfer-proof image. Proof files live under
     * `writable/uploads/` (outside the public webroot) specifically so
     * they can't be fetched by guessing a URL — this endpoint is the only
     * way to reach one, gated by the same tenant/permission filters as
     * every other owner route.
     */
    public function proof($id = null)
    {
        $payment = $this->payments->find((int) $id);

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
     * `approve` is validated manually rather than via a validation rule:
     * CI4's in_list[] rule compares against string literals, which doesn't
     * reliably match a real JSON boolean (`true`/`false`) vs a JSON string
     * (`"true"`) vs a form-encoded value — easier to just check it exists.
     */
    public function verify($id = null)
    {
        $data = $this->request->getJSON(true) ?? $this->request->getPost();

        if (! array_key_exists('approve', $data)) {
            return $this->validationError(['approve' => 'The approve field is required.']);
        }

        try {
            $result = $this->paymentService->verify(
                (int) $id,
                AuthHelper::id(),
                filter_var($data['approve'], FILTER_VALIDATE_BOOLEAN),
                $data['notes'] ?? null
            );

            return $this->ok($result, 'Pembayaran berhasil diproses.');
        } catch (RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }
}
