<?= $this->extend('layouts/dashboard') ?>
<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="mb-4">
            <h1 class="fs-3 mb-1">Pembayaran</h1>
            <p class="mb-0 text-muted">Verifikasi pembayaran pelanggan</p>
        </div>
    </div>
</div>

<div id="pageError" class="alert alert-danger d-none"></div>
<div id="pageSuccess" class="alert alert-success d-none"></div>

<div class="d-flex gap-2 mb-3 flex-wrap justify-content-between">
    <select id="statusFilter" class="form-select" style="max-width: 200px;">
        <option value="">Semua Status</option>
        <option value="pending" selected>Menunggu Verifikasi</option>
        <option value="verified">Terverifikasi</option>
        <option value="rejected">Ditolak</option>
    </select>
    <select id="methodFilter" class="form-select" style="max-width: 200px;">
        <option value="">Semua Metode</option>
        <option value="qris">QRIS</option>
        <option value="transfer">Transfer</option>
        <option value="gateway">Gateway</option>
        <option value="cash">Tunai</option>
    </select>
</div>

<div class="card table-responsive">
    <table class="table mb-0 text-nowrap table-hover">
        <thead class="table-light border-light">
            <tr>
                <th>Pelanggan</th>
                <th>Tagihan</th>
                <th>Metode</th>
                <th>Jumlah</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody id="paymentTableBody">
            <tr><td colspan="6" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span> Memuat data...</td></tr>
        </tbody>
    </table>
</div>
<nav class="mt-3">
    <ul class="pagination justify-content-center" id="pagination"></ul>
</nav>

<!-- Verify Modal -->
<div class="modal fade" id="verifyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Pembayaran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="verifyBody">
                <div class="text-center py-3"><span class="spinner-border spinner-border-sm"></span></div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
let currentPage = 1;
let currentPaymentId = null;

const statusBadge = (s) => ({
    pending: '<span class="badge bg-secondary-subtle text-secondary">Menunggu</span>',
    verified: '<span class="badge bg-success-subtle text-success">Terverifikasi</span>',
    rejected: '<span class="badge bg-danger-subtle text-danger">Ditolak</span>',
}[s] || s);

const methodLabel = (m) => ({ qris: 'QRIS', transfer: 'Transfer', gateway: 'Gateway', cash: 'Tunai' }[m] || m);

function showMessage(type, msg) {
    const el = document.getElementById(type === 'error' ? 'pageError' : 'pageSuccess');
    el.textContent = msg;
    el.classList.remove('d-none');
    setTimeout(() => el.classList.add('d-none'), 5000);
}

function formatRp(n) { return 'Rp' + Number(n).toLocaleString('id-ID'); }

async function loadPayments(page = 1) {
    currentPage = page;
    const status = document.getElementById('statusFilter').value;
    const method = document.getElementById('methodFilter').value;
    const params = new URLSearchParams({ page, per_page: 10 });
    if (status) params.set('status', status);
    if (method) params.set('payment_method', method);

    const tbody = document.getElementById('paymentTableBody');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span> Memuat data...</td></tr>';

    try {
        const body = await nethubFetch('/api/owner/payments?' + params.toString());
        if (!body.success) { showMessage('error', body.message); return; }

        const { items, pagination } = body.data;
        if (items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">Belum ada pembayaran.</td></tr>';
        } else {
            tbody.innerHTML = items.map(p => `
                <tr class="align-middle">
                    <td>${p.customer_name || '-'}</td>
                    <td>${p.invoice_number || '-'}</td>
                    <td>${methodLabel(p.payment_method)}</td>
                    <td>${formatRp(p.amount)}</td>
                    <td>${statusBadge(p.status)}</td>
                    <td><a href="#" title="Lihat" onclick="openVerify(${p.id}); return false;"><i class="ti ti-eye"></i></a></td>
                </tr>
            `).join('');
        }
        renderPagination(pagination);
    } catch (err) {
        if (err.message !== 'Unauthenticated') showMessage('error', 'Gagal memuat data pembayaran.');
    }
}

function renderPagination(p) {
    const el = document.getElementById('pagination');
    if (p.total_pages <= 1) { el.innerHTML = ''; return; }
    let html = '';
    for (let i = 1; i <= p.total_pages; i++) {
        html += `<li class="page-item ${i === p.page ? 'active' : ''}"><a class="page-link" href="#" onclick="loadPayments(${i}); return false;">${i}</a></li>`;
    }
    el.innerHTML = html;
}

async function openVerify(id) {
    currentPaymentId = id;
    new bootstrap.Modal(document.getElementById('verifyModal')).show();
    const box = document.getElementById('verifyBody');
    box.innerHTML = '<div class="text-center py-3"><span class="spinner-border spinner-border-sm"></span></div>';

    const body = await nethubFetch('/api/owner/payments/' + id);
    if (!body.success) { box.innerHTML = `<div class="alert alert-danger mb-0">${body.message}</div>`; return; }
    const p = body.data;

    let proofHtml = '';
    if (p.payment_method === 'transfer' && p.proof_image) {
        proofHtml = `<div class="mb-3"><img id="proofImg" class="img-fluid rounded border" alt="Bukti transfer" style="max-height:300px;"></div>`;
    }

    const actionsHtml = p.status === 'pending' ? `
        <div class="mb-3">
            <label class="form-label">Catatan (opsional)</label>
            <textarea id="verifyNotes" class="form-control" rows="2"></textarea>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-success flex-fill" onclick="submitVerify(true)"><i class="ti ti-check"></i> Setujui</button>
            <button class="btn btn-danger flex-fill" onclick="submitVerify(false)"><i class="ti ti-x"></i> Tolak</button>
        </div>` : `<div class="text-muted small">Sudah diproses pada ${p.verified_at || '-'}.${p.notes ? ' Catatan: ' + p.notes : ''}</div>`;

    box.innerHTML = `
        <table class="table table-sm mb-3">
            <tr><th>Pelanggan</th><td>${p.customer_name || '-'}</td></tr>
            <tr><th>Tagihan</th><td>${p.invoice_number || '-'}</td></tr>
            <tr><th>Metode</th><td>${methodLabel(p.payment_method)}</td></tr>
            <tr><th>Jumlah</th><td>${formatRp(p.amount)}</td></tr>
            <tr><th>Status</th><td>${statusBadge(p.status)}</td></tr>
        </table>
        ${proofHtml}
        ${actionsHtml}
    `;

    if (p.payment_method === 'transfer' && p.proof_image) {
        try {
            let res = await fetch('/api/owner/payments/' + id + '/proof', {
                headers: { Authorization: 'Bearer ' + nethubToken },
            });
            if (res.status === 401 && await nethubRefreshToken()) {
                res = await fetch('/api/owner/payments/' + id + '/proof', {
                    headers: { Authorization: 'Bearer ' + nethubToken },
                });
            }
            if (res.ok) {
                const blob = await res.blob();
                document.getElementById('proofImg').src = URL.createObjectURL(blob);
            }
        } catch (err) { /* proof image is optional visually, ignore failure */ }
    }
}

async function submitVerify(approve) {
    const notes = document.getElementById('verifyNotes')?.value || '';

    try {
        const body = await nethubFetch(`/api/owner/payments/${currentPaymentId}/verify`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ approve, notes }),
        });
        if (!body.success) { showMessage('error', body.message); return; }

        bootstrap.Modal.getInstance(document.getElementById('verifyModal')).hide();
        showMessage('success', body.message);
        loadPayments(currentPage);
    } catch (err) {
        if (err.message !== 'Unauthenticated') showMessage('error', 'Gagal memproses verifikasi.');
    }
}

document.getElementById('statusFilter').addEventListener('change', () => loadPayments(1));
document.getElementById('methodFilter').addEventListener('change', () => loadPayments(1));

loadPayments();
</script>
<?= $this->endSection() ?>
