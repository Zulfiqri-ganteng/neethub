<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>

<div class="container d-flex align-items-center justify-content-center min-vh-100">
    <div class="card" style="max-width:420px; width:100%;">
        <div class="card-body p-5">
            <div class="text-center mb-3">
                <i class="ti ti-lock-question fs-1 text-primary"></i>
                <h1 class="card-title mb-1 h5 mt-2">Lupa Password</h1>
                <p class="text-muted small">Masukkan email akun Anda, kami kirim link reset password</p>
            </div>

            <div id="formError" class="alert alert-danger d-none"></div>
            <div id="formSuccess" class="alert alert-success d-none"></div>

            <form id="forgotForm" class="needs-validation" novalidate>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input id="email" type="email" class="form-control" placeholder="nama@email.com" required autofocus>
                </div>
                <button class="btn btn-primary w-100" type="submit">
                    <span id="formSpinner" class="spinner-border spinner-border-sm d-none me-1"></span>
                    Kirim Link Reset
                </button>
            </form>

            <p class="text-center text-muted small mt-3 mb-0">
                <a href="/login">Kembali ke halaman masuk</a>
            </p>
        </div>
    </div>
</div>

<script>
document.getElementById('forgotForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const errBox = document.getElementById('formError');
    const okBox = document.getElementById('formSuccess');
    const spinner = document.getElementById('formSpinner');
    errBox.classList.add('d-none');
    okBox.classList.add('d-none');
    spinner.classList.remove('d-none');

    try {
        const res = await fetch('/api/auth/forgot-password', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: document.getElementById('email').value }),
        });
        const body = await res.json();
        spinner.classList.add('d-none');

        if (!body.success) {
            errBox.textContent = body.message || 'Gagal mengirim link reset.';
            errBox.classList.remove('d-none');
            return;
        }

        okBox.textContent = body.message;
        okBox.classList.remove('d-none');
        document.getElementById('forgotForm').reset();
    } catch (err) {
        spinner.classList.add('d-none');
        errBox.textContent = 'Tidak dapat menghubungi server.';
        errBox.classList.remove('d-none');
    }
});
</script>

<?= $this->endSection() ?>
