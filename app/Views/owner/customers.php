<?= $this->extend('layouts/dashboard') ?>
<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h1 class="fs-3 mb-1">Pelanggan</h1>
                <p class="mb-0 text-muted">Kelola data pelanggan internet Anda</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#customerModal" onclick="openCreateModal()">
                <i class="ti ti-plus"></i> Tambah Pelanggan
            </button>
        </div>
    </div>
</div>

<div id="pageError" class="alert alert-danger d-none"></div>
<div id="pageSuccess" class="alert alert-success d-none"></div>

<div class="d-flex gap-2 mb-3 flex-wrap justify-content-between">
    <input type="text" id="searchInput" class="form-control" placeholder="Cari nama, kode, telepon..." style="max-width: 280px;">
    <select id="statusFilter" class="form-select" style="max-width: 180px;">
        <option value="">Semua Status</option>
        <option value="active">Aktif</option>
        <option value="pending">Pending</option>
        <option value="suspended">Suspend</option>
        <option value="disabled">Nonaktif</option>
    </select>
</div>

<div class="card table-responsive">
    <table class="table mb-0 text-nowrap table-hover">
        <thead class="table-light border-light">
            <tr>
                <th>Kode</th>
                <th>Nama</th>
                <th>Telepon</th>
                <th>Paket</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody id="customerTableBody">
            <tr><td colspan="6" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span> Memuat data...</td></tr>
        </tbody>
    </table>
</div>
<nav class="mt-3">
    <ul class="pagination justify-content-center" id="pagination"></ul>
</nav>

<!-- Add/Edit Modal -->
<div class="modal fade" id="customerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="customerForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="customerModalTitle">Tambah Pelanggan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="customerId">
                    <div class="mb-3">
                        <label class="form-label">Nama</label>
                        <input type="text" id="custName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Telepon</label>
                        <input type="text" id="custPhone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" id="custEmail" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alamat</label>
                        <textarea id="custAddress" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tanggal Mulai Berlangganan
                            <span class="text-muted small">(dipakai sebagai patokan tanggal tagihan bulanan)</span>
                        </label>
                        <input type="date" id="custInstallDate" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Paket</label>
                        <select id="custPackage" class="form-select">
                            <option value="">- Belum ditentukan -</option>
                        </select>
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
let currentPage = 1;
let packages = [];
let searchTimeout = null;

const statusBadge = (status) => ({
    active: '<span class="badge bg-success-subtle text-success">Aktif</span>',
    pending: '<span class="badge bg-secondary-subtle text-secondary">Pending</span>',
    suspended: '<span class="badge bg-warning-subtle text-warning">Suspend</span>',
    disabled: '<span class="badge bg-danger-subtle text-danger">Nonaktif</span>',
}[status] || status);

function showMessage(type, msg) {
    const el = document.getElementById(type === 'error' ? 'pageError' : 'pageSuccess');
    el.textContent = msg;
    el.classList.remove('d-none');
    setTimeout(() => el.classList.add('d-none'), 4000);
}

async function loadPackages() {
    const body = await nethubFetch('/api/owner/packages?per_page=100');
    if (body.success) {
        packages = body.data.items;
        document.getElementById('custPackage').innerHTML = '<option value="">- Belum ditentukan -</option>' +
            packages.map(p => `<option value="${p.id}">${p.name} (Rp${Number(p.price).toLocaleString('id-ID')})</option>`).join('');
    }
}

async function loadCustomers(page = 1) {
    currentPage = page;
    const search = document.getElementById('searchInput').value;
    const status = document.getElementById('statusFilter').value;
    const params = new URLSearchParams({ page, per_page: 10 });
    if (search) params.set('search', search);
    if (status) params.set('status', status);

    const tbody = document.getElementById('customerTableBody');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span> Memuat data...</td></tr>';

    try {
        const body = await nethubFetch('/api/owner/customers?' + params.toString());
        if (!body.success) { showMessage('error', body.message); return; }

        const { items, pagination } = body.data;
        if (items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">Belum ada pelanggan.</td></tr>';
        } else {
            tbody.innerHTML = items.map(c => `
                <tr class="align-middle">
                    <td>${c.customer_code}</td>
                    <td>${c.name}</td>
                    <td>${c.phone || '-'}</td>
                    <td>${c.package_name || '-'}</td>
                    <td>${statusBadge(c.status)}</td>
                    <td>
                        <a href="#" class="me-2" title="Edit" onclick="openEditModal(${c.id}); return false;"><i class="ti ti-edit"></i></a>
                        ${c.status === 'suspended'
                            ? `<a href="#" class="link-success me-2" title="Aktifkan" onclick="changeStatus(${c.id}, 'enable'); return false;"><i class="ti ti-player-play"></i></a>`
                            : `<a href="#" class="link-warning me-2" title="Suspend" onclick="changeStatus(${c.id}, 'suspend'); return false;"><i class="ti ti-player-pause"></i></a>`}
                        <a href="#" class="link-danger" title="Hapus" onclick="deleteCustomer(${c.id}, '${c.name.replace(/'/g, "\\'")}'); return false;"><i class="ti ti-trash"></i></a>
                    </td>
                </tr>
            `).join('');
        }
        renderPagination(pagination);
    } catch (err) {
        if (err.message !== 'Unauthenticated') showMessage('error', 'Gagal memuat data pelanggan.');
    }
}

function renderPagination(p) {
    const el = document.getElementById('pagination');
    if (p.total_pages <= 1) { el.innerHTML = ''; return; }
    let html = '';
    for (let i = 1; i <= p.total_pages; i++) {
        html += `<li class="page-item ${i === p.page ? 'active' : ''}"><a class="page-link" href="#" onclick="loadCustomers(${i}); return false;">${i}</a></li>`;
    }
    el.innerHTML = html;
}

function openCreateModal() {
    document.getElementById('customerForm').reset();
    document.getElementById('customerId').value = '';
    document.getElementById('customerModalTitle').textContent = 'Tambah Pelanggan';
}

async function openEditModal(id) {
    const body = await nethubFetch('/api/owner/customers/' + id);
    if (!body.success) { showMessage('error', body.message); return; }
    const c = body.data;
    document.getElementById('customerId').value = c.id;
    document.getElementById('custName').value = c.name;
    document.getElementById('custPhone').value = c.phone || '';
    document.getElementById('custEmail').value = c.email || '';
    document.getElementById('custAddress').value = c.address || '';
    document.getElementById('custInstallDate').value = c.installation_date || '';
    document.getElementById('custPackage').value = c.package_id || '';
    document.getElementById('customerModalTitle').textContent = 'Edit Pelanggan';
    new bootstrap.Modal(document.getElementById('customerModal')).show();
}

document.getElementById('customerForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('customerId').value;
    const payload = {
        name: document.getElementById('custName').value,
        phone: document.getElementById('custPhone').value,
        email: document.getElementById('custEmail').value,
        address: document.getElementById('custAddress').value,
        installation_date: document.getElementById('custInstallDate').value || null,
        package_id: document.getElementById('custPackage').value || null,
    };

    try {
        const body = await nethubFetch(id ? `/api/owner/customers/${id}` : '/api/owner/customers', {
            method: id ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        if (!body.success) { showMessage('error', body.message); return; }

        bootstrap.Modal.getInstance(document.getElementById('customerModal')).hide();
        showMessage('success', body.message);
        loadCustomers(currentPage);
    } catch (err) {
        if (err.message !== 'Unauthenticated') showMessage('error', 'Gagal menyimpan data.');
    }
});

async function changeStatus(id, action) {
    const label = action === 'suspend' ? 'suspend' : 'aktifkan';
    if (!confirm(`Yakin ingin ${label} pelanggan ini?`)) return;

    try {
        const body = await nethubFetch(`/api/owner/customers/${id}/${action}`, { method: 'POST' });
        showMessage(body.success ? 'success' : 'error', body.message);
        loadCustomers(currentPage);
    } catch (err) {
        if (err.message !== 'Unauthenticated') showMessage('error', 'Gagal mengubah status.');
    }
}

async function deleteCustomer(id, name) {
    if (!confirm(`Yakin ingin menghapus pelanggan "${name}"? Tindakan ini tidak dapat dibatalkan.`)) return;

    try {
        const body = await nethubFetch(`/api/owner/customers/${id}`, { method: 'DELETE' });
        showMessage(body.success ? 'success' : 'error', body.message);
        loadCustomers(currentPage);
    } catch (err) {
        if (err.message !== 'Unauthenticated') showMessage('error', 'Gagal menghapus pelanggan.');
    }
}

document.getElementById('searchInput').addEventListener('input', () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => loadCustomers(1), 400);
});
document.getElementById('statusFilter').addEventListener('change', () => loadCustomers(1));

loadPackages();
loadCustomers();
</script>
<?= $this->endSection() ?>
