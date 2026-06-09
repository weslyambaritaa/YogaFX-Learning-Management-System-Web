# 09-phase-3-tier-management-foundation.md
# Phase 3 Documentation
# Tier Management Foundation

## 1. Purpose

Dokumen ini merangkum pekerjaan yang diselesaikan pada Phase 3 untuk fondasi `Access Tier Management` YogaFX LMS.

Phase ini berfokus pada pengelolaan membership tier dan relasinya ke student, tanpa masuk ke enforcement akses konten seperti:
- module restriction
- lesson restriction
- ebook restriction
- course restriction
- pricing
- payment
- subscription billing
- expiry date membership
- renewal logic

---

## 2. Phase Objective

Tujuan Phase 3 adalah menyiapkan fondasi agar:
- admin dapat mengelola access tier
- student memiliki satu tier aktif
- admin dapat meng-assign tier ke student
- admin dan student dapat melihat tier yang sedang terpasang
- sistem siap dipakai oleh domain berikutnya yang membutuhkan access tier

---

## 3. Scope Implemented

Fitur yang diimplementasikan pada phase ini:
- access tier CRUD oleh admin
- assignment tier ke student dari halaman detail student
- visibilitas tier di admin student list
- visibilitas tier di student dashboard
- visibilitas tier di student profile
- seed default untuk tier final
- proteksi delete tier yang sedang dipakai

Fitur yang sengaja belum diimplementasikan pada phase ini:
- enforcement tier ke module, lesson, ebook, dan course
- riwayat perubahan tier
- subscription lifecycle
- pembayaran dan billing
- expiry dan renewal membership

---

## 4. Technical Direction

Fondasi tier phase ini dibangun dengan:
- entity `access_tiers` terpisah
- relasi satu tier aktif ke satu student melalui `users.access_tier_id`
- admin pages berbasis Inertia untuk create, edit, list, dan delete tier
- integrasi assignment tier ke halaman detail student yang sudah ada dari Phase 2
- shared auth props agar tier user dapat dibaca di sisi frontend student

Alasan arah ini dipilih:
- sesuai keputusan bahwa satu student hanya punya satu tier aktif
- lebih sederhana dan stabil untuk phase foundation
- cukup kuat untuk menjadi dependency domain learning berikutnya
- menghindari over-engineering seperti history tier dan subscription logic terlalu dini

---

## 5. Data Changes

Perubahan data utama pada phase ini:

### Tabel baru `access_tiers`
Field yang dibuat:
- `name`
- `slug`
- `description`
- `is_active`
- timestamps

### Perubahan tabel `users`
Kolom baru:
- `access_tier_id`

### Catatan data model
- satu user student hanya punya satu tier aktif
- relasi tier ke student dibuat nullable
- ketika tier dihapus dan relasi masih ada, delete tidak diizinkan di level aplikasi
- foreign key `users.access_tier_id` diset `nullOnDelete` sebagai pengaman tambahan

---

## 6. Tier Rules Implemented

Aturan bisnis yang diterapkan pada phase ini:
- tier final yang dipakai:
  - `starter_kit`
  - `online`
  - `master_class`
- satu student hanya boleh punya satu tier aktif
- perubahan tier student dilakukan dengan overwrite langsung
- belum ada riwayat perubahan tier
- admin dapat membuat dan mengedit tier
- tier yang sedang dipakai student tidak boleh di-hard-delete
- `is_active` tersedia untuk menandai tier aktif atau nonaktif

Catatan:
- `is_active` belum dipakai untuk memblok akses konten
- phase ini hanya menyiapkan fondasi data dan manajemen

---

## 7. Route Structure

Route admin baru yang aktif setelah Phase 3:
- `/admin/access-tiers`
- `/admin/access-tiers/create`
- `POST /admin/access-tiers`
- `/admin/access-tiers/{accessTier}/edit`
- `PATCH /admin/access-tiers/{accessTier}`
- `DELETE /admin/access-tiers/{accessTier}`

Integrasi route existing:
- `/admin/students`
- `/admin/students/{student}`
- `PATCH /admin/students/{student}`

Perilaku route:
- admin mengelola tier dari area `Access Tiers`
- admin meng-assign tier dari halaman detail student
- student tidak memiliki halaman tier khusus
- student hanya melihat tier aktifnya di dashboard dan profile

---

## 8. Authorization & Access Rules

Aturan akses pada phase ini:
- hanya `admin` yang dapat membuka route `access-tiers`
- hanya `admin` yang dapat create, edit, update, dan delete tier
- hanya `admin` yang dapat assign tier ke student
- student tidak dapat mengakses halaman manajemen tier admin
- student hanya melihat tier miliknya secara read-only

Middleware yang dipakai:
- `auth`
- `role:admin`
- `role:student`

---

## 9. UI Surfaces Added Or Updated

Surface utama yang aktif pada phase ini:
- admin access tier list page
- admin create access tier page
- admin edit access tier page
- access tier reusable form component
- admin student detail page dengan assignment tier
- student dashboard dengan visibilitas tier
- student profile dengan visibilitas tier
- authenticated navigation admin yang kini memuat menu `Access Tiers`

Navigasi yang aktif:
- admin: `Dashboard`, `Access Tiers`, `Students`
- student: `Dashboard`, `Profile`

---

## 10. Files Changed In Phase 3

### Database
- `database/migrations/2026_06_09_090000_create_access_tiers_table.php`
- `database/migrations/2026_06_09_090100_add_access_tier_id_to_users_table.php`
- `database/seeders/DatabaseSeeder.php`
- `database/seeders/AccessTierSeeder.php`
- `database/factories/AccessTierFactory.php`
- `database/factories/UserFactory.php`

### Backend
- `app/Models/AccessTier.php`
- `app/Models/User.php`
- `app/Http/Controllers/Admin/AccessTierController.php`
- `app/Http/Controllers/Admin/StudentController.php`
- `app/Http/Requests/Admin/AccessTierRequest.php`
- `app/Http/Requests/Admin/AdminStudentUpdateRequest.php`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `routes/web.php`

### Frontend
- `resources/js/Components/AccessTierForm.jsx`
- `resources/js/Layouts/AuthenticatedLayout.jsx`
- `resources/js/Pages/Admin/AccessTiers/Index.jsx`
- `resources/js/Pages/Admin/AccessTiers/Create.jsx`
- `resources/js/Pages/Admin/AccessTiers/Edit.jsx`
- `resources/js/Pages/Admin/Dashboard.jsx`
- `resources/js/Pages/Admin/Students/Index.jsx`
- `resources/js/Pages/Admin/Students/Edit.jsx`
- `resources/js/Pages/Student/Dashboard.jsx`
- `resources/js/Pages/Profile/Edit.jsx`

### Testing
- `tests/Feature/AccessTierTest.php`
- `tests/Feature/ProfileTest.php`

---

## 11. Seed Data

Phase ini menambahkan seed default untuk tier:
- `Starter Kit`
- `Online`
- `Master Class`

Seeder ini menjadi fondasi awal agar environment development langsung memiliki 3 tier final tanpa input manual.

---

## 12. Validation Completed

Validasi yang berhasil dijalankan pada phase ini:
- admin dapat melihat daftar access tier
- admin dapat membuat access tier
- admin dapat mengedit access tier
- admin dapat menghapus access tier yang belum dipakai
- admin tidak dapat menghapus access tier yang sedang dipakai student
- admin dapat assign tier ke student dari halaman detail student
- daftar student admin menampilkan tier student
- student dashboard menampilkan tier aktif
- student profile menampilkan tier aktif
- student tidak dapat mengakses route admin access tier
- seluruh test feature yang ada lulus

Command validasi yang berhasil dijalankan:
- `php artisan migrate`
- `php artisan db:seed`
- `php artisan migrate:status`
- `php artisan route:list`
- `php artisan db:table access_tiers --database=pgsql`
- `php artisan db:table users --database=pgsql`
- `php artisan test`

---

## 13. Known Notes

- Dokumen `03-business-rules.md` tetap tidak tersedia di repo saat phase ini dikerjakan.
- Source of truth struktur entity yang dipakai pada phase ini adalah `docs/03-erd.md`.
- Source of truth struktur halaman yang dipakai pada phase ini adalah `docs/05-information-architecture.md`.
- `is_active` pada tier saat ini belum mengontrol akses domain belajar.
- phase ini belum menyentuh pricing, payment, subscription, expiry, atau renewal.
- `npm run build` tetap memiliki issue frontend/CSS terpisah yang tidak berasal dari implementasi tier ini.

---

## 14. Outcome

Phase 3 menghasilkan fondasi access tier yang siap dipakai oleh domain berikutnya:
- sistem sekarang memiliki entity tier yang eksplisit
- student sudah dapat dihubungkan ke satu tier aktif
- admin sudah dapat mengelola tier dan assignment tier
- visibilitas tier sudah tersedia di area admin dan student
- seed default tier sudah tersedia untuk environment development

Fondasi ini menjadi dependency langsung untuk:
- Learning Content
- Learning Progress
- Assessment
- Assignment
- Certificate

Phase berikutnya dapat mulai membangun domain konten dengan asumsi bahwa relasi student ke tier sudah tersedia dan stabil.
