<?= $this->extend('layouts/super_admin') ?>
<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <h1 class="fs-3 mb-1">Pengaturan Platform</h1>
        <p class="mb-4 text-muted">Harga langganan, masa trial, masa tenggang, dan pengiriman email berlaku untuk semua tenant</p>
    </div>
</div>

<div id="pageError" class="alert alert-danger d-none"></div>
<div id="pageSuccess" class="alert alert-success d-none"></div>

<div class="card mb-3" style="max-width: 560px;">
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

<div class="card mb-3" style="max-width: 560px;">
    <div class="card-body p-4">
        <h5 class="mb-1">Tier Harga Berdasarkan Jumlah Pelanggan</h5>
        <p class="text-muted small mb-3">
            Atur biaya langganan platform bertingkat sesuai jumlah pelanggan tenant — biaya di atas
            hanya dipakai sebagai cadangan kalau tidak ada tier yang cocok.
        </p>
        <a href="/superadmin/pricing-tiers" class="btn btn-outline-primary">
            <i class="ti ti-stack-2"></i> Kelola Tier Harga
        </a>
    </div>
</div>

<div class="card" style="max-width: 560px;">
    <div class="card-body p-4">
        <h5 class="mb-1">Pengiriman Email (SMTP)</h5>
        <p class="text-muted small mb-3">
            Dipakai untuk email sistem (mis. reset password) ke semua role. Isi pakai email domain/hosting kamu sendiri.
            <span id="smtpConfiguredBadge"></span>
        </p>
        <form id="smtpForm">
            <div class="mb-3">
                <label class="form-label">SMTP Host</label>
                <input type="text" class="form-control" id="smtpHost" placeholder="mis. mail.kangmuslim.com">
            </div>
            <div class="row">
                <div class="col-6 mb-3">
                    <label class="form-label">Port</label>
                    <input type="number" class="form-control" id="smtpPort" placeholder="587">
                </div>
                <div class="col-6 mb-3">
                    <label class="form-label">Enkripsi</label>
                    <select class="form-select" id="smtpCrypto">
                        <option value="tls">TLS</option>
                        <option value="ssl">SSL</option>
                        <option value="">Tanpa enkripsi</option>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Username SMTP</label>
                <input type="text" class="form-control" id="smtpUser" placeholder="mis. noreply@neethub.my.id">
            </div>
            <div class="mb-3">
                <label class="form-label">Password SMTP</label>
                <input type="password" class="form-control" id="smtpPass" placeholder="Kosongkan jika tidak diganti">
            </div>
            <div class="mb-3">
                <label class="form-label">Nama Pengirim</label>
                <input type="text" class="form-control" id="smtpFromName" placeholder="NetHub">
            </div>
            <div class="mb-3">
                <label class="form-label">Email Pengirim</label>
                <input type="email" class="form-control" id="smtpFromEmail" placeholder="noreply@neethub.my.id">
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Simpan SMTP</button>
                <button type="button" class="btn btn-outline-secondary" onclick="testSmtp()">
                    <span id="testSmtpSpinner" class="spinner-border spinner-border-sm d-none me-1"></span>
                    Test SMTP
                </button>
            </div>
            <div class="mt-3">
                <label class="form-label small">Kirim email percobaan ke</label>
                <input type="email" class="form-control form-control-sm" id="testSmtpTarget" placeholder="Kosongkan untuk kirim ke email Anda sendiri">
            </div>
        </form>
    </div>
</div>

<div class="card mt-3" style="max-width: 560px;">
    <div class="card-body p-4">
        <h5 class="mb-1">Login Google</h5>
        <p class="text-muted small mb-3">
            Client ID dari Google Cloud Console (jenis "Web application"). Kosongkan untuk
            menonaktifkan tombol "Masuk/Daftar dengan Google" di seluruh aplikasi (web &amp; Android).
        </p>
        <form id="googleForm">
            <div class="mb-3">
                <label class="form-label">Google Client ID</label>
                <input type="text" class="form-control" id="googleClientId" placeholder="xxxxx.apps.googleusercontent.com">
            </div>
            <button type="submit" class="btn btn-primary">Simpan Login Google</button>
        </form>
    </div>
</div>

<div class="card mt-3" style="max-width: 560px;">
    <div class="card-body p-4">
        <h5 class="mb-1">Aplikasi Android</h5>
        <p class="text-muted small mb-1" id="mobileApkInfo">Belum ada APK terdaftar.</p>
        <p class="text-muted small mb-3">
            Versi/build APK diisi otomatis saat rilis (bukan diketik manual di sini). Hanya batas
            minimal wajib-update dan mode pemeliharaan yang diatur dari sini.
        </p>
        <form id="mobileAppForm">
            <div class="mb-3">
                <label class="form-label">Wajib Update Minimal Build</label>
                <input type="number" class="form-control" id="mobileMinBuild" min="1">
            </div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="mobileMaintenance">
                <label class="form-check-label" for="mobileMaintenance">Mode Pemeliharaan (blokir sementara akses aplikasi Android)</label>
            </div>
            <div class="mb-3">
                <label class="form-label">Pesan Pemeliharaan</label>
                <textarea class="form-control" id="mobileMaintenanceMsg" rows="2"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Simpan Aplikasi Android</button>
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
    setTimeout(() => el.classList.add('d-none'), 8000);
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

    document.getElementById('smtpHost').value = body.data.smtp_host || '';
    document.getElementById('smtpPort').value = body.data.smtp_port || 587;
    document.getElementById('smtpCrypto').value = body.data.smtp_crypto ?? 'tls';
    document.getElementById('smtpUser').value = body.data.smtp_user || '';
    document.getElementById('smtpFromName').value = body.data.smtp_from_name || '';
    document.getElementById('smtpFromEmail').value = body.data.smtp_from_email || '';
    document.getElementById('smtpConfiguredBadge').innerHTML = body.data.smtp_configured
        ? '<span class="badge bg-success-subtle text-success">Sudah diatur</span>'
        : '<span class="badge bg-secondary-subtle text-secondary">Belum diatur</span>';

    document.getElementById('googleClientId').value = body.data.google_client_id || '';

    document.getElementById('mobileMinBuild').value = body.data.mobile_min_build || 1;
    document.getElementById('mobileMaintenance').checked = !!body.data.mobile_maintenance;
    document.getElementById('mobileMaintenanceMsg').value = body.data.mobile_maintenance_msg || '';
    document.getElementById('mobileApkInfo').textContent = body.data.mobile_apk_build
        ? `Rilis terpasang saat ini: v${body.data.mobile_apk_version || '-'} (build ${body.data.mobile_apk_build})${body.data.mobile_apk_notes ? ' — ' + body.data.mobile_apk_notes : ''}`
        : 'Belum ada APK terdaftar — upload lewat FTP lalu daftarkan lewat endpoint apk/register.';
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

function smtpFormValues() {
    return {
        smtp_host: document.getElementById('smtpHost').value,
        smtp_port: document.getElementById('smtpPort').value || 587,
        smtp_crypto: document.getElementById('smtpCrypto').value,
        smtp_user: document.getElementById('smtpUser').value,
        smtp_pass: document.getElementById('smtpPass').value,
        smtp_from_name: document.getElementById('smtpFromName').value,
        smtp_from_email: document.getElementById('smtpFromEmail').value,
    };
}

document.getElementById('smtpForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const body = await nethubFetch('/api/superadmin/settings', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(smtpFormValues()),
    });

    showMessage(body.success ? 'success' : 'error', body.message);
    document.getElementById('smtpPass').value = '';
    if (body.success) loadSettings();
});

document.getElementById('googleForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const body = await nethubFetch('/api/superadmin/settings', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            google_client_id: document.getElementById('googleClientId').value,
        }),
    });

    showMessage(body.success ? 'success' : 'error', body.message);
});

document.getElementById('mobileAppForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const body = await nethubFetch('/api/superadmin/settings', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            mobile_min_build: document.getElementById('mobileMinBuild').value,
            mobile_maintenance: document.getElementById('mobileMaintenance').checked,
            mobile_maintenance_msg: document.getElementById('mobileMaintenanceMsg').value,
        }),
    });

    showMessage(body.success ? 'success' : 'error', body.message);
});

async function testSmtp() {
    const spinner = document.getElementById('testSmtpSpinner');
    spinner.classList.remove('d-none');

    const body = await nethubFetch('/api/superadmin/settings/test-smtp', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            ...smtpFormValues(),
            to_email: document.getElementById('testSmtpTarget').value || undefined,
        }),
    });

    spinner.classList.add('d-none');
    showMessage(body.success ? 'success' : 'error', body.message);
}

loadSettings();
</script>
<?= $this->endSection() ?>
