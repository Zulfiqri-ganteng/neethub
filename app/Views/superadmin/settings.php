<?= $this->extend('layouts/super_admin') ?>
<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <h1 class="fs-3 mb-1">Pengaturan Platform</h1>
        <p class="mb-4 text-muted">Harga langganan, masa trial, dan masa tenggang berlaku untuk semua tenant</p>
    </div>
</div>

<div id="pageError" class="alert alert-danger d-none"></div>
<div id="pageSuccess" class="alert alert-success d-none"></div>

<div class="card" style="max-width: 560px;">
    <div class="card-body p-4">
        <form id="settingsForm">
            <div class="mb-3">
                <label class="form-label">Biaya Langganan / Bulan (Rp)</label>
                <input type="number" class="form-control" id="monthlyFee" min="0" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Masa Trial (hari)</label>
                <input type="number" class="form-control" id="trialDays" min="1" required>
                <div class="form-text">Berlaku untuk tenant baru yang mendaftar setelah pengaturan ini disimpan.</div>
            </div>
            <div class="mb-3">
                <label class="form-label">Masa Tenggang Setelah Jatuh Tempo (hari)</label>
                <input type="number" class="form-control" id="graceDays" min="0" required>
                <div class="form-text">Tenant otomatis disuspend jika tagihan belum lunas melewati masa tenggang ini.</div>
            </div>
            <hr>
            <p class="text-muted small mb-2">Rekening tujuan transfer manual (ditampilkan ke tenant saat bayar)</p>
            <div class="mb-3">
                <label class="form-label">Nama Bank</label>
                <input type="text" class="form-control" id="bankName">
            </div>
            <div class="mb-3">
                <label class="form-label">Nomor Rekening</label>
                <input type="text" class="form-control" id="bankAccountNumber">
            </div>
            <div class="mb-3">
                <label class="form-label">Atas Nama</label>
                <input type="text" class="form-control" id="bankAccountName">
            </div>
            <button type="submit" class="btn btn-primary">Simpan Pengaturan</button>
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

async function loadSettings() {
    const body = await nethubFetch('/api/superadmin/settings');
    if (!body.success) { showMessage('error', body.message); return; }

    document.getElementById('monthlyFee').value = body.data.monthly_fee;
    document.getElementById('trialDays').value = body.data.trial_days;
    document.getElementById('graceDays').value = body.data.grace_days;
    document.getElementById('bankName').value = body.data.bank_name || '';
    document.getElementById('bankAccountNumber').value = body.data.bank_account_number || '';
    document.getElementById('bankAccountName').value = body.data.bank_account_name || '';
}

document.getElementById('settingsForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const body = await nethubFetch('/api/superadmin/settings', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            monthly_fee: document.getElementById('monthlyFee').value,
            trial_days: document.getElementById('trialDays').value,
            grace_days: document.getElementById('graceDays').value,
            bank_name: document.getElementById('bankName').value,
            bank_account_number: document.getElementById('bankAccountNumber').value,
            bank_account_name: document.getElementById('bankAccountName').value,
        }),
    });

    showMessage(body.success ? 'success' : 'error', body.message);
});

loadSettings();
</script>
<?= $this->endSection() ?>
