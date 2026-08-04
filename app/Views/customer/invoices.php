<?= $this->extend('layouts/customer') ?>
<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="mb-4">
            <h1 class="fs-3 mb-1">Tagihan Saya</h1>
            <p class="mb-0 text-muted">Daftar tagihan &amp; pembayaran</p>
        </div>
    </div>
</div>

<div id="pageError" class="alert alert-danger d-none"></div>
<div id="pageSuccess" class="alert alert-success d-none"></div>

<div class="d-flex gap-2 mb-3">
    <select id="statusFilter" class="form-select" style="max-width: 220px;">
        <option value="">Semua Status</option>
        <option value="unpaid">Belum Lunas</option>
        <option value="overdue">Terlambat</option>
        <option value="paid">Lunas</option>
    </select>
</div>

<div class="card table-responsive">
    <table class="table mb-0 text-nowrap table-hover">
        <thead class="table-light border-light">
            <tr>
                <th>No. Tagihan</th>
                <th>Periode</th>
                <th>Total</th>
                <th>Jatuh Tempo</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody id="invoiceTableBody">
            <tr><td colspan="6" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span> Memuat data...</td></tr>
        </tbody>
    </table>
</div>
<nav class="mt-3"><ul class="pagination justify-content-center" id="pagination"></ul></nav>

<!-- Pay Modal -->
<div class="modal fade" id="payModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bayar Tagihan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="payBody"></div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
let currentPage = 1;
let currentInvoiceId = null;
let tenantBankInfo = null;

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
    setTimeout(() => el.classList.add('d-none'), 6000);
}

function formatRp(n) { return 'Rp' + Number(n).toLocaleString('id-ID'); }

async function loadInvoices(page = 1) {
    currentPage = page;
    const status = document.getElementById('statusFilter').value;
    const params = new URLSearchParams({ page, per_page: 10 });
    if (status) params.set('status', status);

    const tbody = document.getElementById('invoiceTableBody');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span> Memuat data...</td></tr>';

    try {
        const body = await nethubFetch('/api/customer/invoices?' + params.toString());
        if (!body.success) { showMessage('error', body.message); return; }

        const { items, pagination } = body.data;
        tbody.innerHTML = items.length === 0
            ? '<tr><td colspan="6" class="text-center py-4 text-muted">Belum ada tagihan.</td></tr>'
            : items.map(i => `
                <tr class="align-middle">
                    <td>${i.invoice_number}</td>
                    <td>${i.period_start} s/d ${i.period_end}</td>
                    <td>${formatRp(i.total_amount)}</td>
                    <td>${i.due_date}</td>
                    <td>${statusBadge(i.status)}</td>
                    <td>
                        ${i.status !== 'paid' ? `<button class="btn btn-primary btn-sm me-2" onclick="openPay(${i.id})">Bayar</button>` : ''}
                        <a href="#" title="Download PDF" onclick="downloadInvoicePdf(${i.id}, '${i.invoice_number}'); return false;"><i class="ti ti-file-type-pdf"></i></a>
                    </td>
                </tr>
            `).join('');
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

async function downloadInvoicePdf(id, invoiceNumber) {
    try {
        let res = await fetch(`/api/customer/invoices/${id}/pdf`, {
            headers: { Authorization: 'Bearer ' + nethubToken },
        });
        if (res.status === 401 && await nethubRefreshToken()) {
            res = await fetch(`/api/customer/invoices/${id}/pdf`, {
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

async function openPay(id) {
    currentInvoiceId = id;
    new bootstrap.Modal(document.getElementById('payModal')).show();

    if (!tenantBankInfo) {
        const dash = await nethubFetch('/api/customer/dashboard');
        if (dash.success) tenantBankInfo = dash.data.tenant;
    }

    document.getElementById('payBody').innerHTML = `
        <div class="d-flex gap-2 mb-3">
            <button class="btn btn-outline-primary flex-fill" onclick="showQris()"><i class="ti ti-qrcode"></i> QRIS</button>
            <button class="btn btn-outline-primary flex-fill" onclick="showTransfer()"><i class="ti ti-building-bank"></i> Transfer Manual</button>
        </div>
        <div id="payMethodBody"></div>
    `;
}

async function showQris() {
    const box = document.getElementById('payMethodBody');
    box.innerHTML = '<div class="text-center py-3"><span class="spinner-border spinner-border-sm"></span></div>';

    const body = await nethubFetch('/api/customer/invoices/' + currentInvoiceId + '/qris');
    if (!body.success) { box.innerHTML = `<div class="alert alert-danger mb-0">${body.message}</div>`; return; }

    box.innerHTML = `
        <div class="text-center mb-3">
            <img src="${body.data.image_data_uri}" class="img-fluid" style="max-width:250px;" alt="QRIS">
        </div>
        <p class="text-muted small text-center">Scan QR di atas menggunakan aplikasi e-wallet/m-banking Anda.</p>
        <button class="btn btn-success w-100" onclick="submitPayment('qris')">Saya Sudah Bayar</button>
    `;
}

function showTransfer() {
    const box = document.getElementById('payMethodBody');
    const bank = tenantBankInfo || {};
    box.innerHTML = `
        <div class="alert alert-info small">
            <strong>Transfer ke:</strong><br>
            ${bank.bank_name || '-'} — ${bank.bank_account_number || '-'}<br>
            a.n. ${bank.bank_account_name || '-'}
        </div>
        <div class="mb-3">
            <label class="form-label">Unggah Bukti Transfer</label>
            <input type="file" id="proofFile" class="form-control" accept="image/jpeg,image/png,image/webp" required>
        </div>
        <button class="btn btn-success w-100" onclick="submitPayment('transfer')">Kirim Bukti Transfer</button>
    `;
}

async function submitPayment(method, isRetry = false) {
    const formData = new FormData();
    formData.append('payment_method', method);

    if (method === 'transfer') {
        const file = document.getElementById('proofFile').files[0];
        if (!file) { showMessage('error', 'Pilih file bukti transfer terlebih dahulu.'); return; }
        formData.append('proof', file);
    }

    try {
        const res = await fetch(`/api/customer/invoices/${currentInvoiceId}/pay`, {
            method: 'POST',
            headers: { Authorization: 'Bearer ' + nethubToken },
            body: formData,
        });

        // Filling out a payment form can easily take longer than the
        // 15-min access token — refresh once and resend rather than
        // making the customer redo the upload.
        if (res.status === 401 && !isRetry && await nethubRefreshToken()) {
            return submitPayment(method, true);
        }

        const body = await res.json();
        if (!body.success) { showMessage('error', body.message); return; }

        bootstrap.Modal.getInstance(document.getElementById('payModal')).hide();
        showMessage('success', body.message);
        loadInvoices(currentPage);
    } catch (err) {
        showMessage('error', 'Gagal mengirim pembayaran.');
    }
}

document.getElementById('statusFilter').addEventListener('change', () => loadInvoices(1));

loadInvoices();
</script>
<?= $this->endSection() ?>
