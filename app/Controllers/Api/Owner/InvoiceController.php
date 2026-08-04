<?php

namespace App\Controllers\Api\Owner;

use App\Controllers\Api\BaseApiController;
use App\Repositories\InvoiceRepository;
use App\Services\InvoicePdfService;
use App\Services\InvoiceService;
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
