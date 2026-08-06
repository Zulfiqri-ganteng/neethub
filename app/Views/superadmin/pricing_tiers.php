<?= $this->extend('layouts/super_admin') ?>
<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h1 class="fs-3 mb-1">Tier Harga Platform</h1>
                <p class="mb-0 text-muted">Biaya langganan platform bertingkat berdasarkan jumlah pelanggan tenant. Biaya flat di halaman Pengaturan hanya dipakai sebagai cadangan kalau tidak ada tier yang cocok.</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tierModal" onclick="openCreateModal()">
                <i class="ti ti-plus"></i> Tambah Tier
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
                <th>Rentang Pelanggan</th>
                <th>Biaya / Bulan</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody id="tierTableBody">
            <tr><td colspan="3" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span> Memuat data...</td></tr>
        </tbody>
    </table>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="tierModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="tierForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="tierModalTitle">Tambah Tier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="tierId">
                    <p class="text-muted small">Tenant dengan jumlah pelanggan dalam rentang ini akan ditagih sebesar biaya di bawah pada tagihan bulanan berikutnya. Rentang tidak boleh tumpang tindih dengan tier lain.</p>
                    <div class="mb-3">
                        <label class="form-label">Jumlah Pelanggan Minimum</label>
                        <input type="number" id="tierMin" class="form-control" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jumlah Pelanggan Maksimum</label>
                        <input type="number" id="tierMax" class="form-control" min="0" placeholder="Kosongkan untuk &quot;ke atas&quot;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Biaya / Bulan (Rp)</label>
                        <input type="number" id="tierFee" class="form-control" min="0" required>
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
function showMessage(type, msg) {
    const el = document.getElementById(type === 'error' ? 'pageError' : 'pageSuccess');
    el.textContent = msg;
    el.classList.remove('d-none');
    setTimeout(() => el.classList.add('d-none'), 4000);
}

function rangeLabel(t) {
    return t.max_customers !== null
        ? `${t.min_customers}–${t.max_customers} pelanggan`
        : `${t.min_customers} pelanggan ke atas`;
}

async function loadTiers() {
    const tbody = document.getElementById('tierTableBody');
    tbody.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm"></span> Memuat data...</td></tr>';

    try {
        const body = await nethubFetch('/api/superadmin/pricing-tiers');
        if (!body.success) { showMessage('error', body.message); return; }

        const items = body.data;
        if (items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-muted">Belum ada tier. Semua tenant memakai biaya flat di Pengaturan Platform.</td></tr>';
            return;
        }
        tbody.innerHTML = items.map(t => `
            <tr class="align-middle">
                <td>${rangeLabel(t)}</td>
                <td>Rp${Number(t.monthly_fee).toLocaleString('id-ID')}</td>
                <td>
                    <a href="#" class="me-2" title="Edit" onclick="openEditModal(${t.id}); return false;"><i class="ti ti-edit"></i></a>
                    <a href="#" class="link-danger" title="Hapus" onclick="deleteTier(${t.id}, '${rangeLabel(t).replace(/'/g, "\\'")}'); return false;"><i class="ti ti-trash"></i></a>
                </td>
            </tr>
        `).join('');
    } catch (err) {
        if (err.message !== 'Unauthenticated') showMessage('error', 'Gagal memuat data tier.');
    }
}

function openCreateModal() {
    document.getElementById('tierForm').reset();
    document.getElementById('tierId').value = '';
    document.getElementById('tierModalTitle').textContent = 'Tambah Tier';
}

async function openEditModal(id) {
    const body = await nethubFetch('/api/superadmin/pricing-tiers');
    if (!body.success) { showMessage('error', body.message); return; }
    const t = body.data.find(x => Number(x.id) === Number(id));
    if (!t) { showMessage('error', 'Tier tidak ditemukan.'); return; }

    document.getElementById('tierId').value = t.id;
    document.getElementById('tierMin').value = t.min_customers;
    document.getElementById('tierMax').value = t.max_customers ?? '';
    document.getElementById('tierFee').value = t.monthly_fee;
    document.getElementById('tierModalTitle').textContent = 'Edit Tier';
    new bootstrap.Modal(document.getElementById('tierModal')).show();
}

document.getElementById('tierForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('tierId').value;
    const maxVal = document.getElementById('tierMax').value;
    const payload = {
        min_customers: Number(document.getElementById('tierMin').value),
        max_customers: maxVal === '' ? null : Number(maxVal),
        monthly_fee: Number(document.getElementById('tierFee').value),
    };

    try {
        const body = await nethubFetch(id ? `/api/superadmin/pricing-tiers/${id}` : '/api/superadmin/pricing-tiers', {
            method: id ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        if (!body.success) { showMessage('error', body.message); return; }

        bootstrap.Modal.getInstance(document.getElementById('tierModal')).hide();
        showMessage('success', body.message);
        loadTiers();
    } catch (err) {
        if (err.message !== 'Unauthenticated') showMessage('error', 'Gagal menyimpan tier.');
    }
});

async function deleteTier(id, label) {
    if (!confirm(`Yakin ingin menghapus tier "${label}"?`)) return;

    try {
        const body = await nethubFetch(`/api/superadmin/pricing-tiers/${id}`, { method: 'DELETE' });
        showMessage(body.success ? 'success' : 'error', body.message);
        loadTiers();
    } catch (err) {
        if (err.message !== 'Unauthenticated') showMessage('error', 'Gagal menghapus tier.');
    }
}

loadTiers();
</script>
<?= $this->endSection() ?>
