<?= $this->extend('layouts/dashboard') ?>
<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="mb-4">
            <h1 class="fs-3 mb-1">Laporan</h1>
            <p class="mb-0 text-muted">Pendapatan, pelanggan, dan riwayat pembayaran</p>
        </div>
    </div>
</div>

<div id="pageError" class="alert alert-danger d-none"></div>

<ul class="nav nav-tabs mb-3" id="reportTabs">
    <li class="nav-item"><a class="nav-link active" href="#" data-tab="revenue" onclick="switchTab('revenue'); return false;">Pendapatan</a></li>
    <li class="nav-item"><a class="nav-link" href="#" data-tab="customers" onclick="switchTab('customers'); return false;">Pelanggan</a></li>
    <li class="nav-item"><a class="nav-link" href="#" data-tab="payments" onclick="switchTab('payments'); return false;">Pembayaran</a></li>
</ul>

<!-- Pendapatan -->
<div id="tab-revenue" class="report-tab">
    <div class="card p-3 mb-3">
        <div class="d-flex gap-2 flex-wrap align-items-end">
            <div>
                <label class="form-label small mb-1">Dari</label>
                <input type="date" id="revFrom" class="form-control form-control-sm">
            </div>
            <div>
                <label class="form-label small mb-1">Sampai</label>
                <input type="date" id="revTo" class="form-control form-control-sm">
            </div>
            <div>
                <label class="form-label small mb-1">Kelompokkan</label>
                <select id="revGroup" class="form-select form-select-sm">
                    <option value="daily">Harian</option>
                    <option value="monthly">Bulanan</option>
                </select>
            </div>
            <button class="btn btn-primary btn-sm" onclick="loadRevenue()">Terapkan</button>
            <div class="ms-auto d-flex gap-2">
                <button class="btn btn-outline-secondary btn-sm" onclick="exportReport('revenue','csv')"><i class="ti ti-file-spreadsheet"></i> CSV</button>
                <button class="btn btn-outline-secondary btn-sm" onclick="exportReport('revenue','pdf')"><i class="ti ti-file-type-pdf"></i> PDF</button>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-6"><div class="card p-3"><div class="text-muted small">Total Pendapatan</div><div class="fs-4 fw-bold" id="revTotalAmount">Rp0</div></div></div>
        <div class="col-md-6"><div class="card p-3"><div class="text-muted small">Jumlah Invoice Lunas</div><div class="fs-4 fw-bold" id="revTotalCount">0</div></div></div>
    </div>

    <div class="card table-responsive">
        <table class="table mb-0 table-hover">
            <thead class="table-light"><tr><th>Periode</th><th>Jumlah Invoice</th><th>Total</th></tr></thead>
            <tbody id="revenueTableBody"><tr><td colspan="3" class="text-center py-4 text-muted">Memuat...</td></tr></tbody>
        </table>
    </div>
</div>

<!-- Pelanggan -->
<div id="tab-customers" class="report-tab d-none">
    <div class="d-flex justify-content-end mb-3 gap-2">
        <button class="btn btn-outline-secondary btn-sm" onclick="exportReport('customers','csv')"><i class="ti ti-file-spreadsheet"></i> CSV</button>
        <button class="btn btn-outline-secondary btn-sm" onclick="exportReport('customers','pdf')"><i class="ti ti-file-type-pdf"></i> PDF</button>
    </div>

    <div class="row mb-3" id="custSummaryCards"></div>

    <div class="row">
        <div class="col-md-5">
            <div class="card table-responsive">
                <table class="table mb-0 table-hover">
                    <thead class="table-light"><tr><th>Paket</th><th>Jumlah Pelanggan</th></tr></thead>
                    <tbody id="byPackageBody"><tr><td colspan="2" class="text-center py-4 text-muted">Memuat...</td></tr></tbody>
                </table>
            </div>
        </div>
        <div class="col-md-7">
            <div class="card table-responsive" style="max-height: 420px; overflow-y: auto;">
                <table class="table mb-0 table-hover">
                    <thead class="table-light"><tr><th>Kode</th><th>Nama</th><th>Paket</th><th>Status</th></tr></thead>
                    <tbody id="customerListBody"><tr><td colspan="4" class="text-center py-4 text-muted">Memuat...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Pembayaran -->
<div id="tab-payments" class="report-tab d-none">
    <div class="card p-3 mb-3">
        <div class="d-flex gap-2 flex-wrap align-items-end">
            <div>
                <label class="form-label small mb-1">Dari</label>
                <input type="date" id="payFrom" class="form-control form-control-sm">
            </div>
            <div>
                <label class="form-label small mb-1">Sampai</label>
                <input type="date" id="payTo" class="form-control form-control-sm">
            </div>
            <div>
                <label class="form-label small mb-1">Metode</label>
                <select id="payMethod" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="qris">QRIS</option>
                    <option value="transfer">Transfer</option>
                    <option value="gateway">Gateway</option>
                    <option value="cash">Tunai</option>
                </select>
            </div>
            <div>
                <label class="form-label small mb-1">Status</label>
                <select id="payStatus" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="pending">Menunggu</option>
                    <option value="verified">Terverifikasi</option>
                    <option value="rejected">Ditolak</option>
                </select>
            </div>
            <button class="btn btn-primary btn-sm" onclick="loadPaymentsReport()">Terapkan</button>
            <div class="ms-auto d-flex gap-2">
                <button class="btn btn-outline-secondary btn-sm" onclick="exportReport('payments','csv')"><i class="ti ti-file-spreadsheet"></i> CSV</button>
                <button class="btn btn-outline-secondary btn-sm" onclick="exportReport('payments','pdf')"><i class="ti ti-file-type-pdf"></i> PDF</button>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-6"><div class="card p-3"><div class="text-muted small">Total Terverifikasi</div><div class="fs-4 fw-bold" id="payTotalAmount">Rp0</div></div></div>
        <div class="col-md-6"><div class="card p-3"><div class="text-muted small">Jumlah Transaksi</div><div class="fs-4 fw-bold" id="payTotalCount">0</div></div></div>
    </div>

    <div class="card table-responsive">
        <table class="table mb-0 table-hover">
            <thead class="table-light"><tr><th>No. Invoice</th><th>Pelanggan</th><th>Metode</th><th>Status</th><th>Jumlah</th><th>Tanggal</th></tr></thead>
            <tbody id="paymentsTableBody"><tr><td colspan="6" class="text-center py-4 text-muted">Memuat...</td></tr></tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
function showMessage(msg) {
    const el = document.getElementById('pageError');
    el.textContent = msg;
    el.classList.remove('d-none');
    setTimeout(() => el.classList.add('d-none'), 5000);
}

function formatRp(n) { return 'Rp' + Number(n || 0).toLocaleString('id-ID'); }

function monthRange() {
    const now = new Date();
    const first = new Date(now.getFullYear(), now.getMonth(), 1);
    const last = new Date(now.getFullYear(), now.getMonth() + 1, 0);
    const fmt = (d) => d.toISOString().slice(0, 10);
    return [fmt(first), fmt(last)];
}

function switchTab(tab) {
    document.querySelectorAll('.report-tab').forEach(el => el.classList.add('d-none'));
    document.getElementById('tab-' + tab).classList.remove('d-none');
    document.querySelectorAll('#reportTabs .nav-link').forEach(el => el.classList.toggle('active', el.dataset.tab === tab));
}

async function loadRevenue() {
    const from = document.getElementById('revFrom').value;
    const to = document.getElementById('revTo').value;
    const group = document.getElementById('revGroup').value;
    const tbody = document.getElementById('revenueTableBody');
    tbody.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span></td></tr>';

    try {
        const body = await nethubFetch(`/api/owner/reports/revenue?from=${from}&to=${to}&group=${group}`);
        if (!body.success) { showMessage(body.message); return; }
        const d = body.data;
        document.getElementById('revTotalAmount').textContent = formatRp(d.total_amount);
        document.getElementById('revTotalCount').textContent = d.total_count;
        tbody.innerHTML = d.rows.length === 0
            ? '<tr><td colspan="3" class="text-center py-4 text-muted">Tidak ada data.</td></tr>'
            : d.rows.map(r => `<tr><td>${r.period}</td><td>${r.count}</td><td>${formatRp(r.total)}</td></tr>`).join('');
    } catch (err) {
        if (err.message !== 'Unauthenticated') showMessage('Gagal memuat laporan pendapatan.');
    }
}

async function loadCustomersReport() {
    try {
        const body = await nethubFetch('/api/owner/reports/customers');
        if (!body.success) { showMessage(body.message); return; }
        const d = body.data;

        const cards = [
            ['Total', d.summary.total, 'primary'],
            ['Aktif', d.summary.active, 'success'],
            ['Pending', d.summary.pending, 'secondary'],
            ['Suspend', d.summary.suspended, 'warning'],
            ['Nonaktif', d.summary.disabled, 'danger'],
        ];
        document.getElementById('custSummaryCards').innerHTML = cards.map(([label, val, color]) => `
            <div class="col"><div class="card p-3 text-center">
                <div class="text-muted small">${label}</div>
                <div class="fs-4 fw-bold text-${color}">${val}</div>
            </div></div>
        `).join('');

        document.getElementById('byPackageBody').innerHTML = d.by_package.length === 0
            ? '<tr><td colspan="2" class="text-center py-4 text-muted">Tidak ada data.</td></tr>'
            : d.by_package.map(p => `<tr><td>${p.package_name || '- Belum ditentukan -'}</td><td>${p.count}</td></tr>`).join('');

        const statusBadge = (s) => ({
            active: '<span class="badge bg-success-subtle text-success">Aktif</span>',
            pending: '<span class="badge bg-secondary-subtle text-secondary">Pending</span>',
            suspended: '<span class="badge bg-warning-subtle text-warning">Suspend</span>',
            disabled: '<span class="badge bg-danger-subtle text-danger">Nonaktif</span>',
        }[s] || s);

        document.getElementById('customerListBody').innerHTML = d.items.length === 0
            ? '<tr><td colspan="4" class="text-center py-4 text-muted">Belum ada pelanggan.</td></tr>'
            : d.items.map(c => `<tr><td>${c.customer_code}</td><td>${c.name}</td><td>${c.package_name || '-'}</td><td>${statusBadge(c.status)}</td></tr>`).join('');
    } catch (err) {
        if (err.message !== 'Unauthenticated') showMessage('Gagal memuat laporan pelanggan.');
    }
}

async function loadPaymentsReport() {
    const from = document.getElementById('payFrom').value;
    const to = document.getElementById('payTo').value;
    const method = document.getElementById('payMethod').value;
    const status = document.getElementById('payStatus').value;
    const tbody = document.getElementById('paymentsTableBody');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span></td></tr>';

    const params = new URLSearchParams({ from, to });
    if (method) params.set('method', method);
    if (status) params.set('status', status);

    const methodLabel = (m) => ({ qris: 'QRIS', transfer: 'Transfer', gateway: 'Gateway', cash: 'Tunai' }[m] || m);
    const statusBadge = (s) => ({
        pending: '<span class="badge bg-secondary-subtle text-secondary">Menunggu</span>',
        verified: '<span class="badge bg-success-subtle text-success">Terverifikasi</span>',
        rejected: '<span class="badge bg-danger-subtle text-danger">Ditolak</span>',
    }[s] || s);

    try {
        const body = await nethubFetch('/api/owner/reports/payments?' + params.toString());
        if (!body.success) { showMessage(body.message); return; }
        const d = body.data;
        document.getElementById('payTotalAmount').textContent = formatRp(d.summary.total_amount);
        document.getElementById('payTotalCount').textContent = d.summary.count;
        tbody.innerHTML = d.rows.length === 0
            ? '<tr><td colspan="6" class="text-center py-4 text-muted">Tidak ada data.</td></tr>'
            : d.rows.map(p => `<tr>
                <td>${p.invoice_number || '-'}</td><td>${p.customer_name || '-'}</td>
                <td>${methodLabel(p.payment_method)}</td><td>${statusBadge(p.status)}</td>
                <td>${formatRp(p.amount)}</td><td>${p.created_at}</td>
            </tr>`).join('');
    } catch (err) {
        if (err.message !== 'Unauthenticated') showMessage('Gagal memuat laporan pembayaran.');
    }
}

async function exportReport(type, format) {
    let params = new URLSearchParams({ type, format });
    if (type === 'revenue') {
        params.set('from', document.getElementById('revFrom').value);
        params.set('to', document.getElementById('revTo').value);
        params.set('group', document.getElementById('revGroup').value);
    } else if (type === 'payments') {
        params.set('from', document.getElementById('payFrom').value);
        params.set('to', document.getElementById('payTo').value);
        if (document.getElementById('payMethod').value) params.set('method', document.getElementById('payMethod').value);
        if (document.getElementById('payStatus').value) params.set('status', document.getElementById('payStatus').value);
    }

    try {
        let res = await fetch('/api/owner/reports/export?' + params.toString(), {
            headers: { Authorization: 'Bearer ' + nethubToken },
        });
        if (res.status === 401 && await nethubRefreshToken()) {
            res = await fetch('/api/owner/reports/export?' + params.toString(), {
                headers: { Authorization: 'Bearer ' + nethubToken },
            });
        }
        if (!res.ok) { showMessage('Gagal mengunduh laporan.'); return; }

        const blob = await res.blob();
        const disposition = res.headers.get('Content-Disposition') || '';
        const match = disposition.match(/filename="?([^"]+)"?/);
        const filename = match ? match[1] : `laporan_${type}.${format}`;

        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
    } catch (err) {
        showMessage('Gagal mengunduh laporan.');
    }
}

const [defaultFrom, defaultTo] = monthRange();
document.getElementById('revFrom').value = defaultFrom;
document.getElementById('revTo').value = defaultTo;
document.getElementById('payFrom').value = defaultFrom;
document.getElementById('payTo').value = defaultTo;

loadRevenue();
loadCustomersReport();
loadPaymentsReport();
</script>
<?= $this->endSection() ?>
