<?php
$statusLabel = ['unpaid' => 'Belum Dibayar', 'paid' => 'Lunas', 'overdue' => 'Terlambat'][$invoice['status']] ?? $invoice['status'];
$statusColor = ['unpaid' => '#6c757d', 'paid' => '#198754', 'overdue' => '#dc3545'][$invoice['status']] ?? '#6c757d';
$rp = static fn ($n) => 'Rp' . number_format((float) $n, 0, ',', '.');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #222; }
    .header { width: 100%; margin-bottom: 20px; }
    .header td { vertical-align: top; }
    .tenant-name { font-size: 18px; font-weight: bold; }
    .muted { color: #777; font-size: 11px; }
    .invoice-title { font-size: 22px; font-weight: bold; text-align: right; color: #444; }
    .status-badge { display: inline-block; padding: 3px 10px; border-radius: 4px; color: #fff; font-size: 11px; background: <?= $statusColor ?>; }
    table.details { width: 100%; border-collapse: collapse; margin-top: 20px; }
    table.details th, table.details td { border: 1px solid #ddd; padding: 6px 10px; text-align: left; font-size: 12px; }
    table.details th { background: #f5f5f5; width: 35%; }
    table.amounts { width: 100%; margin-top: 20px; border-collapse: collapse; }
    table.amounts td { padding: 5px 10px; font-size: 12px; }
    table.amounts .total-row td { border-top: 2px solid #333; font-weight: bold; font-size: 14px; }
    .footer { margin-top: 30px; font-size: 10px; color: #999; text-align: center; }
</style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="width: 60%;">
                <?php if ($logoDataUri): ?><img src="<?= $logoDataUri ?>" style="max-height: 50px; margin-bottom: 8px;"><br><?php endif; ?>
                <div class="tenant-name"><?= esc($tenant['name'] ?? 'NetHub') ?></div>
                <div class="muted">
                    <?= esc($tenant['address'] ?? '') ?><br>
                    <?= esc($tenant['whatsapp'] ?? '') ?> <?= $tenant['email'] ? '&middot; ' . esc($tenant['email']) : '' ?>
                </div>
            </td>
            <td style="width: 40%;">
                <div class="invoice-title">INVOICE</div>
                <div class="muted" style="text-align: right;"><?= esc($invoice['invoice_number']) ?></div>
                <div style="text-align: right; margin-top: 8px;"><span class="status-badge"><?= esc($statusLabel) ?></span></div>
            </td>
        </tr>
    </table>

    <table class="details">
        <tr><th>Pelanggan</th><td><?= esc($customer['name'] ?? '-') ?> (<?= esc($customer['customer_code'] ?? '-') ?>)</td></tr>
        <tr><th>Alamat</th><td><?= esc($customer['address'] ?? '-') ?></td></tr>
        <tr><th>Paket</th><td><?= esc($package['name'] ?? '-') ?></td></tr>
        <tr><th>Periode</th><td><?= esc($invoice['period_start']) ?> s/d <?= esc($invoice['period_end']) ?></td></tr>
        <tr><th>Jatuh Tempo</th><td><?= esc($invoice['due_date']) ?></td></tr>
        <?php if ($invoice['status'] === 'paid' && $invoice['paid_at']): ?>
        <tr><th>Dibayar Pada</th><td><?= esc($invoice['paid_at']) ?></td></tr>
        <?php endif; ?>
    </table>

    <table class="amounts">
        <tr><td>Biaya Paket</td><td style="text-align:right;"><?= $rp($invoice['amount']) ?></td></tr>
        <?php if ((float) $invoice['discount'] > 0): ?>
        <tr><td>Diskon</td><td style="text-align:right;">-<?= $rp($invoice['discount']) ?></td></tr>
        <?php endif; ?>
        <?php if ((float) $invoice['tax'] > 0): ?>
        <tr><td>Pajak</td><td style="text-align:right;"><?= $rp($invoice['tax']) ?></td></tr>
        <?php endif; ?>
        <tr class="total-row"><td>Total</td><td style="text-align:right;"><?= $rp($invoice['total_amount']) ?></td></tr>
    </table>

    <?php if (! empty($invoice['notes'])): ?>
    <div class="muted" style="margin-top: 15px;">Catatan: <?= esc($invoice['notes']) ?></div>
    <?php endif; ?>

    <div class="footer">Dokumen ini dibuat otomatis oleh NetHub pada <?= date('d/m/Y H:i') ?>.</div>
</body>
</html>
