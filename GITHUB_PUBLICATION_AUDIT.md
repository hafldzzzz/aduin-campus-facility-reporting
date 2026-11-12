# Audit publikasi GitHub ADUIN

**Tanggal pemeriksaan:** 12 November 2026 (WIB).  
**Status:** salinan portofolio siap ditinjau pemilik; belum ada repository remote, push, atau unggahan.  
**Cakupan:** source versi fitur `monthly-report-20261111/htdocs`, kebutuhan runtime, data demo sintetis, screenshot, dokumentasi, serta inventaris seluruh berkas publik.

## Hasil utama

- **80 berkas publik**, termasuk 38 berkas PHP, 7 screenshot, 1 schema struktur saja, dependency PDF/font dan lisensi.
- 53 berkas inti/aset/dependency diambil dengan daftar izin dari source versi fitur terbaru. Direktori runtime hanya mempertahankan aturan akses dan penanda folder kosong.
- Hash SHA-256 **2.555 berkas proyek asal**, termasuk salinan pengembangan dan paket deployment yang sudah ada, dibandingkan sebelum/sesudah persiapan: **0 berubah, 0 ditambah, 0 dihapus**. Bukti berisi path asal disimpan terpisah dan tidak dibundel.
- Tidak ada koneksi ke database aplikasi yang sudah ada atau hosting. Pengujian menulis hanya pada instance MySQL baru di direktori sementara, loopback, port terpisah, dengan pemeriksaan direktori data sebelum membuat schema uji.
- Tidak ditemukan nilai kredensial privat dalam hasil salinan. Perbandingan literal dari 12 konfigurasi privat terhadap seluruh byte berkas publik menghasilkan **0 kecocokan**; nilai rahasia tidak dicatat dalam laporan ini.
- Tidak ada data akun produksi, hash password tersimpan, nomor WhatsApp, unggahan pengguna, log, session, backup, arsip deployment, identitas hosting, atau path Windows privat pada berkas publik.

## Perubahan khusus salinan publik

| Berkas / kelompok | Penyesuaian dan alasan |
| --- | --- |
| `config/database.php` | Melepas penguncian nama database/host produksi; seluruh koneksi memakai konfigurasi privat yang diisi pengguna. Tanpa konfigurasi, gagal aman dengan petunjuk setup. |
| `config/example.local.php` | Template lokal tanpa username/password database atau token; `aduin_demo`, HTTPS lokal opsional, WhatsApp nonaktif. |
| `login.php` | Hash dummy tetap dari source diganti pembuatan nilai dummy baru, sehingga tidak ada hash password tersimpan di source publik. Autentikasi akun tetap memakai `password_verify()`. |
| `petugas/profil.php` | Placeholder email diarahkan ke domain non-deliverable `example.invalid`. |
| `admin/dashboard.php` | Menghapus satu karakter `<` berlebih sebelum blok PHP navbar yang tampak pada halaman. |
| `admin/laporan-bulanan.php` | Menambahkan tautan favicon ke logo yang sudah disertakan, menghindari permintaan favicon yang hilang. |
| `assets/img/logo-aduin.png` | Menghapus metadata provenance tambahan `caBX`. Byte data piksel PNG terkompresi (`IDAT`) dibandingkan dan tetap identik. |
| `database/schema.sql` | Struktur delapan tabel direkonstruksi dari kebutuhan query dan migrasi struktural. Tidak mengambil dump/data database; mencakup idempotensi pengajuan dan tabel WhatsApp kosong. |
| `bin/seed-demo.php` | CLI, hanya lingkungan lokal/test dan host loopback; nama database dibatasi; cocokkan database aktif serta daftar tabel dan pastikan semuanya kosong. Transaksi baru membuat identitas sintetis dan password acak; tidak ada opsi melewati guard. |
| `.gitignore`, `.gitattributes` | Mengabaikan konfigurasi privat, unggahan, runtime, dump, backup, deployment dan file editor. Font PDF `.dat`, `.htaccess`, schema aman, dan screenshot tetap dapat diikutkan Git. Teks dinormalisasi LF tanpa mengubah logika. |
| Dokumentasi / pengujian | README Indonesia, galeri, kredit dependency, pemeriksaan laporan, pemindai publikasi, dan manifest SHA-256 ditambahkan. |

## Berkas yang dikecualikan

| Kelompok | Alasan / kebijakan |
| --- | --- |
| Semua `config/local.php`, konfigurasi produksi, `.env`, sertifikat/key privat, konfigurasi FTP/editor | Mungkin mengandung password, token, akun atau endpoint privat. Hanya `config/example.local.php`, `config/database.php` dan aturan akses yang dibundel. |
| `_development/`, `_deployment/`, ZIP/arsip, backup, evidence lama | Mengandung duplikasi paket, catatan operasional, potensi konfigurasi dan path privat; tidak disalin sebagai direktori. Pemilihan source dilakukan langsung dari daftar izin pada versi fitur terbaru. |
| Dump SQL lama serta data dari database lokal/online | Tidak digunakan sebagai fixture; hanya schema struktur aman yang dibuat. |
| Semua isi `uploads/pengaduan`, `uploads/perbaikan`, log, session, cache/rate-limit runtime | Bisa mengandung foto, identitas atau jejak penggunaan. Folder kosong dan `.htaccess` yang diperlukan dipertahankan. |
| Token/penerima Meta/WhatsApp, FTP, akun hosting | Tidak disalin. Source layanan WhatsApp dipertahankan tanpa data dan dinonaktifkan pada template. |
| Catatan deployment/security lama dan skrip operasional yang terikat lingkungan asal | Tidak diperlukan runtime aplikasi publik dan dapat menyebut informasi privat. Diganti dokumentasi lokal yang aman. |
| Konfigurasi, password acak, database, log, sesi dan profil browser pengujian baru | Seluruhnya berada di luar folder publik dan tidak termasuk manifest. |

## Hasil verifikasi

| Pemeriksaan | Hasil |
| --- | --- |
| PHP lint | **38/38 lulus** pada PHP 8.3.28. |
| Pengujian laporan tanpa database | **19/19 lulus**: batas bulan/tahun, kabisat, input invalid, formula CSV, round-trip pemisah/kutip, BOM UTF-8, PDF/font tertanam, dan periode kosong. |
| HTTP pada MySQL sintetis terisolasi | **54/54 lulus**: seluruh halaman role, login, pembatasan role/kepemilikan, CSRF, pengajuan idempoten, penugasan, perubahan status, tracking publik yang menyembunyikan detail dan akses lengkap pemilik, serta unduh PDF/CSV. |
| Unggah dan akses media sintetis | **6/6 lulus**: unggah PNG sintetis baru, gambar hasil pengodean ulang, penolakan anonim/petugas yang belum ditugaskan, akses petugas yang ditugaskan, traversal ditolak. Berkas uji tidak dibundel. |
| Seed demo | **6/6 lulus**: menolak environment produksi, host remote, nama database lain, dan WhatsApp aktif sebelum membuka koneksi; pembuatan awal pada schema baru berhasil dan seed kedua ditolak tanpa penggantian data. Login tiga role memvalidasi password/hash baru. |
| Browser / screenshot | **7/7 halaman** ditangkap dan diperiksa visual; desktop/seluler tanpa overflow horizontal; tidak ada respons gagal untuk resource lokal pada pengambilan akhir. |
| Aturan `.gitignore` | **25/25 lulus**, termasuk penolakan konfigurasi privat/dump/unggahan serta pemeliharaan `.htaccess`, schema, font `.dat`, dan screenshot. Metadata Git pengujian berada di luar folder publik. |
| Referensi berkas | Semua include literal PHP, URL aset lokal dan tautan Markdown yang dapat diperiksa statis ditemukan. Dependency font dinamis diperiksa melalui daftar wajib dan pembuatan PDF aktual. |
| Audit rahasia/PII/path | Pemindaian teks, pola token/key/hash, password literal, email/telepon, isi SQL, nama/jenis berkas, direktori runtime, metadata PNG, dan perbandingan nilai konfigurasi privat: tidak ada temuan yang belum ditangani. |
| Integritas hasil | `PUBLICATION_MANIFEST.sha256` mencakup setiap berkas publik kecuali manifest itu sendiri. Pemindai memeriksa daftar serta hash ketika manifest tersedia. |

## Data dan screenshot yang aman

Seed membuat “Admin Demo”, “Mahasiswa Demo”, “Petugas Demo”, empat kategori fasilitas, dan enam pengaduan sintetis pada bulan berjalan dengan variasi Menunggu/Diproses/Selesai. Email hanya memakai domain `example.invalid`; nomor WhatsApp dan foto kosong. Password acak tiap akun dibuat saat instalasi melalui `random_bytes()` dan `password_hash()`, ditampilkan sekali sesudah transaksi berhasil; tidak ada password/hash dalam fixture SQL.

Screenshot menampilkan enam fixture dasar dan dua simulasi alur tambahan; seluruh nomor berawalan `DEMO-` maupun `LAP-` yang terlihat dibuat baru oleh pengujian lokal. Tidak ada address bar, path komputer, cookie, password, sesi browser, data pengguna nyata, atau foto pengguna. Metadata PNG yang dapat membawa teks/EXIF/provenance tidak dibundel.

Header copyright dependency tFPDF dan pembaca font memuat kontak publik penulis upstream. Kontak atribusi tersebut dipertahankan bersama lisensi; kontak itu bukan data pengguna aplikasi atau fixture demo. Source tFPDF dan font DejaVu beserta cache/metrik dan lisensi lengkap tetap tersedia.

## Batasan dan risiko yang perlu ditinjau pemilik

1. Audit ini memeriksa kebersihan paket publik dan menjalankan alur representatif; tidak menjamin aplikasi bebas seluruh kerentanan. Pemindai berbasis pola tidak membuktikan ketiadaan secret yang disamarkan atau dienkode di luar pola pemeriksaan.
2. Pengujian HTTP menggunakan PHP development server dengan router uji yang meniru pembatasan Apache, pada loopback saja. **Penerapan `.htaccess` pada Apache/Laragon dan hosting tetap perlu diperiksa** sebelum deployment; berkas asli yang dibutuhkan dipertahankan. Konfigurasi tanpa `mod_rewrite` perlu memastikan akses upload tetap tertutup.
3. Instalasi virtual host root diuji melalui loopback; URL subfolder didokumentasikan berdasarkan helper path aplikasi tetapi belum diuji langsung pada virtual host Laragon. Kompatibilitas versi PHP/MySQL lain tidak dinyatakan lulus.
4. Asset antarmuka masih memakai CDN, termasuk referensi Lucide `latest`. Perubahan upstream, koneksi internet, dan ketentuan lisensi CDN perlu ditinjau bila aplikasi diterbitkan untuk pemakaian operasional.
5. Integrasi WhatsApp disertakan sebagai source nonaktif. Pengiriman jaringan, consent penerima nyata, cron/worker dan kredensial layanan tidak diuji atau dikonfigurasi pada paket ini.
6. Tautan demo online hanya didokumentasikan sesuai permintaan. Ketersediaan, isi, hak akses, dan keamanan hosting tersebut tidak diperiksa, serta tidak ada unggahan ke hosting.
7. Pemilik perlu memastikan hak publikasi source, nama/logo dan dependensi sebelum publikasi. Tidak ada lisensi aplikasi baru yang diasumsikan; teks lisensi upstream dipertahankan.
8. Jangan memasukkan `config/local.php`, hasil seed, dump database atau unggahan runtime saat kelak commit. Audit ulang setiap perubahan setelah paket ini ditinjau. `.gitignore` tidak mencegah `git add -f` atau secret yang ditulis ke source biasa.

## Inventaris seluruh berkas publik

Daftar berikut adalah seluruh file yang disiapkan untuk publikasi. Hash masing-masing, termasuk laporan ini, tersedia pada manifest; manifest tidak memasukkan hash dirinya sendiri agar tidak membuat ketergantungan melingkar.

| Berkas | Klasifikasi |
| --- | --- |
| `.gitattributes` | Aturan Git aman dan akhir baris |
| `.gitignore` | Aturan Git aman dan akhir baris |
| `.htaccess` | Aturan akses Apache; diperlukan aplikasi/perlindungan file |
| `GITHUB_PUBLICATION_AUDIT.md` | Dokumentasi atau manifest integritas |
| `PUBLICATION_MANIFEST.sha256` | Dokumentasi atau manifest integritas |
| `README.md` | Dokumentasi atau manifest integritas |
| `THIRD_PARTY_NOTICES.md` | Dokumentasi atau manifest integritas |
| `admin/dashboard.php` | Source aplikasi yang diperlukan |
| `admin/detail-pengaduan.php` | Source aplikasi yang diperlukan |
| `admin/laporan-bulanan.php` | Source aplikasi yang diperlukan |
| `admin/pengaduan.php` | Source aplikasi yang diperlukan |
| `admin/pengguna.php` | Source aplikasi yang diperlukan |
| `admin/petugas.php` | Source aplikasi yang diperlukan |
| `admin/unduh-laporan-bulanan.php` | Source aplikasi yang diperlukan |
| `assets/css/monthly-report.css` | Aset aplikasi; logo tanpa metadata tambahan |
| `assets/css/style.css` | Aset aplikasi; logo tanpa metadata tambahan |
| `assets/img/logo-aduin.png` | Aset aplikasi; logo tanpa metadata tambahan |
| `assets/js/internal.js` | Aset aplikasi; logo tanpa metadata tambahan |
| `assets/js/script.js` | Aset aplikasi; logo tanpa metadata tambahan |
| `bin/.htaccess` | Aturan akses Apache; diperlukan aplikasi/perlindungan file |
| `bin/seed-demo.php` | Pembuat fixture sintetis; guard CLI/lokal/database kosong |
| `config/.htaccess` | Aturan akses Apache; diperlukan aplikasi/perlindungan file |
| `config/database.php` | Source aplikasi yang diperlukan |
| `config/example.local.php` | Template konfigurasi tanpa kredensial |
| `database/.htaccess` | Aturan akses Apache; diperlukan aplikasi/perlindungan file |
| `database/schema.sql` | Struktur database saja; tanpa baris data/hash |
| `docs/.htaccess` | Aturan akses Apache; diperlukan aplikasi/perlindungan file |
| `docs/screenshots/.gitkeep` | Penanda folder kosong; tanpa data runtime |
| `docs/screenshots/01-beranda.png` | Screenshot sintetis; diperiksa visual dan metadata |
| `docs/screenshots/02-dashboard-admin.png` | Screenshot sintetis; diperiksa visual dan metadata |
| `docs/screenshots/03-laporan-bulanan.png` | Screenshot sintetis; diperiksa visual dan metadata |
| `docs/screenshots/04-dashboard-mahasiswa.png` | Screenshot sintetis; diperiksa visual dan metadata |
| `docs/screenshots/05-dashboard-petugas.png` | Screenshot sintetis; diperiksa visual dan metadata |
| `docs/screenshots/06-tracking.png` | Screenshot sintetis; diperiksa visual dan metadata |
| `docs/screenshots/07-laporan-mobile.png` | Screenshot sintetis; diperiksa visual dan metadata |
| `docs/screenshots/README.md` | Dokumentasi atau manifest integritas |
| `includes/.htaccess` | Aturan akses Apache; diperlukan aplikasi/perlindungan file |
| `includes/bootstrap.php` | Source aplikasi yang diperlukan |
| `includes/monthly_report.php` | Source aplikasi yang diperlukan |
| `includes/navbar.php` | Source aplikasi yang diperlukan |
| `includes/pdf/COPYING.LESSER-2.1.txt` | Dependency PDF/font, metrik, atau lisensi upstream |
| `includes/pdf/README-upstream.txt` | Dependency PDF/font, metrik, atau lisensi upstream |
| `includes/pdf/font/unifont/DejaVuSansCondensed-Bold.ttf` | Dependency PDF/font, metrik, atau lisensi upstream |
| `includes/pdf/font/unifont/DejaVuSansCondensed.ttf` | Dependency PDF/font, metrik, atau lisensi upstream |
| `includes/pdf/font/unifont/DejaVu_LICENSE.txt` | Dependency PDF/font, metrik, atau lisensi upstream |
| `includes/pdf/font/unifont/dejavusanscondensed-bold.cw.dat` | Dependency PDF/font, metrik, atau lisensi upstream |
| `includes/pdf/font/unifont/dejavusanscondensed-bold.cw127.php` | Dependency PDF/font, metrik, atau lisensi upstream |
| `includes/pdf/font/unifont/dejavusanscondensed-bold.mtx.php` | Dependency PDF/font, metrik, atau lisensi upstream |
| `includes/pdf/font/unifont/dejavusanscondensed.cw.dat` | Dependency PDF/font, metrik, atau lisensi upstream |
| `includes/pdf/font/unifont/dejavusanscondensed.cw127.php` | Dependency PDF/font, metrik, atau lisensi upstream |
| `includes/pdf/font/unifont/dejavusanscondensed.mtx.php` | Dependency PDF/font, metrik, atau lisensi upstream |
| `includes/pdf/font/unifont/ttfonts.php` | Dependency PDF/font, metrik, atau lisensi upstream |
| `includes/pdf/tfpdf.php` | Dependency PDF/font, metrik, atau lisensi upstream |
| `includes/security.php` | Source aplikasi yang diperlukan |
| `index.php` | Source aplikasi yang diperlukan |
| `login.php` | Source aplikasi yang diperlukan |
| `logout.php` | Source aplikasi yang diperlukan |
| `media.php` | Source aplikasi yang diperlukan |
| `pengaduan.php` | Source aplikasi yang diperlukan |
| `petugas/dashboard.php` | Source aplikasi yang diperlukan |
| `petugas/detail-tugas.php` | Source aplikasi yang diperlukan |
| `petugas/profil.php` | Source aplikasi yang diperlukan |
| `petugas/riwayat.php` | Source aplikasi yang diperlukan |
| `petugas/tugas.php` | Source aplikasi yang diperlukan |
| `services/.htaccess` | Aturan akses Apache; diperlukan aplikasi/perlindungan file |
| `services/WhatsAppNotifier.php` | Source aplikasi yang diperlukan |
| `services/WhatsAppOutbox.php` | Source aplikasi yang diperlukan |
| `services/monthly_report_pdf.php` | Source aplikasi yang diperlukan |
| `storage/.htaccess` | Aturan akses Apache; diperlukan aplikasi/perlindungan file |
| `storage/rate-limits/.gitkeep` | Penanda folder kosong; tanpa data runtime |
| `tests/.htaccess` | Aturan akses Apache; diperlukan aplikasi/perlindungan file |
| `tests/audit-publication.py` | Pengujian lokal; tanpa data privat |
| `tests/report-smoke.php` | Pengujian lokal; tanpa data privat |
| `tracking.php` | Source aplikasi yang diperlukan |
| `uploads/.htaccess` | Aturan akses Apache; diperlukan aplikasi/perlindungan file |
| `uploads/pengaduan/.gitkeep` | Penanda folder kosong; tanpa data runtime |
| `uploads/perbaikan/.gitkeep` | Penanda folder kosong; tanpa data runtime |
| `user/buat-pengaduan.php` | Source aplikasi yang diperlukan |
| `user/dashboard.php` | Source aplikasi yang diperlukan |
| `user/riwayat.php` | Source aplikasi yang diperlukan |
