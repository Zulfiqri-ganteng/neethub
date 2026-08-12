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
            <div id="googleHintBox" class="alert alert-info small d-none">
                Akun Google Anda belum terdaftar. Klik tombol Google di bawah untuk melengkapi data usaha.
            </div>

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

            <div id="googleAuthBlock" class="d-none">
                <div class="d-flex align-items-center my-3">
                    <hr class="flex-grow-1">
                    <span class="text-muted small px-2">atau</span>
                    <hr class="flex-grow-1">
                </div>
                <div id="googleSignInButton" class="d-flex justify-content-center"></div>
            </div>

            <!-- Shown after Google confirms the email is new — asks only for what
                 Google itself can't provide (business name, phone). -->
            <div id="googleDetailsForm" class="d-none">
                <div id="googleDetailsError" class="alert alert-danger d-none"></div>
                <p class="text-muted small mb-3">
                    Masuk dengan <strong id="googleDetailsEmail"></strong>. Lengkapi data usaha untuk
                    menyelesaikan pendaftaran.
                </p>
                <div class="mb-3">
                    <label for="gTenantName" class="form-label">Nama Bisnis WiFi/ISP</label>
                    <input id="gTenantName" type="text" class="form-control" placeholder="mis. RT/RW Net Melati" required minlength="3">
                </div>
                <div class="mb-3">
                    <label for="gOwnerName" class="form-label">Nama Anda</label>
                    <input id="gOwnerName" type="text" class="form-control" required minlength="2">
                </div>
                <div class="mb-3">
                    <label for="gPhone" class="form-label">No. WhatsApp (opsional)</label>
                    <input id="gPhone" type="text" class="form-control" placeholder="08xxxxxxxxxx">
                </div>
                <button class="btn btn-primary w-100" type="button" onclick="submitGoogleRegistration()">
                    <span id="googleDetailsSpinner" class="spinner-border spinner-border-sm d-none me-1"></span>
                    Daftar dengan Google
                </button>
                <button class="btn btn-link w-100 mt-1" type="button" onclick="cancelGoogleDetails()">Batal</button>
            </div>

            <p id="loginLinkRow" class="text-center text-muted small mt-3 mb-0">
                Sudah punya akun? <a href="/login">Masuk di sini</a>
            </p>
        </div>
    </div>
</div>

<script>
let googleIdToken = null;

if (new URLSearchParams(window.location.search).get('google') === '1') {
    document.getElementById('googleHintBox').classList.remove('d-none');
}

async function initGoogleSignIn() {
    try {
        const res = await fetch('/api/auth/google/config');
        const body = await res.json();
        const cfg = body.data;
        if (!cfg || !cfg.enabled || typeof google === 'undefined') return;

        google.accounts.id.initialize({
            client_id: cfg.client_id,
            callback: handleGoogleCredential,
        });
        document.getElementById('googleAuthBlock').classList.remove('d-none');
        google.accounts.id.renderButton(document.getElementById('googleSignInButton'), {
            type: 'standard', theme: 'outline', size: 'large', shape: 'pill',
            text: 'signup_with', logo_alignment: 'center', width: 340,
        });
    } catch (err) { /* Google signup is an optional convenience — never block the normal form. */ }
}
// The GSI library loads with `async`, so its own timing isn't guaranteed
// relative to this inline script — wait for the window `load` event (fires
// only once every subresource, including the async GSI script, has
// finished) instead of racing a same-tick `typeof google` check.
window.addEventListener('load', initGoogleSignIn);

async function handleGoogleCredential(response) {
    googleIdToken = response.credential;

    try {
        const res = await fetch('/api/auth/google', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_token: googleIdToken }),
        });
        const body = await res.json();

        if (!body.success) {
            document.getElementById('registerError').textContent = body.message || 'Login dengan Google gagal.';
            document.getElementById('registerError').classList.remove('d-none');
            return;
        }

        if (body.data.needs_registration) {
            document.getElementById('registerForm').classList.add('d-none');
            document.getElementById('googleAuthBlock').classList.add('d-none');
            document.getElementById('loginLinkRow').classList.add('d-none');
            document.getElementById('googleHintBox').classList.add('d-none');
            document.getElementById('googleDetailsEmail').textContent = body.data.google.email;
            document.getElementById('gOwnerName').value = body.data.google.name || '';
            document.getElementById('googleDetailsForm').classList.remove('d-none');
            return;
        }

        // Email already has an account — this was actually a login, not a signup.
        localStorage.setItem('nethub_access_token', body.data.access_token);
        localStorage.setItem('nethub_refresh_token', body.data.refresh_token);
        localStorage.setItem('nethub_role', body.data.user.role_slug);
        localStorage.setItem('nethub_name', body.data.user.name);
        const role = body.data.user.role_slug;
        window.location.href = role === 'pelanggan' ? '/customer/dashboard' : role === 'super_admin' ? '/superadmin/tenants' : '/dashboard';
    } catch (err) {
        document.getElementById('registerError').textContent = 'Tidak dapat menghubungi server.';
        document.getElementById('registerError').classList.remove('d-none');
    }
}

function cancelGoogleDetails() {
    googleIdToken = null;
    document.getElementById('googleDetailsForm').classList.add('d-none');
    document.getElementById('registerForm').classList.remove('d-none');
    document.getElementById('googleAuthBlock').classList.remove('d-none');
    document.getElementById('loginLinkRow').classList.remove('d-none');
}

async function submitGoogleRegistration() {
    const errBox = document.getElementById('googleDetailsError');
    const spinner = document.getElementById('googleDetailsSpinner');
    errBox.classList.add('d-none');
    spinner.classList.remove('d-none');

    try {
        const res = await fetch('/api/auth/google', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id_token: googleIdToken,
                tenant_name: document.getElementById('gTenantName').value,
                owner_name: document.getElementById('gOwnerName').value,
                phone: document.getElementById('gPhone').value,
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
}

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
