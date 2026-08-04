<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>

<div class="container d-flex align-items-center justify-content-center min-vh-100">
    <div class="card" style="max-width:420px; width:100%;">
        <div class="card-body p-5">
            <div class="text-center mb-3">
                <i class="ti ti-wifi fs-1 text-primary"></i>
                <h1 class="card-title mb-1 h5 mt-2">NetHub</h1>
                <p class="text-muted small">Masuk ke akun Anda</p>
            </div>

            <div id="loginError" class="alert alert-danger d-none"></div>

            <form id="loginForm" class="needs-validation" novalidate>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input id="email" type="email" class="form-control" placeholder="nama@email.com" required autofocus>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input id="password" type="password" class="form-control" placeholder="Password" required minlength="6">
                </div>
                <button class="btn btn-primary w-100" type="submit">
                    <span id="loginSpinner" class="spinner-border spinner-border-sm d-none me-1"></span>
                    Masuk
                </button>
            </form>

            <p class="text-center text-muted small mt-3 mb-0">
                Pemilik bisnis WiFi/ISP baru? <a href="/register">Daftar gratis di sini</a>
            </p>
        </div>
    </div>
</div>

<script>
document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const errBox = document.getElementById('loginError');
    const spinner = document.getElementById('loginSpinner');
    errBox.classList.add('d-none');
    spinner.classList.remove('d-none');

    try {
        const res = await fetch('/api/auth/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                email: document.getElementById('email').value,
                password: document.getElementById('password').value,
            }),
        });
        const body = await res.json();

        if (!body.success) {
            errBox.textContent = body.message || 'Login gagal.';
            errBox.classList.remove('d-none');
            spinner.classList.add('d-none');
            return;
        }

        localStorage.setItem('nethub_access_token', body.data.access_token);
        localStorage.setItem('nethub_refresh_token', body.data.refresh_token);
        localStorage.setItem('nethub_role', body.data.user.role_slug);
        localStorage.setItem('nethub_name', body.data.user.name);
        const role = body.data.user.role_slug;
        window.location.href = role === 'pelanggan' ? '/customer/dashboard' : role === 'super_admin' ? '/superadmin/tenants' : '/dashboard';
    } catch (err) {
        errBox.textContent = 'Tidak dapat menghubungi server.';
        errBox.classList.remove('d-none');
        spinner.classList.add('d-none');
    }
});
</script>

<?= $this->endSection() ?>
