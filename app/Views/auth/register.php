<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>

<div class="container d-flex align-items-center justify-content-center min-vh-100 py-4">
    <div class="card" style="max-width:460px; width:100%;">
        <div class="card-body p-5">
            <div class="text-center mb-3">
                <i class="ti ti-wifi fs-1 text-primary"></i>
                <h1 class="card-title mb-1 h5 mt-2">Daftar NetHub</h1>
                <p class="text-muted small">Gratis <?= (int) ($trialDays ?? 30) ?> hari, tanpa kartu kredit</p>
            </div>

            <div id="registerError" class="alert alert-danger d-none"></div>

            <form id="registerForm" class="needs-validation" novalidate>
                <div class="mb-3">
                    <label for="tenantName" class="form-label">Nama Bisnis WiFi/ISP</label>
                    <input id="tenantName" type="text" class="form-control" placeholder="mis. RT/RW Net Melati" required minlength="3">
                </div>
                <div class="mb-3">
                    <label for="ownerName" class="form-label">Nama Anda</label>
                    <input id="ownerName" type="text" class="form-control" placeholder="Nama lengkap" required minlength="2">
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input id="email" type="email" class="form-control" placeholder="nama@email.com" required autofocus>
                </div>
                <div class="mb-3">
                    <label for="phone" class="form-label">No. WhatsApp (opsional)</label>
                    <input id="phone" type="text" class="form-control" placeholder="08xxxxxxxxxx">
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input id="password" type="password" class="form-control" placeholder="Minimal 8 karakter" required minlength="8">
                </div>
                <button class="btn btn-primary w-100" type="submit">
                    <span id="registerSpinner" class="spinner-border spinner-border-sm d-none me-1"></span>
                    Daftar Sekarang
                </button>
            </form>

            <p class="text-center text-muted small mt-3 mb-0">
                Sudah punya akun? <a href="/login">Masuk di sini</a>
            </p>
        </div>
    </div>
</div>

<script>
document.getElementById('registerForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const errBox = document.getElementById('registerError');
    const spinner = document.getElementById('registerSpinner');
    errBox.classList.add('d-none');
    spinner.classList.remove('d-none');

    try {
        const res = await fetch('/api/auth/register', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                tenant_name: document.getElementById('tenantName').value,
                owner_name: document.getElementById('ownerName').value,
                email: document.getElementById('email').value,
                phone: document.getElementById('phone').value,
                password: document.getElementById('password').value,
            }),
        });
        const body = await res.json();

        if (!body.success) {
            const firstError = body.errors ? Object.values(body.errors)[0] : null;
            errBox.textContent = firstError || body.message || 'Registrasi gagal.';
            errBox.classList.remove('d-none');
            spinner.classList.add('d-none');
            return;
        }

        localStorage.setItem('nethub_access_token', body.data.access_token);
        localStorage.setItem('nethub_refresh_token', body.data.refresh_token);
        localStorage.setItem('nethub_role', body.data.user.role_slug);
        localStorage.setItem('nethub_name', body.data.user.name);
        window.location.href = '/dashboard';
    } catch (err) {
        errBox.textContent = 'Tidak dapat menghubungi server.';
        errBox.classList.remove('d-none');
        spinner.classList.add('d-none');
    }
});
</script>

<?= $this->endSection() ?>
