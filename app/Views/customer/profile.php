<?= $this->extend('layouts/customer') ?>
<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="mb-4">
            <h1 class="fs-3 mb-1">Profil Saya</h1>
            <p class="mb-0 text-muted">Lihat &amp; ubah data akun Anda</p>
        </div>
    </div>
</div>

<div id="pageError" class="alert alert-danger d-none"></div>
<div id="pageSuccess" class="alert alert-success d-none"></div>

<div id="loadingBox" class="text-center py-5 text-muted"><span class="spinner-border spinner-border-sm"></span> Memuat profil...</div>

<div class="card d-none" id="profileCard">
    <div class="card-body p-4">
        <form id="profileForm">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nama</label>
                    <input type="text" id="pfName" class="form-control" required minlength="2">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Telepon</label>
                    <input type="text" id="pfPhone" class="form-control">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Email <span class="text-muted small">(tidak dapat diubah)</span></label>
                <input type="email" id="pfEmail" class="form-control" disabled>
            </div>
            <div class="mb-3">
                <label class="form-label">Alamat</label>
                <textarea id="pfAddress" class="form-control" rows="2"></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Password Baru <span class="text-muted small">(kosongkan jika tidak diubah)</span></label>
                <input type="password" id="pfPassword" class="form-control" minlength="8">
            </div>
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </form>
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

(async () => {
    try {
        const body = await nethubFetch('/api/customer/profile');
        if (!body.success) { showMessage('error', body.message); return; }
        const { user, customer } = body.data;

        document.getElementById('pfName').value = user.name || '';
        document.getElementById('pfPhone').value = user.phone || '';
        document.getElementById('pfEmail').value = user.email || '';
        document.getElementById('pfAddress').value = (customer && customer.address) || '';

        document.getElementById('loadingBox').classList.add('d-none');
        document.getElementById('profileCard').classList.remove('d-none');
    } catch (err) {
        if (err.message !== 'Unauthenticated') showMessage('error', 'Gagal memuat profil.');
    }
})();

document.getElementById('profileForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const payload = {
        name: document.getElementById('pfName').value,
        phone: document.getElementById('pfPhone').value,
        address: document.getElementById('pfAddress').value,
    };
    const password = document.getElementById('pfPassword').value;
    if (password) payload.password = password;

    try {
        const body = await nethubFetch('/api/customer/profile', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        if (!body.success) { showMessage('error', body.message); return; }

        showMessage('success', body.message);
        document.getElementById('pfPassword').value = '';
        localStorage.setItem('nethub_name', payload.name);
    } catch (err) {
        if (err.message !== 'Unauthenticated') showMessage('error', 'Gagal menyimpan profil.');
    }
});
</script>
<?= $this->endSection() ?>
