<?php

namespace App\Controllers\Api\Customer;

use App\Controllers\Api\BaseApiController;
use App\Helpers\AuthHelper;
use App\Helpers\TenantHelper;
use App\Models\TenantModel;
use App\Repositories\CustomerRepository;
use App\Repositories\InvoiceRepository;
use App\Services\InvoicePdfService;
use App\Services\PaymentService;
use RuntimeException;

class InvoiceController extends BaseApiController
{
    private const MAX_PROOF_SIZE_KB = 5120; // 5MB, per the spec's upload limit

    public function index()
    {
        $customer = $this->ownCustomerOrFail();
        if ($customer instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $customer;
        }

        [$page, $perPage] = $this->paginationParams();
        $filters = array_filter(['status' => $this->request->getGet('status')]);

        return $this->paginated((new InvoiceRepository())->paginateForCustomer($customer['id'], $filters, $page, $perPage));
    }

    public function show($id = null)
    {
        $invoice = $this->ownInvoiceOrFail((int) $id);
        if ($invoice instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $invoice;
        }

        return $this->ok($invoice);
    }

    public function pdf($id = null)
    {
        $invoice = $this->ownInvoiceOrFail((int) $id);
        if ($invoice instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $invoice;
        }

        $pdf = (new InvoicePdfService())->generate($invoice);

        return $this->response->download($invoice['invoice_number'] . '.pdf', $pdf);
    }

    public function qris($id = null)
    {
        $invoice = $this->ownInvoiceOrFail((int) $id);
        if ($invoice instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $invoice;
        }

        try {
            return $this->ok((new PaymentService())->generateQrisForInvoice($invoice));
        } catch (RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }

    /**
     * multipart/form-data: payment_method=qris|transfer|gateway,
     * proof=<file> (required for transfer)
     */
    public function pay($id = null)
    {
        $invoice = $this->ownInvoiceOrFail((int) $id);
        if ($invoice instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $invoice;
        }

        $method = $this->request->getPost('payment_method');
        if (! in_array($method, ['qris', 'transfer', 'gateway'], true)) {
            return $this->fail('payment_method harus qris, transfer, atau gateway.', 422);
        }

        if ($method === 'gateway') {
            return $this->payViaGateway($invoice);
        }

        $proofPath = null;

        if ($method === 'transfer') {
            $file = $this->request->getFile('proof');

            if (! $file || ! $file->isValid()) {
                return $this->fail('Bukti transfer wajib diunggah.', 422);
            }

            if (! in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true)) {
                return $this->fail('Format bukti transfer harus JPG, PNG, atau WEBP.', 422);
            }

            if ($file->getSize() > self::MAX_PROOF_SIZE_KB * 1024) {
                return $this->fail('Ukuran bukti transfer maksimal 5MB.', 422);
            }

            $newName = $file->getRandomName();
            $file->move(WRITEPATH . 'uploads/payment_proofs', $newName);
            $proofPath = 'payment_proofs/' . $newName;
        }

        $customer = (new CustomerRepository())->findByUserId(AuthHelper::id());

        try {
            $payment = (new PaymentService())->submitPayment($customer, $invoice, $method, $proofPath);

            return $this->created($payment, 'Pembayaran berhasil diajukan, menunggu verifikasi.');
        } catch (RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }

    private function payViaGateway(array $invoice)
    {
        $customer = (new CustomerRepository())->findByUserId(AuthHelper::id());
        $tenant   = (new TenantModel())->find(TenantHelper::requireTenantId());

        try {
            $result = (new PaymentService())->initiateGatewayPayment($customer, $invoice, $tenant);

            return $this->created($result, 'Transaksi berhasil dibuat, lanjutkan pembayaran di halaman gateway.');
        } catch (RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }

    /**
     * @return array|\CodeIgniter\HTTP\ResponseInterface
     */
    private function ownCustomerOrFail()
    {
        $customer = (new CustomerRepository())->findByUserId(AuthHelper::id());

        return $customer ?? $this->notFound('Data pelanggan tidak ditemukan untuk akun ini.');
    }

    /**
     * @return array|\CodeIgniter\HTTP\ResponseInterface
     */
    private function ownInvoiceOrFail(int $invoiceId)
    {
        $customer = $this->ownCustomerOrFail();
        if ($customer instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $customer;
        }

        $invoice = (new InvoiceRepository())->find($invoiceId);

        // Belongs to this tenant (repository already enforces that) AND to
        // THIS customer specifically — a pelanggan must never see or pay
        // another customer's invoice even within the same tenant.
        if (! $invoice || (int) $invoice['customer_id'] !== (int) $customer['id']) {
            return $this->notFound('Tagihan tidak ditemukan.');
        }

        return $invoice;
    }
}
