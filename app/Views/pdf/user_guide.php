<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 70px 55px; }
    body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #222; line-height: 1.5; }
    h1 { font-size: 22px; color: #1E4E8C; margin: 0 0 6px; }
    h2 { font-size: 17px; color: #1E4E8C; border-bottom: 2px solid #1E4E8C; padding-bottom: 4px; margin: 0 0 14px; }
    h3 { font-size: 13.5px; color: #1E4E8C; margin: 18px 0 6px; }
    p { margin: 6px 0; text-align: justify; }
    .cover { text-align: center; padding-top: 200px; }
    .cover h1 { font-size: 30px; }
    .cover .sub { font-size: 15px; color: #555; margin-top: 6px; }
    .cover .meta { margin-top: 60px; font-size: 11px; color: #888; }
    .chapter { page-break-before: always; }
    .toc-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    .toc-table td { padding: 6px 4px; font-size: 12.5px; }
    .toc-table td.no { width: 30px; color: #1E4E8C; font-weight: bold; }
    ol, ul { margin: 6px 0; padding-left: 22px; }
    li { margin-bottom: 4px; }
    table.ref { width: 100%; border-collapse: collapse; margin: 10px 0 14px; }
    table.ref th, table.ref td { border: 1px solid #ccc; padding: 5px 8px; font-size: 11px; text-align: left; vertical-align: top; }
    table.ref th { background: #f0f4f9; color: #1E4E8C; }
    table.ref td.center, table.ref th.center { text-align: center; }
    .check { color: #198754; font-weight: bold; }
    .cross { color: #ccc; }
    .box-tip { background: #eef6ff; border-left: 4px solid #1E4E8C; padding: 8px 12px; margin: 10px 0; font-size: 11.5px; }
    .box-warn { background: #fff8ec; border-left: 4px solid #d9822b; padding: 8px 12px; margin: 10px 0; font-size: 11.5px; }
    .box-note { background: #f4f4f4; border-left: 4px solid #888; padding: 8px 12px; margin: 10px 0; font-size: 11.5px; }
    code { background: #f0f0f0; padding: 1px 5px; border-radius: 3px; font-size: 11px; }
    .small { font-size: 10.5px; color: #777; }
    .footer-note { text-align: center; font-size: 10px; color: #999; margin-top: 40px; }
</style>
</head>
<body>

<!-- COVER -->
<div class="cover">
    <h1>Panduan Penggunaan NetHub</h1>
    <div class="sub">Platform Manajemen Pelanggan, Tagihan &amp; Jaringan<br>untuk RT/RW Net, ISP Kecil, dan WISP</div>
    <div class="meta">
        Versi Web - Dibuat <?= date('d F Y') ?><br>
        Dokumen ini menjelaskan seluruh menu pada dashboard Pemilik/Admin dan Portal Pelanggan.
    </div>
</div>

<!-- TOC -->
<div class="chapter">
<h2>Daftar Isi</h2>
<table class="toc-table">
    <tr><td class="no">1.</td><td>Pendahuluan</td></tr>
    <tr><td class="no">2.</td><td>Peran Pengguna &amp; Hak Akses</td></tr>
    <tr><td class="no">3.</td><td>Masuk ke Sistem (Login)</td></tr>
    <tr><td class="no">4.</td><td>Dashboard</td></tr>
    <tr><td class="no">5.</td><td>Kelola Pelanggan</td></tr>
    <tr><td class="no">6.</td><td>Kelola Paket Internet</td></tr>
    <tr><td class="no">7.</td><td>Tagihan</td></tr>
    <tr><td class="no">8.</td><td>Pembayaran</td></tr>
    <tr><td class="no">9.</td><td>Router</td></tr>
    <tr><td class="no">10.</td><td>Monitoring</td></tr>
    <tr><td class="no">11.</td><td>Broadcast WhatsApp</td></tr>
    <tr><td class="no">12.</td><td>Laporan</td></tr>
    <tr><td class="no">13.</td><td>Pengaturan</td></tr>
    <tr><td class="no">14.</td><td>Portal Pelanggan</td></tr>
    <tr><td class="no">15.</td><td>Catatan Penting &amp; Pertanyaan Umum</td></tr>
</table>
</div>

<!-- 1. PENDAHULUAN -->
<div class="chapter">
<h2>1. Pendahuluan</h2>
<p>
    <strong>NetHub</strong> adalah platform manajemen untuk penyedia layanan internet skala kecil
    dan menengah  -  RT/RW Net, ISP kecil, maupun WISP. Satu platform ini menggantikan pencatatan
    manual (buku/Excel/chat WhatsApp) untuk mengelola pelanggan, paket internet, tagihan,
    pembayaran, perangkat router, hingga komunikasi ke pelanggan.
</p>
<p>
    NetHub bersifat <strong>multi-tenant</strong>: setiap usaha (disebut <em>tenant</em>) memiliki
    data pelanggan, tagihan, dan pengaturannya sendiri yang sepenuhnya terpisah dari usaha lain
    yang juga memakai NetHub. Data satu usaha tidak akan pernah terlihat oleh usaha lain.
</p>
<h3>Dua Sisi Aplikasi</h3>
<ul>
    <li><strong>Dashboard Pemilik/Staf</strong>  -  untuk pemilik usaha dan tim (admin, teknisi,
        kasir) mengelola seluruh operasional: pelanggan, tagihan, pembayaran, router, dsb.</li>
    <li><strong>Portal Pelanggan</strong>  -  untuk pelanggan akhir melihat tagihan mereka sendiri,
        melakukan pembayaran, dan mengubah data profil.</li>
</ul>
<div class="box-tip">
    <strong>Tips:</strong> Dokumen ini mengikuti urutan menu pada sidebar dashboard, dari atas ke
    bawah, sehingga bisa dibaca berurutan seperti alur kerja sehari-hari.
</div>
</div>

<!-- 2. PERAN PENGGUNA -->
<div class="chapter">
<h2>2. Peran Pengguna &amp; Hak Akses</h2>
<p>Setiap pengguna di sisi Pemilik/Staf memiliki salah satu dari 4 peran berikut. Hak akses
diatur otomatis oleh sistem  -  mencoba mengakses fitur di luar hak akses akan menampilkan pesan
"tidak diizinkan", bukan menyembunyikan menunya.</p>
<table class="ref">
    <tr>
        <th>Menu</th><th class="center">Owner</th><th class="center">Admin</th>
        <th class="center">Teknisi</th><th class="center">Kasir</th>
    </tr>
    <tr><td>Dashboard</td><td class="center check">Ya</td><td class="center check">Ya</td><td class="center check">Ya</td><td class="center check">Ya</td></tr>
    <tr><td>Pelanggan (lihat)</td><td class="center check">Ya</td><td class="center check">Ya</td><td class="center check">Ya</td><td class="center check">Ya</td></tr>
    <tr><td>Pelanggan (tambah/edit)</td><td class="center check">Ya</td><td class="center check">Ya</td><td class="center cross">-</td><td class="center cross">-</td></tr>
    <tr><td>Pelanggan (hapus)</td><td class="center check">Ya</td><td class="center cross">-</td><td class="center cross">-</td><td class="center cross">-</td></tr>
    <tr><td>Suspend/Aktifkan Pelanggan</td><td class="center check">Ya</td><td class="center check">Ya</td><td class="center check">Ya</td><td class="center cross">-</td></tr>
    <tr><td>Paket Internet</td><td class="center check">Ya</td><td class="center check">Ya</td><td class="center cross">-</td><td class="center cross">-</td></tr>
    <tr><td>Tagihan (lihat/generate)</td><td class="center check">Ya</td><td class="center check">Ya</td><td class="center cross">-</td><td class="center check">Ya</td></tr>
    <tr><td>Pembayaran (verifikasi)</td><td class="center check">Ya</td><td class="center check">Ya</td><td class="center cross">-</td><td class="center check">Ya</td></tr>
    <tr><td>Router</td><td class="center check">Ya</td><td class="center check">Ya<span class="small">*</span></td><td class="center check">Ya<span class="small">*</span></td><td class="center cross">-</td></tr>
    <tr><td>Monitoring</td><td class="center check">Ya</td><td class="center check">Ya</td><td class="center check">Ya</td><td class="center cross">-</td></tr>
    <tr><td>Broadcast WhatsApp</td><td class="center check">Ya</td><td class="center check">Ya</td><td class="center cross">-</td><td class="center cross">-</td></tr>
    <tr><td>Laporan</td><td class="center check">Ya</td><td class="center check">Ya</td><td class="center cross">-</td><td class="center check">Ya</td></tr>
    <tr><td>Pengaturan</td><td class="center check">Ya</td><td class="center cross">-</td><td class="center cross">-</td><td class="center cross">-</td></tr>
</table>
<p class="small">* Admin &amp; Teknisi bisa mengelola/test router, tapi hanya Owner yang bisa menghapus router.</p>
<div class="box-note">
    Pengguna dengan peran <strong>Pelanggan</strong> tidak memakai dashboard ini sama sekali  - 
    mereka masuk ke <strong>Portal Pelanggan</strong> yang terpisah (lihat Bab 14).
</div>
</div>

<!-- 3. LOGIN -->
<div class="chapter">
<h2>3. Masuk ke Sistem (Login)</h2>
<ol>
    <li>Buka alamat NetHub di browser, lalu isi <strong>Email</strong> dan <strong>Kata Sandi</strong>.</li>
    <li>Usaha baru dapat mendaftar sendiri (self-registration)  -  pendaftar pertama otomatis
        menjadi <strong>Owner</strong> untuk usaha tersebut, lalu bisa menambahkan akun Admin/
        Teknisi/Kasir dari dalam sistem.</li>
    <li>Setelah login, sistem otomatis mengarahkan ke Dashboard (untuk Pemilik/Staf) atau ke
        Portal Pelanggan (untuk Pelanggan), tergantung jenis akun.</li>
</ol>
<div class="box-tip">
    <strong>Sesi login otomatis diperpanjang.</strong> Anda tidak perlu login ulang setiap
    beberapa menit  -  sistem memperbarui sesi secara otomatis di belakang layar selama Anda masih
    aktif menggunakan aplikasi. Anda hanya perlu login ulang jika benar-benar tidak membuka
    aplikasi selama beberapa hari.
</div>
</div>

<!-- 4. DASHBOARD -->
<div class="chapter">
<h2>4. Dashboard</h2>
<p>Halaman pertama setelah login. Menampilkan ringkasan kondisi usaha secara sekilas:</p>
<ul>
    <li><strong>Jumlah pelanggan</strong>  -  total, aktif, pending, suspend</li>
    <li><strong>Status tagihan</strong>  -  belum lunas, terlambat, lunas</li>
    <li><strong>Pendapatan bulan berjalan</strong>  -  total dari tagihan yang sudah lunas</li>
    <li><strong>Jumlah paket aktif</strong></li>
    <li><strong>Pelanggan yang sedang online</strong>  -  dari data router (lihat Bab 10)</li>
</ul>
<p class="small">Data dashboard di-cache selama 5 menit demi kecepatan, jadi perubahan terbaru
mungkin baru terlihat setelah beberapa menit.</p>
</div>

<!-- 5. PELANGGAN -->
<div class="chapter">
<h2>5. Kelola Pelanggan</h2>
<h3>Menambah Pelanggan Baru</h3>
<ol>
    <li>Klik <strong>Tambah Pelanggan</strong>.</li>
    <li>Isi Nama, Telepon, Email, Alamat.</li>
    <li>Isi <strong>Tanggal Mulai Berlangganan</strong>  -  tanggal ini menjadi patokan tanggal
        tagihan bulanan pelanggan tersebut <em>selamanya</em>. Sangat penting untuk pelanggan yang
        dipindahkan dari sistem manual, agar tanggal tagihnya tidak berubah dari kebiasaan
        sebelumnya.</li>
    <li>Pilih Paket. Jika router &amp; username PPPoE juga diisi, sistem otomatis mendaftarkan
        pelanggan ke router (lihat Bab 9).</li>
    <li>Klik <strong>Simpan</strong>.</li>
</ol>
<h3>Mengubah, Suspend, dan Menghapus</h3>
<ul>
    <li><strong>Edit</strong>  -  ubah data atau ganti paket. Jika paket diganti, sistem otomatis
        mengirim kecepatan baru ke router (jika pelanggan sudah terhubung ke router).</li>
    <li><strong>Suspend</strong>  -  memutus akses internet pelanggan di router (jika terhubung) dan
        menandai status "Suspend" di sistem. Biasa dipakai untuk pelanggan yang menunggak.</li>
    <li><strong>Aktifkan</strong>  -  kebalikan dari suspend, menyambungkan kembali.</li>
    <li><strong>Hapus</strong>  -  menghapus pelanggan dari daftar (data tetap tersimpan untuk
        riwayat/audit, hanya disembunyikan dari tampilan).</li>
</ul>
<div class="box-warn">
    <strong>Penting:</strong> Sistem <u>tidak pernah</u> men-suspend pelanggan secara otomatis
    walaupun tagihannya sudah terlambat. Suspend selalu merupakan keputusan manual dari
    Pemilik/Admin/Teknisi melalui tombol Suspend. Ini disengaja, agar pemilik usaha yang
    sepenuhnya memegang kendali kapan akses pelanggan diputus.
</div>
</div>

<!-- 6. PAKET -->
<div class="chapter">
<h2>6. Kelola Paket Internet</h2>
<p>Menu ini mengatur produk/paket internet yang ditawarkan ke pelanggan.</p>
<ol>
    <li>Klik <strong>Tambah Paket</strong>.</li>
    <li>Isi Nama Paket, Kecepatan Download &amp; Upload (Mbps), Harga.</li>
    <li>Pilih Siklus Tagihan: <strong>Mingguan</strong>, <strong>Bulanan</strong>,
        <strong>Tahunan</strong>, atau <strong>Custom</strong> (isi sendiri jumlah harinya).</li>
    <li>Aktifkan/nonaktifkan paket dengan toggle Aktif  -  paket nonaktif tidak muncul lagi sebagai
        pilihan saat menambah pelanggan baru, tapi pelanggan lama yang sudah memakainya tidak
        terpengaruh.</li>
</ol>
</div>

<!-- 7. TAGIHAN -->
<div class="chapter">
<h2>7. Tagihan</h2>
<h3>Generate Tagihan</h3>
<p>Tombol <strong>Generate Tagihan</strong> membuat tagihan baru untuk semua pelanggan aktif yang
sudah waktunya ditagih (periode tagihan sebelumnya sudah berakhir). Tombol ini aman diklik
berkali-kali  -  pelanggan yang periode tagihannya belum berakhir otomatis dilewati, tidak akan
tertagih dua kali.</p>
<div class="box-note">
    Pada server produksi, proses generate tagihan biasanya dijadwalkan otomatis setiap hari
    (melalui cron job), sehingga tombol ini sebagai cadangan bila perlu generate manual.
</div>
<h3>Melihat Detail &amp; Mengunduh PDF</h3>
<p>Klik ikon mata untuk melihat rincian tagihan (jumlah, diskon, pajak, total, jatuh tempo).
Klik ikon PDF untuk mengunduh tagihan dalam bentuk file PDF yang bisa dicetak atau dikirim ke
pelanggan  -  berisi logo usaha (jika sudah diatur, lihat Bab 13), data pelanggan, dan rincian
biaya.</p>
<h3>Status Tagihan</h3>
<table class="ref">
    <tr><th>Status</th><th>Arti</th></tr>
    <tr><td>Belum Lunas</td><td>Tagihan baru dibuat, belum melewati jatuh tempo.</td></tr>
    <tr><td>Terlambat</td><td>Sudah melewati tanggal jatuh tempo dan belum dibayar.</td></tr>
    <tr><td>Lunas</td><td>Pembayaran sudah diverifikasi oleh Pemilik/Admin/Kasir.</td></tr>
</table>
</div>

<!-- 8. PEMBAYARAN -->
<div class="chapter">
<h2>8. Pembayaran</h2>
<p>Pelanggan dapat membayar tagihan melalui dua cara dari Portal Pelanggan mereka:</p>
<ul>
    <li><strong>QRIS</strong>  -  kode QR dinamis (nominal sudah sesuai tagihan) dibuat otomatis dari
        kode QRIS statis milik usaha yang diatur di Pengaturan (Bab 13).</li>
    <li><strong>Transfer Manual</strong>  -  pelanggan mengunggah foto bukti transfer.</li>
</ul>
<p>Kedua metode ini <strong>selalu memerlukan verifikasi manual</strong> dari Pemilik/Admin/Kasir
sebelum tagihan ditandai lunas  -  sistem tidak pernah mengonfirmasi pembayaran secara otomatis
untuk kedua metode ini (kecuali memakai Payment Gateway, lihat di bawah).</p>
<h3>Memverifikasi Pembayaran</h3>
<ol>
    <li>Buka menu Pembayaran, cari status <strong>Menunggu Verifikasi</strong>.</li>
    <li>Klik ikon mata untuk melihat detail  -  untuk transfer manual, bukti foto transfer akan
        ditampilkan.</li>
    <li>Klik <strong>Setujui</strong> jika benar sudah dibayar (tagihan otomatis berubah jadi
        Lunas), atau <strong>Tolak</strong> jika tidak valid (isi alasan penolakan).</li>
</ol>
<h3>Payment Gateway (Opsional)</h3>
<p>Jika Midtrans atau Xendit sudah dikonfigurasi di Pengaturan, pelanggan juga bisa membayar
lewat gateway tersebut (kartu, e-wallet, virtual account, dsb.) dan verifikasinya
<strong>otomatis</strong> begitu gateway mengonfirmasi pembayaran  -  tidak perlu diverifikasi
manual.</p>
</div>

<!-- 9. ROUTER -->
<div class="chapter">
<h2>9. Router</h2>
<p>Menu ini menghubungkan NetHub ke perangkat router agar pemutusan/penyambungan akses internet
pelanggan bisa dilakukan otomatis dari sistem, tanpa login manual ke router.</p>
<h3>Menambah Router</h3>
<ol>
    <li>Klik <strong>Tambah Router</strong>.</li>
    <li>Isi Nama, Tipe Router, Alamat IP, Port, Username, Password admin router.</li>
    <li>Klik <strong>Test Koneksi</strong> untuk memastikan NetHub bisa terhubung ke router
        sebelum dipakai.</li>
</ol>
<div class="box-warn">
    <strong>Saat ini hanya tipe MikroTik yang didukung penuh.</strong> Merk lain (Ubiquiti, Huawei,
    Cisco, dll.) akan menampilkan pesan "belum diimplementasikan"  -  bukan error, hanya belum
    tersedia. Untuk MikroTik, pelanggan dikelola sebagai <em>PPP Secret</em>, jadi pastikan
    layanan PPPoE sudah aktif di router.
</div>
<p>Setelah router terhubung dan pelanggan diberi Username/Password PPPoE, sistem otomatis:</p>
<ul>
    <li>Mendaftarkan pelanggan baru ke router dengan batas kecepatan sesuai paketnya.</li>
    <li>Memutus akses saat pelanggan di-suspend (dan langsung memutus sesi yang sedang aktif).</li>
    <li>Memperbarui batas kecepatan saat pelanggan ganti paket (dan langsung memutus-sambung ulang
        sesi yang sedang aktif agar kecepatan barunya langsung berlaku, tidak menunggu pelanggan
        me-restart modem sendiri).</li>
</ul>
</div>

<!-- 10. MONITORING -->
<div class="chapter">
<h2>10. Monitoring</h2>
<h3>Pelanggan Online</h3>
<p>Menampilkan daftar pelanggan yang sedang terhubung ke internet, diperbarui otomatis setiap
kurang lebih 30 detik oleh sistem di latar belakang (bukan data real-time per detik).</p>
<h3>Cek Traffic Pelanggan</h3>
<p>Pilih satu pelanggan untuk melihat pemakaian traffic-nya secara langsung (memanggil router
saat itu juga)  -  dipakai sesekali untuk troubleshooting, bukan untuk dipantau terus-menerus.</p>
</div>

<!-- 11. BROADCAST -->
<div class="chapter">
<h2>11. Broadcast WhatsApp</h2>
<p>Mengirim pesan WhatsApp massal ke pelanggan, dikirim dari <strong>nomor WhatsApp usaha Anda
sendiri</strong> (lihat cara menghubungkannya di Bab 13  -  Pengaturan).</p>
<ol>
    <li>Pilih target: <strong>Semua Pelanggan</strong>, <strong>Pelanggan Aktif</strong>,
        <strong>Pelanggan Menunggak</strong>, atau <strong>Pilih Manual</strong> (centang satu per
        satu).</li>
    <li>Tulis isi pesan.</li>
    <li>Klik Kirim  -  akan diminta konfirmasi sekali lagi sebelum benar-benar terkirim.</li>
</ol>
<p class="small">Pesan masuk ke antrean dan diproses bertahap oleh sistem di latar belakang, jadi
untuk jumlah pelanggan banyak, pengiriman tidak instan (biasanya selesai dalam 1-2 menit).</p>
</div>

<!-- 12. LAPORAN -->
<div class="chapter">
<h2>12. Laporan</h2>
<p>Menyediakan tiga jenis laporan, masing-masing sebagai tab terpisah:</p>
<h3>Tab Pendapatan</h3>
<p>Total pendapatan dari tagihan yang sudah lunas, dikelompokkan per hari atau per bulan sesuai
rentang tanggal yang dipilih.</p>
<h3>Tab Pelanggan</h3>
<p>Ringkasan jumlah pelanggan per status (aktif/pending/suspend/nonaktif) dan per paket, lengkap
dengan daftar lengkap seluruh pelanggan.</p>
<h3>Tab Pembayaran</h3>
<p>Daftar transaksi pembayaran dalam rentang tanggal tertentu, bisa difilter per metode
(QRIS/Transfer/Gateway/Tunai) dan status.</p>
<h3>Mengekspor Laporan</h3>
<p>Setiap tab memiliki tombol <strong>CSV</strong> (untuk dibuka di Excel/Google Sheets) dan
<strong>PDF</strong> (untuk dicetak atau diarsipkan), mengikuti filter tanggal/kategori yang
sedang aktif di layar.</p>
</div>

<!-- 13. PENGATURAN -->
<div class="chapter">
<h2>13. Pengaturan</h2>
<h3>Profil Usaha</h3>
<p>Nama usaha, WhatsApp kontak, email, dan alamat  -  dipakai di berbagai tempat seperti header PDF
tagihan dan pesan otomatis ke pelanggan.</p>
<h3>Logo &amp; Brand</h3>
<p>Unggah logo usaha (JPG/PNG/WEBP/SVG, maksimal 2MB). Logo ini akan tampil otomatis di bagian
atas setiap PDF tagihan yang diunduh.</p>
<h3>Rekening Bank &amp; QRIS</h3>
<p>Isi nama bank, nomor rekening, dan nama pemilik rekening untuk metode Transfer Manual. Untuk
QRIS, tempelkan <strong>kode QRIS statis</strong> yang didapat dari bank/PSP tempat usaha Anda
terdaftar sebagai merchant QRIS  -  NetHub akan otomatis membuat kode QR dengan nominal yang sesuai
untuk setiap tagihan.</p>
<h3>WhatsApp Gateway</h3>
<p>Pesan (pengingat tagihan, konfirmasi pembayaran, broadcast) dikirim dari nomor WhatsApp usaha
Anda sendiri, melalui layanan pihak ketiga <strong>Fonnte</strong>. Cara menghubungkan:</p>
<ol>
    <li>Daftar akun gratis di <code>fonnte.com</code>.</li>
    <li>Di dashboard Fonnte, hubungkan nomor WhatsApp Anda (scan QR, seperti WhatsApp Web).</li>
    <li>Salin <em>device token</em> dari Fonnte, tempelkan di kolom Fonnte Device Token, lalu
        simpan.</li>
</ol>
<div class="box-note">Selama token belum diisi, pesan tetap diproses sistem tapi tidak benar-benar
terkirim ke WhatsApp (hanya tercatat di log server)  -  tidak akan membuat sistem error.</div>
<h3>Payment Gateway (Opsional)</h3>
<p>Bila ingin menerima pembayaran otomatis lewat kartu/e-wallet/virtual account, hubungkan salah
satu: <strong>Midtrans</strong> atau <strong>Xendit</strong>  -  pilih salah satu, lalu isi
kredensial (server key/secret key) dari akun merchant Anda sendiri di gateway tersebut.</p>
<h3>Backup &amp; Restore</h3>
<p>Fitur ini untuk berjaga-jaga terhadap kehilangan data, atau memindahkan data ke instalasi
NetHub lain.</p>
<ul>
    <li><strong>Download Backup</strong>  -  mengunduh file <code>.json</code> berisi seluruh data
        <strong>Paket, Pelanggan, dan Tagihan</strong> milik usaha Anda. Kata sandi/kredensial
        (PPPoE, router, payment gateway) sengaja <u>tidak</u> disertakan demi keamanan.</li>
    <li><strong>Restore</strong>  -  mengunggah kembali file backup tersebut. Data yang sudah ada
        (dikenali dari kode pelanggan/nomor tagihan/nama paket yang sama) akan <strong>dilewati
        secara otomatis, tidak pernah ditimpa atau dihapus</strong>  -  jadi proses restore aman
        dijalankan berkali-kali tanpa risiko duplikat atau kehilangan data yang sudah ada.
        Riwayat pembayaran tidak ikut direstore.</li>
</ul>
</div>

<!-- 14. PORTAL PELANGGAN -->
<div class="chapter">
<h2>14. Portal Pelanggan</h2>
<p>Halaman terpisah yang diakses pelanggan menggunakan akun mereka sendiri. Menunya jauh lebih
sederhana dibanding sisi Pemilik/Staf:</p>
<h3>Dashboard</h3>
<p>Menampilkan paket yang sedang dipakai dan tagihan terdekat yang harus dibayar.</p>
<h3>Tagihan Saya</h3>
<ul>
    <li>Melihat seluruh riwayat tagihan beserta statusnya.</li>
    <li>Tombol <strong>Bayar</strong> membuka pilihan QRIS atau Transfer Manual.</li>
    <li>Tombol PDF untuk mengunduh salinan tagihan.</li>
</ul>
<h3>Profil</h3>
<p>Pelanggan dapat memperbarui nama, telepon, alamat, dan kata sandi login mereka sendiri. Email
tidak dapat diubah sendiri oleh pelanggan.</p>
</div>

<!-- 15. FAQ -->
<div class="chapter">
<h2>15. Catatan Penting &amp; Pertanyaan Umum</h2>

<h3>Apakah pelanggan otomatis diputus jika telat bayar?</h3>
<p>Tidak. Suspend selalu tindakan manual dari Pemilik/Admin/Teknisi. Sistem hanya menandai
tagihan sebagai "Terlambat", tidak memutus akses secara otomatis.</p>

<h3>Kenapa router saya tidak bisa terhubung?</h3>
<p>Pastikan: (1) tipe router yang dipilih adalah MikroTik, (2) layanan API RouterOS aktif di
router (biasanya port 8728), (3) alamat IP/port/username/password sudah benar, dan (4) router
dapat dijangkau dari server tempat NetHub berjalan (bukan hanya dari jaringan lokal Anda).</p>

<h3>Kenapa pesan WhatsApp tidak terkirim ke pelanggan?</h3>
<p>Pastikan WhatsApp Gateway (Fonnte) sudah dihubungkan di Pengaturan (Bab 13). Selama belum
dihubungkan, pesan tetap "diproses" oleh sistem tapi tidak benar-benar sampai ke WhatsApp
pelanggan.</p>

<h3>Apa bedanya Transfer Manual dan Payment Gateway?</h3>
<p>Transfer Manual selalu butuh diverifikasi oleh staf secara manual (lihat bukti transfernya).
Payment Gateway (Midtrans/Xendit) terverifikasi otomatis begitu pelanggan selesai membayar di
gateway tersebut, tanpa perlu campur tangan staf.</p>

<h3>Apakah data backup bisa dipulihkan ke usaha lain?</h3>
<p>Backup hanya bisa di-restore ke akun/usaha Anda sendiri yang sedang login  -  tidak bisa
bercampur dengan data usaha lain, walaupun keduanya memakai NetHub yang sama.</p>

<div class="footer-note"> -  Dokumen ini dibuat otomatis oleh NetHub  - </div>
</div>

</body>
</html>
