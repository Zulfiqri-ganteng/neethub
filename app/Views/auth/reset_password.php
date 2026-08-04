<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>

<div class="container d-flex align-items-center justify-content-center min-vh-100">
    <div class="card" style="max-width:420px; width:100%;">
        <div class="card-body p-5">
            <div class="text-center mb-3">
                <i class="ti ti-lock fs-1 text-primary"></i>
                <h1 class="card-title mb-1 h5 mt-2">Atur Password Baru</h1>
                <p class="text-muted small">Link ini hanya berlaku 1 jam dan sekali pakai</p>
            </div>

            <div id="formError" class="alert alert-danger d-none"></div>
            <div id="formSuccess" class="alert alert-success d-none"></div>
            <div id="noTokenWarning" class="alert alert-warning d-none">
                Link tidak lengkap. Minta link reset baru lewat <a href="/forgot-password">halaman lupa password</a>.
            </div>

            <form id="resetForm" class="needs-validation" novalidate>
                <div class="mb-3">
                    <label for="password" class="form-label">Password Baru</label>
                    <input id="password" type="password" class="form-control" placeholder="Minimal 8 karakter" required minlength="8">
                </div>
                <div class="mb-3">
                    <label for="passwordConfirm" class="form-label">Ulangi Password Baru</label>
                    <input id="passwordConfirm" type="password" class="form-control" placeholder="Ulangi password" required minlength="8">
                </div>
                <button class="btn btn-primary w-100" type="submit">
                    <span id="formSpinner" class="spinner-border spinner-border-sm d-none me-1"></span>
                    Simpan Password Baru
                </button>
            </form>
        </div>
    </div>
</div>

<script>
const params = new URLSearchParams(window.location.search);
const email = params.get('email');
const token = params.get('token');

if (!email || !token) {
    document.getElementById('resetForm').classList.add('d-none');
    document.getElementById('noTokenWarning').classList.remove('d-none');
}

document.getElementById('resetForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const errBox = document.getElementById('formError');
    const okBox = document.getElementById('formSuccess');
    const spinner = document.getElementById('formSpinner');
    errBox.classList.add('d-none');
    okBox.classList.add('d-none');

    const password = document.getElementById('password').value;
    if (password !== document.getElementById('passwordConfirm').value) {
        errBox.textContent = 'Konfirmasi password tidak sama.';
        errBox.classList.remove('d-none');
        return;
    }

    spinner.classList.remove('d-none');

    try {
        const res = await fetch('/api/auth/reset-password', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, token, password }),
        });
        const body = await res.json();
        spinner.classList.add('d-none');

        if (!body.success) {
            errBox.textContent = body.message || 'Gagal mereset password.';
            errBox.classList.remove('d-none');
            return;
        }

        document.getElementById('resetForm').classList.add('d-none');
        okBox.textContent = body.message + ' Mengalihkan ke halaman masuk...';
        okBox.classList.remove('d-none');
        setTimeout(() => window.location.href = '/login', 2000);
    } catch (err) {
        spinner.classList.add('d-none');
        errBox.textContent = 'Tidak dapat menghubungi server.';
        errBox.classList.remove('d-none');
    }
});
</script>

<?= $this->endSection() ?>
