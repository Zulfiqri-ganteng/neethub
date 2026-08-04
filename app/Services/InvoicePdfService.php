<?php

namespace App\Services;

use App\Helpers\TenantHelper;
use App\Models\CustomerModel;
use App\Models\PackageModel;
use App\Models\TenantModel;

/**
 * Renders a single invoice to PDF. Customer/package are looked up directly
 * by ID (not through a tenant-scoped repository) because the invoice itself
 * was already confirmed to belong to the current tenant by the caller
 * (InvoiceRepository::find()) before this runs — same trust boundary
 * CustomerController already relies on when it looks up a package by ID
 * after a tenant-scoped customer lookup.
 */
class InvoicePdfService
{
    public function generate(array $invoice): string
    {
        $tenant   = (new TenantModel())->find(TenantHelper::requireTenantId());
        $customer = (new CustomerModel())->find((int) $invoice['customer_id']);
        $package  = $invoice['package_id'] ? (new PackageModel())->find((int) $invoice['package_id']) : null;

        $logoDataUri = null;
        if (! empty($tenant['logo'])) {
            $path = FCPATH . $tenant['logo'];
            if (is_file($path)) {
                $mime        = mime_content_type($path) ?: 'image/png';
                $logoDataUri = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
            }
        }

        $html = view('pdf/invoice', [
            'invoice'     => $invoice,
            'tenant'      => $tenant,
            'customer'    => $customer,
            'package'     => $package,
            'logoDataUri' => $logoDataUri,
        ]);

        return (new PdfService())->render($html);
    }
}
