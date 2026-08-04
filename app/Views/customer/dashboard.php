<?= $this->extend('layouts/customer') ?>
<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="mb-4">
            <h1 class="fs-3 mb-1">Dashboard</h1>
            <p class="mb-0 text-muted" id="welcomeText">&nbsp;</p>
        </div>
    </div>
</div>

<div id="pageError" class="alert alert-danger d-none"></div>

<div id="dashboardBody" class="row g-3">
    <div class="col-12 text-center text-muted py-5"><span class="spinner-border spinner-border-sm"></span> Memuat data...</div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.getElementById('welcomeText').textContent = 'Halo, ' + (localStorage.getItem('nethub_name') || '') + '.';

const statusBadge = (s) => ({
    active: '<span class="badge bg-success-subtle text-success">Aktif</span>',
    pending: '<span class="badge bg-secondary-subtle text-secondary">Pending</span>',
    suspended: '<span class="badge bg-warning-subtle text-warning">Suspend</span>',
    disabled: '<span class="badge bg-danger-subtle text-danger">Nonaktif</span>',
}[s] || s);

function formatRp(n) { return 'Rp' + Number(n).toLocaleString('id-ID'); }

(async () => {
    try {
        const body = await nethubFetch('/api/customer/dashboard');
        const box = document.getElementById('dashboardBody');

        if (!body.success) {
            document.getElementById('pageError').textContent = body.message;
            document.getElementById('pageError').classList.remove('d-none');
            box.innerHTML = '';
            return;
        }

        const { customer, package: pkg, next_due } = body.data;

        let dueHtml = '<p class="text-muted mb-0">Tidak ada tagihan yang perlu dibayar.</p>';
        if (next_due) {
            dueHtml = `
                <p class="mb-1">No. Tagihan: <strong>${next_due.invoice_number}</strong></p>
                <p class="mb-1">Jumlah: <strong>${formatRp(next_due.total_amount)}</strong></p>
                <p class="mb-3">Jatuh Tempo: <strong>${next_due.due_date}</strong></p>
                <a href="/customer/invoices" class="btn btn-primary btn-sm">Bayar Sekarang</a>`;
        }

        box.innerHTML = `
            <div class="col-12 col-md-6">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <h6 class="mb-3"><i class="ti ti-user me-1"></i> Status Akun</h6>
                        <p class="mb-1">Nama: <strong>${customer.name}</strong></p>
                        <p class="mb-1">Kode Pelanggan: <strong>${customer.customer_code}</strong></p>
                        <p class="mb-0">Status: ${statusBadge(customer.status)}</p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <h6 class="mb-3"><i class="ti ti-wifi me-1"></i> Paket Internet</h6>
                        ${pkg
                            ? `<p class="mb-1">${pkg.name}</p><p class="mb-0 text-muted small">${pkg.speed_download}/${pkg.speed_upload} Mbps &middot; ${formatRp(pkg.price)}/bulan</p>`
                            : '<p class="text-muted mb-0">Belum ada paket ditentukan.</p>'}
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="card">
                    <div class="card-body p-4">
                        <h6 class="mb-3"><i class="ti ti-receipt me-1"></i> Tagihan Terdekat</h6>
                        ${dueHtml}
                    </div>
                </div>
            </div>
        `;
    } catch (err) {
        if (err.message !== 'Unauthenticated') {
            document.getElementById('pageError').textContent = 'Gagal memuat dashboard.';
            document.getElementById('pageError').classList.remove('d-none');
        }
    }
})();
</script>
<?= $this->endSection() ?>
