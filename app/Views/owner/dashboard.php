<?= $this->extend('layouts/dashboard') ?>
<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="mb-6">
            <h1 class="fs-3 mb-1">Dashboard</h1>
            <p class="text-muted" id="welcomeText">&nbsp;</p>
        </div>
    </div>
</div>

<div id="statsError" class="alert alert-danger d-none"></div>

<div class="row g-3 mb-3" id="statsRow">
    <div class="col-12 text-center text-muted py-5">
        <span class="spinner-border spinner-border-sm"></span> Memuat data...
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.getElementById('welcomeText').textContent = 'Halo, ' + (localStorage.getItem('nethub_name') || '') + '.';

function statCard(label, value, icon) {
    return `<div class="col-12 col-sm-6 col-md-3">
        <div class="card h-100">
            <div class="card-body p-4">
                <h6 class="mb-4"><i class="ti ${icon} me-1"></i> ${label}</h6>
                <h3 class="mb-1 fw-bold">${value}</h3>
            </div>
        </div>
    </div>`;
}

(async () => {
    try {
        const body = await nethubFetch('/api/owner/dashboard/stats');

        if (!body.success) {
            document.getElementById('statsError').textContent = body.message;
            document.getElementById('statsError').classList.remove('d-none');
            document.getElementById('statsRow').innerHTML = '';
            return;
        }

        const s = body.data;
        document.getElementById('statsRow').innerHTML =
            statCard('Pelanggan Aktif', s.customers.active, 'ti-users') +
            statCard('Tagihan Belum Lunas', s.invoices.unpaid, 'ti-file-invoice') +
            statCard('Tagihan Overdue', s.invoices.overdue, 'ti-alert-triangle') +
            statCard('Revenue Bulan Ini', 'Rp' + Number(s.revenue_this_month).toLocaleString('id-ID'), 'ti-cash');
    } catch (err) {
        if (err.message !== 'Unauthenticated') {
            document.getElementById('statsError').textContent = 'Tidak dapat memuat data dashboard.';
            document.getElementById('statsError').classList.remove('d-none');
        }
    }
})();
</script>
<?= $this->endSection() ?>
