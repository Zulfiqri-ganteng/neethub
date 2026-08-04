<?php

namespace App\Controllers\Api\Owner;

use App\Controllers\Api\BaseApiController;
use App\Helpers\TenantHelper;
use App\Models\TenantModel;
use App\Services\PdfService;
use App\Services\ReportService;

class ReportController extends BaseApiController
{
    private ReportService $reports;

    public function __construct()
    {
        $this->reports = new ReportService();
    }

    public function revenue()
    {
        [$from, $to, $group] = $this->revenueParams();

        return $this->ok($this->reports->revenue($from, $to, $group));
    }

    public function customers()
    {
        return $this->ok($this->reports->customers());
    }

    public function payments()
    {
        [$from, $to] = $this->dateRangeParams();

        $filters = array_filter([
            'method' => $this->request->getGet('method'),
            'status' => $this->request->getGet('status'),
        ]);

        return $this->ok($this->reports->payments($from, $to, $filters));
    }

    /**
     * GET /owner/reports/export?type=revenue|customers|payments&format=csv|pdf&...
     * Same query params as the matching read endpoint above.
     */
    public function export()
    {
        $type   = $this->request->getGet('type');
        $format = $this->request->getGet('format');

        if (! in_array($type, ['revenue', 'customers', 'payments'], true)) {
            return $this->fail('type harus revenue, customers, atau payments.', 422);
        }

        if (! in_array($format, ['csv', 'pdf'], true)) {
            return $this->fail('format harus csv atau pdf.', 422);
        }

        [$rows, $headers, $title] = $this->buildExportData($type);

        return $format === 'csv'
            ? $this->exportCsv($type, $headers, $rows)
            : $this->exportPdf($type, $title, $headers, $rows);
    }

    /**
     * @return array{0: array, 1: array, 2: string} [rows, headers, title]
     */
    private function buildExportData(string $type): array
    {
        if ($type === 'revenue') {
            [$from, $to, $group] = $this->revenueParams();
            $data = $this->reports->revenue($from, $to, $group);
            $rows = array_map(static fn ($r) => [$r['period'], (int) $r['count'], (float) $r['total']], $data['rows']);

            return [$rows, ['Periode', 'Jumlah Invoice', 'Total (Rp)'], "Laporan Pendapatan ({$from} s/d {$to})"];
        }

        if ($type === 'customers') {
            $data = $this->reports->customers();
            $rows = array_map(static fn ($c) => [
                $c['customer_code'], $c['name'], $c['phone'] ?? '-', $c['package_name'] ?? '-',
                $c['status'], $c['installation_date'] ?? '-',
            ], $data['items']);

            return [$rows, ['Kode', 'Nama', 'Telepon', 'Paket', 'Status', 'Tgl Berlangganan'], 'Laporan Pelanggan'];
        }

        [$from, $to] = $this->dateRangeParams();
        $filters = array_filter([
            'method' => $this->request->getGet('method'),
            'status' => $this->request->getGet('status'),
        ]);
        $data = $this->reports->payments($from, $to, $filters);
        $rows = array_map(static fn ($p) => [
            $p['invoice_number'] ?? '-', $p['customer_name'] ?? '-', $p['payment_method'],
            $p['status'], (float) $p['amount'], $p['created_at'],
        ], $data['rows']);

        return [$rows, ['No. Invoice', 'Pelanggan', 'Metode', 'Status', 'Jumlah (Rp)', 'Tanggal'], "Laporan Pembayaran ({$from} s/d {$to})"];
    }

    private function exportCsv(string $type, array $headers, array $rows)
    {
        $buffer = fopen('php://temp', 'w+');
        fputcsv($buffer, $headers);
        foreach ($rows as $row) {
            fputcsv($buffer, $row);
        }
        rewind($buffer);
        $csv = stream_get_contents($buffer);
        fclose($buffer);

        return $this->response->download('laporan_' . $type . '_' . date('Ymd_His') . '.csv', $csv);
    }

    private function exportPdf(string $type, string $title, array $headers, array $rows)
    {
        $tenant = (new TenantModel())->find(TenantHelper::requireTenantId());

        $html = view('pdf/report', [
            'title'      => $title,
            'headers'    => $headers,
            'rows'       => $rows,
            'tenantName' => $tenant['name'] ?? 'NetHub',
        ]);
        $pdf = (new PdfService())->render($html);

        return $this->response->download('laporan_' . $type . '_' . date('Ymd_His') . '.pdf', $pdf);
    }

    /**
     * @return array{0: string, 1: string, 2: string} [from, to, group]
     */
    private function revenueParams(): array
    {
        [$from, $to] = $this->dateRangeParams();
        $group = $this->request->getGet('group') === 'monthly' ? 'monthly' : 'daily';

        return [$from, $to, $group];
    }

    /**
     * Defaults to the current month when no range is given, so a request
     * with no query params still returns a sensibly bounded result instead
     * of scanning every row the tenant has ever had.
     *
     * @return array{0: string, 1: string} [from, to]
     */
    private function dateRangeParams(): array
    {
        $from = $this->request->getGet('from') ?: date('Y-m-01');
        $to   = $this->request->getGet('to') ?: date('Y-m-t');

        return [$from, $to];
    }
}
