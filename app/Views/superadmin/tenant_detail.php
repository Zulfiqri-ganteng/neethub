<?= $this->extend('layouts/super_admin') ?>
<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <a href="/superadmin/tenants" class="text-muted small"><i class="ti ti-arrow-left"></i> Kembali ke daftar tenant</a>
                <h1 class="fs-3 mb-1 mt-1" id="tenantName">Memuat...</h1>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-danger btn-sm" id="suspendBtn" onclick="suspendTenant()">
                    <i class="ti ti-lock"></i> Suspend
                </button>
                <button class="btn btn-outline-success btn-sm d-none" id="reactivateBtn" onclick="reactivateTenant()">
                    <i class="ti ti-lock-open"></i> Aktifkan Kembali
                </button>
            </div>
        </div>
    </div>
</div>

<div id="pageError" class="alert alert-danger d-none"></div>
<div id="pageSuccess" class="alert alert-success d-none"></div>

<div class="card mb-3">
    <div class="card-body p-4" id="tenantInfo">
        <span class="spinner-border spinner-border-sm"></span> Memuat data tenant...
    </div>
</div>

<div class="card">
    <div class="card-body p-4">
        <h5 class="mb-3">Riwayat Tagihan Platform</h5>
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

<!-- Verify payment modal -->
<div class="modal fade" id="verifyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Verifikasi Pembayaran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="verifyModalBody">
                <span class="spinner-border spinner-border-sm"></span>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const tenantId = <?= (int) $tenantId ?>;
let currentPayment = null;

function formatRp(n) { return 'Rp' + Number(n).toLocaleString('id-ID'); }

const tenantStatusBadge = (s) => ({
    active: '<span class="badge bg-success-subtle text-success">Aktif</span>',
    suspended: '<span class="badge bg-danger-subtle text-danger">Disuspend</span>',
}[s] || s);

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

async function loadTenant() {
    const body = await nethubFetch(`/api/superadmin/tenants/${tenantId}`);
    if (!body.success) { showMessage('error', body.message); return; }

    const t = body.data;
    document.getElementById('tenantName').textContent = t.name;
    document.getElementById('suspendBtn').classList.toggle('d-none', t.status !== 'active');
    document.getElementById('reactivateBtn').classList.toggle('d-none', t.status !== 'suspended');

    document.getElementById('tenantInfo').innerHTML = `
        <div class="row g-3">
            <div class="col-md-3"><div class="small text-muted">Email</div><div>${t.email || '-'}</div></div>
            <div class="col-md-3"><div class="small text-muted">WhatsApp</div><div>${t.whatsapp || '-'}</div></div>
            <div class="col-md-3"><div class="small text-muted">Plan</div><div class="text-capitalize">${t.plan}</div></div>
            <div class="col-md-3"><div class="small text-muted">Status</div><div>${tenantStatusBadge(t.status)} ${t.suspended_reason ? `<span class="small text-muted">(${t.suspended_reason})</span>` : ''}</div></div>
        </div>
    `;

    const rows = t.platform_invoices.map(inv => `
        <tr>
            <td>${inv.period_start} s/d ${inv.period_end}</td>
            <td>${formatRp(inv.amount)}</td>
            <td>${inv.due_date}</td>
            <td>${invoiceStatusBadge(inv.status)}</td>
            <td class="text-end">
                ${inv.status !== 'paid' ? `<button class="btn btn-sm btn-outline-primary" onclick="openVerify(${inv.id})">Cek Pembayaran</button>` : ''}
            </td>
        </tr>
    `).join('');
    document.getElementById('invoiceTableBody').innerHTML = rows || '<tr><td colspan="5" class="text-center py-4 text-muted">Belum ada tagihan.</td></tr>';
}

async function openVerify(invoiceId) {
    new bootstrap.Modal(document.getElementById('verifyModal')).show();
    const box = document.getElementById('verifyModalBody');
    box.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    const body = await nethubFetch('/api/superadmin/platform-payments/pending');
    if (!body.success) { box.innerHTML = `<div class="alert alert-danger mb-0">${body.message}</div>`; return; }

    const payment = body.data.find(p => p.platform_invoice_id === invoiceId);
    if (!payment) {
        box.innerHTML = '<div class="alert alert-warning mb-0">Belum ada bukti pembayaran yang diunggah tenant untuk tagihan ini.</div>';
        return;
    }

    currentPayment = payment;
    box.innerHTML = `
        <p class="mb-2"><strong>${formatRp(payment.amount)}</strong> via ${payment.payment_method}</p>
        <div class="text-center mb-3">
            <img src="/api/superadmin/platform-payments/${payment.id}/proof" class="img-fluid rounded border" style="max-height:300px;" onerror="this.replaceWith(document.createTextNode('Gagal memuat gambar bukti.'))">
        </div>
        <textarea id="verifyNotes" class="form-control mb-3" rows="2" placeholder="Catatan (opsional)"></textarea>
        <div class="d-flex gap-2">
            <button class="btn btn-danger flex-fill" onclick="submitVerify(false)">Tolak</button>
            <button class="btn btn-success flex-fill" onclick="submitVerify(true)">Setujui</button>
        </div>
    `;
}

async function submitVerify(approve) {
    const notes = document.getElementById('verifyNotes').value;
    const body = await nethubFetch(`/api/superadmin/platform-payments/${currentPayment.id}/verify`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ approve, notes }),
    });

    bootstrap.Modal.getInstance(document.getElementById('verifyModal')).hide();
    showMessage(body.success ? 'success' : 'error', body.message);
    if (body.success) loadTenant();
}

async function suspendTenant() {
    const reason = prompt('Alasan suspend (opsional):') || '';
    const body = await nethubFetch(`/api/superadmin/tenants/${tenantId}/suspend`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ reason }),
    });
    showMessage(body.success ? 'success' : 'error', body.message);
    if (body.success) loadTenant();
}

async function reactivateTenant() {
    const body = await nethubFetch(`/api/superadmin/tenants/${tenantId}/reactivate`, { method: 'POST' });
    showMessage(body.success ? 'success' : 'error', body.message);
    if (body.success) loadTenant();
}

loadTenant();
</script>
<?= $this->endSection() ?>
