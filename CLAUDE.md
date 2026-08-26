# Aturan AI Pengembangan

## Tujuan

Ikuti workflow `/implement-pic-task` untuk mengerjakan task dari Google Sheet berdasarkan PIC. Satu task per branch. Hasil default selalu **uncommitted** sampai user memeriksa dan mengatakan `oke`.

## Stack dan konvensi

- Laravel 13, PHP 8.3+, Inertia.js, Vue 3, Vite, Tailwind.
- Kode backend: `app/`.
- Migration/model/factory/seeder: `database/`.
- Route: `routes/web.php` atau `routes/auth.php` sesuai konteks.
- PHPUnit: `tests/Feature/` dan `tests/Unit/`; konfigurasi ada di `phpunit.xml`.
- Halaman Inertia: `resources/js/Pages/`; layout: `resources/js/Layouts/`; komponen: `resources/js/Components/`.
- Ikuti pola Breeze yang sudah ada: controller tipis, FormRequest untuk validasi, route bernama, `Inertia::render()`, `useForm`, `RefreshDatabase`, factory, `actingAs()`.
- Pertahankan keselarasan route name, nama action/controller, path halaman Inertia, dan nama test.
- Gunakan dependency yang sudah terpasang. Jangan menambah package untuk kebutuhan yang dapat ditangani framework atau standard library.

## Sumber task

- Baca Google Sheet memakai Google Sheets MCP yang tersedia. Jangan menebak `spreadsheetId`, sheet/tab, range, header, PIC, user story, atau acceptance criteria.
- Minta input yang hilang: `spreadsheetId`, nama tab/range, PIC aktif, atau task ID.
- Identifikasi header sebelum memfilter. Normalisasi whitespace dan perbandingan PIC case-insensitive.
- Proses hanya baris yang PIC-nya cocok dan statusnya actionable. Jika ada lebih dari satu kandidat tanpa task ID, tampilkan kandidat lalu minta pilihan.
- Missing/ambiguous header, PIC, user story, acceptance criteria, atau status adalah blocker. Jangan mengarang requirement.
- Jangan menulis balik ke Sheet, mengubah status, atau mengirim data keluar kecuali user meminta secara eksplisit.
- Jangan menampilkan credential/token. Jangan menyimpan credential di repository.

## Git dan branch

- Sebelum mulai, `git status --short` wajib bersih. Jika tidak bersih, berhenti dan minta arahan.
- Branch dasar wajib `development` lokal. Verifikasi branch aktif sebelum membuat branch.
- Buat branch non-destruktif `feature/<task-id>-<slug>` dari `development`; sanitasi task ID/slug menjadi karakter aman.
- Jika branch target sudah ada, jangan reset, hapus, overwrite, force-update, merge, atau rebase. Berhenti dan minta arahan.
- Jangan commit, push, merge, rebase, amend, reset, atau menghapus perubahan tanpa instruksi eksplisit.
- Jangan membuat commit pada turn implementasi. Setelah verifikasi, berhenti dalam keadaan uncommitted dan tunggu user mengatakan `oke`.

## Requirement dan test

- Baca user story serta seluruh acceptance criteria sebelum mengedit kode.
- Buat test matrix: setiap acceptance criterion memiliki minimal satu test bernama dan dapat dilacak.
- Tambahkan kasus relevan untuk happy path, validasi, authentication/authorization, persistence, failure, boundary, dan duplicate/concurrency bila berlaku.
- Tulis test sebelum implementasi jika praktis; jangan menghapus atau melemahkan test yang ada untuk membuat suite hijau.
- Gunakan assertion Laravel yang sesuai: status/redirect, session errors, database, guest/auth, dan Inertia props/page.
- Jika acceptance criteria bertentangan atau tidak testable, berhenti untuk klarifikasi.

## Implementasi

- Implementasikan hanya file yang diperlukan untuk story: migration, model, FormRequest, middleware, controller, route, Vue page, layout/component, dan test.
- Middleware bukan checklist wajib; tambahkan hanya jika ada aturan lintas-request yang jelas.
- Validasi semua input dari trust boundary. Pertahankan authorization, CSRF, escaping, dan accessibility.
- Jangan membuat abstraction, service, repository, event, dependency, atau refactor spekulatif.
- Refactor hanya jika diperlukan untuk memenuhi story, menghapus duplikasi nyata, memperbaiki testability, atau menjaga konvensi.
- Jangan mengubah auth starter atau behavior unrelated.

## Verifikasi dan handoff

- Jalankan test terfokus terlebih dahulu.
- Jalankan `php artisan test`, `npm run build`, route check, formatter/static check yang sudah tersedia dan relevan.
- Laporkan command serta output/failure secara akurat. Jangan menyatakan berhasil jika command gagal atau dilewati.
- Sebelum handoff, periksa `git diff`, `git diff --check`, dan `git status --short`.
- Ringkasan wajib mencakup task/PIC, branch, test matrix, changed files, command/results, known gaps, dan fakta bahwa belum ada commit.
- Setelah ringkasan, tunggu review user. Hanya setelah user secara eksplisit mengatakan `oke`, minta konfirmasi tindakan commit yang diinginkan; jangan menganggap `oke` mengizinkan push/merge.
