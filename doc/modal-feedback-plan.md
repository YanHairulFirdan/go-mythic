# Modal / Kas Usaha — Feedback Klien: Pertanyaan & Rencana PR

**Tanggal:** 2026-09-10
**Konteks:** Feedback klien atas fitur Modal / Kas Usaha:

1. Fitur **edit modal belum ada**.
2. **Setting modal perlu disesuaikan** agar memudahkan pelaporan 1 tahun.
3. **Top-up modal membingungkan user** — kalau modal untuk 1 tahun dan user top-up tiap bulan, rekapitulasi tahunan jadi rancu.
4. Usulan klien: **tanggal selesai (expired date) dijadikan opsional**.

---

## 1. Kondisi implementasi saat ini

| Aspek | Kondisi |
|---|---|
| `capital_entries` | `initial_amount` + `start_date` + `end_date` (dua-duanya **wajib**), soft delete. Satu entry aktif non-overlap per company. `initial_amount` **sengaja immutable**. |
| `capital_topups` | Nempel ke entry aktif; `amount` + `extended_end_date` (opsional). Tanpa soft delete. |
| Angka turunan | `periodTotal` = initial + Σ topup. `currentTotal` = periodTotal + income − expense **dalam rentang [start, end]**. |
| Form buat | Preset **1 Hari / 1 Minggu / 1 Bulan / Custom**; end date otomatis dari preset. |
| CRUD | Hanya **create** + **top-up** (`PATCH`). **Tidak ada edit, tidak ada delete** untuk entry. Salah input → mentok. |
| Dashboard | "Performance card" (Owner): `laba ÷ modal` sepanjang periode modal. Denominator = `periodTotal()` yang **membesar tiap top-up**. |
| Laporan (ProfitLoss) | Periode: today / week / month / custom. **Tidak ada preset tahunan**, tidak terhubung ke modal. |

### Akar masalah

Fitur mencampur dua konsep:

- **Model sekarang = "amplop budget berjangka"** — set X rupiah untuk N hari, app melacak burn-down (`currentTotal` boleh minus, "indikator bukan limit"). Karena itu presetnya hari/minggu/bulan.
- **Model yang klien mau = "modal disetor / ekuitas pemilik"** — ledger berjalan dari dana yang pemilik suntikkan, dilaporkan per tahun. Dana yang ditambah kemudian = **injeksi modal baru bertanggal**, bukan "top-up budget". Ekuitas tidak punya "tanggal selesai" alami.

Top-up terasa aneh dan end date terasa dipaksakan karena fitur terjebak di antara keduanya.

### Kenapa "end_date opsional" saja tidak cukup

`end_date` dipakai di banyak tempat:

- `currentTotal()`, dashboard `laba ÷ modal`, ProfitLoss → semua `whereBetween(transaction_date, [start, end])`. Null end = tiap tempat butuh cabang "sampai hari ini / akhir tahun".
- Aturan non-overlap (`overlapping($start, $end)`) pecah dengan null.
- History page hitung status `Aktif | Kadaluarsa` dari `end_date`.
- Preset 1 hari/minggu/bulan jadi tidak relevan.

Jadi ini bukan ganti 1 kolom — rework logika periode di model + 2 controller + FormRequest + 2 halaman + migration + ~6 file test.

---

## 2. Draft pertanyaan untuk klien

> Konteks untuk klien: saat ini "modal" = **jumlah dana untuk satu periode berjangka** (mis. 30 hari), dan "top-up" menambah dana ke periode yang sama. Feedback Anda mengarah ke model **modal disetor yang dilaporkan per tahun**. Beberapa hal perlu kami pastikan sebelum mengubah.

### A. Arti "modal" dalam laporan tahunan

**A1.** Skenario: modal awal tahun Rp100 jt. Maret tambah Rp20 jt, Juli tambah Rp15 jt. Di **laporan tahunan**, angka "Modal" yang Anda harapkan:
- (a) **Rp100 jt** — modal awal tahun saja
- (b) **Rp135 jt** — modal awal + semua tambahan sepanjang tahun
- (c) **Rata-rata tertimbang** sepanjang tahun (Rp100 jt selama Jan–Feb, Rp120 jt Mar–Jun, dst.)
- (d) lainnya — mohon jelaskan

**A2.** Angka **"laba ÷ modal"** (persentase performa) di dashboard sebaiknya memakai definisi modal yang mana dari A1? Boleh berbeda antara dashboard dan laporan tahunan?

**A3.** Laporan tahunan dihitung per **tahun kalender (Jan–Des)** atau per **tahun buku** yang Anda tentukan sendiri (mis. Jul–Jun)?

### B. Menambah modal di tengah tahun

**B1.** Istilah **"Top-up modal"** membingungkan karena *namanya*, atau karena *efeknya* ke angka rekap? (menentukan: cukup ganti label, atau perlu ubah perhitungan)

**B2.** Saat menambah modal di tengah tahun, Anda ingin melihat **riwayat tiap penambahan** (tanggal + nominal), atau cukup **satu angka modal total** yang ter-update?

**B3.** Modal pernah **berkurang** (mis. penarikan dana oleh pemilik), atau hanya pernah bertambah?

### C. Masa berlaku / tanggal selesai modal

**C1.** Anda menyarankan tanggal selesai jadi opsional. Yang Anda inginkan:
- (a) Modal **tanpa tanggal selesai** — berjalan terus sampai Anda ubah sendiri
- (b) Modal **otomatis per tahun** — tiap awal tahun mulai periode baru, saldo akhir tahun lalu jadi saldo awal
- (c) Tetap ada tanggal selesai, tapi **default 1 tahun** dan bisa dikosongkan

**C2.** Kalau modal tanpa tanggal selesai: laporan & perhitungan performa dibatasi **sampai hari ini**, atau **sampai akhir tahun berjalan**?

**C3.** Preset cepat **"1 Hari / 1 Minggu / 1 Bulan"** yang ada sekarang — masih dipakai, atau boleh diganti dengan **"1 Tahun / Tanpa batas / Custom"**?

### D. Edit & hapus modal

**D1.** Untuk fitur **edit modal** — yang perlu bisa diubah: nominal saja, tanggal saja, atau keduanya?

**D2.** Skenario: Anda mengedit modal yang sudah punya transaksi & tambahan dana di dalamnya, lalu mengubah tanggalnya sehingga beberapa transaksi jadi "di luar periode". Sistem sebaiknya:
- (a) Simpan saja, angka menyesuaikan otomatis
- (b) Tampilkan peringatan dulu ("5 transaksi akan keluar dari periode ini"), Anda konfirmasi
- (c) Tolak perubahan

**D3.** Fitur **hapus modal** perlu ada? Kalau ya, boleh menghapus modal yang sudah punya transaksi terkait?

### E. Istilah & tampilan

**E1.** Istilah yang Anda lebih paham untuk layar ini: **"Modal"**, **"Modal / Kas Usaha"**, **"Modal disetor"**, atau lainnya?

**E2.** Di layar modal, membantu jika kami tampilkan **dua angka terpisah** — "Modal awal" dan "Total modal masuk tahun ini" — daripada satu angka gabungan seperti sekarang?

---

## 3. Rencana per PR

Urutan: **PR 1 bisa mulai sekarang** (tidak butuh jawaban klien). **PR 2 & 3 menunggu** jawaban di atas.

### PR 1 — Edit & hapus modal

**Tujuan:** melengkapi CRUD `capital_entries` yang sekarang cuma create + top-up.
**Blocker jawaban klien:** tidak ada untuk versi minimal. D1–D3 hanya menyempurnakan (warning vs blokir).

| Layer | Perubahan |
|---|---|
| Route | `PATCH /capital/{capitalEntry}` → `update`; `DELETE /capital/{capitalEntry}` → `destroy` |
| Request | `UpdateCapitalEntryRequest` — `initial_amount` (required, numeric, gt:0), `start_date` / `end_date` (date, after_or_equal). Cek non-overlap **kecuali entry ini sendiri** (`where('id','!=',$entry->id)`). Owner-only + tenant scope (pola sama `TopUpCapitalEntryRequest`). |
| Controller | `update()` isi fillable. `destroy()` soft delete (model sudah `SoftDeletes`). Versi minimal: tanpa cascade — angka turunan (`periodTotal` / `currentTotal`) recompute otomatis. Opsi D2(b): hitung & kirim jumlah transaksi yang akan keluar rentang untuk warning. |
| Frontend | `Capital/Index.vue` kartu entry aktif: tombol **Edit** (modal dialog, pre-fill nominal + tanggal, mirror struktur dialog top-up) + tombol **Hapus** (dialog konfirmasi). Ini sekaligus jawaban "edit modal belum ada". |
| Test | `CapitalEntryEditTest`: owner edit nominal/tanggal sukses; non-owner 403; guest redirect; overlap dengan entry **lain** ditolak; edit ke rentang sama OK; `assertDatabaseHas`; delete → `assertSoftDeleted`; delete non-owner 403; Inertia props. |

**Risiko:** rendah — semua angka derived, tinggal recompute.
**Di luar scope:** perubahan konsep periode, cascade ke top-up/transaksi.

### PR 2 — Masa berlaku opsional + periode tahunan

**Tujuan:** `end_date` boleh kosong; form fokus ke periode 1 tahun / tanpa batas.
**Blocker jawaban klien:** **C1, C2, C3** (menentukan `effectiveEndDate` = hari ini vs akhir tahun, dan set preset).

| Layer | Perubahan |
|---|---|
| Migration | `end_date` → `nullable()->change()` (Laravel native, tanpa dbal). File migrasi baru. |
| Model | `effectiveEndDate(): string` = `end_date ?? <kebijakan C2>`; `isOpenEnded(): bool`. Semua konsumen `end_date` diarahkan ke sini. |
| Scopes | `activeOn`: `start <= date AND (end IS NULL OR end >= date)`. `overlapping`: idem dengan OR null (closure berkurung di query builder). |
| Model | `currentTotal()` pakai `effectiveEndDate()` sebagai batas atas. |
| Request | `StoreCapitalEntryRequest`: opsi `duration` → `1_year` / `no_end` / `custom` (sesuai C3); `end_date` nullable saat `no_end`; `resolvedRange()` bisa mengembalikan end `null`. |
| Frontend | `Capital/Index.vue` form buat: ganti 4 tombol preset; saat "Tanpa tanggal selesai" → sembunyikan input tanggal selesai. |
| Dashboard | `DashboardController::performanceCard` + `capitalWidget` pakai `effectiveEndDate()`. |
| History | `Capital/History.vue` + controller: status `end_date === null` → "Tanpa batas / Aktif". |
| Test | Sesuaikan `CapitalEntryTest`, `CapitalWidgetTest`, `RunningCapitalTest`, `CapitalAlertTest`, `CapitalHistoryTest`, `CapitalTopUpTest` untuk null end. Kasus baru: buat entry tanpa end, widget dengan entry tanpa end, overlap logic dengan null. |

**Risiko:** sedang — menyentuh ~6 file test; SQL overlap dengan OR harus hati-hati (kurung).
**Di luar scope:** laporan tahunan, relabel top-up.
**Urutan:** merge **setelah PR 1** (dua-duanya menyentuh `Capital/Index.vue` & `CapitalEntryTest`).

### PR 3 — Laporan tahunan + reframe "Tambah modal"

**Tujuan:** "pelaporan 1 tahun" yang diminta + hilangkan kebingungan angka modal.
**Blocker jawaban klien:** **A1, A2, A3, B1, B2, E2**.

| Layer | Perubahan |
|---|---|
| ProfitLoss | `ProfitLossController`: tambah `period = 'year'` → `[awal tahun (A3), hari ini / akhir tahun]`, label "Tahun YYYY". Untuk owner: sertakan figur modal tahun itu (definisi A1) + rasio `laba ÷ modal`. |
| Frontend | `Reports/ProfitLoss.vue`: chip "1 Tahun"; blok "Modal vs Laba" saat tersedia. |
| Capital index | `CapitalEntryController::index` + `capitalWidget`: kirim `initial_amount` (sudah ada) + `injections_this_year` (Σ top-up bertanggal dalam tahun laporan). |
| Frontend | `Capital/Index.vue`: pecah satu angka `period_total` → **"Modal awal"** + **"Total modal masuk (tahun ini)"** (E2). |
| Label | "Top-up Modal" → **"Tambah modal"** (label saja; tabel/model `capital_topups` tetap — rename tabel cosmetic & berisiko, tidak dilakukan). |
| Dashboard | Denominator performance card: figur terdefinisi (A2). **Rekomendasi default (§5): jangan ubah rumus, tetap `periodTotal()`, cukup perbaiki label & pemecahan angka** — supaya KPI dashboard tidak berubah tanpa sign-off. |
| Test | `ProfitLossTest` periode year + rasio modal; dashboard denominator; payload `Capital/Index`. |

**Risiko:** rendah **jika** memakai rekomendasi default §5 (tidak ubah rumus denominator); sedang jika klien minta definisi modal baru → butuh sign-off (A2).
**Urutan:** setelah PR 2 (logika "year" bersandar pada `effectiveEndDate` & kemungkinan entry tanpa-end).

---

## 4. Catatan proses

- Kode modal sekarang mengacu ke **US-MK-01 / 01B / 02 / 03 / 06** di Google Sheet. Feedback ini butuh AC baru/revisi — idealnya ambil/isi baris Sheet-nya dulu, atau perlakukan off-Sheet (seperti currency input & owner branding).
- Estimasi kasar: PR 1 kecil (~1 sesi), PR 2 sedang, PR 3 sedang. Total jauh lebih besar dari task currency input.
- Dependency antar-PR: PR 1 independen → PR 2 → PR 3. PR 1 & PR 2 sama-sama menyentuh `Capital/Index.vue` + `CapitalEntryTest`, jadi PR 1 di-merge dulu untuk hindari konflik.

---

## 5. Rekomendasi default (sementara klien berhalangan menjawab)

Semua pilihan diarahkan ke opsi **paling tidak mengejutkan, paling sedikit kode, dan reversible** kalau klien nanti tidak setuju. Tandai jelas di kode/PR sebagai "asumsi, menunggu konfirmasi klien".

| # | Pertanyaan | Rekomendasi | Alasan |
|---|---|---|---|
| **A1** | Arti "modal" di laporan tahunan | **(b) modal awal + semua tambahan tahun itu**, ditampilkan sebagai rincian ("Modal awal Rp X + Tambahan Rp Y = Rp Z"), bukan satu angka | UMKM berpikir "berapa total uang yang saya tanam". Rata-rata tertimbang (c) lebih tepat untuk ROI tapi terlalu abstrak; bisa ditambah belakangan |
| **A2** | Denominator "laba ÷ modal" di dashboard | **Jangan ubah rumusnya** — tetap `periodTotal()` (initial + semua tambahan). Cukup perbaiki label & pemecahan angka | Keluhan klien soal *kebingungan tampilan*, bukan matematika. Tidak ubah rumus = KPI dashboard tidak berubah = **PR 3 aman tanpa jawaban klien** |
| **A3** | Tahun kalender vs tahun buku | **Tahun kalender (Jan–Des)** | Standar UMKM Indonesia; tahun pajak juga kalender. Config tahun buku ditambah nanti kalau diminta |
| **B1** | "Top-up" bingung karena nama atau efek | Asumsikan **dua-duanya** → rename "Tambah modal" **dan** pisah angka tampilan | Dua-duanya murah, sekali kerjakan |
| **B2** | Riwayat tiap penambahan vs satu angka | **Dua-duanya** — headline satu angka jelas, rincian riwayat tetap ada (halaman "Riwayat modal" sudah ada) | Audit trail berguna, tapi jangan jadi angka utama |
| **B3** | Modal pernah berkurang (penarikan) | Asumsikan **ya, nanti** — **jangan bangun sekarang**. Desain model injeksi supaya penarikan bisa ditambah tanpa ubah skema | Prive/penarikan pemilik umum di UMKM, tapi di luar scope feedback ini |
| **C1** | Bentuk "end_date opsional" | **(c) default 1 tahun dari start + toggle "Tanpa tanggal selesai" (simpan NULL)** | Paling sedikit ganggu logika periode yang ada; sekaligus kasih default "pelaporan 1 tahun". Rollover per tahun (b) = lebih banyak kode, bisa dilapis belakangan |
| **C2** | Tanpa end date → laporan sampai kapan | `effectiveEndDate() = end_date ?? hari ini` di semua perhitungan running. Laporan tahunan pakai jendela [1 Jan, 31 Des] sendiri, lepas dari entry | Satu aturan, dipakai di semua tempat |
| **C3** | Preset 1 hari/minggu/bulan | **Ganti** dengan "1 Tahun / Tanpa batas / Custom" | Preset pendek itu untuk model "amplop budget" yang ditinggalkan. Tidak ada yang set modal usaha untuk 1 hari |
| **D1** | Edit: nominal/tanggal/dua-duanya | **Dua-duanya**, prioritas nominal | Kasus umum = "salah ketik nominal" |
| **D2** | Edit tanggal buang transaksi dari periode | **(b) warning + konfirmasi** ("5 transaksi akan keluar dari periode ini") | Jangan diam-diam ubah angka laporan; jangan hard-block (owner mungkin memang perlu koreksi) |
| **D3** | Hapus modal + hapus yang ada transaksinya | **Sediakan hapus** (soft delete). Kalau ada transaksi terkait: **boleh, tapi konfirmasi** dengan rincian | Soft delete = bisa dipulihkan. Owner yang salah buat butuh jalan keluar |
| **E1** | Istilah layar | **"Modal Usaha"** | "Modal / Kas Usaha" — garis miringnya membingungkan, "Kas" konsep lain. "Modal disetor" terlalu jargon |
| **E2** | Dua angka terpisah | **Ya** — headline "Total modal", sub-baris "Modal awal Rp X · Tambahan tahun ini Rp Y" | Ini inti perbaikan kebingungan |

### Cara lanjut tanpa klien

- **PR 1 (edit & hapus modal): kerjakan sekarang.** Tidak ada asumsi — murni menutup gap "edit modal belum ada". D2/D3 pakai rekomendasi di atas (warning + konfirmasi, soft delete).
- **PR 2 & 3: kerjakan di atas asumsi tabel ini**, ditandai jelas sebagai "asumsi, menunggu konfirmasi klien". Aman karena semua pilihan = opsi paling ringan & paling bisa dibatalkan; dan berkat **A2** (tidak ubah rumus, hanya tampilan) tidak ada KPI yang berubah diam-diam.
- **Satu-satunya yang sebaiknya tunggu klien:** rollover per tahun (**C1 opsi b**) vs default-1-tahun. Rekomendasi default-1-tahun tidak menutup jalan untuk menambah rollover nanti.
