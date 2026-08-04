<?= $this->extend('layouts/dashboard') ?>
<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="mb-4">
            <h1 class="fs-3 mb-1">Pengaturan</h1>
            <p class="mb-0 text-muted">Profil usaha, rekening/QRIS, WhatsApp, dan payment gateway</p>
        </div>
    </div>
</div>

<div id="pageError" class="alert alert-danger d-none"></div>
<div id="pageSuccess" class="alert alert-success d-none"></div>

<div id="loadingBox" class="text-center py-5 text-muted"><span class="spinner-border spinner-border-sm"></span> Memuat pengaturan...</div>

<div id="settingsForms" class="d-none">
    <ul class="nav nav-tabs mb-3 flex-nowrap overflow-auto text-nowrap" id="settingsTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabProfile" type="button">Profil Usaha</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabLogo" type="button">Logo &amp; Brand</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabBank" type="button">Bank &amp; QRIS</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabWhatsapp" type="button">WhatsApp</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabGateway" type="button">Payment Gateway</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabBackup" type="button">Backup &amp; Restore</button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- Profil Usaha -->
        <div class="tab-pane fade show active" id="tabProfile" role="tabpanel">
            <div class="card">
                <div class="card-body p-4">
                    <form id="profileForm">
                        <div class="mb-3">
                            <label class="form-label">Nama Usaha</label>
                            <input type="text" id="setName" class="form-control" required minlength="3">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">WhatsApp (kontak usaha)</label>
                                <input type="text" id="setWhatsapp" class="form-control" placeholder="628123456789">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" id="setEmail" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Alamat</label>
                            <textarea id="setAddress" class="form-control" rows="2"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Simpan Profil</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Logo & Brand -->
        <div class="tab-pane fade" id="tabLogo" role="tabpanel">
            <div class="card">
                <div class="card-body p-4">
                    <h5 class="mb-3">Logo &amp; Brand</h5>
                    <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center gap-3">
                        <div id="logoPreviewBox" class="border rounded d-flex align-items-center justify-content-center flex-shrink-0" style="width: 80px; height: 80px; overflow: hidden; background: #f8f9fa;">
                            <img id="logoPreview" src="" alt="Logo" class="d-none" style="max-width: 100%; max-height: 100%;">
                            <i id="logoPlaceholder" class="ti ti-photo text-muted fs-3"></i>
                        </div>
                        <div class="flex-fill w-100">
                            <input type="file" id="logoFile" class="form-control form-control-sm mb-2" accept="image/jpeg,image/png,image/webp,image/svg+xml">
                            <div class="d-flex gap-2 flex-wrap">
                                <button type="button" class="btn btn-primary btn-sm" onclick="uploadLogo()"><i class="ti ti-upload"></i> Unggah Logo</button>
                                <button type="button" class="btn btn-outline-danger btn-sm d-none" id="removeLogoBtn" onclick="removeLogo()"><i class="ti ti-trash"></i> Hapus</button>
                            </div>
                            <p class="text-muted small mb-0 mt-2">JPG/PNG/WEBP/SVG, maksimal 2MB. Tampil di sidebar, laporan, dan header PDF tagihan.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bank & QRIS -->
        <div class="tab-pane fade" id="tabBank" role="tabpanel">
            <div class="card">
                <div class="card-body p-4">
                    <h5 class="mb-3">Rekening Bank &amp; QRIS</h5>
                    <form id="bankForm">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Nama Bank</label>
                                <input type="text" id="setBankName" class="form-control">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">No. Rekening</label>
                                <input type="text" id="setBankAccountNumber" class="form-control">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Atas Nama</label>
                                <input type="text" id="setBankAccountName" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kode QRIS Statis <span class="text-muted small">(dari PSP/bank tempat Anda terdaftar merchant QRIS)</span></label>
                            <textarea id="setQrisCode" class="form-control font-monospace" rows="3" placeholder="00020101021126..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Simpan Bank &amp; QRIS</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- WhatsApp Gateway -->
        <div class="tab-pane fade" id="tabWhatsapp" role="tabpanel">
            <div class="card">
                <div class="card-body p-4">
                    <h5 class="mb-1">WhatsApp Gateway</h5>
                    <p class="text-muted small mb-2">Status: <span id="waStatus">-</span></p>
                    <div class="alert alert-info small">
                        <strong>Cara kerjanya:</strong> pesan (reminder tagihan, konfirmasi bayar, broadcast)
                        dikirim dari <strong>nomor WhatsApp usaha Anda sendiri</strong>, bukan nomor NetHub.
                        Untuk menghubungkannya:
                        <ol class="mb-0 mt-1 ps-3">
                            <li>Daftar akun gratis di <a href="https://fonnte.com" target="_blank" rel="noopener">fonnte.com</a></li>
                            <li>Di dashboard Fonnte, hubungkan nomor WhatsApp Anda (scan QR, seperti WhatsApp Web)</li>
                            <li>Salin <em>device token</em> dari Fonnte, tempel di bawah ini</li>
                        </ol>
                        Selama belum diisi, pesan tetap diproses sistem tapi <strong>tidak benar-benar
                        terkirim ke WhatsApp</strong> (hanya tercatat di log server).
                    </div>
                    <form id="waForm">
                        <div class="mb-3">
                            <label class="form-label">Fonnte Device Token <span class="text-muted small">(kosongkan jika tidak diubah)</span></label>
                            <input type="text" id="setWaToken" class="form-control" placeholder="Tempel token dari dashboard Fonnte">
                        </div>
                        <button type="submit" class="btn btn-primary">Simpan WhatsApp Gateway</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Payment Gateway -->
        <div class="tab-pane fade" id="tabGateway" role="tabpanel">
            <div class="card">
                <div class="card-body p-4">
                    <h5 class="mb-1">Payment Gateway</h5>
                    <p class="text-muted small mb-3">Status saat ini: <span id="gatewayStatus">-</span></p>
                    <form id="gatewayForm">
                        <div class="mb-3">
                            <label class="form-label">Pilih Gateway</label>
                            <select id="setGateway" class="form-select">
                                <option value="">- Tidak Diubah -</option>
                                <option value="midtrans">Midtrans</option>
                                <option value="xendit">Xendit</option>
                            </select>
                        </div>

                        <div id="midtransFields" class="d-none">
                            <div class="mb-3">
                                <label class="form-label">Server Key</label>
                                <input type="text" id="mtServerKey" class="form-control" placeholder="SB-Mid-server-xxxxx">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Client Key</label>
                                <input type="text" id="mtClientKey" class="form-control" placeholder="SB-Mid-client-xxxxx">
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="mtProduction">
                                <label class="form-check-label" for="mtProduction">Mode Production</label>
                            </div>
                        </div>

                        <div id="xenditFields" class="d-none">
                            <div class="mb-3">
                                <label class="form-label">Secret Key</label>
                                <input type="text" id="xnSecretKey" class="form-control" placeholder="xnd_development_xxxxx">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Callback Verification Token</label>
                                <input type="text" id="xnCallbackToken" class="form-control">
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="xnProduction">
                                <label class="form-check-label" for="xnProduction">Mode Production</label>
                            </div>
                        </div>

                        <p class="text-muted small" id="gatewayHint">Pilih gateway untuk mengatur/mengganti kredensial.</p>
                        <button type="submit" class="btn btn-primary">Simpan Payment Gateway</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Backup & Restore -->
        <div class="tab-pane fade" id="tabBackup" role="tabpanel">
            <div class="card">
                <div class="card-body p-4">
                    <h5 class="mb-3">Backup &amp; Restore</h5>
                    <div class="alert alert-info small">
                        Backup berisi data <strong>paket, pelanggan, dan tagihan</strong> milik tenant Anda
                        (kredensial seperti password PPPoE/router dan payment gateway <strong>tidak</strong>
                        disertakan, demi keamanan — perlu diisi ulang manual bila migrasi ke instalasi baru).
                        Pembayaran tidak direstore otomatis (riwayat transaksi finansial sengaja tidak
                        diimpor ulang) — hanya paket, pelanggan, dan tagihan. Restore <strong>tidak pernah
                        menimpa/menghapus data yang sudah ada</strong> — data dengan kode/nomor yang sama
                        akan dilewati (skip), bukan ditimpa.
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="downloadBackup()"><i class="ti ti-download"></i> Download Backup</button>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <input type="file" id="restoreFile" class="form-control form-control-sm" accept="application/json">
                            <button type="button" class="btn btn-outline-warning btn-sm text-nowrap" onclick="restoreBackup()"><i class="ti ti-upload"></i> Restore</button>
                        </div>
                    </div>
                    <div id="restoreResult" class="mt-3"></div>
                </div>
            </div>
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
    setTimeout(() => el.classList.add('d-none'), 5000);
}

async function saveSettings(payload, successMsg) {
    try {
        const body = await nethubFetch('/api/owner/settings', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        if (!body.success) { showMessage('error', body.message); return null; }
        showMessage('success', successMsg);
        return body.data;
    } catch (err) {
        if (err.message !== 'Unauthenticated') showMessage('error', 'Gagal menyimpan pengaturan.');
        return null;
    }
}

document.getElementById('setGateway').addEventListener('change', (e) => {
    document.getElementById('midtransFields').classList.toggle('d-none', e.target.value !== 'midtrans');
    document.getElementById('xenditFields').classList.toggle('d-none', e.target.value !== 'xendit');
});

document.getElementById('profileForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = await saveSettings({
        name: document.getElementById('setName').value,
        whatsapp: document.getElementById('setWhatsapp').value,
        email: document.getElementById('setEmail').value,
        address: document.getElementById('setAddress').value,
    }, 'Profil usaha berhasil disimpan.');

    // Nama usaha berubah -> sidebar harus langsung ikut berubah, bukan
    // cuma setelah reload halaman.
    if (data) nethubApplyBranding();
});

document.getElementById('bankForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    await saveSettings({
        bank_name: document.getElementById('setBankName').value,
        bank_account_number: document.getElementById('setBankAccountNumber').value,
        bank_account_name: document.getElementById('setBankAccountName').value,
        qris_static_code: document.getElementById('setQrisCode').value,
    }, 'Rekening & QRIS berhasil disimpan.');
});

document.getElementById('waForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const token = document.getElementById('setWaToken').value;

    // Blank = "don't touch" (same as router password on edit), never
    // silently disconnects an already-working WhatsApp connection.
    if (!token) { showMessage('error', 'Isi device token untuk menyimpan, atau biarkan halaman ini tanpa submit jika tidak ada perubahan.'); return; }

    const data = await saveSettings({ whatsapp_token: token }, 'WhatsApp gateway berhasil disimpan.');
    if (data) {
        renderWaStatus(data);
        document.getElementById('setWaToken').value = '';
    }
});

document.getElementById('gatewayForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const gateway = document.getElementById('setGateway').value;
    const payload = {};

    // Only touch payment_gateway/config when the owner actually picked one
    // — sending an empty string for the ENUM column would error, and we
    // never want to silently wipe an already-working config.
    if (gateway === 'midtrans') {
        payload.payment_gateway = 'midtrans';
        payload.payment_gateway_config = {
            server_key: document.getElementById('mtServerKey').value,
            client_key: document.getElementById('mtClientKey').value,
            is_production: document.getElementById('mtProduction').checked,
        };
    } else if (gateway === 'xendit') {
        payload.payment_gateway = 'xendit';
        payload.payment_gateway_config = {
            secret_key: document.getElementById('xnSecretKey').value,
            callback_token: document.getElementById('xnCallbackToken').value,
            is_production: document.getElementById('xnProduction').checked,
        };
    } else {
        showMessage('error', 'Pilih gateway terlebih dahulu untuk menyimpan.');
        return;
    }

    const data = await saveSettings(payload, 'Payment gateway berhasil disimpan.');
    if (data) renderGatewayStatus(data);
});

function renderGatewayStatus(s) {
    const label = { midtrans: 'Midtrans', xendit: 'Xendit' }[s.payment_gateway] || 'Belum diatur';
    const configured = s.payment_gateway_configured
        ? '<span class="badge bg-success-subtle text-success">Terkonfigurasi</span>'
        : '<span class="badge bg-secondary-subtle text-secondary">Belum diatur</span>';
    document.getElementById('gatewayStatus').innerHTML = `${label} ${configured}`;
}

function renderLogo(s) {
    const preview = document.getElementById('logoPreview');
    const placeholder = document.getElementById('logoPlaceholder');
    const removeBtn = document.getElementById('removeLogoBtn');

    if (s.logo) {
        preview.src = '/' + s.logo + '?t=' + Date.now();
        preview.classList.remove('d-none');
        placeholder.classList.add('d-none');
        removeBtn.classList.remove('d-none');
    } else {
        preview.classList.add('d-none');
        placeholder.classList.remove('d-none');
        removeBtn.classList.add('d-none');
    }
}

async function uploadLogo() {
    const file = document.getElementById('logoFile').files[0];
    if (!file) { showMessage('error', 'Pilih file logo terlebih dahulu.'); return; }

    const formData = new FormData();
    formData.append('logo', file);

    try {
        let res = await fetch('/api/owner/settings/logo', {
            method: 'POST',
            headers: { Authorization: 'Bearer ' + nethubToken },
            body: formData,
        });
        if (res.status === 401 && await nethubRefreshToken()) {
            res = await fetch('/api/owner/settings/logo', {
                method: 'POST',
                headers: { Authorization: 'Bearer ' + nethubToken },
                body: formData,
            });
        }
        const body = await res.json();
        if (!body.success) { showMessage('error', body.message); return; }

        showMessage('success', body.message);
        renderLogo(body.data);
        document.getElementById('logoFile').value = '';
        nethubApplyBranding();
    } catch (err) {
        showMessage('error', 'Gagal mengunggah logo.');
    }
}

async function removeLogo() {
    if (!confirm('Hapus logo saat ini?')) return;

    try {
        const body = await nethubFetch('/api/owner/settings/logo', { method: 'DELETE' });
        if (!body.success) { showMessage('error', body.message); return; }
        showMessage('success', body.message);
        renderLogo({ logo: null });
        nethubApplyBranding();
    } catch (err) {
        if (err.message !== 'Unauthenticated') showMessage('error', 'Gagal menghapus logo.');
    }
}

async function downloadBackup() {
    try {
        let res = await fetch('/api/owner/backup/export', {
            headers: { Authorization: 'Bearer ' + nethubToken },
        });
        if (res.status === 401 && await nethubRefreshToken()) {
            res = await fetch('/api/owner/backup/export', {
                headers: { Authorization: 'Bearer ' + nethubToken },
            });
        }
        if (!res.ok) { showMessage('error', 'Gagal mengunduh backup.'); return; }

        const blob = await res.blob();
        const disposition = res.headers.get('Content-Disposition') || '';
        const match = disposition.match(/filename="?([^"]+)"?/);
        const filename = match ? match[1] : 'nethub_backup.json';

        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
    } catch (err) {
        showMessage('error', 'Gagal mengunduh backup.');
    }
}

async function restoreBackup() {
    const file = document.getElementById('restoreFile').files[0];
    if (!file) { showMessage('error', 'Pilih file backup (.json) terlebih dahulu.'); return; }
    if (!confirm('Restore data dari file backup ini? Data yang sudah ada tidak akan ditimpa, hanya data baru yang akan ditambahkan.')) return;

    const formData = new FormData();
    formData.append('backup_file', file);

    try {
        let res = await fetch('/api/owner/backup/restore', {
            method: 'POST',
            headers: { Authorization: 'Bearer ' + nethubToken },
            body: formData,
        });
        if (res.status === 401 && await nethubRefreshToken()) {
            res = await fetch('/api/owner/backup/restore', {
                method: 'POST',
                headers: { Authorization: 'Bearer ' + nethubToken },
                body: formData,
            });
        }
        const body = await res.json();
        if (!body.success) { showMessage('error', body.message); return; }

        showMessage('success', body.message);
        const r = body.data;
        document.getElementById('restoreResult').innerHTML = `
            <div class="alert alert-success small mb-0">
                Paket: ${r.packages.created} ditambahkan, ${r.packages.skipped} dilewati (sudah ada)<br>
                Pelanggan: ${r.customers.created} ditambahkan, ${r.customers.skipped} dilewati (sudah ada)<br>
                Tagihan: ${r.invoices.created} ditambahkan, ${r.invoices.skipped} dilewati (sudah ada)
            </div>`;
        document.getElementById('restoreFile').value = '';
    } catch (err) {
        showMessage('error', 'Gagal melakukan restore.');
    }
}

function renderWaStatus(s) {
    document.getElementById('waStatus').innerHTML = s.whatsapp_configured
        ? '<span class="badge bg-success-subtle text-success">Terhubung</span>'
        : '<span class="badge bg-secondary-subtle text-secondary">Belum terhubung — pesan hanya dicatat di log, tidak benar-benar terkirim</span>';
}

(async () => {
    try {
        const body = await nethubFetch('/api/owner/settings');
        if (!body.success) { showMessage('error', body.message); return; }
        const s = body.data;

        document.getElementById('setName').value = s.name || '';
        document.getElementById('setWhatsapp').value = s.whatsapp || '';
        document.getElementById('setEmail').value = s.email || '';
        document.getElementById('setAddress').value = s.address || '';
        renderLogo(s);

        document.getElementById('setBankName').value = s.bank_name || '';
        document.getElementById('setBankAccountNumber').value = s.bank_account_number || '';
        document.getElementById('setBankAccountName').value = s.bank_account_name || '';
        document.getElementById('setQrisCode').value = s.qris_static_code || '';

        renderWaStatus(s);
        renderGatewayStatus(s);

        document.getElementById('loadingBox').classList.add('d-none');
        document.getElementById('settingsForms').classList.remove('d-none');
    } catch (err) {
        if (err.message !== 'Unauthenticated') showMessage('error', 'Gagal memuat pengaturan.');
    }
})();
</script>
<?= $this->endSection() ?>
