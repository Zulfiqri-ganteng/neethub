<?= $this->extend('layouts/super_admin') ?>
<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h1 class="fs-3 mb-1">Tenant</h1>
                <p class="mb-0 text-muted">Semua bisnis WiFi/ISP yang memakai NetHub</p>
            </div>
            <button class="btn btn-outline-secondary btn-sm" onclick="loadTenants()">
                <i class="ti ti-refresh"></i> Refresh
            </button>
        </div>
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
                        <th>Tenant</th>
                        <th>Plan</th>
                        <th>Status</th>
                        <th>Tagihan Platform Terakhir</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="tenantTableBody">
                    <tr><td colspan="5" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span> Memuat data...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
function formatRp(n) { return 'Rp' + Number(n).toLocaleString('id-ID'); }

const tenantStatusBadge = (s) => ({
    active: '<span class="badge bg-success-subtle text-success">Aktif</span>',
    suspended: '<span class="badge bg-danger-subtle text-danger">Disuspend</span>',
}[s] || s);

const invoiceStatusBadge = (s) => ({
    unpaid: '<span class="badge bg-secondary-subtle text-secondary">Belum Lunas</span>',
    paid: '<span class="badge bg-success-subtle text-success">Lunas</span>',
    overdue: '<span class="badge bg-danger-subtle text-danger">Terlambat</span>',
}[s] || '<span class="text-muted">Belum ada tagihan</span>');

function showMessage(type, msg) {
    const el = document.getElementById(type === 'error' ? 'pageError' : 'pageSuccess');
    el.textContent = msg;
    el.classList.remove('d-none');
    setTimeout(() => el.classList.add('d-none'), 5000);
}

async function loadTenants() {
    const body = await nethubFetch('/api/superadmin/tenants?per_page=100');
    if (!body.success) { showMessage('error', body.message); return; }

    const rows = body.data.items.map(t => `
        <tr>
            <td>
                <a href="/superadmin/tenants/${t.id}" class="fw-semibold text-decoration-none">${t.name}</a>
                <div class="small text-muted">${t.email || '-'}</div>
            </td>
            <td class="text-capitalize">${t.plan}</td>
            <td>
                ${tenantStatusBadge(t.status)}
                ${t.suspended_reason ? `<div class="small text-muted">${t.suspended_reason}</div>` : ''}
            </td>
            <td>
                ${t.latest_platform_invoice ? invoiceStatusBadge(t.latest_platform_invoice.status) + ' ' + formatRp(t.latest_platform_invoice.amount) : invoiceStatusBadge(null)}
            </td>
            <td class="text-end">
                <a href="/superadmin/tenants/${t.id}" class="btn btn-sm btn-outline-primary">Detail</a>
            </td>
        </tr>
    `).join('');

    document.getElementById('tenantTableBody').innerHTML = rows || '<tr><td colspan="5" class="text-center py-4 text-muted">Belum ada tenant.</td></tr>';
}

loadTenants();
</script>
<?= $this->endSection() ?>
