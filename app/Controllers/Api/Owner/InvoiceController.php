<?php

namespace App\Controllers\Api\Owner;

use App\Controllers\Api\BaseApiController;
use App\Helpers\AuthHelper;
use App\Models\TenantModel;
use App\Repositories\InvoiceRepository;
use App\Services\InvoicePdfService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Helpers\TenantHelper;
use RuntimeException;

class InvoiceController extends BaseApiController
{
    private InvoiceRepository $invoices;
    private InvoiceService $invoiceService;

    public function __construct()
    {
        $this->invoices       = new InvoiceRepository();
        $this->invoiceService = new InvoiceService();
    }

    public function index()
    {
        [$page, $perPage] = $this->paginationParams();

        $filters = array_filter([
            'status'      => $this->request->getGet('status'),
            'customer_id' => $this->request->getGet('customer_id'),
        ]);

        return $this->paginated($this->invoices->paginate($filters, $page, $perPage));
    }

    public function show($id = null)
    {
        $invoice = $this->invoices->find((int) $id);

        if (! $invoice) {
            return $this->notFound('Tagihan tidak ditemukan.');
        }

        return $this->ok($invoice);
    }

    public function pdf($id = null)
    {
        $invoice = $this->invoices->find((int) $id);

        if (! $invoice) {
            return $this->notFound('Tagihan tidak ditemukan.');
        }

        $pdf = (new InvoicePdfService())->generate($invoice);

        return $this->response->download($invoice['invoice_number'] . '.pdf', $pdf);
    }

    /**
     * Lets the owner/admin/kasir generate a per-invoice dynamic QRIS + view
     * the tenant's bank transfer info themselves, so a customer never has to
     * log in to be handed a QR/bank number — matches how payment
     * confirmation is already 100% manual on the owner side.
     */
    public function qris($id = null)
    {
        $invoice = $this->invoices->find((int) $id);

        if (! $invoice) {
            return $this->notFound('Tagihan tidak ditemukan.');
        }

        try {
            $qris = (new PaymentService())->generateQrisForInvoice($invoice);
        } catch (RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        $tenant = (new TenantModel())->find(TenantHelper::requireTenantId());

        return $this->ok($qris + [
            'bank_name'           => $tenant['bank_name'] ?? null,
            'bank_account_number' => $tenant['bank_account_number'] ?? null,
            'bank_account_name'   => $tenant['bank_account_name'] ?? null,
        ]);
    }

    /**
     * Lets the owner/admin/kasir record a payment they personally collected
     * (cash in hand, or a transfer that came in outside the customer
     * portal) and mark the invoice paid immediately — no customer
     * submission required at all. This is the "notebook style" manual
     * workflow: the staff member is both the one who received the money and
     * the one confirming it, in one step.
     *
     * Body: { "method": "cash"|"transfer", "notes": "..." (optional) }
     */
    public function payManual($id = null)
    {
        $invoice = $this->invoices->find((int) $id);

        if (! $invoice) {
            return $this->notFound('Tagihan tidak ditemukan.');
        }

        $data   = $this->request->getJSON(true) ?? $this->request->getPost();
        $method = $data['method'] ?? 'cash';

        try {
            $result = (new PaymentService())->recordManual(
                $invoice,
                $method,
                $data['notes'] ?? null,
                AuthHelper::id()
            );

            return $this->ok($result, 'Pembayaran berhasil dicatat.');
        } catch (RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }

    /**
     * Body: { "customer_id": 5 } to generate for one customer, or
     * {} / omitted to generate for every eligible customer in the tenant.
     */
    public function generate()
    {
        $data       = $this->request->getJSON(true) ?? $this->request->getPost();
        $customerId = $data['customer_id'] ?? null;

        try {
            if ($customerId) {
                $invoice = $this->invoiceService->generateForCustomer((int) $customerId);

                return $this->created($invoice, 'Tagihan berhasil dibuat.');
            }

            $result = $this->invoiceService->generateDueForTenant();

            return $this->ok($result, count($result['generated']) . ' tagihan berhasil dibuat.');
        } catch (RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }
}
