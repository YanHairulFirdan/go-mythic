# PRD — Aplikasi Pencatatan Keuangan UMKM (MVP)

**Versi:** 1.4 — Penambahan aturan lifecycle Invoice, middleware status akun dan subscription, kuota berbasis input UTC, attachment transaksi, serta audit log berbasis `spatie/laravel-activitylog`
**Tanggal:** 18 Agustus 2026
**Target rilis MVP:** 2 minggu (tim 2–3 developer, metode vibecoding)
**Tech stack:** Laravel, Inertia.js, Vue, MySQL, Docker, Redis

---

## 1. Latar Belakang & Tujuan

Produk ini menyasar pengusaha UMKM menengah ke bawah yang masih melakukan pencatatan keuangan secara manual. Tujuan utama MVP adalah memodernisasi pencatatan transaksi dengan UI yang intuitif untuk pengguna awam teknologi, tanpa kompleksitas fitur akuntansi yang berlebihan.

**Positioning:** Pasar pencatatan keuangan UMKM di Indonesia sudah dikuasai pemain besar yang gratis (BukuKas, BukuWarung, dengan basis pengguna ratusan ribu UMKM), yang memonetisasi lewat layanan finansial (agen pembayaran, PPOB) bukan lewat langganan. Produk ini **tidak bersaing head-to-head di ranah "pencatatan gratis tanpa batas"** — melainkan memposisikan diri sebagai **"naik kelas dari pencatatan sederhana"**: untuk UMKM yang sudah mulai punya tim dan butuh kontrol akses & audit trail yang jelas (siapa input transaksi apa, kapan), bukan sekadar berbagi akun. Fitur multi-user kompetitor gratis umumnya masih basic (akses dibagi rata tanpa audit trail terstruktur) — celah inilah yang jadi diferensiasi utama produk ini.

**Model bisnis (MVP):** Freemium 2-tier sederhana — **Free** (solo Owner, limit transaksi harian) vs **Paid** (buka kapasitas Employee tanpa batas, 1 harga flat). Ini adalah versi **paling minim** yang cukup untuk membuktikan sinyal inti dari proposal yang sudah disetujui: apakah UMKM yang mulai punya tim bersedia bayar untuk kolaborasi. Model langganan yang lebih kaya (5-tier bundle / per-seat + trial — lihat Opsi A & B di modul Subscription) sengaja **ditunda ke fase 1.5** setelah sinyal ini tervalidasi, supaya effort development 2 minggu fokus ke pembuktian konsep dulu, bukan ke kompleksitas billing.

---

## 2. Asumsi & Batasan MVP

| Area | Keputusan |
|---|---|
| Struktur tenant | Berbasis `company` (bukan langsung `user`), agar siap dikembangkan ke multi-branch di fase berikutnya. Untuk MVP: 1 company = 1 toko/usaha. |
| Multi-branch | **Tidak masuk MVP** — direncanakan sebagai fitur premium fase berikutnya. |
| Produk | **Tidak masuk MVP** — dipangkas untuk mengejar timeline (juga tidak disebut di proposal v2). Transaksi cukup income/expense + kategori, tanpa kaitan ke produk spesifik. |
| Role | 2 role yang bisa login: **Owner** (superadmin, akses penuh) dan **Employee** (input transaksi, lihat transaksi miliknya sendiri). Roster tim (termasuk daily worker tanpa akun login) dikelola terpisah — lihat baris "Roster tim & daily worker" di bawah. |
| Roster tim & daily worker | Tabel `employees` (roster bisnis: nama + flag `has_access_to_system`) dipisah dari tabel `users` (kredensial login). Daily worker = baris `employees` dengan `has_access_to_system=false`, tanpa akun login sama sekali — bukan entitas terpisah, bukan juga dicampur ke `users`. Latar belakang keputusan ini ada di bagian 9. |
| Customer | **Baru, masuk MVP** — entitas ringan (nama, kontak, alamat) untuk mencatat pelanggan yang berelasi ke transaksi income. Detail di modul 3.9. |
| Invoice | **Baru, masuk MVP** — tagihan sederhana tanpa status/tracking, terdiri dari rincian item (`invoice_items`) yang menentukan total secara otomatis. Detail di modul 3.10. |
| Onboarding Employee | Dibuatkan langsung oleh Owner (username + password), bukan lewat invitation link. |
| Approval transaksi | Tidak ada — transaksi yang diinput Employee langsung final. |
| Audit trail transaksi | **Full log** menggunakan `spatie/laravel-activitylog` — setiap create/update/delete transaksi dicatat dengan actor, subject, event, properties perubahan, dan timestamp. Tidak ada halaman viewer log di MVP; data tersimpan untuk audit, UI cukup menampilkan "last updated by" di detail transaksi. |
| Modal/Kas usaha | Modul baru — pencatatan modal awal (base capital) per periode sebagai baseline pembanding terhadap income/expense. Bukan multi-akun kas/bank (klarifikasi terhadap istilah "Manajemen Kas & Rekening" di proposal v2). Detail di modul 3.8. |
| Visibilitas transaksi | Owner melihat semua transaksi; Employee hanya melihat transaksi yang ia input sendiri. |
| Attachment bukti | **Masuk MVP** — transaksi dapat memiliki satu bukti opsional berupa gambar selain GIF, maksimal 1 MB, pada private storage; akses mengikuti authorization transaksi. Bukti transfer pembayaran memakai pola private storage yang sama. |
| Transaksi masa depan | **Ditolak** — `transaction_date` tidak boleh lebih besar dari tanggal UTC saat input, meskipun masih berada dalam rentang modal aktif. |
| Pencegahan double-submit | MVP mengandalkan disable button setelah submit untuk mencegah double-click. Idempotency key/server-side deduplication belum masuk MVP; retry jaringan dan multi-tab dicatat sebagai batasan yang belum dijamin. |
| Zona waktu | **UTC** untuk penyimpanan timestamp, perhitungan kuota harian, dan evaluasi tanggal modal/transaksi. Batas hari kuota: `00:00:00` sampai `23:59:59` UTC. |
| Status akun Employee | Status akses dan alasan inaktivasi disimpan terpisah dari status subscription: `status` (`active`/`inactive`) dan `inactive_reason` (`manual`/`subscription_expired`/`company_closed`, nullable). |
| Middleware akses | Request terautentikasi melewati `EnsureUserActive`, lalu `EnsureCompanySubscription`. Status subscription diperiksa live berdasarkan `paid_until`; hasil boleh dicache Redis dan harus di-invalidate saat nilai berubah. |
| Lifecycle Invoice | Invoice bebas diedit/dihapus hanya sebelum memiliki transaksi terkait. Setelah memiliki transaksi, Customer, Worker, dan item invoice dibekukan. Invoice yang total transaksi terkaitnya sudah sama dengan nominal total tidak menerima transaksi baru. |
| Counter kuota | Counter diperbarui setelah transaksi berhasil commit; Redis gagal → fallback ke query database. Soft-delete mengembalikan counter; perubahan tipe memindahkan counter antarjenis. |
| Reset password/notifikasi | Email sebagai channel **satu-satunya** untuk MVP. WhatsApp (via WAHA, self-hosted gratis) untuk verifikasi/OTP/reset **dipindah ke fase 2** demi menjaga timeline 2 minggu — lihat bagian Risiko & Fase 2. |
| Model subscription | **Free vs Paid (2 kondisi saja) untuk MVP** — bayar di muka (pay-before-use), manual transfer + admin approve, tanpa payment gateway. Model 5-tier / per-seat+trial yang lebih detail (Opsi A & B) ditunda ke fase 1.5. Detail lengkap di modul Subscription (3.6). |
| Export data | **Tidak masuk MVP** — dipangkas untuk mengejar timeline. Laporan tetap tersedia dilihat langsung di in-app (dashboard/Analytic), hanya kemampuan unduh file yang ditunda. |
| Analytic | Ringkasan angka (card: total pemasukan, pengeluaran, saldo bersih) — **tanpa grafik/chart** untuk MVP guna menghemat waktu integrasi library visualisasi. |
| Harga langganan (nominal) | **Rp99.000/bulan flat** untuk Paid tier — dipilih untuk kesan premium/tepercaya (vs opsi lebih murah Rp79rb), diposisikan di antara aplikasi pencatatan gratis (~Rp12.500/bulan) dan software akuntansi kelas atas seperti Kledo/Jurnal (Rp149rb+/bulan). Estimasi strategis berbasis benchmark pasar — **belum divalidasi riset harga langsung ke calon user**, lihat catatan di bagian Risiko. |

---

## 3. Breakdown Fitur per Modul

Setiap fitur ditandai prioritas:
- 🔴 **Must-have** — wajib ada saat rilis MVP
- 🟡 **Should-have** — dikejar jika waktu memungkinkan
- ⚪ **Defer** — dipindah ke fase 2 (di luar 2 minggu ini)

### 3.1 Modul Autentikasi

| Fitur | Prioritas | Catatan |
|---|---|---|
| Register (Owner, membuat company baru) | 🔴 | Email wajib diisi & unik per company |
| Login (Owner & Employee) | 🔴 | Session-based, standar Laravel/Inertia |
| Logout | 🔴 | |
| Reset password via Email | 🔴 | Laravel built-in reset flow + queue |
| Reset password / verifikasi akun / OTP via WhatsApp (WAHA) | ⚪ | **Dipindah ke fase 2** — lihat bagian Risiko. Untuk MVP, Email jadi satu-satunya channel. |
| Fallback otomatis WA → Email | ⚪ | Tidak relevan untuk MVP karena WA belum aktif |
| Health check sesi WAHA + alert admin | ⚪ | Fase 2, bareng dengan aktivasi fitur WA |
| Verifikasi email saat register | ⚪ | Skip untuk MVP demi onboarding cepat |
| Employee dibuatkan akun oleh Owner | 🔴 | Form input di menu "Kelola Karyawan": nama, username, password |

### 3.2 Modul Pencatatan Transaksi (Core)

| Fitur | Prioritas | Catatan |
|---|---|---|
| CRUD transaksi (income/expense) | 🔴 | Field: tanggal, jenis, nominal, kategori, metode pembayaran, catatan |
| Master data Kategori transaksi | 🔴 | Preset default + bisa ditambah custom oleh Owner |
| Pilih metode pembayaran (cash/transfer/QRIS, dll) | 🔴 | Dropdown sederhana, bukan integrasi rekonsiliasi bank |
| Audit trail penuh (`spatie/laravel-activitylog`) | 🔴 | Catat create/update/delete dengan actor, subject, event, properties old/new, dan timestamp. Tanpa halaman viewer di MVP; UI cukup tampilkan "last updated by" di detail transaksi |
| Indikator kuota harian (radial chart `n/150`, khusus Free) | 🔴 | Counter per company, jenis transaksi, dan tanggal input (`created_at`), dengan window `00:00:00–23:59:59` UTC; soft-delete tidak dihitung; warna ikut design token; disembunyikan untuk company Paid |
| Filter & pencarian transaksi (by tanggal, kategori) | 🔴 | Untuk kebutuhan Analytic (in-app) juga |
| Edit/Delete transaksi | 🔴 | Owner bisa edit/hapus semua; Employee hanya transaksi miliknya. Delete = soft delete dan mengembalikan kuota tipe lama; perubahan income↔expense mengembalikan counter tipe lama lalu mengurangi quota tipe baru |
| Upload bukti transaksi | 🔴 | Satu attachment opsional per transaksi, gambar selain GIF maksimal 1 MB pada private storage; akses mengikuti authorization transaksi |
| Blokir input transaksi jika modal/kas belum diset | 🔴 | Berlaku untuk Owner & Employee — lihat modul 3.8 Modal/Kas Usaha (US-MK-04) |
| Kaitkan transaksi ke Customer (`customer_id`) | 🔴 | **Baru** — nullable, khusus transaksi income. Otomatis terisi & terkunci dari Invoice kalau transaksi terhubung ke satu (lihat modul 3.9 & 3.10) |
| Kaitkan transaksi ke Invoice (`invoice_id`) | 🔴 | **Baru** — nullable, khusus income. Satu invoice bisa dikaitkan ke banyak transaksi; validasi SUM mencegah total transaksi terkait melebihi nominal invoice — lihat modul 3.10 |
| Kaitkan transaksi ke Employee/Worker pelaksana (`employee_id`) | 🔴 | **Baru** — nullable, mencatat siapa yang mengerjakan (bisa Employee ber-akun atau daily worker tanpa akun), terpisah dari `created_by` (siapa yang input transaksinya di aplikasi) |
| Master data Produk (kaitkan transaksi ke produk) | ⚪ | **Dipangkas dari MVP** — tidak disebut juga di proposal v2. Fase 2. |
| Upload foto struk/nota | 🔴 | Masuk MVP sebagai satu attachment opsional per transaksi; gambar selain GIF, maksimal 1 MB, di private storage; akses mengikuti authorization transaksi. |
| Approval flow | ⚪ | Tidak masuk MVP (sudah diputuskan langsung final) |
| Tracking stok/inventory | ⚪ | Di luar scope MVP |

### 3.3 Modul Role & Permission

> ⚠️ **Revisi v1.3:** Skema Role & Permission direvisi untuk mengakomodasi daily worker (kebutuhan spesifik client pilot, bukan cuma untuk Employee ber-akses). Perubahan intinya: tabel `employees` (roster tim) dipisah dari tabel `users` (kredensial login) — lihat rasional lengkap di bagian 9.

| Fitur | Prioritas | Catatan |
|---|---|---|
| Tabel `employees` sebagai roster tim, terpisah dari `users` | 🔴 | **Baru.** `employees`: `id`, `company_id`, `name`, `has_access_to_system` (boolean), `user_id` (nullable FK → `users`). Setiap baris `employees` mewakili "orang di tim", baik yang bisa login maupun tidak |
| 2 role di `users`: Owner & Employee | 🔴 | `users` **tidak berubah** dari v1.2 — cukup kolom `role` enum + Gate/Middleware Laravel. Hanya berisi akun yang benar-benar bisa login (Owner + Employee dengan `has_access_to_system=true`) |
| Daily worker = `employees` tanpa akun login | 🔴 | **Baru.** Baris `employees` dengan `has_access_to_system=false`, `user_id=NULL`. Tidak pernah menyentuh alur login/session sama sekali — cuma direferensikan transaksi/invoice untuk tracking siapa yang mengerjakan |
| Scoping data by role (Owner lihat semua, Employee lihat miliknya) | 🔴 | Query scope di level Eloquent |
| Kelola Karyawan (list, tambah Employee ber-akun, tambah Worker tanpa akun, nonaktifkan) | 🔴 | Satu halaman menampilkan gabungan Employee & Worker (query dari `employees`, join opsional ke `users`). Form tambah punya 2 varian: dengan akun (isi username+password) atau tanpa akun (cuma nama). Nonaktifkan = soft-disable, bukan hard delete |
| Breakdown sederhana di detail Employee/Worker (total nominal, jumlah transaksi dikerjakan) | 🔴 | **Baru.** Dihitung on-the-fly dari `transactions` (`SUM`/`COUNT` where `employee_id` = orang tsb) — pola identik dengan breakdown Customer (modul 3.9), berlaku sama untuk Employee ber-akun maupun Worker |
| Employee zero-access ke Analytic & Modal/Kas Usaha | 🔴 | Menu tidak tampil di navigasi Employee; percobaan akses endpoint langsung ditolak 403 (defense in depth, bukan cuma disembunyikan di UI) |
| Role granular (kasir vs bookkeeper, dll) | ⚪ | Di luar scope MVP, 2 role cukup |

### 3.4 Modul Analytic

| Fitur | Prioritas | Catatan |
|---|---|---|
| Ringkasan total pemasukan, pengeluaran, saldo bersih per periode | 🔴 | Card summary di dashboard — memenuhi kriteria proposal "ringkasan real-time sesuai peran" tanpa perlu grafik. Filter periode: hari ini/minggu ini/bulan ini/custom range |
| Breakdown per kategori (income & expense terpisah) | 🔴 | List kategori dengan total masing-masing, diurutkan terbesar — masih dalam bentuk angka/list, bukan chart |
| Akses menu Analytic khusus Owner (zero-access Employee) | 🔴 | Employee tidak melihat menu ini sama sekali; lihat modul 3.3 Role & Permission |
| Grafik cashflow (chart visual) | ⚪ | **Dipangkas dari MVP** — hemat waktu integrasi library chart. Fase 2. |
| Breakdown per produk | ⚪ | Fase 2 (bergantung data Produk yang sudah dipangkas dari MVP) |
| Profit margin | ⚪ | Fase 2 (juga bergantung pada data produk yang sudah dipangkas dari MVP) |
| Caching dengan Redis | 🟡 | Untuk mempercepat query dashboard jika data mulai besar; bisa mulai tanpa cache dulu, tambahkan jika sempat |

### 3.5 Modul Export Data

| Fitur | Prioritas | Catatan |
|---|---|---|
| Export transaksi ke Excel/CSV | ⚪ | **Dipangkas dari MVP** — proposal hanya mensyaratkan "laporan dihasilkan otomatis", bukan file unduhan. Laporan tetap terlihat lengkap di in-app (modul Analytic + filter tanggal). Fase 2. |
| Export ke PDF | ⚪ | Fase 2 |
| Export khusus premium (dibatasi free tier) | ⚪ | Perlu keputusan bisnis lebih lanjut setelah model subscription final; tidak relevan untuk MVP karena export sendiri belum ada |

### 3.6 Modul Subscription

> ⚠️ **Status: Pending Keputusan Stakeholder (jangka panjang).** Proposal awal (v2, sudah disetujui reviewer) menetapkan model **per-seat kontinu + trial kolaborasi 2 bulan** (Opsi A). Melalui pendalaman teknis & bisnis lebih lanjut, tim mengusulkan revisi ke model **5-tier bundle pay-before-use** (Opsi B). **Untuk MVP 2 minggu ini, kita TIDAK membangun salah satu dari keduanya secara penuh** — supaya effort development tidak habis di kompleksitas billing sebelum sinyal intinya sendiri tervalidasi. Sebagai gantinya, MVP membangun versi **paling minim** yang cukup untuk mengukur kriteria sukses proposal (khususnya: apakah Owner mau bayar untuk menambah staf). Opsi A/B penuh menyusul di fase 1.5 setelah data validasi terkumpul.

**Model MVP — Free vs Paid (2 kondisi):**

| Kondisi | Kapasitas Employee | Limit transaksi/hari (per jenis) | Harga |
|---|---|---|---|
| **Free** | 0 (solo Owner) | 150 per jenis (income & expense terpisah) per hari UTC | Rp 0 |
| **Paid** | Tanpa batas | Tanpa batas | **Rp99.000/bulan flat** |

**Prinsip inti — pay before use:**
- Company mulai di Free. Begitu Owner coba tambah Employee pertama → diarahkan bayar dulu (manual transfer, admin approve).
- Setelah admin approve, company menjadi **Paid**: `paid_until` = waktu approval + 30 hari, kapasitas Employee terbuka tanpa batas, limit transaksi harian tidak berlaku lagi.
- Selama di Paid, tambah Employee **kapan saja, gratis, langsung aktif** — tidak ada tagihan tambahan per Employee (karena cuma ada 1 harga flat, bukan per-seat).
- Setiap pembayaran (baik pertama kali maupun perpanjangan) punya efek yang sama: extend `paid_until` +30 hari dari nilai lama jika masih aktif, atau set `paid_until` = waktu approval +30 hari jika sudah Free/closed. **Tidak ada konsep "upgrade antar tier"** karena cuma ada 1 tier berbayar — ini yang paling menyederhanakan dibanding baik Opsi A maupun B.
- Semua timestamp dan perhitungan waktu memakai UTC.

| Fitur | Prioritas | Catatan |
|---|---|---|
| Kolom `paid_until` per company | 🔴 | Sumber kebenaran tunggal untuk akses — dicek live, bukan di-set cron. Null untuk company di Free |
| Middleware `EnsureCompanySubscription` cek akses live (`now() <= paid_until` ATAU sedang di Free) | 🔴 | Berjalan setelah `EnsureUserActive`; berlaku untuk Owner & semua Employee |
| Cache Redis untuk hasil cek akses | 🔴 | Key per company, invalidate saat `paid_until` berubah |
| Counter transaksi harian (Redis, per company per jenis) | 🔴 | Key `company:{id}:txn_count:{type}:{date}` dengan `{date}` = tanggal **input** (`created_at`) dalam UTC, window `00:00:00–23:59:59`, TTL auto-expire akhir hari. Di-increment hanya setelah transaksi berhasil commit; soft-delete men-decrement; edit `income↔expense` mengembalikan kuota tipe lama lalu memakai kuota tipe baru. Redis gagal → fallback `COUNT` dari database dengan semantik identik (exclude soft-deleted). MVP mengandalkan disable button; retry jaringan/multi-tab dan deduplikasi server belum dijamin. **Hanya berlaku di Free** |
| Soft warning di 80% limit, hard block di 100% (Free) | 🔴 | Pesan block mengarahkan ke halaman "Upgrade ke Paid" sebagai jalan keluar |
| Blokir tambah Employee selama masih Free | 🔴 | Kapasitas Free = 0, arahkan ke halaman pembayaran |
| Tambah Employee tanpa batas & gratis selama Paid | 🔴 | Tidak ada tagihan tambahan per Employee |
| Pembayaran (pertama kali / perpanjangan) → admin approve → extend/set `paid_until` +30 hari | 🔴 | Satu jenis approval saja, tidak ada percabangan "upgrade vs renewal" seperti di Opsi A/B |
| **Graceful degrade ke Free saat `paid_until` lewat tanpa pembayaran** | 🔴 | Owner **tetap bisa login**, otomatis turun ke Free dan kembali terkena limit transaksi 150 income + 150 expense per hari. Bukan blokir total |
| **Semua Employee otomatis nonaktif saat degrade ke Free** | 🔴 | `users.status=inactive`, `inactive_reason=subscription_expired`; Owner tetap aktif dan dapat mengakses sistem; Worker roster tanpa akun tidak terpengaruh |
| **Employee otomatis aktif kembali saat company balik ke Paid** | 🔴 | Hanya akun Employee dengan `inactive_reason=subscription_expired` yang dipulihkan; akun dengan `manual` tetap nonaktif |
| Status akun Employee dan alasan inaktivasi | 🔴 | Simpan `status` (`active`/`inactive`) dan `inactive_reason` (`manual`/`subscription_expired`/`company_closed`, nullable) secara terpisah |
| **Middleware status akun** | 🔴 | `EnsureUserActive` menolak user Employee inactive sebelum `EnsureCompanySubscription` dijalankan |
| **Tutup akun dan reaktivasi** | 🔴 | Company berstatus `closed` tidak dapat login. Reaktivasi hanya melalui admin setelah pembayaran disetujui; tidak ada reaktivasi mandiri Owner |
| Notifikasi reminder H-3 sebelum jatuh tempo (email) | 🟡 | Best-effort via scheduled job |
| Admin panel approve pembayaran manual | 🔴 | Satu halaman, satu jenis aksi — lihat modul Super Admin |
| Model 5-tier / per-seat+trial (Opsi A/B penuh) | ⚪ | **Fase 1.5** — dibangun setelah sinyal validasi dari model minim ini terkumpul |
| Integrasi payment gateway (Midtrans/Xendit) | ⚪ | Di luar scope MVP |

**Alur billing (MVP):**

1. **Owner di Free:** Solo, tanpa Employee, tanpa tagihan. Dibatasi limit transaksi harian per jenis (150), soft warning di 80%, hard block di 100% dengan CTA "Upgrade ke Paid".
2. **Owner coba tambah Employee pertama:** Kapasitas Free = 0 → diblokir, diarahkan ke halaman pembayaran.
3. **Owner bayar manual, upload bukti:** Admin approve → `paid_until` = hari ini + 30 hari, company jadi Paid, limit transaksi harian tidak berlaku lagi.
4. **Tambah Employee kapan saja selama Paid:** Langsung aktif, tanpa tagihan tambahan — berapa pun jumlahnya.
5. **Sebelum `paid_until` habis, Owner bayar lagi:** Admin approve → `paid_until` di-extend +30 hari dari nilai lama.
6. **Kalau tidak dibayar sampai lewat `paid_until`:** Company **otomatis kembali ke Free**. Owner tetap bisa login dengan batasan Free. **Semua Employee otomatis nonaktif** (kapasitas Free = 0, tidak perlu logic pemilihan).
7. **Owner bayar lagi setelah degrade:** Admin approve → company balik Paid, `paid_until` di-set hari ini +30 hari, **semua Employee yang sebelumnya aktif otomatis pulih** (tanpa perlu Owner pilih manual satu-satu, karena Paid tidak berkuota).

**Alur Tutup Akun (Account Closure):**

Karena model pay-before-use (dibayar di muka, tidak ada tagihan menggantung), Tutup Akun sederhana:

1. Owner klik "Tutup Akun" → pop-up konfirmasi (data tidak hilang, tapi akun langsung nonaktif).
2. Company langsung diberi status `closed` (**soft-close, bukan hard delete**). Owner & semua Employee tidak bisa login. Data tetap tersimpan untuk audit/restore.
3. Reaktivasi hanya dapat dilakukan melalui admin setelah pembayaran disetujui. Admin mengubah status company menjadi `active`, menetapkan `paid_until`, serta memulihkan Employee yang sebelumnya dinonaktifkan karena `inactive_reason=company_closed`.
4. Owner tidak dapat mengaktifkan kembali akun secara mandiri karena company berstatus `closed` tidak dapat login.

**Rencana Fase 1.5 (referensi, TIDAK dikerjakan di MVP):**

Setelah model minim ini memvalidasi sinyal inti (Owner solo mau bayar untuk nambah tim), salah satu dari dua opsi berikut akan dikembangkan lebih lanjut sesuai keputusan stakeholder:

| Aspek | Opsi A — Per-seat + Trial (Disetujui Proposal) | Opsi B — 5-Tier Bundle (Revisi Diusulkan Tim) |
|---|---|---|
| Struktur harga | Linear per staf/bulan | Flat per tier (Free→Bronze→Silver→Gold→Platinum, kapasitas Employee bertingkat) |
| Trial gratis | 2 bulan begitu staf pertama ditambahkan | Tidak ada trial — pay-before-use langsung |
| Diferensiasi fitur per level | Tidak ada — semua staf dapat fitur sama | Tangga fitur (audit trail, analytic, export) tiap tier |
| Kesesuaian dengan proposal yang sudah di-acc | ✅ Sesuai | ⚠️ Perlu re-approval dari reviewer/stakeholder |

*(Detail teknis lengkap kedua opsi — termasuk kalkulasi prorata, kapasitas tiap tier, dan mekanisme graceful degrade bertingkat — sudah pernah dirancang penuh dalam proses diskusi tim dan bisa diambil kembali sebagai starting point begitu fase 1.5 dimulai, supaya tidak perlu didesain ulang dari nol.)*

### 3.7 Modul Super Admin Panel (Internal, khusus tim kami)

Panel ini dipakai oleh admin internal (tim kami), **bukan** oleh Owner/Employee. Mencakup approve pembayaran manual serta manajemen company dasar (detail, daftar user, aktivasi/ban, tutup company) — tanpa kelengkapan operasional lanjutan seperti statistik detail atau monitoring WAHA yang masih di fase 2.

| Fitur | Prioritas | Catatan |
|---|---|---|
| Login admin terpisah (guard/tabel berbeda dari `users`) | 🔴 | Isolasi keamanan dari akun customer |
| 1 akun admin fixed (dibuat manual via seeder) | 🔴 | Tidak ada UI untuk kelola admin lain di MVP |
| List Company + halaman detail | 🔴 | List: nama usaha, Owner, status (Free/Paid), `paid_until`. Detail: info usaha + daftar user (Owner & Employee) per company |
| List pembayaran pending + Aksi "Approve" | 🔴 | Satu jenis pengajuan saja (tidak ada percabangan renewal/upgrade seperti di Opsi A/B) — approve langsung extend/set `paid_until` |
| Aktivasi/nonaktifkan user (Owner atau Employee) | 🔴 | Toggle `users.status` dari halaman detail company — reuse field existing, bukan kolom baru |
| Ban user dengan alasan wajib | 🔴 | Set `users.status=inactive` + `users.inactive_reason=admin_ban`; user langsung ter-logout dari sesi aktif (pola sama US-AUTH-08 AC2) |
| Tutup / aktifkan kembali company | 🔴 | Admin bisa set `companies.status=closed` manual (selain otomatis lewat US-SUB-07); reaktivasi tetap lewat alur approve pembayaran (US-SUB-07 AC4) |
| Statistik detail per company (jumlah transaksi, dsb) | ⚪ | **Dipangkas dari MVP** — fase 2, detail company MVP cukup info + daftar user, tanpa angka statistik tambahan |
| Monitoring status koneksi WAHA + reconnect dari panel | ⚪ | Fase 2, bareng dengan aktivasi fitur WA |
| Log pengiriman WA/Email gagal | ⚪ | Fase 2, relevan setelah WA aktif |

**Catatan teknis:** disarankan pakai **Filament** untuk generate CRUD panel ini dengan cepat — scope bertambah dibanding rencana awal (kini termasuk detail company + manajemen user), realistis diselesaikan dalam 1–2 hari kerja.

### 3.8 Modul Modal/Kas Usaha

Modul baru — klarifikasi terhadap item "Manajemen Kas & Rekening" di proposal v2. Bukan multi-akun kas/bank; ini pencatatan **modal awal (base capital)** per periode sebagai baseline pembanding terhadap income/expense (misal: set modal Rp1.000.000, dibandingkan dengan pemasukan/pengeluaran periode berjalan).

| Fitur | Prioritas | Catatan |
|---|---|---|
| Set modal awal (nominal + masa berlaku) | 🔴 | Hanya bisa diakses saat **tidak ada modal aktif** (constraint: satu modal aktif dalam satu waktu, tidak boleh overlap). Dropdown durasi: 1 hari / 1 minggu / 1 bulan (30 hari) / Custom Range (date range picker) |
| Top-up modal aktif | 🔴 | Selama modal masih aktif, Owner "top-up" (edit entry yang sama, bukan bikin entry baru) — bukan modal aktif ganda. Preview reaktif: Total Modal Periode Ini & Total Modal Saat Ini update live saat nominal tambahan diketik |
| Extend end date saat top-up | 🔴 | Opsional — kalau diisi, masa berlaku modal aktif diperpanjang; kalau tidak, end date tetap |
| Widget Total Modal Periode Ini & Total Modal Saat Ini | 🔴 | Widget terpisah dari Analytic, tampil di dashboard. Periode Ini = akumulasi nominal awal+top-up; Saat Ini = Periode Ini dikurangi net expense sejak start_date |
| Riwayat modal & top-up | 🔴 | List entry modal per periode + expandable riwayat top-up di dalamnya (nominal, tanggal, ada/tidaknya extend). Hanya Owner yang bisa akses |
| Blokir transaksi jika tidak ada modal aktif | 🔴 | Tombol submit form transaksi disabled sampai modal aktif diset — berlaku untuk Owner maupun Employee |
| Alert saat mau transaksi tanpa modal aktif | 🔴 | Untuk Owner: alert + tombol "Set Modal Sekarang". Untuk Employee: alert tanpa tombol (arahkan hubungi Owner) |
| Alert global non-removable saat belum ada modal aktif | 🔴 | Banner sticky tampil di **semua halaman aplikasi** (bukan cuma dashboard), untuk Owner & Employee, tidak bisa di-dismiss manual — hilang otomatis begitu modal aktif diset |
| Perilaku saat Total Modal Saat Ini negatif (pemakaian > modal) | 🔴 | **Dikonfirmasi:** transaksi tetap diizinkan (tidak diblok) — modal sebagai indikator informasional, bukan hard limit. Angka ditampilkan negatif/merah sebagai sinyal ke Owner. Beda dengan US-MK-04 (belum ada modal aktif sama sekali), yang tetap diblok total. |
| Akses menu khusus Owner (zero-access Employee untuk kelola, bukan untuk alert) | 🔴 | Employee tidak bisa set/top-up modal maupun lihat riwayat, tapi tetap menerima alert (lihat 2 baris di atas) |

### 3.9 Modul Customer

> **Baru di v1.3** — hasil gap analysis dengan client, sebelumnya tidak ada di PRD maupun proposal v2. Customer adalah entitas ringan (bukan sub-modul Invoice) yang berelasi ke transaksi income.

| Fitur | Prioritas | Catatan |
|---|---|---|
| CRUD data Customer (nama, kontak, alamat) | 🔴 | Owner & Employee dua-duanya bisa menambah Customer baru — didesain supaya bisa ditambah cepat saat sedang mencatat transaksi, bukan cuma lewat halaman kelola terpisah |
| Kaitkan Customer ke transaksi (`transactions.customer_id`) | 🔴 | Nullable, **khusus transaksi income** — Customer secara konsep adalah pembeli/pelanggan (pihak yang membayar), bukan relevan untuk expense |
| Kaitkan Customer ke Invoice (`invoices.customer_id`) | 🔴 | **Wajib diisi** — beda dari relasi ke transaksi yang nullable, karena invoice by definition selalu untuk satu customer tertentu |
| Auto-lock `customer_id` transaksi saat terhubung Invoice | 🔴 | Kalau transaksi punya `invoice_id`, `customer_id`-nya otomatis ikut dari invoice tsb (dikunci sistem, tidak bisa dipilih beda secara manual) — supaya query "semua transaksi milik Customer X" tetap satu kolom, tidak perlu logic beda-beda tergantung sumbernya |
| Breakdown sederhana di detail Customer (total nominal, jumlah transaksi) | 🔴 | **Baru.** Dihitung on-the-fly dari `transactions` (`SUM`/`COUNT` where `customer_id` = customer tsb) — bukan field agregat tersimpan. Efektif "gratis" karena data & relasinya sudah ada, cuma tambah query aggregate di halaman yang sudah direncanakan (US-CUST-03) |

### 3.10 Modul Invoice

> **Baru di v1.3** — hasil gap analysis dengan client, sebelumnya tidak ada di PRD maupun proposal v2. Invoice di sini adalah tagihan sederhana **tanpa status/tracking** (bukan Draft/Terkirim/Lunas) — progress dihitung on-the-fly dari transaksi yang terhubung. Invoice yang sudah memiliki transaksi terkait masuk keadaan **frozen**: Customer, Worker/Employee, dan `invoice_items` tidak dapat diedit atau dihapus. Invoice tanpa transaksi terkait masih dapat diedit atau dihapus oleh pembuatnya (Employee) atau Owner.

| Fitur | Prioritas | Catatan |
|---|---|---|
| Create Invoice (customer, worker/employee opsional, rincian item) | 🔴 | Owner & Employee bisa membuat Invoice. Field `employee_id` **opsional** — tidak semua invoice perlu penanggung jawab pengerjaan tercatat |
| Edit/hapus Invoice dan item | 🔴 | Invoice hanya dapat diedit/dihapus jika belum memiliki transaksi terkait. Setelah transaksi pertama terkait, Invoice **frozen**: Customer, Worker/Employee, dan seluruh `invoice_items` tidak dapat diubah/dihapus |
| Rincian item (`invoice_items`: deskripsi + nominal) | 🔴 | Minimal 1 item wajib per Invoice — supaya tagihan jelas terdiri dari apa saja, bukan cuma satu angka total tanpa rincian |
| `nominal_total` dihitung otomatis dari `SUM(invoice_items.amount)` | 🔴 | **Bukan** field tersimpan/diinput manual terpisah — mencegah risiko total invoice tidak sinkron dengan rincian itemnya |
| Kaitkan transaksi ke Invoice (`transactions.invoice_id`) | 🔴 | Nullable, satu Invoice bisa dikaitkan ke banyak transaksi (one-to-many) |
| Validasi SUM saat transaksi disubmit/diedit | 🔴 | `SUM(transaksi terkait yang tidak soft-deleted, exclude yang sedang diedit) + nominal_baru ≤ nominal_total`; jika total sudah sama dengan nominal invoice, transaksi baru ditolak dengan pesan saldo tersisa Rp0 |
| Validasi concurrency pengaitan transaksi | 🔴 | Validasi dan penyimpanan dilakukan dalam DB transaction dengan `lockForUpdate()` pada Invoice; request bersamaan harus divalidasi ulang setelah lock diperoleh |
| Memindahkan atau melepas transaksi dari Invoice | 🔴 | Boleh jika user berwenang; validasi ulang Invoice tujuan. Invoice lama otomatis memiliki saldo kembali. Jika Invoice tujuan penuh, pemindahan ditolak |
| Entry point pengaitan Invoice–Transaksi | 🔴 | Fokus di **form Transaksi** (pilih/cari Invoice existing saat input transaksi). Shortcut juga tersedia dari halaman detail Invoice, tapi cuma navigasi + pre-fill ke form Transaksi yang sama — bukan alur input terpisah |
| Progress Invoice dihitung on-the-fly | 🔴 | `SUM` transaksi terkait yang tidak soft-deleted dibandingkan `nominal_total`, dihitung real-time — bukan field status tersimpan, supaya tidak ada risiko data drift |
| List Invoice (Owner & Employee) | 🔴 | Halaman terpisah menampilkan semua Invoice + progress/sisa saldo yang belum "terpakai" transaksi. Employee juga punya akses karena perlu mencari Invoice saat mencatat transaksi |
| Khusus transaksi income | 🔴 | Konsisten dengan Customer — Invoice merepresentasikan tagihan ke pelanggan, bukan relevan untuk expense |
| Status/tracking Invoice (Draft/Terkirim/Lunas) | ⚪ | **Sengaja tidak dibangun** — diputuskan cukup relasi ke transaksi, tanpa workflow status terpisah |
| Entitas `item` generik/polymorphic lintas modul | ⚪ | **Sengaja ditunda** — `invoice_items` di sini scope-nya sempit (khusus rincian Invoice), bukan entitas reusable lintas vertikal bisnis. Lihat bagian 9 untuk rasional lengkap |

---

## 4. Gambaran Entitas Data (High-Level)

Bukan skema final, hanya panduan awal untuk tim dev:

- `companies` — data usaha (nama, alamat, dll), `status` (`active`/`closed`), `paid_until` (nullable)
- `users` — akun login, relasi ke `company_id`, kolom `role` (owner/employee), `status` (`active`/`inactive`), `inactive_reason` (`manual`/`subscription_expired`/`company_closed`/`admin_ban`, nullable). Hanya berisi akun yang benar-benar bisa login
- `employees` — **baru (v1.3)**. Roster tim, relasi ke `company_id`, `name`, `has_access_to_system` (boolean), `user_id` (nullable FK → `users`, terisi hanya kalau `has_access_to_system=true`). Daily worker = baris dengan `has_access_to_system=false` & `user_id=NULL`
- `customers` — **baru (v1.3)**. Relasi ke `company_id`, `name`, `contact`, `address`
- `invoices` — **baru (v1.3)**. Relasi ke `company_id`, `customer_id` (wajib), `employee_id` (nullable, FK → `employees`), `created_by`. **Tidak** ada kolom status; keadaan frozen ditentukan dari ada/tidaknya transaksi terkait yang tidak soft-deleted
- `invoice_items` — **baru (v1.3)**. Relasi ke `invoice_id`, `description`, `amount` (>0). Minimal 1 baris per invoice; `nominal_total` invoice dihitung dari `SUM(amount)`, bukan kolom tersendiri
- `transaction_categories` — relasi ke `company_id`, preset + custom
- `transactions` — relasi ke `company_id`, `category_id`, `created_by`, `updated_by`, `payment_method`. **Tambahan v1.3:** `customer_id` (nullable FK → `customers`, khusus income), `invoice_id` (nullable FK → `invoices`, khusus income), `employee_id` (nullable FK → `employees`, siapa yang mengerjakan — terpisah dari `created_by`), `attachment_path` (nullable, private storage)
- `activity_log` — dikelola oleh `spatie/laravel-activitylog`; subject `Transaction`, causer user/admin, event create/update/delete, properties perubahan, timestamp. Tidak menggunakan tabel audit custom.
- `capital_entries` — relasi ke `company_id`, nominal (akumulasi setelah top-up), `start_date`, `end_date`, `created_by`; constraint hanya satu entry aktif per company dalam satu waktu (tidak overlap). Semua tanggal/timestamp disimpan dan dievaluasi dalam UTC
- `capital_topups` — relasi ke `capital_entry_id`, nominal tambahan, `changed_by`, `changed_at`, `extended_end_date` (nullable) — riwayat top-up per entry modal, dipakai untuk US-MK-03
- `payments` — relasi ke `company_id`, nominal, bukti transfer (`attachment_path`, private storage), status (`pending`/`approved`), `approved_by`, `approved_at`

*(Tabel `products` dan `product_id` di `transactions` sengaja tidak dimasukkan — Produk tetap di luar MVP. Attachment transaksi dan bukti pembayaran masuk MVP. Tabel `item` generik/polymorphic lintas modul juga sengaja tidak dibangun — lihat bagian 9.)*

---

## 5. Saran Pembagian Kerja (Tim 2–3 Developer, 2 Minggu)

Ini hanya kerangka awal — sesuaikan dengan skill masing-masing anggota tim. Dengan scope yang sudah dipangkas, ada buffer waktu lebih longgar dibanding rencana awal.

- **Developer A:** Modul Autentikasi + Role/Permission (fondasi, harus selesai duluan karena modul lain bergantung padanya, termasuk skema `employees`/`users` yang direvisi di v1.3)
- **Developer B:** Modul Pencatatan Transaksi + Analytic + Modal/Kas Usaha + Customer & Invoice (paling berat, prioritas utama — Modal/Kas Usaha berelasi erat dengan validasi form transaksi di US-MK-04; Invoice juga berelasi erat lewat validasi SUM di form transaksi yang sama)
- **Developer C:** Modul Subscription (Free/Paid) + Super Admin Panel minimal + polish UI/UX keseluruhan

**Saran urutan pengerjaan:**
1. **Hari 1–3:** Setup project (Docker, struktur DB, Auth dasar, layout Inertia+Vue)
2. **Hari 4–7:** Core Transaksi + Role scoping berjalan penuh
3. **Hari 8–9:** Analytic (ringkasan card) + Subscription flow (Free/Paid)
4. **Hari 10–11:** Super Admin Panel minimal (Filament)
5. **Hari 12–14:** Testing lintas modul, bugfix, polish UI, buffer untuk hal tak terduga

---

## 6. Fitur yang Sengaja Di-defer ke Fase 2

- Multi-branch per company
- Tracking stok/inventory
- Approval flow transaksi
- Integrasi payment gateway otomatis
- **Master data Produk** (kaitkan transaksi ke produk) — beserta breakdown produk di Analytic
- **Export Data (Excel/CSV/PDF)** — laporan tetap tersedia in-app, hanya file unduhan yang ditunda
- **Grafik/chart di Analytic** — MVP cukup ringkasan angka (card)
- **Model Subscription lengkap** (Opsi A per-seat+trial ATAU Opsi B 5-tier bundle) — MVP pakai versi minimal Free/Paid, fase 1.5 baru bangun salah satu opsi penuh
- Analytic mendalam (breakdown kategori, profit margin)
- Role granular (lebih dari Owner/Employee)
- Verifikasi email saat registrasi
- **Integrasi WhatsApp (WAHA)** — verifikasi akun, reset password, OTP; termasuk fallback logic dan health check sesi
- Monitoring & reconnect sesi WAHA di Super Admin Panel
- Super Admin Panel: statistik detail per company, list Employee lintas company (cross-company), log pengiriman WA/Email gagal

---

## 7. Risiko yang Perlu Dipantau

- **Model subscription MVP terlalu sederhana untuk validasi bisnis penuh:** karena cuma Free/Paid tanpa tier, beberapa insight yang diharapkan proposal (misal pola upgrade bertahap) tidak akan terlihat di MVP ini — cuma sinyal biner "mau bayar atau tidak". Ini trade-off sadar demi kecepatan; insight lebih kaya baru didapat di fase 1.5.
- **WAHA untuk verifikasi akun/reset password/OTP (fase 2):** WAHA adalah klien WhatsApp tidak resmi (menjalankan WhatsApp Web di balik layar), bukan API resmi Meta. WhatsApp secara aktif bisa mendeteksi & memblokir nomor yang dipakai dengan cara ini, terutama karena pola pengiriman 1 nomor bisnis → banyak nomor merchant berbeda (rawan terdeteksi sebagai bot/spam). Kalau nomor ke-ban, tidak ada proses banding yang jelas. **Saat fitur ini dikerjakan nanti, wajib** ada fallback otomatis ke Email untuk semua flow security-critical, nomor dedicated khusus bisnis, dan monitoring kesehatan sesi secara berkala.
- **Proses approve pembayaran manual:** akan menjadi bottleneck operasional saat jumlah user bertambah — perlu direncanakan proses/SOP-nya di luar sistem, bahkan sebelum jadi bottleneck teknis.
- **Harga langganan Rp99.000/bulan belum divalidasi riset:** angka ini estimasi strategis berbasis benchmark kompetitor (di antara aplikasi pencatatan gratis ~Rp12.500/bulan dan software akuntansi kelas atas Rp149rb+/bulan) dan daya beli lokal (UMK Denpasar 2026 ~Rp3,5 juta/bulan), dipilih untuk kesan premium/tepercaya. Belum diuji langsung ke calon user Bali — proposal v2 sendiri menyarankan validasi via survei Van Westendorp atau fake-door test sebelum harga dikunci permanen untuk rilis publik. Nominal tier Opsi A/B di fase 1.5 juga masih perlu keputusan bisnis terpisah.

---

## 8. User Stories per Modul

Format: **Sebagai** [role], **saya ingin** [aksi], **supaya** [manfaat]. Dilengkapi Acceptance Criteria (AC) untuk memperjelas batasan "selesai".

### 8.1 Modul Autentikasi

**US-AUTH-01 — Register akun Owner**
Sebagai calon Owner, saya ingin mendaftar akun baru dengan membuat data usaha saya, supaya saya bisa mulai mencatat transaksi keuangan usaha saya.
- AC1: Form register meminta nama usaha, nama pemilik, email (wajib & unik), nomor WhatsApp, password.
- AC2: Setelah submit, sistem langsung membuat `company` baru + akun Owner, tanpa perlu verifikasi email (skip untuk MVP).
- AC3: Setelah register, Owner langsung diarahkan ke dashboard (auto-login).
- AC4: Validasi password minimum (misal 8 karakter) ditampilkan di form.

**US-AUTH-02 — Login**
Sebagai Owner atau Employee, saya ingin login menggunakan email/username dan password, supaya saya bisa mengakses data usaha yang relevan dengan role saya.
- AC1: Login gagal menampilkan pesan error yang jelas tanpa membocorkan apakah email/username terdaftar atau tidak (mitigasi enumeration).
- AC2: Setelah login, redirect ke dashboard sesuai role (Owner lihat semua, Employee lihat scope miliknya).
- AC3: Employee dengan status `inactive` (dinonaktifkan Owner, atau otomatis nonaktif karena company degrade ke Free) tidak bisa login — pesan jelas menyebutkan alasannya.

**US-AUTH-03 — Logout**
Sebagai user yang sedang login, saya ingin logout, supaya sesi saya di device ini berakhir dan data usaha tetap aman.
- AC1: Logout menghapus session dan redirect ke halaman login.

**US-AUTH-04 — Reset password via Email**
Sebagai user yang lupa password, saya ingin reset password lewat email, supaya saya bisa mengakses akun saya kembali.
- AC1: User input email terdaftar, sistem kirim link reset (berlaku terbatas, misal 60 menit).
- AC2: Link reset hanya bisa dipakai sekali.
- AC3: Setelah password direset, semua sesi aktif lain di-invalidate (memaksa login ulang).

**US-AUTH-05 — Reset password / verifikasi akun / OTP via WhatsApp (WAHA) — *(Fase 2, tidak dikerjakan di MVP)***
Sebagai user yang lupa password atau perlu verifikasi, saya ingin menerima kode/link lewat WhatsApp, supaya saya tetap bisa reset akun walau jarang cek email.
- AC1: Sistem mengirim OTP/link reset via WAHA ke nomor WA terdaftar.
- AC2: Jika pengiriman WA gagal (sesi WAHA terputus, timeout, error API), sistem **otomatis** kirim ulang via Email tanpa user perlu request ulang secara manual.
- AC3: OTP via WA punya masa berlaku terbatas (misal 5–10 menit) dan hanya bisa dipakai sekali.
- AC4: Kegagalan pengiriman WA dicatat di log untuk dipantau admin (indikasi kalau sesi WAHA perlu di-scan ulang).

**US-AUTH-06 — Health check sesi WAHA — *(Fase 2, tidak dikerjakan di MVP)***
Sebagai admin sistem, saya ingin mendapat alert kalau sesi WhatsApp (WAHA) terputus, supaya saya bisa scan ulang QR code secepatnya sebelum banyak user gagal menerima OTP.
- AC1: Job terjadwal mengecek status koneksi WAHA secara berkala (misal tiap 15 menit).
- AC2: Kalau status terputus, kirim notifikasi ke admin (email internal/dashboard admin).
- AC3: Status koneksi WAHA terlihat di admin panel secara real-time.

**US-AUTH-07 — Owner membuat akun Employee**
Sebagai Owner, saya ingin membuat akun untuk karyawan saya langsung (nama, username, password), supaya karyawan saya bisa mulai input transaksi tanpa proses invitation yang ribet.
- AC1: Form "Tambah Karyawan" di menu Kelola Karyawan: nama, username, password (di-generate otomatis atau diisi manual oleh Owner).
- AC2: Kalau company masih berstatus Free, tombol "Tambah Karyawan" diarahkan ke halaman pembayaran (lihat US-SUB-02) — tidak bisa membuat akun Employee sampai company jadi Paid.
- AC3: Kalau company sudah Paid, Employee baru langsung aktif tanpa hambatan apa pun, berapa kali pun ditambahkan.
- AC4: Owner bisa melihat daftar semua Employee beserta statusnya (`active`/`inactive`) di satu halaman.

**US-AUTH-07B — Owner menambahkan daily worker tanpa akun login** *(Baru, v1.3)*
Sebagai Owner, saya ingin mencatat pekerja lepas/harian di roster tim saya tanpa perlu membuatkan akun login, supaya saya bisa men-tracking siapa yang mengerjakan suatu pekerjaan tanpa memberi akses ke sistem.
- AC1: Form "Tambah Worker" di menu Kelola Karyawan (varian tanpa akun): cukup nama, tanpa username/password.
- AC2: Baris `employees` yang dihasilkan punya `has_access_to_system=false` dan `user_id=NULL` — tidak pernah bisa login.
- AC3: Penambahan worker **tidak** dibatasi status Free/Paid (beda dari Employee ber-akun di US-AUTH-07 AC2) — karena worker tidak menambah kapasitas login, tidak relevan dengan limit Subscription.
- AC4: Worker tampil di list yang sama dengan Employee ber-akun (US-AUTH-07 AC4), dibedakan lewat badge/indikator status akses.

**US-AUTH-08 — Owner menonaktifkan akun Employee**
Sebagai Owner, saya ingin menonaktifkan akun karyawan yang sudah tidak bekerja lagi, supaya dia tidak bisa lagi mengakses data usaha saya.
- AC1: Aksi nonaktifkan mengubah status jadi `inactive` (soft-disable, bukan hard delete — histori transaksi yang pernah diinput Employee tsb tetap tersimpan).
- AC2: Employee yang dinonaktifkan langsung ter-logout dari sesi aktifnya (kalau sedang login).

### 8.2 Modul Super Admin Panel

**US-ADMIN-01 — Login admin**
Sebagai admin internal, saya ingin login ke panel terpisah dari akun customer, supaya akses saya terisolasi dan lebih aman.
- AC1: Login admin menggunakan guard/tabel terpisah dari `users` (Owner/Employee).
- AC2: URL panel admin berbeda dari aplikasi utama (misal `/admin`).
- AC3: Akun admin untuk MVP dibuat manual via seeder — tidak ada UI register/kelola admin lain.

**US-ADMIN-02 — Melihat list Company**
Sebagai admin, saya ingin melihat daftar semua Company yang terdaftar beserta status langganannya, supaya saya bisa memantau siapa saja pengguna platform ini.
- AC1: List menampilkan nama usaha, nama Owner, status (Free/Paid), `paid_until`.
- AC2: Bisa mencari/filter berdasarkan nama usaha, email Owner, atau status.

**US-ADMIN-03 — Melihat daftar pembayaran pending**
Sebagai admin, saya ingin melihat daftar pengajuan pembayaran yang menunggu approval, supaya saya tahu mana yang perlu diproses.
- AC1: List menampilkan nama Company, nominal, bukti transfer, tanggal pengajuan.
- AC2: Bisa difilter berdasarkan status (`pending`, `approved`).

**US-ADMIN-04 — Approve pembayaran**
Sebagai admin, saya ingin menandai suatu pengajuan sebagai lunas, supaya company terkait otomatis jadi Paid/diperpanjang.
- AC1: Aksi "Approve" mengubah status pengajuan jadi `approved`.
- AC2: `paid_until` otomatis di-extend +30 hari dari nilai lama (kalau sudah Paid) atau di-set ke tanggal approve +30 hari (kalau baru naik dari Free).
- AC3: Tercatat siapa admin yang melakukan approve dan kapan (audit sederhana).

**US-ADMIN-05 — Admin melihat detail company**
Sebagai admin, saya ingin membuka halaman detail sebuah company, supaya saya bisa melihat info usaha dan daftar user di dalamnya sekaligus.
- AC1: Detail menampilkan nama usaha, nama Owner, status subscription (Free/Paid), `paid_until`, dan status company (`active`/`closed`).
- AC2: Detail menampilkan daftar user (Owner & Employee) beserta role dan status (`active`/`inactive`).
- AC3: Aksi "Detail" di List Company (US-ADMIN-02) membuka halaman ini.

**US-ADMIN-06 — Admin mengaktifkan/menonaktifkan user**
Sebagai admin, saya ingin mengaktifkan atau menonaktifkan akun Owner/Employee dari halaman detail company, supaya saya bisa membantu mengelola akses saat dibutuhkan (misal permintaan Owner lewat support).
- AC1: Aksi toggle mengubah `users.status` antara `active`/`inactive`.
- AC2: User yang dinonaktifkan langsung ter-logout dari sesi aktifnya (pola sama US-AUTH-08 AC2).
- AC3: Konfirmasi dialog muncul sebelum aksi dijalankan.

**US-ADMIN-07 — Admin mem-ban user**
Sebagai admin, saya ingin mem-ban user yang melanggar ketentuan, supaya aksesnya dicabut beserta alasannya tercatat.
- AC1: Aksi "Ban" mewajibkan admin mengisi alasan (textarea, wajib).
- AC2: Ban mengubah `users.status=inactive` dan `users.inactive_reason=admin_ban`.
- AC3: User yang di-ban langsung ter-logout dari sesi aktifnya.
- AC4: Hanya admin yang bisa membalikkan ban (lewat aksi aktifkan di US-ADMIN-06) — Owner tidak bisa self-reactivate user yang di-ban admin.

**US-ADMIN-08 — Admin menutup atau mengaktifkan kembali company**
Sebagai admin, saya ingin menutup atau mengaktifkan kembali sebuah company dari panel admin, supaya saya bisa menindaklanjuti pelanggaran atau permintaan penutupan di luar alur self-service Owner (US-SUB-07).
- AC1: Aksi "Tutup Company" mengubah `companies.status=closed`; Owner & semua Employee company tsb langsung tidak bisa login (sama seperti US-SUB-07 AC2).
- AC2: Aksi "Aktifkan Company" mengubah `companies.status=active` dan memulihkan user yang sebelumnya `inactive` dengan `inactive_reason=company_closed` (tidak memulihkan user yang di-ban admin secara terpisah).
- AC3: Konfirmasi dialog wajib sebelum aksi tutup/aktifkan dijalankan.

### 8.3 Modul Subscription

**US-SUB-01 — Melihat limit transaksi harian (Free)**
Sebagai Owner di Free, saya ingin tahu berapa transaksi yang sudah saya input hari ini, supaya saya tidak kaget saat mendekati limit.
- AC1: Dashboard menampilkan indikator pemakaian (misal "120/150 transaksi hari ini") per jenis transaksi (income/expense terpisah).
- AC2: Soft warning muncul saat mencapai 80% dari limit (150/hari), berupa notifikasi non-blocking.
- AC3: Saat mencapai 100%, input transaksi baru untuk jenis tsb diblokir sampai keesokan hari (reset otomatis jam 00:00) — pesan block menyertakan CTA "Upgrade ke Paid".

**US-SUB-02 — Membayar untuk upgrade ke Paid**
Sebagai Owner, saya ingin menyelesaikan pembayaran manual, supaya saya bisa membuka kapasitas Employee tanpa batas.
- AC1: Halaman pembayaran menampilkan harga Paid (**Rp99.000/bulan**) dan instruksi transfer manual.
- AC2: Owner upload bukti bayar, status pengajuan jadi `pending`, admin menerima notifikasi.
- AC3: Selama menunggu approval, Owner tetap di Free (belum bisa tambah Employee).

**US-SUB-03 — Tambah Employee tanpa batas selama Paid**
Sebagai Owner yang sudah Paid, saya ingin menambahkan Employee kapan saja tanpa hambatan, supaya saya bisa mengembangkan tim tanpa mikirin tagihan tambahan.
- AC1: Tidak ada pengecekan kuota — semua penambahan Employee langsung aktif selama company berstatus Paid.

**US-SUB-04 — Reminder H-3 sebelum jatuh tempo**
Sebagai Owner, saya ingin diingatkan sebelum masa aktif langganan saya habis, supaya saya bisa perpanjang tepat waktu.
- AC1: Notifikasi (email, best-effort) dikirim 3 hari sebelum `paid_until`.
- AC2: Kegagalan pengiriman notifikasi ini tidak memengaruhi akses (bukan mekanisme keamanan, cuma reminder).

**US-SUB-05 — Graceful degrade ke Free saat tidak diperpanjang**
Sebagai Owner yang lupa/tidak sempat perpanjang, saya ingin akun saya tetap bisa dipakai dengan keterbatasan, supaya saya tidak kehilangan akses total dan data saya tetap aman.
- AC1: Begitu `now() > paid_until`, sistem otomatis menganggap company berada di Free (dicek live, bukan lewat job terjadwal).
- AC2: Owner tetap bisa login, berlaku semua batasan Free (limit transaksi harian, 0 kuota Employee).
- AC3: Semua Employee otomatis berstatus `inactive` dengan `inactive_reason=subscription_expired`; Owner tetap aktif dan dapat mengakses sistem dengan aturan Free (150 income + 150 expense per hari).
- AC4: Owner mendapat notifikasi bahwa langganannya sudah tidak aktif dan diarahkan ke halaman pembayaran.

**US-SUB-06 — Employee otomatis pulih saat balik ke Paid**
Sebagai Owner yang baru saja membayar lagi setelah sempat degrade ke Free, saya ingin karyawan saya otomatis bisa login lagi, supaya saya tidak perlu mengaktifkan satu-satu secara manual.
- AC1: Begitu admin approve pembayaran dan company balik ke Paid, semua Employee yang berstatus `inactive` akibat degrade otomatis berubah jadi `active`.
- AC2: Employee yang memang sengaja dinonaktifkan Owner sebelumnya (bukan karena degrade) tetap `inactive` — tidak ikut terangkat otomatis.

**US-SUB-07 — Tutup akun**
Sebagai Owner, saya ingin menutup akun usaha saya, supaya saya berhenti menggunakan layanan tanpa harus menghapus data secara permanen.
- AC1: Pop-up konfirmasi menjelaskan konsekuensi (data tidak hilang, tapi akun langsung nonaktif).
- AC2: Setelah konfirmasi, status Company langsung jadi `closed` (soft-close) — Owner & semua Employee langsung tidak bisa login.
- AC3: Data (transaksi, dll) tetap tersimpan di database, tidak dihapus.
- AC4: Reaktivasi hanya dapat dilakukan admin setelah pembayaran disetujui; admin mengubah status company menjadi `active`, menetapkan `paid_until`, dan memulihkan akun yang sebelumnya dinonaktifkan karena `company_closed`. Owner tidak dapat reaktivasi mandiri.

### 8.4 Modul Pencatatan Transaksi

**US-TR-01 — Mencatat transaksi baru**
Sebagai Owner atau Employee, saya ingin mencatat transaksi income atau expense, supaya semua pemasukan/pengeluaran toko tercatat rapi.
- AC1: Form transaksi: jenis (income/expense), nominal, kategori, tanggal, catatan (opsional). Kategori income dan expense terpisah.
- AC2: `transaction_date` wajib berada dalam rentang modal aktif (`start_date ≤ transaction_date ≤ end_date`, `end_date` inklusif) dan tidak boleh lebih besar dari tanggal UTC saat input; transaksi di luar aturan ditolak.
- AC3: Transaksi yang disubmit langsung final — tidak ada status "pending approval".
- AC4: Sistem mencatat `created_by` dan timestamp; `spatie/laravel-activitylog` membuat activity untuk event `created` pada model `Transaction` dengan causer dan properties yang relevan.
- AC5: Nominal harus > 0, jenis dan kategori wajib diisi.
- AC6: Jika kuota harian (Free) sudah tercapai (150/hari per jenis), submit diblok dengan pesan limit.
- AC7: Submit diblok kalau belum ada modal/kas aktif — lihat US-MK-04.
- AC8: Satu attachment opsional dapat diunggah pada transaksi; hanya gambar selain GIF, maksimal 1 MB, disimpan di private storage, dan aksesnya mengikuti authorization transaksi.

**US-TR-01B — Indikator sisa kuota harian (radial chart)**
Sebagai Owner atau Employee di company Free, saya ingin melihat sisa kuota transaksi harian dalam bentuk chart bulat, supaya saya sadar sebelum kena limit.
- AC1: Chart bulat format `n/150` di tengah lingkaran, terpisah untuk kuota income dan expense.
- AC2: Progress ring terisi proporsional (misal 120/150 ≈ 80%).
- AC3: Warna ring ikut design token: Indigo (`#4F46E5`) normal (0–80%), Amber (`#F59E0B`) warning (>80–99%), Rose saat 100%.
- AC4: Angka `n/150` dan label memakai Plus Jakarta Sans sesuai design system; tidak ada font display terpisah.
- AC5: Disembunyikan untuk company Paid.

**US-TR-02 — Mengedit transaksi**
Sebagai Owner atau Employee, saya ingin mengubah data transaksi yang sudah tercatat, supaya saya bisa memperbaiki kesalahan input.
- AC1: Employee hanya bisa edit transaksinya sendiri (`created_by` = user aktif); Owner bisa edit semua transaksi di tokonya.
- AC2: Setiap edit memperbarui `updated_by`/`updated_at`, dan tercatat di `spatie/laravel-activitylog` untuk event `updated` pada model `Transaction`, termasuk properties perubahan lama → baru.
- AC3: Data audit log cukup tersimpan di database — tanpa halaman viewer khusus di MVP.
- AC4: Perubahan jenis `income↔expense` memindahkan quota dari tipe lama ke tipe baru; jika quota tipe baru penuh, edit ditolak tanpa mengubah data transaksi.

**US-TR-03 — Menghapus transaksi**
Sebagai Owner atau Employee, saya ingin menghapus transaksi yang salah/tidak valid, supaya catatan keuangan tetap akurat.
- AC1: Employee hanya bisa hapus transaksinya sendiri; Owner bisa hapus semua transaksi di tokonya.
- AC2: Konfirmasi dialog muncul sebelum hapus.
- AC3: Soft delete (bukan hard delete).
- AC4: Aksi hapus tercatat di `spatie/laravel-activitylog` untuk event `deleted` pada model `Transaction`, termasuk snapshot properties sebelum dihapus.
- AC5: Soft-delete mengembalikan counter quota tipe transaksi dan transaksi yang dihapus tidak dihitung dalam quota, progress Invoice, maupun agregasi laporan.

**US-TR-04 — Melihat daftar transaksi**
Sebagai Owner atau Employee, saya ingin melihat daftar transaksi dalam bentuk list, supaya saya bisa memantau riwayat pencatatan.
- AC1: Owner melihat seluruh transaksi toko; Employee hanya melihat transaksi yang ia input sendiri.
- AC2: Filter by jenis, kategori, dan rentang tanggal.
- AC3: Indikator visual income (hijau) vs expense (rose) sesuai design system.
- AC4: Pagination/infinite scroll untuk performa.

**US-TR-05 — Melihat detail transaksi**
Sebagai Owner atau Employee, saya ingin membuka detail satu transaksi, supaya saya bisa lihat siapa yang input dan kapan.
- AC1: Detail menampilkan nominal, kategori, tanggal, catatan, dan "last updated by" (nama + waktu perubahan terakhir, kalau pernah diedit).
- AC2: Employee hanya bisa membuka detail transaksinya sendiri (akses ke transaksi orang lain ditolak/404).
- AC3: Attachment hanya dapat ditampilkan atau diunduh oleh user yang berwenang mengakses transaksi tersebut.

### 8.5 Modul Role & Permission

**US-RP-01 — Pembatasan akses berdasarkan role di seluruh modul**
Sebagai sistem, saya ingin membatasi menu dan endpoint sesuai role (Owner/Employee), supaya setiap user hanya bisa melakukan aksi sesuai wewenangnya.
- AC1: Employee tidak melihat menu: Kelola Karyawan, Analytic, Modal/Kas Usaha (kelola), Subscription — hanya menu input transaksi & daftar transaksi miliknya.
- AC2: Owner melihat semua menu yang relevan dengan tokonya.
- AC3: Percobaan akses langsung ke endpoint di luar wewenang role (via URL/API) ditolak 403 — bukan cuma disembunyikan di level UI.
- AC4: **(Baru, v1.3)** Baris `employees` dengan `has_access_to_system=false` (daily worker) tidak pernah dievaluasi oleh Gate/Middleware permission — worker bukan aktor dalam sistem, cuma data yang direferensikan (mirip Customer), sehingga tidak ada konsep "role" atau "akses" yang berlaku untuknya sama sekali.

*(Catatan: pembuatan akun Employee, penonaktifan, dan daftar Employee sudah dicakup masing-masing di US-AUTH-07 dan US-AUTH-08 — tidak diduplikasi di sini. Penambahan daily worker tanpa akun dicakup di US-AUTH-07B.)*

### 8.6 Modul Analytic

**US-AN-01 — Melihat ringkasan income vs expense**
Sebagai Owner, saya ingin melihat ringkasan total income dan expense, supaya saya tahu kondisi keuangan toko tanpa hitung manual.
- AC1: Ringkasan menampilkan total income, total expense, dan net (selisih) untuk periode yang dipilih (hari ini/minggu ini/bulan ini/custom range).
- AC2: Tampilan angka/ringkasan teks — tanpa grafik/chart (fase 2).
- AC3: Angka dihitung real-time dari data transaksi.

**US-AN-02 — Melihat breakdown per kategori**
Sebagai Owner, saya ingin melihat total transaksi dikelompokkan per kategori, supaya saya tahu pos pengeluaran/pemasukan terbesar.
- AC1: List kategori income dengan total masing-masing, sorted terbesar; sama untuk expense.
- AC2: Mengikuti filter periode yang sama seperti US-AN-01.

**US-AN-03 — Akses Analytic dibatasi untuk Employee**
Sebagai sistem, saya ingin membatasi akses menu Analytic hanya untuk Owner, supaya data ringkasan keuangan toko tidak terekspos ke semua staf.
- AC1: Employee tidak melihat menu Analytic sama sekali di navigasi (zero-access, bukan versi terbatas).
- AC2: Percobaan akses langsung ke endpoint Analytic oleh Employee ditolak 403.
- AC3: Kebutuhan Employee melihat riwayat kerjanya sendiri sudah terpenuhi lewat US-TR-04 (daftar transaksi miliknya) — tidak perlu menu Analytic terpisah untuk Employee.

### 8.7 Modul Modal/Kas Usaha

Modul baru — klarifikasi terhadap "Manajemen Kas & Rekening" di proposal v2 (lihat modul 3.8 dan Kesenjangan Proposal v2 di bagian 9).

**US-MK-01 — Set modal awal (belum ada modal aktif)**
Sebagai Owner, saya ingin menambahkan modal dengan nominal dan masa berlaku tertentu saat belum ada modal aktif, supaya ada baseline yang bisa dibandingkan dengan transaksi income/expense.
- AC1: Form hanya bisa diakses kalau tidak ada modal aktif saat ini (constraint: satu modal aktif per company, tidak boleh overlap).
- AC2: Dropdown durasi: 1 hari, 1 minggu, 1 bulan (30 hari), atau Custom Range (date range picker).
- AC3: Durasi preset → start date default hari ini, end date dihitung otomatis.
- AC4: Nominal harus > 0.
- AC5: Setelah disubmit, tombol berubah jadi "Top-up Modal" (US-MK-01B) selama entry ini aktif.

**US-MK-01B — Top-up modal yang sedang aktif**
Sebagai Owner, saya ingin menambah nominal (dan opsional memperpanjang masa berlaku) ke modal yang sedang aktif, supaya saya bisa nambah kas kapan saja tanpa menunggu modal lama expired atau menciptakan modal aktif ganda.
- AC1: Form mengedit entry modal aktif yang sama (bukan entry baru).
- AC2: Menampilkan **Total Modal Periode Ini** (akumulasi nominal awal + semua top-up, read-only) dan **Total Modal Saat Ini** (Periode Ini dikurangi net expense sejak start_date, read-only), plus input **Tambahan Modal**.
- AC3: Kedua angka read-only ter-update reaktif (live preview) saat nominal tambahan diketik, sebelum disimpan.
- AC4: Opsi extend end date (opsional) — kalau diisi, end date entry aktif diperpanjang; kalau tidak, tetap seperti semula.
- AC5: Setiap top-up tercatat di `capital_topups` untuk kebutuhan riwayat (US-MK-03).

**US-MK-02 — Widget total modal aktif**
Sebagai Owner, saya ingin melihat widget total modal yang sedang berlaku, supaya saya tahu baseline dan sisa modal saya tanpa hitung manual.
- AC1: Widget terpisah dari Analytic, tampil di dashboard.
- AC2: Menampilkan Total Modal Periode Ini dan Total Modal Saat Ini, serta tanggal expire modal aktif.
- AC3: Kalau tidak ada modal aktif, widget menampilkan status kosong "Belum ada modal aktif".

**US-MK-03 — Riwayat modal & top-up**
Sebagai Owner, saya ingin melihat riwayat semua modal yang pernah saya set termasuk riwayat top-up-nya, supaya saya bisa mengecek kapan dan berapa saya top-up sebelumnya.
- AC1: List entry modal (nominal akhir, start date, end date, status Aktif/Kadaluarsa, tanggal dibuat).
- AC2: Tiap entry bisa di-expand untuk lihat riwayat top-up (tanggal, nominal tambahan, ada/tidaknya extend end date).
- AC3: Diurutkan dari yang terbaru dibuat. Employee tidak punya akses ke halaman ini.

**US-MK-04 — Alert & blokir transaksi saat tidak ada modal aktif**
Sebagai sistem, saya ingin memblokir input transaksi dan menampilkan alert saat tidak ada modal aktif, supaya setiap transaksi yang tercatat selalu punya baseline modal yang valid.
- AC1: Sebelum form transaksi bisa disubmit, sistem menolak `transaction_date` yang lebih besar dari tanggal UTC saat input, lalu mengecek apakah ada entry modal aktif untuk tanggal transaksi tersebut.
- AC2: Kalau tidak ada: form tetap bisa dibuka/diisi, tapi tombol submit disabled, dengan alert banner "Belum ada modal aktif".
- AC3: Alert muncul untuk Owner maupun Employee. Untuk Owner: alert menyertakan tombol "Set Modal Sekarang" (buka US-MK-01). Untuk Employee: alert tanpa tombol set — pesan mengarahkan hubungi Owner.
- AC4: Begitu Owner set modal baru, blokir otomatis hilang.

**US-MK-05 — Alert non-removable global saat belum ada modal aktif**
Sebagai sistem, saya ingin menampilkan alert yang tidak bisa ditutup secara global di seluruh halaman aplikasi saat tidak ada modal aktif, supaya Owner maupun Employee langsung sadar begitu pertama kali buka aplikasi, di halaman mana pun mereka berada.
- AC1: Alert tampil sebagai banner sticky di **semua halaman** (dashboard, daftar transaksi, form transaksi, analytic, dsb) — bukan cuma dashboard.
- AC2: Tampil untuk Owner maupun Employee. Bersifat non-removable — tidak ada tombol tutup, hanya hilang otomatis begitu ada modal aktif yang valid.
- AC3: Isi alert mengikuti pola yang sama dengan US-MK-04 AC3 (Owner dapat tombol set, Employee tidak).
- AC4: Alert tetap tampil kalau modal yang ada sudah expired (bukan cuma saat belum pernah diset sama sekali).

**US-MK-06 — Transaksi tetap berjalan saat modal minus**
Sebagai sistem, saya ingin tetap mengizinkan pencatatan transaksi meskipun Total Modal Saat Ini sudah negatif, supaya modal berfungsi sebagai indikator informasional (bukan hard limit) dan Owner/Employee tidak terhambat mencatat transaksi nyata yang terjadi.
- AC1: Transaksi tetap bisa disubmit meski Total Modal Saat Ini akan menjadi negatif setelah transaksi ini — berbeda dengan US-MK-04 (belum ada modal aktif sama sekali) yang tetap diblok total.
- AC2: Widget MK-02 menampilkan Total Modal Saat Ini dalam warna negatif/merah saat nilainya di bawah nol, sebagai sinyal visual ke Owner.

### 8.8 Modul Customer *(Baru, v1.3)*

**US-CUST-01 — Menambah Customer baru**
Sebagai Owner atau Employee, saya ingin menambahkan data Customer baru (nama, kontak, alamat), supaya saya bisa mengaitkan transaksi/invoice ke pelanggan yang jelas.
- AC1: Form Customer bisa diakses dari halaman kelola Customer maupun langsung dari form Transaksi/Invoice (tambah cepat tanpa pindah halaman).
- AC2: Field wajib: nama. Kontak dan alamat opsional.

**US-CUST-02 — Mengaitkan Customer ke transaksi income**
Sebagai Owner atau Employee, saya ingin memilih Customer saat mencatat transaksi income, supaya saya tahu pemasukan itu dari siapa.
- AC1: Field `customer_id` di form transaksi cuma muncul untuk jenis income, nullable (boleh dikosongkan).
- AC2: Kalau transaksi terhubung ke Invoice (`invoice_id` terisi), `customer_id` otomatis ikut dari invoice tsb dan tidak bisa diubah manual di form transaksi.

**US-CUST-03 — Melihat daftar transaksi & breakdown per Customer**
Sebagai Owner, saya ingin melihat riwayat transaksi dan ringkasan sederhana yang terkait dengan satu Customer tertentu, supaya saya bisa memantau kontribusinya ke pemasukan usaha tanpa hitung manual.
- AC1: Halaman detail Customer menampilkan daftar transaksi income yang terkait (`customer_id` = customer tsb), baik yang lewat Invoice maupun berdiri sendiri.
- AC2: **(Baru)** Di atas daftar transaksi, tampil ringkasan breakdown sederhana: total nominal (SUM seluruh transaksi terkait), jumlah transaksi (COUNT), dan tanggal transaksi terakhir. Dihitung on-the-fly dari data yang sama seperti AC1 — tanpa tabel/field agregat tersendiri.

**US-CUST-04 — Melihat breakdown sederhana di detail Employee/Worker** *(Baru)*
Sebagai Owner, saya ingin melihat ringkasan sederhana transaksi yang dikerjakan oleh seorang Employee atau Worker di halaman detailnya, supaya saya bisa memantau kontribusinya tanpa hitung manual.
- AC1: Halaman detail Employee/Worker (dari Kelola Karyawan) menampilkan ringkasan: total nominal (SUM transaksi dengan `employee_id` = orang tsb) dan jumlah transaksi (COUNT).
- AC2: Berlaku sama untuk Employee ber-akun maupun Worker tanpa akun — keduanya sama-sama baris di `employees`, query-nya identik.
- AC3: Dihitung on-the-fly, pola yang sama seperti US-CUST-03 AC2 — bukan field agregat tersimpan terpisah.

### 8.9 Modul Invoice *(Baru, v1.3)*

**US-INV-01 — Membuat Invoice baru**
Sebagai Owner atau Employee, saya ingin membuat tagihan (Invoice) untuk seorang Customer dengan rincian item yang jelas, supaya Customer tahu persis apa yang ditagihkan.
- AC1: Form Invoice: pilih/tambah Customer (wajib), pilih Employee/worker penanggung jawab (opsional), rincian item (deskripsi + nominal, minimal 1 baris).
- AC2: `nominal_total` dihitung otomatis dari `SUM(invoice_items.amount)` — tidak ada input manual terpisah untuk total.
- AC3: Invoice **tidak** punya field status (bukan Draft/Terkirim/Lunas) — begitu dibuat, langsung bisa dikaitkan ke transaksi.
- AC4: Setelah transaksi non-soft-deleted pertama terkait, Invoice frozen; Customer, Worker/Employee, dan seluruh `invoice_items` tidak dapat diedit atau dihapus.

**US-INV-02 — Mengaitkan transaksi ke Invoice**
Sebagai Owner atau Employee, saya ingin mengaitkan transaksi income yang saya catat ke sebuah Invoice, supaya saya bisa men-tracking sejauh mana tagihan itu sudah "terbayar" lewat transaksi yang tercatat.
- AC1: Entry point utama ada di form Transaksi — field pencarian/pilih Invoice existing, nullable.
- AC2: Validasi saat submit/edit: `SUM(transaksi lain yang terhubung ke invoice yang sama, exclude transaksi ini kalau sedang diedit) + nominal transaksi ini ≤ nominal_total invoice`. Kalau gagal, submit ditolak dengan pesan yang menyebutkan sisa saldo invoice yang masih tersedia.
- AC3: Begitu `invoice_id` dipilih, `customer_id` transaksi otomatis terisi & terkunci dari invoice (lihat US-CUST-02 AC2).
- AC4: Field `invoice_id` cuma muncul untuk transaksi jenis income.
- AC5: Validasi dan penyimpanan pengaitan dilakukan dalam DB transaction dengan `lockForUpdate()` pada Invoice; request bersamaan divalidasi ulang setelah lock diperoleh.

**US-INV-03 — Shortcut pengaitan dari halaman detail Invoice**
Sebagai Owner atau Employee, saya ingin membuka form Transaksi langsung dari halaman detail Invoice dengan invoice sudah terpilih otomatis, supaya saya tidak perlu mencari ulang invoice-nya di form Transaksi.
- AC1: Tombol di halaman detail Invoice membuka form Transaksi yang sama seperti US-INV-02, dengan `invoice_id` (dan `customer_id` turunannya) sudah ter-pre-fill.
- AC2: Ini murni navigasi + pre-fill — tidak ada alur input transaksi terpisah khusus dari sisi Invoice.

**US-INV-04 — Melihat progress Invoice**
Sebagai Owner, saya ingin melihat sejauh mana suatu Invoice sudah "terpakai" oleh transaksi yang terhubung, supaya saya tahu sisa tagihan yang belum tercatat sebagai transaksi.
- AC1: Progress dihitung on-the-fly: `SUM` transaksi terkait dibandingkan `nominal_total` — bukan field status yang tersimpan.
- AC2: Ditampilkan di halaman detail Invoice dan List Invoice (US-INV-05).

**US-INV-05 — Melihat List Invoice**
Sebagai Owner atau Employee, saya ingin melihat semua Invoice yang pernah dibuat beserta progress-nya, supaya saya bisa memantau tagihan mana yang masih ada sisa saldo belum tercatat — termasuk saat sedang mencari Invoice yang tepat untuk dikaitkan ke transaksi (US-INV-02).
- AC1: List menampilkan Customer, nominal total, progress (SUM transaksi terkait vs total), tanggal dibuat.
- AC2: Bisa difilter/dicari berdasarkan nama Customer.
- AC3: **Terbuka untuk Owner & Employee** — beda dari Analytic/Modal-Kas yang zero-access Employee, karena Employee juga perlu lihat daftar Invoice saat mencatat transaksi (bukan cuma Owner yang berkepentingan).

---

## 8A. Breakdown Feature & Task Implementasi

Task di bawah adalah task implementasi, bukan satu task per Acceptance Criterion. Setiap task dapat mencakup beberapa AC dalam satu User Story, tetapi tetap menunjuk tepat satu User Story. Format: **Task** — *User Story* — **AC terkait**.

### Feature 1: Auth & Tenant

- Task: Buat migration dan model `companies` — *US-AUTH-01* — **AC terkait:** AC1–AC4.
- Task: Buat migration dan model `users` — *US-AUTH-07* — **AC terkait:** AC1–AC4.
- Task: Buat migration dan model `employees` — *US-AUTH-07B* — **AC terkait:** AC1–AC4.
- Task: Implementasi register Owner dan pembuatan company — *US-AUTH-01* — **AC terkait:** AC1–AC4.
- Task: Implementasi login/logout — *US-AUTH-02* — **AC terkait:** AC1–AC3.
- Task: Implementasi middleware `EnsureUserActive` — *US-AUTH-08* — **AC terkait:** AC1–AC4.
- Task: Implementasi reset password via Email — *US-AUTH-04* — **AC terkait:** AC1–AC3.
- Task: Implementasi CRUD roster Employee dan daily worker — *US-AUTH-07B* — **AC terkait:** AC1–AC4.
- Task: Implementasi pembuatan serta penonaktifan akun Employee — *US-AUTH-07* — **AC terkait:** AC1–AC4.
- Task: Implementasi tutup akun company dan soft-close — *US-SUB-07* — **AC terkait:** AC1–AC3.
- Task: Buat feature/integration tests register dan company — *US-AUTH-01* — **AC terkait:** seluruh AC yang diuji.
- Task: Buat feature/integration tests login dan logout — *US-AUTH-02* — **AC terkait:** seluruh AC yang diuji.
- Task: Buat feature/integration tests verifikasi email — *US-AUTH-03* — **AC terkait:** seluruh AC yang diuji.
- Task: Buat feature/integration tests reset password — *US-AUTH-04* — **AC terkait:** seluruh AC yang diuji.
- Task: Buat feature/integration tests akun Employee — *US-AUTH-07* — **AC terkait:** seluruh AC yang diuji.
- Task: Buat feature/integration tests roster Employee dan daily worker — *US-AUTH-07B* — **AC terkait:** seluruh AC yang diuji.
- Task: Buat feature/integration tests status akun — *US-AUTH-08* — **AC terkait:** seluruh AC yang diuji.
- Task: Buat feature/integration tests tutup company — *US-SUB-07* — **AC terkait:** seluruh AC yang diuji.

### Feature 2: Subscription & Pembayaran

- Task: Buat migration `payments` dan kolom `companies.paid_until` — *US-SUB-02* — **AC terkait:** AC1–AC3.
- Task: Tambahkan field `companies.paid_until` untuk approval pembayaran — *US-ADMIN-04* — **AC terkait:** AC1–AC3.
- Task: Implementasi middleware `EnsureCompanySubscription` dengan status live dan cache Redis — *US-SUB-05* — **AC terkait:** AC1–AC4.
- Task: Implementasi halaman plan, pembayaran manual, dan upload bukti — *US-SUB-02* — **AC terkait:** AC1–AC3.
- Task: Implementasi list dan review approval pembayaran — *US-ADMIN-03* — **AC terkait:** AC1–AC2.
- Task: Implementasi approval pembayaran dan perpanjangan `paid_until` — *US-ADMIN-04* — **AC terkait:** AC1–AC3.
- Task: Implementasi quota Free dan indikator pemakaian per jenis — *US-SUB-01* — **AC terkait:** AC1–AC3.
- Task: Implementasi degrade subscription ke Free — *US-SUB-05* — **AC terkait:** AC1–AC4.
- Task: Implementasi pemulihan Employee saat kembali ke Paid — *US-SUB-06* — **AC terkait:** AC1–AC2.
- Task: Implementasi reminder H-3 — *US-SUB-04* — **AC terkait:** AC1–AC2.
- Task: Buat feature/integration tests status subscription — *US-SUB-01* — **AC terkait:** seluruh AC yang diuji.
- Task: Buat feature/integration tests checkout dan pembayaran — *US-SUB-02* — **AC terkait:** seluruh AC yang diuji.
- Task: Buat feature/integration tests aktivasi subscription — *US-SUB-03* — **AC terkait:** seluruh AC yang diuji.
- Task: Buat feature/integration tests degrade dan pemulihan — *US-SUB-04* — **AC terkait:** seluruh AC yang diuji.
- Task: Buat feature/integration tests fallback Free — *US-SUB-05* — **AC terkait:** seluruh AC yang diuji.
- Task: Buat feature/integration tests pemulihan Employee — *US-SUB-06* — **AC terkait:** seluruh AC yang diuji.

### Feature 3: Modal/Kas Usaha

- Task: Buat migration dan model `capital_entries` — *US-MK-01* — **AC terkait:** AC1–AC5.
- Task: Buat migration dan model `capital_topups` — *US-MK-01B* — **AC terkait:** AC1–AC5.
- Task: Implementasi set modal awal dan validasi overlap — *US-MK-01* — **AC terkait:** AC1–AC5.
- Task: Implementasi top-up dan extend end date — *US-MK-01B* — **AC terkait:** AC1–AC5.
- Task: Implementasi widget modal berjalan — *US-MK-02* — **AC terkait:** AC1–AC3.
- Task: Implementasi perhitungan modal berjalan — *US-MK-06* — **AC terkait:** AC1–AC2.
- Task: Implementasi alert global non-dismissible — *US-MK-04* — **AC terkait:** AC1–AC4.
- Task: Implementasi modal-active guard transaksi — *US-MK-05* — **AC terkait:** AC1–AC4.
- Task: Implementasi riwayat modal dan top-up — *US-MK-03* — **AC terkait:** AC1–AC3.
- Task: Buat feature/integration tests set modal — *US-MK-01* — **AC terkait:** seluruh AC yang diuji.
- Task: Buat feature/integration tests topup modal — *US-MK-01B* — **AC terkait:** seluruh AC yang diuji.
- Task: Buat feature/integration tests widget modal — *US-MK-02* — **AC terkait:** seluruh AC yang diuji.
- Task: Buat feature/integration tests riwayat modal — *US-MK-03* — **AC terkait:** seluruh AC yang diuji.
- Task: Buat feature/integration tests alert global — *US-MK-04* — **AC terkait:** seluruh AC yang diuji.
- Task: Buat feature/integration tests modal-active guard — *US-MK-05* — **AC terkait:** seluruh AC yang diuji.
- Task: Buat feature/integration tests perhitungan modal — *US-MK-06* — **AC terkait:** seluruh AC yang diuji.

### Feature 4: Pencatatan Transaksi

- Task: Buat migration dan model `transactions` — *US-TR-01* — **AC terkait:** AC1–AC8.
- Task: Buat policy tenant transaksi — *US-TR-02* — **AC terkait:** AC1.
- Task: Buat policy scope Employee transaksi — *US-TR-03* — **AC terkait:** AC1.
- Task: Buat policy edit transaksi — *US-TR-04* — **AC terkait:** AC1.
- Task: Buat policy soft-delete transaksi — *US-TR-05* — **AC terkait:** AC2.
- Task: Buat endpoint create transaction — *US-TR-01* — **AC terkait:** AC2–AC7.
- Task: Buat form transaksi income/expense — *US-TR-01* — **AC terkait:** AC1, AC3, AC5, AC8.
- Task: Implementasi validasi tanggal UTC — *US-TR-01* — **AC terkait:** AC2, AC7.
- Task: Implementasi modal-active guard transaksi — *US-MK-04* — **AC terkait:** AC1–AC4.
- Task: Implementasi quota harian pada create transaction — *US-TR-01* — **AC terkait:** AC6.
- Task: Implementasi daily quota guard Redis dengan fallback DB — *US-TR-01B* — **AC terkait:** AC1–AC5.
- Task: Implementasi upload attachment private storage — *US-TR-01* — **AC terkait:** AC8.
- Task: Implementasi authorization attachment transaksi — *US-TR-05* — **AC terkait:** AC3.
- Task: Implementasi audit trail create Transaction — *US-TR-01* — **AC terkait:** AC4.
- Task: Implementasi audit trail update Transaction — *US-TR-02* — **AC terkait:** AC2–AC3.
- Task: Implementasi audit trail delete Transaction — *US-TR-03* — **AC terkait:** AC4.
- Task: Buat list, filter, pagination transaksi — *US-TR-04* — **AC terkait:** AC1–AC4.
- Task: Buat detail transaksi dan authorization akses — *US-TR-05* — **AC terkait:** AC1–AC3.
- Task: Implementasi edit transaksi dan transfer quota — *US-TR-02* — **AC terkait:** AC1, AC4.
- Task: Implementasi soft-delete transaksi dan pemulihan quota — *US-TR-03* — **AC terkait:** AC1–AC5.
- Task: Buat feature/integration tests create transaction — *US-TR-01* — **AC terkait:** seluruh AC yang diuji.
- Task: Buat feature/integration tests daily quota — *US-TR-01B* — **AC terkait:** seluruh AC yang diuji.
- Task: Buat feature/integration tests update transaction — *US-TR-02* — **AC terkait:** seluruh AC yang diuji.
- Task: Buat feature/integration tests delete transaction — *US-TR-03* — **AC terkait:** seluruh AC yang diuji.
- Task: Buat feature/integration tests list transaction — *US-TR-04* — **AC terkait:** seluruh AC yang diuji.
- Task: Buat feature/integration tests detail transaction — *US-TR-05* — **AC terkait:** seluruh AC yang diuji.

### Feature 5: Role & Permission

- Task: Implementasi policy, Gate, dan menu berdasarkan role — *US-RP-01* — **AC terkait:** AC1–AC4.
- Task: Implementasi tenant scoping pada seluruh query dan endpoint — *US-RP-01* — **AC terkait:** AC1–AC3.
- Task: Buat feature/integration tests Role & Permission — *US-RP-01* — **AC terkait:** AC1–AC4.

### Feature 6: Analytic

- Task: Buat query ringkasan income, expense, dan net per periode — *US-AN-01* — **AC terkait:** AC1–AC3.
- Task: Buat query breakdown per kategori — *US-AN-02* — **AC terkait:** AC1–AC2.
- Task: Buat halaman analytic Owner dan pembatasan Employee — *US-AN-03* — **AC terkait:** AC1–AC3.
- Task: Buat feature/integration tests ringkasan analytic — *US-AN-01* — **AC terkait:** seluruh AC yang diuji.
- Task: Buat feature/integration tests breakdown kategori — *US-AN-02* — **AC terkait:** seluruh AC yang diuji.
- Task: Buat feature/integration tests akses analytic — *US-AN-03* — **AC terkait:** seluruh AC yang diuji.

### Feature 7: Customer

- Task: Buat migration dan model `customers` — *US-CUST-01* — **AC terkait:** AC1–AC2.
- Task: Implementasi CRUD Customer dan quick-create — *US-CUST-01* — **AC terkait:** AC1–AC2.
- Task: Implementasi relasi Customer ke transaksi income dan Invoice — *US-CUST-02* — **AC terkait:** AC1–AC2.
- Task: Implementasi detail Customer dan breakdown transaksi — *US-CUST-03* — **AC terkait:** AC1–AC2.
- Task: Implementasi breakdown Employee/Worker — *US-CUST-04* — **AC terkait:** AC1–AC3.
- Task: Buat feature/integration tests CRUD Customer — *US-CUST-01* — **AC terkait:** seluruh AC yang diuji.
- Task: Buat feature/integration tests relasi Customer — *US-CUST-02* — **AC terkait:** seluruh AC yang diuji.
- Task: Buat feature/integration tests detail Customer — *US-CUST-03* — **AC terkait:** seluruh AC yang diuji.
- Task: Buat feature/integration tests breakdown Employee/Worker — *US-CUST-04* — **AC terkait:** seluruh AC yang diuji.

### Feature 8: Invoice

- Task: Buat migration dan model `invoices` serta `invoice_items` — *US-INV-01* — **AC terkait:** AC1–AC4.
- Task: Implementasi CRUD Invoice dan item — *US-INV-01* — **AC terkait:** AC1–AC4.
- Task: Implementasi freeze Invoice setelah transaksi pertama — *US-INV-01* — **AC terkait:** AC4.
- Task: Implementasi pengaitan transaksi ke Invoice dengan `lockForUpdate()` — *US-INV-02* — **AC terkait:** AC1–AC5.
- Task: Implementasi validasi SUM, full Invoice, unlink, dan pemindahan transaksi — *US-INV-02* — **AC terkait:** AC2, AC5.
- Task: Implementasi shortcut form transaksi dari Invoice — *US-INV-03* — **AC terkait:** AC1–AC2.
- Task: Implementasi progress Invoice on-demand — *US-INV-04* — **AC terkait:** AC1–AC2.
- Task: Implementasi list Invoice — *US-INV-05* — **AC terkait:** AC1–AC3.
- Task: Buat feature/integration tests create Invoice — *US-INV-01* — **AC terkait:** seluruh AC yang diuji.
- Task: Buat feature/integration tests edit Invoice — *US-INV-02* — **AC terkait:** seluruh AC yang diuji.
- Task: Buat feature/integration tests hapus Invoice — *US-INV-03* — **AC terkait:** seluruh AC yang diuji.
- Task: Buat feature/integration tests tanggal Invoice — *US-INV-04* — **AC terkait:** seluruh AC yang diuji.
- Task: Buat feature/integration tests progress Invoice — *US-INV-05* — **AC terkait:** seluruh AC yang diuji.

### Feature 9: Super Admin

- Task: Buat guard, migration, dan login admin — *US-ADMIN-01* — **AC terkait:** AC1–AC3.
- Task: Buat dashboard dan list Company — *US-ADMIN-02* — **AC terkait:** AC1–AC2.
- Task: Buat list pembayaran pending — *US-ADMIN-03* — **AC terkait:** AC1–AC2.
- Task: Implementasi approval pembayaran — *US-ADMIN-04* — **AC terkait:** AC1–AC3.
- Task: Implementasi reaktivasi company setelah approval — *US-SUB-07* — **AC terkait:** AC4.
- Task: Buat feature/integration tests login Super Admin — *US-ADMIN-01* — **AC terkait:** seluruh AC yang diuji.
- Task: Buat feature/integration tests list company — *US-ADMIN-02* — **AC terkait:** seluruh AC yang diuji.
- Task: Buat feature/integration tests review approval — *US-ADMIN-03* — **AC terkait:** seluruh AC yang diuji.
- Task: Buat feature/integration tests approval pembayaran — *US-ADMIN-04* — **AC terkait:** seluruh AC yang diuji.

> **Catatan traceability:** User Story non-MVP (US-AUTH-05, US-AUTH-06) tetap terdokumentasi di atas dan tidak memiliki task implementasi MVP. Feature Export Data serta fitur defer lainnya juga tidak dibuatkan task MVP.

---

---

## 9. Kesenjangan dengan Proposal v2 (Perlu Direview Bersama)

Hasil cross-check PRD ini terhadap dokumen proposal v2 yang sudah disetujui reviewer:

| Item | Status |
|---|---|
| **Manajemen Kas & Rekening** | Ada di scope proposal v2. Setelah diklarifikasi rekan tim, ternyata maksudnya bukan multi-akun kas/bank, melainkan pencatatan **modal awal per periode sebagai baseline pembanding** — sudah dirancang penuh sebagai modul baru **Modal/Kas Usaha** (lihat 3.8 dan 8.7) dan dimasukkan ke MVP. Seluruh perilaku sudah dikonfirmasi, termasuk kasus modal minus (US-MK-06). |
| **Master data Produk** | Tidak ada di proposal v2 maupun di PRD versi revisi ini — sudah konsisten setelah pemangkasan. |
| **Export Data** | Ada di scope proposal v2 (implisit, sebagai bagian dari "laporan keuangan"), **dipangkas dari MVP** di PRD ini — laporan tetap terpenuhi lewat tampilan in-app, hanya file unduhan yang ditunda. Perlu dikonfirmasi ke reviewer apakah ini cukup memenuhi ekspektasi mereka. |
| **MVP Success Criteria** (target kuantitatif: 30% trial upgrade seat, 80% kepuasan) | Ada di proposal v2, belum di-mapping ke PRD ini — sebaiknya jadi acuan bersama saat user testing MVP nanti. Model Free/Paid yang disederhanakan di PRD ini tetap bisa diukur terhadap kriteria ini. |
| **Risks & Assumptions** (6 risiko bisnis di proposal, termasuk soal harga & validasi target user) | Ada di proposal v2; PRD ini baru mencakup risiko teknis & satu risiko baru soal simplifikasi model (lihat bagian 7). Risiko bisnis proposal tetap berlaku dan belum diduplikasi di sini. |
| **Super Admin Panel** | Ada di PRD ini (sudah dipangkas ke versi minimal), **tidak disebut** di proposal v2 — proposal mungkin perlu diupdate untuk mencerminkan ini kalau dipresentasikan ulang. |
| **Customer & Invoice** | Tidak ada di proposal v2, muncul dari gap analysis diskusi dengan client (bukan dari proposal awal). Dimasukkan ke MVP di v1.3 karena client secara eksplisit membutuhkan pencatatan tagihan (Invoice) dan pelanggan (Customer) — bukan cuma pencatatan transaksi generik. Perlu dikonfirmasi ke reviewer proposal v2 apakah ini konsisten dengan lingkup kerja yang disepakati awal. |
| **Daily worker** | Kebutuhan spesifik salah satu calon client pilot (bukan bagian dari visi inti SaaS: kolaborasi pencatatan Owner–Employee). Ditangani dengan skema `employees` (roster) terpisah dari `users` (kredensial) — lihat rasional teknis di bawah — supaya tidak memaksa konsep "orang yang bisa login" bercampur dengan "orang yang cuma direferensikan data". |

**Rasional teknis — kenapa `employees` dipisah dari `users` (bukan pakai flag `has_access_to_system` langsung di `users`):**

Sempat dipertimbangkan opsi lebih sederhana: tambah kolom `has_access_to_system` langsung di tabel `users` yang sudah ada. Ditolak karena:
- `username`/`password` jadi nullable untuk baris yang tidak pernah login — inkonsistensi nullability begini biasanya sinyal ada dua entitas berbeda tercampur di satu tabel.
- Semua query "siapa yang bisa login" (dipakai di alur login, reset password, session, dsb) butuh filter tambahan `WHERE has_access_to_system = true` di setiap tempat — kalau ada satu titik yang lupa, berisiko worker ketarik ke alur yang seharusnya cuma untuk Employee ber-akses.
- Kolom `role` (enum owner/employee) jadi ambigu untuk baris worker, yang bukan Owner maupun Employee dalam pengertian permission.

Pola `employees` (roster, selalu ada) + `users` (kredensial, opsional lewat `user_id`) menghindari tiga masalah itu sekaligus: `users` tetap 100% murni (setiap baris pasti bisa login, tanpa kolom kosong), sementara Worker punya "identitas bisnis" (nama, bisa direferensikan transaksi/invoice) tanpa pernah menyentuh apapun yang berhubungan dengan auth. Ini juga menjawab kebutuhan penamaan produk yang diangkat tim: secara istilah ke merchant, Worker tetap tampil sebagai varian dari "Employee" (satu halaman Kelola Karyawan, satu konsep "orang di tim") — cuma di level database keduanya dipisah demi kebersihan skema.

**Rasional teknis — kenapa `invoice_items` dibangun sekarang, sementara entitas `item` generik/polymorphic tetap ditunda:**

Dua hal ini sering disalahpahami sebagai hal yang sama, padahal beda scope. Entitas `item` generik yang sengaja ditunda (bagian 3 di dokumen tracking modul) itu dimaksudkan untuk jadi satu entitas *reusable lintas modul & lintas vertikal bisnis* (Customer di cleaning service, Menu di rumah makan, Produk di retail, dst.) — itu perubahan arsitektur besar untuk kebutuhan yang belum tentu ada. `invoice_items` scope-nya jauh lebih sempit: cuma rincian item **di dalam satu Invoice**, tidak dipaksa reusable ke modul lain. Ini murni soal supaya tagihan tidak sekadar "satu angka total tanpa rincian apa-apa" — kebutuhan dasar yang wajar untuk fitur bernama Invoice, bukan generalisasi arsitektur yang berisiko.
