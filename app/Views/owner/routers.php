<?= $this->extend('layouts/dashboard') ?>
<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h1 class="fs-3 mb-1">Router</h1>
                <p class="mb-0 text-muted">Kelola router MikroTik & perangkat jaringan Anda</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#routerModal" onclick="openCreateModal()">
                <i class="ti ti-plus"></i> Tambah Router
            </button>
        </div>
    </div>
</div>

<div id="pageError" class="alert alert-danger d-none"></div>
<div id="pageSuccess" class="alert alert-success d-none"></div>

<div class="card table-responsive">
    <table class="table mb-0 text-nowrap table-hover">
        <thead class="table-light border-light">
            <tr>
                <th>Nama</th>
                <th>Tipe</th>
                <th>IP : Port</th>
                <th>Status</th>
                <th>Terakhir Dicek</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody id="routerTableBody">
            <tr><td colspan="6" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span> Memuat data...</td></tr>
        </tbody>
    </table>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="routerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="routerForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="routerModalTitle">Tambah Router</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="routerId">
                    <div class="mb-3">
                        <label class="form-label">Nama Router</label>
                        <input type="text" id="rtName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tipe</label>
                        <select id="rtType" class="form-select" required>
                            <option value="mikrotik">MikroTik</option>
                            <option value="ubiquiti" disabled>Ubiquiti (Segera)</option>
                            <option value="omada" disabled>Omada (Segera)</option>
                            <option value="openwrt" disabled>OpenWRT (Segera)</option>
                            <option value="huawei" disabled>Huawei (Segera)</option>
                            <option value="cisco" disabled>Cisco (Segera)</option>
                            <option value="ruijie" disabled>Ruijie (Segera)</option>
                        </select>
                        <small class="text-muted">Hanya MikroTik yang didukung penuh saat ini — brand lain akan menyusul.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mode Koneksi API</label>
                        <select id="rtApiScheme" class="form-select">
                            <option value="api">API (port 8728, plain)</option>
                            <option value="api-ssl">API-SSL (port 8729, terenkripsi — direkomendasikan jika router diakses via internet)</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-8 mb-3">
                            <label class="form-label">IP Address</label>
                            <input type="text" id="rtIp" class="form-control" placeholder="192.168.88.1" required>
                        </div>
                        <div class="col-4 mb-3">
                            <label class="form-label">Port</label>
                            <input type="number" id="rtPort" class="form-control" value="8728">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" id="rtUsername" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password <span id="rtPasswordHint" class="text-muted small d-none">(kosongkan jika tidak diubah)</span></label>
                        <input type="password" id="rtPassword" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Lokasi (opsional)</label>
                        <input type="text" id="rtLocation" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const statusBadge = (s) => ({
    online: '<span class="badge bg-success-subtle text-success">Online</span>',
    offline: '<span class="badge bg-danger-subtle text-danger">Offline</span>',
    unknown: '<span class="badge bg-secondary-subtle text-secondary">Belum Dicek</span>',
}[s] || s);

function showMessage(type, msg) {
    const el = document.getElementById(type === 'error' ? 'pageError' : 'pageSuccess');
    el.textContent = msg;
    el.classList.remove('d-none');
    setTimeout(() => el.classList.add('d-none'), 5000);
}

async function loadRouters() {
    const tbody = document.getElementById('routerTableBody');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span> Memuat data...</td></tr>';

    try {
        const body = await nethubFetch('/api/owner/routers?per_page=100');
        if (!body.success) { showMessage('error', body.message); return; }

        const items = body.data.items;
        if (items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">Belum ada router.</td></tr>';
            return;
        }
        tbody.innerHTML = items.map(r => `
            <tr class="align-middle" id="rt-row-${r.id}">
                <td>${r.name}</td>
                <td>${r.type}</td>
                <td>${r.ip_address}:${r.port}</td>
                <td id="rt-status-${r.id}">${statusBadge(r.status)}</td>
                <td>${r.last_checked_at || '-'}</td>
                <td>
                    <a href="#" class="me-2" title="Test Koneksi" onclick="testConnection(${r.id}); return false;"><i class="ti ti-plug"></i></a>
                    <a href="#" class="me-2" title="Edit" onclick="openEditModal(${r.id}); return false;"><i class="ti ti-edit"></i></a>
                    <a href="#" class="link-danger" title="Hapus" onclick="deleteRouter(${r.id}, '${r.name.replace(/'/g, "\\'")}'); return false;"><i class="ti ti-trash"></i></a>
                </td>
            </tr>
        `).join('');
    } catch (err) {
        if (err.message !== 'Unauthenticated') showMessage('error', 'Gagal memuat data router.');
    }
}

async function testConnection(id) {
    const cell = document.getElementById('rt-status-' + id);
    cell.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Menguji...';

    try {
        const body = await nethubFetch(`/api/owner/routers/${id}/test`, { method: 'POST' });
        if (!body.success) { showMessage('error', body.message); loadRouters(); return; }

        cell.innerHTML = statusBadge(body.data.status);
        if (body.data.status === 'offline' && body.data.error) {
            showMessage('error', 'Router tidak dapat dihubungi: ' + body.data.error);
        } else {
            showMessage('success', 'Router online dan berhasil terhubung.');
        }
    } catch (err) {
        if (err.message !== 'Unauthenticated') showMessage('error', 'Gagal menguji koneksi router.');
    }
}

// Port 8728/8729 are the two well-known MikroTik API ports — auto-swap the
// port field when the scheme changes, but only while it still holds one of
// those two defaults, so a deliberately customized port (e.g. a forwarded
// non-standard port) is never silently overwritten.
document.getElementById('rtApiScheme').addEventListener('change', (e) => {
    const portField = document.getElementById('rtPort');
    const current = Number(portField.value);
    if (current !== 8728 && current !== 8729) return;
    portField.value = e.target.value === 'api-ssl' ? 8729 : 8728;
});

function openCreateModal() {
    document.getElementById('routerForm').reset();
    document.getElementById('routerId').value = '';
    document.getElementById('rtApiScheme').value = 'api';
    document.getElementById('rtPort').value = 8728;
    document.getElementById('rtPassword').required = true;
    document.getElementById('rtPasswordHint').classList.add('d-none');
    document.getElementById('routerModalTitle').textContent = 'Tambah Router';
}

async function openEditModal(id) {
    const body = await nethubFetch('/api/owner/routers/' + id);
    if (!body.success) { showMessage('error', body.message); return; }
    const r = body.data;
    document.getElementById('routerId').value = r.id;
    document.getElementById('rtName').value = r.name;
    document.getElementById('rtType').value = r.type;
    document.getElementById('rtApiScheme').value = r.api_scheme || 'api';
    document.getElementById('rtIp').value = r.ip_address;
    document.getElementById('rtPort').value = r.port;
    document.getElementById('rtUsername').value = r.username;
    document.getElementById('rtPassword').value = '';
    document.getElementById('rtPassword').required = false;
    document.getElementById('rtPasswordHint').classList.remove('d-none');
    document.getElementById('rtLocation').value = r.location || '';
    document.getElementById('routerModalTitle').textContent = 'Edit Router';
    new bootstrap.Modal(document.getElementById('routerModal')).show();
}

document.getElementById('routerForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('routerId').value;
    const payload = {
        name: document.getElementById('rtName').value,
        type: document.getElementById('rtType').value,
        api_scheme: document.getElementById('rtApiScheme').value,
        ip_address: document.getElementById('rtIp').value,
        port: Number(document.getElementById('rtPort').value),
        username: document.getElementById('rtUsername').value,
        location: document.getElementById('rtLocation').value,
    };
    const password = document.getElementById('rtPassword').value;
    if (password) payload.password = password;

    try {
        const body = await nethubFetch(id ? `/api/owner/routers/${id}` : '/api/owner/routers', {
            method: id ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        if (!body.success) { showMessage('error', body.message); return; }

        bootstrap.Modal.getInstance(document.getElementById('routerModal')).hide();
        showMessage('success', body.message);
        loadRouters();
    } catch (err) {
        if (err.message !== 'Unauthenticated') showMessage('error', 'Gagal menyimpan router.');
    }
});

async function deleteRouter(id, name) {
    if (!confirm(`Yakin ingin menghapus router "${name}"?`)) return;

    try {
        const body = await nethubFetch(`/api/owner/routers/${id}`, { method: 'DELETE' });
        showMessage(body.success ? 'success' : 'error', body.message);
        loadRouters();
    } catch (err) {
        if (err.message !== 'Unauthenticated') showMessage('error', 'Gagal menghapus router.');
    }
}

loadRouters();
</script>
<?= $this->endSection() ?>
