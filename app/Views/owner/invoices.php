<?= $this->extend('layouts/dashboard') ?>
<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h1 class="fs-3 mb-1">Tagihan</h1>
                <p class="mb-0 text-muted">Daftar tagihan pelanggan</p>
            </div>
            <button class="btn btn-primary" onclick="generateInvoices()">
                <i class="ti ti-refresh"></i> Generate Tagihan
            </button>
        </div>
    </div>
</div>

<div id="pageError" class="alert alert-danger d-none"></div>
<div id="pageSuccess" class="alert alert-success d-none"></div>

<div class="d-flex gap-2 mb-3 flex-wrap justify-content-between">
    <select id="statusFilter" class="form-select" style="max-width: 200px;">
        <option value="">Semua Status</option>
        <option value="unpaid">Belum Lunas</option>
        <option value="paid">Lunas</option>
        <option value="overdue">Terlambat</option>
        <option value="cancelled">Dibatalkan</option>
    </select>
</div>

<div class="card table-responsive">
    <table class="table mb-0 text-nowrap table-hover">
        <thead class="table-light border-light">
            <tr>
                <th>No. Tagihan</th>
                <th>Pelanggan</th>
                <th>Periode</th>
                <th>Total</th>
                <th>Jatuh Tempo</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody id="invoiceTableBody">
            <tr><td colspan="7" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span> Memuat data...</td></tr>
        </tbody>
    </table>
</div>
<nav class="mt-3">
    <ul class="pagination justify-content-center" id="pagination"></ul>
</nav>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Tagihan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailBody">
                <div class="text-center py-3"><span class="spinner-border spinner-border-sm"></span></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary d-none" id="detailQrisBtn" onclick="showOwnerQris()">
                    <i class="ti ti-qrcode"></i> Tampilkan QR/Rekening
                </button>
                <button type="button" class="btn btn-outline-secondary" id="detailPdfBtn" onclick="downloadInvoicePdf(currentInvoiceId, currentInvoiceNumber)">
                    <i class="ti ti-file-type-pdf"></i> Download PDF
                </button>
            </div>
        </div>
    </div>
</div>

<!-- QRIS / Bank Modal -->
<div class="modal fade" id="qrisModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">QR / Rekening Pembayaran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="qrisBody">
                <div class="text-center py-3"><span class="spinner-border spinner-border-sm"></span></div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
let currentPage = 1;

const statusBadge = (s) => ({
    unpaid: '<span class="badge bg-secondary-subtle text-secondary">Belum Lunas</span>',
    paid: '<span class="badge bg-success-subtle text-success">Lunas</span>',
    overdue: '<span class="badge bg-danger-subtle text-danger">Terlambat</span>',
    cancelled: '<span class="badge bg-dark-subtle text-dark">Dibatalkan</span>',
}[s] || s);

function showMessage(type, msg) {
    const el = document.getElementById(type === 'error' ? 'pageError' : 'pageSuccess');
    el.textContent = msg;
    el.classList.remove('d-none');
    setTimeout(() => el.classList.add('d-none'), 5000);
}

function formatRp(n) { return 'Rp' + Number(n).toLocaleString('id-ID'); }

async function loadInvoices(page = 1) {
    currentPage = page;
    const status = document.getElementById('statusFilter').value;
    const params = new URLSearchParams({ page, per_page: 10 });
    if (status) params.set('status', status);

    const tbody = document.getElementById('invoiceTableBody');
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span> Memuat data...</td></tr>';

    try {
        const body = await nethubFetch('/api/owner/invoices?' + params.toString());
        if (!body.success) { showMessage('error', body.message); return; }

        const { items, pagination } = body.data;
        if (items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Belum ada tagihan.</td></tr>';
        } else {
            tbody.innerHTML = items.map(i => `
                <tr class="align-middle">
                    <td>${i.invoice_number}</td>
                    <td>${i.customer_name || '-'}</td>
                    <td>${i.period_start} s/d ${i.period_end}</td>
                    <td>${formatRp(i.total_amount)}</td>
                    <td>${i.due_date}</td>
                    <td>${statusBadge(i.status)}</td>
                    <td>
                        <a href="#" class="me-2" title="Detail" onclick="viewDetail(${i.id}); return false;"><i class="ti ti-eye"></i></a>
                        <a href="#" title="Download PDF" onclick="downloadInvoicePdf(${i.id}, '${i.invoice_number}'); return false;"><i class="ti ti-file-type-pdf"></i></a>
                    </td>
                </tr>
            `).join('');
        }
        renderPagination(pagination);
    } catch (err) {
        if (err.message !== 'Unauthenticated') showMessage('error', 'Gagal memuat data tagihan.');
    }
}

function renderPagination(p) {
    const el = document.getElementById('pagination');
    if (p.total_pages <= 1) { el.innerHTML = ''; return; }
    let html = '';
    for (let i = 1; i <= p.total_pages; i++) {
        html += `<li class="page-item ${i === p.page ? 'active' : ''}"><a class="page-link" href="#" onclick="loadInvoices(${i}); return false;">${i}</a></li>`;
    }
    el.innerHTML = html;
}

let currentInvoiceId = null;
let currentInvoiceNumber = null;

async function viewDetail(id) {
    new bootstrap.Modal(document.getElementById('detailModal')).show();
    document.getElementById('detailBody').innerHTML = '<div class="text-center py-3"><span class="spinner-border spinner-border-sm"></span></div>';

    const body = await nethubFetch('/api/owner/invoices/' + id);
    if (!body.success) {
        document.getElementById('detailBody').innerHTML = `<div class="alert alert-danger mb-0">${body.message}</div>`;
        return;
    }
    const i = body.data;
    currentInvoiceId = i.id;
    currentInvoiceNumber = i.invoice_number;
    document.getElementById('detailQrisBtn').classList.toggle('d-none', i.status === 'paid');
    document.getElementById('detailBody').innerHTML = `
        <table class="table table-sm mb-0">
            <tr><th>No. Tagihan</th><td>${i.invoice_number}</td></tr>
            <tr><th>Periode</th><td>${i.period_start} s/d ${i.period_end}</td></tr>
            <tr><th>Jumlah</th><td>${formatRp(i.amount)}</td></tr>
            <tr><th>Diskon</th><td>${formatRp(i.discount)}</td></tr>
            <tr><th>Pajak</th><td>${formatRp(i.tax)}</td></tr>
            <tr><th>Total</th><td><strong>${formatRp(i.total_amount)}</strong></td></tr>
            <tr><th>Jatuh Tempo</th><td>${i.due_date}</td></tr>
            <tr><th>Status</th><td>${statusBadge(i.status)}</td></tr>
            <tr><th>Dibayar Pada</th><td>${i.paid_at || '-'}</td></tr>
        </table>`;
}

async function showOwnerQris() {
    new bootstrap.Modal(document.getElementById('qrisModal')).show();
    const box = document.getElementById('qrisBody');
    box.innerHTML = '<div class="text-center py-3"><span class="spinner-border spinner-border-sm"></span></div>';

    try {
        const body = await nethubFetch('/api/owner/invoices/' + currentInvoiceId + '/qris');
        if (!body.success) { box.innerHTML = `<div class="alert alert-danger mb-0">${body.message}</div>`; return; }

        const d = body.data;
        box.innerHTML = `
            <div class="text-center mb-3">
                <img src="${d.image_data_uri}" class="img-fluid" style="max-width:250px;" alt="QRIS">
            </div>
            <p class="text-muted small text-center mb-3">Tunjukkan/kirim QR ini ke pelanggan untuk dipindai lewat aplikasi e-wallet/m-banking.</p>
            <div class="alert alert-info small mb-0">
                <strong>Atau transfer manual ke:</strong><br>
                ${d.bank_name || '-'} — ${d.bank_account_number || '-'}<br>
                a.n. ${d.bank_account_name || '-'}
            </div>
        `;
    } catch (err) {
        if (err.message !== 'Unauthenticated') box.innerHTML = '<div class="alert alert-danger mb-0">Gagal memuat QR.</div>';
    }
}

async function downloadInvoicePdf(id, invoiceNumber) {
    try {
        let res = await fetch(`/api/owner/invoices/${id}/pdf`, {
            headers: { Authorization: 'Bearer ' + nethubToken },
        });
        if (res.status === 401 && await nethubRefreshToken()) {
            res = await fetch(`/api/owner/invoices/${id}/pdf`, {
                headers: { Authorization: 'Bearer ' + nethubToken },
            });
        }
        if (!res.ok) { showMessage('error', 'Gagal mengunduh PDF tagihan.'); return; }

        const blob = await res.blob();
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = (invoiceNumber || 'invoice') + '.pdf';
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
    } catch (err) {
        showMessage('error', 'Gagal mengunduh PDF tagihan.');
    }
}

async function generateInvoices() {
    if (!confirm('Generate tagihan untuk semua pelanggan yang sudah waktunya ditagih?')) return;

    try {
        const body = await nethubFetch('/api/owner/invoices/generate', { method: 'POST' });
        showMessage(body.success ? 'success' : 'error', body.message);
        loadInvoices(currentPage);
    } catch (err) {
        if (err.message !== 'Unauthenticated') showMessage('error', 'Gagal generate tagihan.');
    }
}

document.getElementById('statusFilter').addEventListener('change', () => loadInvoices(1));

loadInvoices();
</script>
<?= $this->endSection() ?>
