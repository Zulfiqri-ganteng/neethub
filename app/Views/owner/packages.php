<?= $this->extend('layouts/dashboard') ?>
<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h1 class="fs-3 mb-1">Paket Internet</h1>
                <p class="mb-0 text-muted">Kelola paket & harga layanan Anda</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#packageModal" onclick="openCreateModal()">
                <i class="ti ti-plus"></i> Tambah Paket
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
                <th>Nama Paket</th>
                <th>Kecepatan</th>
                <th>Harga</th>
                <th>Siklus Tagihan</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody id="packageTableBody">
            <tr><td colspan="6" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span> Memuat data...</td></tr>
        </tbody>
    </table>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="packageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="packageForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="packageModalTitle">Tambah Paket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="packageId">
                    <div class="mb-3">
                        <label class="form-label">Nama Paket</label>
                        <input type="text" id="pkgName" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Download (Mbps)</label>
                            <input type="number" id="pkgDown" class="form-control" min="1" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Upload (Mbps)</label>
                            <input type="number" id="pkgUp" class="form-control" min="1" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Harga (Rp)</label>
                        <input type="number" id="pkgPrice" class="form-control" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Siklus Tagihan</label>
                        <select id="pkgCycle" class="form-select" required>
                            <option value="monthly">Bulanan</option>
                            <option value="weekly">Mingguan</option>
                            <option value="yearly">Tahunan</option>
                            <option value="custom">Custom (hari)</option>
                        </select>
                    </div>
                    <div class="mb-3 d-none" id="pkgCustomDaysWrap">
                        <label class="form-label">Jumlah Hari</label>
                        <input type="number" id="pkgCustomDays" class="form-control" min="1">
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="pkgActive" checked>
                        <label class="form-check-label" for="pkgActive">Aktif</label>
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
const cycleLabel = (c) => ({ monthly: 'Bulanan', weekly: 'Mingguan', yearly: 'Tahunan', custom: 'Custom' }[c] || c);

function showMessage(type, msg) {
    const el = document.getElementById(type === 'error' ? 'pageError' : 'pageSuccess');
    el.textContent = msg;
    el.classList.remove('d-none');
    setTimeout(() => el.classList.add('d-none'), 4000);
}

document.getElementById('pkgCycle').addEventListener('change', (e) => {
    document.getElementById('pkgCustomDaysWrap').classList.toggle('d-none', e.target.value !== 'custom');
});

async function loadPackages() {
    const tbody = document.getElementById('packageTableBody');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span> Memuat data...</td></tr>';

    try {
        const body = await nethubFetch('/api/owner/packages?per_page=100');
        if (!body.success) { showMessage('error', body.message); return; }

        const items = body.data.items;
        if (items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">Belum ada paket.</td></tr>';
            return;
        }
        tbody.innerHTML = items.map(p => `
            <tr class="align-middle">
                <td>${p.name}</td>
                <td>${p.speed_download}/${p.speed_upload} Mbps</td>
                <td>Rp${Number(p.price).toLocaleString('id-ID')}</td>
                <td>${cycleLabel(p.billing_cycle)}</td>
                <td>${Number(p.is_active) === 1
                    ? '<span class="badge bg-success-subtle text-success">Aktif</span>'
                    : '<span class="badge bg-secondary-subtle text-secondary">Nonaktif</span>'}</td>
                <td>
                    <a href="#" class="me-2" title="Edit" onclick="openEditModal(${p.id}); return false;"><i class="ti ti-edit"></i></a>
                    <a href="#" class="link-danger" title="Hapus" onclick="deletePackage(${p.id}, '${p.name.replace(/'/g, "\\'")}'); return false;"><i class="ti ti-trash"></i></a>
                </td>
            </tr>
        `).join('');
    } catch (err) {
        if (err.message !== 'Unauthenticated') showMessage('error', 'Gagal memuat data paket.');
    }
}

function openCreateModal() {
    document.getElementById('packageForm').reset();
    document.getElementById('packageId').value = '';
    document.getElementById('pkgCustomDaysWrap').classList.add('d-none');
    document.getElementById('packageModalTitle').textContent = 'Tambah Paket';
}

async function openEditModal(id) {
    const body = await nethubFetch('/api/owner/packages/' + id);
    if (!body.success) { showMessage('error', body.message); return; }
    const p = body.data;
    document.getElementById('packageId').value = p.id;
    document.getElementById('pkgName').value = p.name;
    document.getElementById('pkgDown').value = p.speed_download;
    document.getElementById('pkgUp').value = p.speed_upload;
    document.getElementById('pkgPrice').value = p.price;
    document.getElementById('pkgCycle').value = p.billing_cycle;
    document.getElementById('pkgCustomDays').value = p.custom_cycle_days || '';
    document.getElementById('pkgCustomDaysWrap').classList.toggle('d-none', p.billing_cycle !== 'custom');
    document.getElementById('pkgActive').checked = Number(p.is_active) === 1;
    document.getElementById('packageModalTitle').textContent = 'Edit Paket';
    new bootstrap.Modal(document.getElementById('packageModal')).show();
}

document.getElementById('packageForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('packageId').value;
    const payload = {
        name: document.getElementById('pkgName').value,
        speed_download: Number(document.getElementById('pkgDown').value),
        speed_upload: Number(document.getElementById('pkgUp').value),
        price: Number(document.getElementById('pkgPrice').value),
        billing_cycle: document.getElementById('pkgCycle').value,
        custom_cycle_days: document.getElementById('pkgCycle').value === 'custom'
            ? Number(document.getElementById('pkgCustomDays').value) : null,
        is_active: document.getElementById('pkgActive').checked ? 1 : 0,
    };

    try {
        const body = await nethubFetch(id ? `/api/owner/packages/${id}` : '/api/owner/packages', {
            method: id ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        if (!body.success) { showMessage('error', body.message); return; }

        bootstrap.Modal.getInstance(document.getElementById('packageModal')).hide();
        showMessage('success', body.message);
        loadPackages();
    } catch (err) {
        if (err.message !== 'Unauthenticated') showMessage('error', 'Gagal menyimpan paket.');
    }
});

async function deletePackage(id, name) {
    if (!confirm(`Yakin ingin menghapus paket "${name}"?`)) return;

    try {
        const body = await nethubFetch(`/api/owner/packages/${id}`, { method: 'DELETE' });
        showMessage(body.success ? 'success' : 'error', body.message);
        loadPackages();
    } catch (err) {
        if (err.message !== 'Unauthenticated') showMessage('error', 'Gagal menghapus paket.');
    }
}

loadPackages();
</script>
<?= $this->endSection() ?>
