<?= $this->extend('layouts/dashboard') ?>
<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h1 class="fs-3 mb-1">Monitoring</h1>
                <p class="mb-0 text-muted">Pelanggan online &amp; traffic real-time</p>
            </div>
            <button class="btn btn-outline-secondary btn-sm" onclick="loadOnlineUsers()">
                <i class="ti ti-refresh"></i> Refresh
            </button>
        </div>
    </div>
</div>

<div id="pageError" class="alert alert-danger d-none"></div>
<div id="pageSuccess" class="alert alert-success d-none"></div>

<!-- Online Users -->
<div class="card mb-3">
    <div class="card-body p-4">
        <h5 class="mb-1">Pelanggan Online</h5>
        <p class="text-muted small mb-3">
            Data ini disinkronkan otomatis dari router setiap ~30 detik (via <code>router:sync</code>),
            bukan dicek langsung setiap kali halaman dibuka.
        </p>
        <div class="table-responsive">
            <table class="table mb-0 text-nowrap table-hover">
                <thead class="table-light border-light">
                    <tr>
                        <th>Pelanggan</th>
                        <th>Username</th>
                        <th>Router</th>
                        <th>IP Address</th>
                        <th>Terakhir Terlihat</th>
                    </tr>
                </thead>
                <tbody id="onlineTableBody">
                    <tr><td colspan="5" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span> Memuat data...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Traffic Lookup -->
<div class="card mb-3">
    <div class="card-body p-4">
        <h5 class="mb-3">Cek Traffic Pelanggan</h5>
        <div class="d-flex gap-2 mb-3 flex-wrap">
            <select id="trafficCustomer" class="form-select" style="max-width: 320px;">
                <option value="">- Pilih Pelanggan -</option>
            </select>
            <button class="btn btn-primary" onclick="checkTraffic()"><i class="ti ti-activity"></i> Cek Traffic</button>
        </div>
        <div id="trafficResult"></div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
function showMessage(type, msg) {
    const el = document.getElementById(type === 'error' ? 'pageError' : 'pageSuccess');
    el.textContent = msg;
    el.classList.remove('d-none');
    setTimeout(() => el.classList.add('d-none'), 5000);
}

async function loadOnlineUsers() {
    const tbody = document.getElementById('onlineTableBody');
    tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span> Memuat data...</td></tr>';

    try {
        const body = await nethubFetch('/api/owner/monitoring/users-online');
        if (!body.success) { showMessage('error', body.message); return; }

        const items = body.data;
        tbody.innerHTML = items.length === 0
            ? '<tr><td colspan="5" class="text-center py-4 text-muted">Tidak ada pelanggan yang sedang online saat ini.</td></tr>'
            : items.map(o => `
                <tr class="align-middle">
                    <td>${o.customer_name || '-'}</td>
                    <td>${o.username}</td>
                    <td>${o.router_name || '-'}</td>
                    <td>${o.ip_address || '-'}</td>
                    <td>${o.last_seen_at}</td>
                </tr>
            `).join('');
    } catch (err) {
        if (err.message !== 'Unauthenticated') showMessage('error', 'Gagal memuat data online.');
    }
}

async function loadCustomerOptions() {
    try {
        const body = await nethubFetch('/api/owner/customers?status=active&per_page=100');
        if (!body.success) return;
        const select = document.getElementById('trafficCustomer');
        select.innerHTML = '<option value="">- Pilih Pelanggan -</option>' +
            body.data.items.map(c => `<option value="${c.id}">${c.name} (${c.customer_code})</option>`).join('');
    } catch (err) { /* non-fatal, dropdown just stays with placeholder */ }
}

async function checkTraffic() {
    const customerId = document.getElementById('trafficCustomer').value;
    const box = document.getElementById('trafficResult');
    if (!customerId) { showMessage('error', 'Pilih pelanggan terlebih dahulu.'); return; }

    box.innerHTML = '<div class="text-center py-3"><span class="spinner-border spinner-border-sm"></span></div>';

    try {
        const body = await nethubFetch('/api/owner/monitoring/traffic?customer_id=' + customerId);
        if (!body.success) {
            box.innerHTML = `<div class="alert alert-warning mb-0">${body.message}</div>`;
            return;
        }
        const rows = Object.entries(body.data).map(([k, v]) => `<tr><th>${k}</th><td>${v}</td></tr>`).join('');
        box.innerHTML = rows
            ? `<table class="table table-sm mb-0"><tbody>${rows}</tbody></table>`
            : '<p class="text-muted mb-0">Tidak ada data traffic (pelanggan mungkin sedang tidak terhubung).</p>';
    } catch (err) {
        if (err.message !== 'Unauthenticated') box.innerHTML = '<div class="alert alert-danger mb-0">Gagal mengambil data traffic.</div>';
    }
}

loadOnlineUsers();
loadCustomerOptions();
</script>
<?= $this->endSection() ?>
