<?= $this->extend('layouts/dashboard') ?>
<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="mb-4">
            <h1 class="fs-3 mb-1">Broadcast WhatsApp</h1>
            <p class="mb-0 text-muted">Kirim pesan ke banyak pelanggan sekaligus</p>
        </div>
    </div>
</div>

<div id="pageError" class="alert alert-danger d-none"></div>
<div id="pageSuccess" class="alert alert-success d-none"></div>

<div class="row">
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-body p-4">
                <form id="broadcastForm">
                    <div class="mb-3">
                        <label class="form-label">Target Penerima</label>
                        <select id="bcTarget" class="form-select">
                            <option value="all">Semua Pelanggan</option>
                            <option value="active">Pelanggan Aktif</option>
                            <option value="overdue">Pelanggan Menunggak</option>
                            <option value="custom">Pilih Manual</option>
                        </select>
                    </div>

                    <div class="mb-3 d-none" id="customPickerWrap">
                        <label class="form-label">Pilih Pelanggan</label>
                        <div class="border rounded p-2" style="max-height: 220px; overflow-y: auto;" id="customPicker">
                            <div class="text-muted small text-center py-2"><span class="spinner-border spinner-border-sm"></span> Memuat...</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Pesan</label>
                        <textarea id="bcMessage" class="form-control" rows="5" placeholder="Tulis pesan broadcast Anda..." required></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-send"></i> Kirim Broadcast
                    </button>
                </form>
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
    setTimeout(() => el.classList.add('d-none'), 6000);
}

let customersLoaded = false;

document.getElementById('bcTarget').addEventListener('change', async (e) => {
    const wrap = document.getElementById('customPickerWrap');
    if (e.target.value !== 'custom') { wrap.classList.add('d-none'); return; }
    wrap.classList.remove('d-none');
    if (customersLoaded) return;

    try {
        const body = await nethubFetch('/api/owner/customers?per_page=100&status=active');
        const picker = document.getElementById('customPicker');
        const items = body.data.items;
        picker.innerHTML = items.length === 0
            ? '<div class="text-muted small">Belum ada pelanggan aktif.</div>'
            : items.map(c => `
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="${c.id}" id="cust-${c.id}">
                    <label class="form-check-label" for="cust-${c.id}">${c.name} <span class="text-muted small">(${c.customer_code})</span></label>
                </div>
            `).join('');
        customersLoaded = true;
    } catch (err) {
        if (err.message !== 'Unauthenticated') showMessage('error', 'Gagal memuat daftar pelanggan.');
    }
});

document.getElementById('broadcastForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const target = document.getElementById('bcTarget').value;
    const message = document.getElementById('bcMessage').value;

    let customerIds = [];
    if (target === 'custom') {
        customerIds = Array.from(document.querySelectorAll('#customPicker input:checked')).map(el => Number(el.value));
        if (customerIds.length === 0) { showMessage('error', 'Pilih minimal satu pelanggan.'); return; }
    }

    if (!confirm('Yakin ingin mengirim broadcast ini?')) return;

    try {
        const body = await nethubFetch('/api/owner/broadcast/whatsapp', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ target, message, customer_ids: customerIds }),
        });
        if (!body.success) { showMessage('error', body.message); return; }

        showMessage('success', body.message);
        document.getElementById('broadcastForm').reset();
        document.getElementById('customPickerWrap').classList.add('d-none');
    } catch (err) {
        if (err.message !== 'Unauthenticated') showMessage('error', 'Gagal mengirim broadcast.');
    }
});
</script>
<?= $this->endSection() ?>
