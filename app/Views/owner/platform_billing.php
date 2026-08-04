<?= $this->extend('layouts/dashboard') ?>
<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <h1 class="fs-3 mb-1">Tagihan Platform</h1>
        <p class="mb-4 text-muted">Biaya langganan NetHub untuk bisnis Anda — terpisah dari tagihan pelanggan Anda sendiri.</p>
    </div>
</div>

<div id="pageError" class="alert alert-danger d-none"></div>
<div id="pageSuccess" class="alert alert-success d-none"></div>

<div class="card">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table mb-0 text-nowrap table-hover">
                <thead class="table-light border-light">
                    <tr>
                        <th>Periode</th>
                        <th>Jumlah</th>
                        <th>Jatuh Tempo</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="invoiceTableBody">
                    <tr><td colspan="5" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Pay modal -->
<div class="modal fade" id="payModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bayar Tagihan Platform</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="payModalBody"></div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
let bankInfo = {};
let currentInvoiceId = null;

function formatRp(n) { return 'Rp' + Number(n).toLocaleString('id-ID'); }

const invoiceStatusBadge = (s) => ({
    unpaid: '<span class="badge bg-secondary-subtle text-secondary">Belum Lunas</span>',
    paid: '<span class="badge bg-success-subtle text-success">Lunas</span>',
    overdue: '<span class="badge bg-danger-subtle text-danger">Terlambat</span>',
}[s] || s);

function showMessage(type, msg) {
    const el = document.getElementById(type === 'error' ? 'pageError' : 'pageSuccess');
    el.textContent = msg;
    el.classList.remove('d-none');
    setTimeout(() => el.classList.add('d-none'), 5000);
}

async function loadInvoices() {
    const body = await nethubFetch('/api/owner/platform-billing');
    if (!body.success) { showMessage('error', body.message); return; }

    bankInfo = body.data.bank || {};

    const rows = body.data.invoices.map(inv => `
        <tr>
            <td>${inv.period_start} s/d ${inv.period_end}</td>
            <td>${formatRp(inv.amount)}</td>
            <td>${inv.due_date}</td>
            <td>${invoiceStatusBadge(inv.status)}</td>
            <td class="text-end">
                ${inv.status !== 'paid' ? `<button class="btn btn-sm btn-primary" onclick="openPay(${inv.id})">Bayar</button>` : ''}
            </td>
        </tr>
    `).join('');

    document.getElementById('invoiceTableBody').innerHTML = rows || '<tr><td colspan="5" class="text-center py-4 text-muted">Belum ada tagihan platform.</td></tr>';
}

function openPay(invoiceId) {
    currentInvoiceId = invoiceId;
    new bootstrap.Modal(document.getElementById('payModal')).show();
    document.getElementById('payModalBody').innerHTML = `
        <div class="alert alert-info small">
            <strong>Transfer ke:</strong><br>
            ${bankInfo.bank_name || '-'} — ${bankInfo.bank_account_number || '-'}<br>
            a.n. ${bankInfo.bank_account_name || '-'}
        </div>
        <div class="mb-3">
            <label class="form-label">Unggah Bukti Transfer</label>
            <input type="file" id="proofFile" class="form-control" accept="image/jpeg,image/png,image/webp" required>
        </div>
        <button class="btn btn-success w-100" onclick="submitPay()">Kirim Bukti Transfer</button>
    `;
}

async function submitPay(isRetry = false) {
    const file = document.getElementById('proofFile').files[0];
    if (!file) { showMessage('error', 'Pilih file bukti transfer terlebih dahulu.'); return; }

    const formData = new FormData();
    formData.append('proof', file);

    try {
        const res = await fetch(`/api/owner/platform-billing/invoices/${currentInvoiceId}/pay`, {
            method: 'POST',
            headers: { Authorization: 'Bearer ' + nethubToken },
            body: formData,
        });

        if (res.status === 401 && !isRetry && await nethubRefreshToken()) {
            return submitPay(true);
        }

        const body = await res.json();
        if (!body.success) { showMessage('error', body.message); return; }

        bootstrap.Modal.getInstance(document.getElementById('payModal')).hide();
        showMessage('success', body.message + ' Menunggu verifikasi Super Admin.');
        loadInvoices();
    } catch (err) {
        showMessage('error', 'Gagal mengunggah bukti transfer.');
    }
}

loadInvoices();
</script>
<?= $this->endSection() ?>
