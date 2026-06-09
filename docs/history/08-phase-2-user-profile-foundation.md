# 08-phase-2-user-profile-foundation.md
# Phase 2 Documentation
# User & Profile Foundation

## 1. Purpose

Dokumen ini merangkum pekerjaan yang diselesaikan pada Phase 2 untuk fondasi `User & Profile` YogaFX LMS.

Phase ini berfokus pada identitas student dan visibilitas admin terhadap data student, tanpa masuk ke domain lain seperti:
- access tier
- modules
- lessons
- assessments
- assignments
- certificates

---

## 2. Phase Objective

Tujuan Phase 2 adalah menyiapkan fondasi agar:
- student memiliki data profile yang lebih lengkap
- student dapat melengkapi dan memperbarui profile sendiri
- admin dapat melihat daftar student
- admin dapat membuka detail student
- admin dapat memperbarui profile student
- onboarding student tidak berhenti di login saja, tetapi diarahkan ke penyelesaian profile bila masih belum lengkap

---

## 3. Scope Implemented

Fitur yang diimplementasikan pada phase ini:
- student profile completion
- student self profile edit
- admin student listing
- admin student detail
- admin edit student profile
- profile validation
- profile completeness check
- post-login redirect untuk student berdasarkan kelengkapan profile

Fitur yang sengaja belum diimplementasikan pada phase ini:
- student profile picture upload final
- access tier assignment
- module access restriction
- lesson domain
- assessment domain
- assignment domain
- certificate domain

---

## 4. Technical Direction

Fondasi profile phase ini dibangun dengan:
- perluasan tabel `users` untuk menyimpan data profile student
- Eloquent model `User` sebagai pusat role dan profile completeness logic
- Inertia pages untuk student profile dan admin student management
- satu request validation yang dapat dipakai untuk student self-update dan admin update student
- redirect pasca-login yang mempertimbangkan role dan kelengkapan profile

Alasan arah ini dipilih:
- selaras dengan dependency order di dokumen implementasi modular
- cukup ringan untuk foundation phase
- menghindari pembuatan entity tambahan sebelum benar-benar dibutuhkan
- menyiapkan fondasi identitas yang nantinya dipakai tier, learning flow, assignment, dan certificate

---

## 5. Data Changes

Perubahan data utama pada phase ini terjadi di tabel `users`.

### Field profile student yang ditambahkan
- `first_name`
- `last_name`
- `whatsapp`
- `preferred_certificate_picture`
- `instagram`
- `country`
- `birth_date`
- `gender`
- `practicing_yoga_for`
- `yoga_sequence_experience`
- `hours_per_week`
- `current_fitness_level`
- `flexibility_rating`
- `motivation`
- `why_yogafx`
- `how_did_you_find_us`

### Catatan data model
- seluruh field profile student ditambahkan langsung ke `users`
- belum ada tabel profile terpisah
- belum ada relasi ke `access_tiers`
- field profile dibuat nullable agar user existing dan admin existing tidak rusak saat migrasi dijalankan

---

## 6. Profile Completeness Logic

Phase ini memperkenalkan konsep `student profile completeness`.

Field yang dianggap wajib untuk menandai profile student sebagai lengkap:
- `first_name`
- `last_name`
- `email`
- `whatsapp`
- `country`
- `birth_date`
- `gender`
- `practicing_yoga_for`
- `yoga_sequence_experience`
- `hours_per_week`
- `current_fitness_level`
- `flexibility_rating`
- `motivation`
- `why_yogafx`
- `how_did_you_find_us`

Catatan:
- `preferred_certificate_picture` masih bersifat opsional pada phase ini
- `instagram` juga bersifat opsional

---

## 7. Route Structure

Route profile dan user management yang aktif setelah Phase 2:
- `/profile`
- `PATCH /profile`
- `/admin/students`
- `/admin/students/{student}`
- `PATCH /admin/students/{student}`

Perilaku route:
- student mengakses `/profile` untuk melengkapi atau memperbarui datanya sendiri
- admin mengakses `/admin/students` untuk melihat daftar student
- admin mengakses `/admin/students/{student}` untuk membuka detail student
- admin dapat memperbarui profile student dari halaman detail tersebut

Integrasi dengan route auth:
- `/dashboard` bertindak sebagai redirector umum
- admin tetap diarahkan ke `/admin/dashboard`
- student yang profile-nya belum lengkap diarahkan ke `/profile`
- student yang profile-nya sudah lengkap diarahkan ke `/student/dashboard`

---

## 8. Authorization & Access Rules

Aturan akses pada phase ini:
- hanya `student` yang dapat membuka dan mengubah route `/profile`
- hanya `admin` yang dapat membuka route admin student management
- student tidak dapat mengakses daftar student admin
- student tidak dapat mengakses detail student lain melalui route admin
- admin tidak menggunakan route student profile sebagai jalur utama pengelolaan student

Middleware yang menjadi fondasi:
- `auth`
- `role:student`
- `role:admin`

---

## 9. UI Surfaces Added Or Updated

Surface utama yang aktif pada phase ini:
- student profile page
- admin student list page
- admin student detail/edit page
- shared student profile form component
- authenticated navigation yang menampilkan item berbeda untuk admin dan student

Navigasi yang aktif:
- student: `Dashboard`, `Profile`
- admin: `Dashboard`, `Students`

---

## 10. Files Changed In Phase 2

### Database
- `database/migrations/2026_06_08_211500_add_student_profile_fields_to_users_table.php`

### Backend
- `app/Models/User.php`
- `app/Http/Controllers/ProfileController.php`
- `app/Http/Controllers/Admin/StudentController.php`
- `app/Http/Requests/ProfileUpdateRequest.php`
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `routes/web.php`
- `database/factories/UserFactory.php`

### Frontend
- `resources/js/Layouts/AuthenticatedLayout.jsx`
- `resources/js/Components/StudentProfileForm.jsx`
- `resources/js/Pages/Profile/Edit.jsx`
- `resources/js/Pages/Admin/Students/Index.jsx`
- `resources/js/Pages/Admin/Students/Edit.jsx`

### Testing
- `tests/Feature/Auth/AuthenticationTest.php`
- `tests/Feature/ProfileTest.php`

---

## 11. Validation Completed

Validasi yang berhasil dijalankan pada phase ini:
- student dapat membuka halaman profile sendiri
- student dapat menyimpan profile sendiri
- nama display user tersinkron dari `first_name` + `last_name`
- student incomplete diarahkan ke profile completion setelah login
- student complete diarahkan ke student dashboard setelah login
- admin dapat melihat daftar student
- admin dapat membuka detail student
- admin dapat memperbarui profile student
- student tidak dapat mengakses route admin student management
- seluruh test feature yang ada lulus

Command validasi yang berhasil dijalankan:
- `php artisan migrate`
- `php artisan migrate:status`
- `php artisan route:list`
- `php artisan db:table users --database=pgsql`
- `php artisan test`

---

## 12. Known Notes

- Dokumen `03-business-rules.md` tetap tidak tersedia di repo saat phase ini dikerjakan.
- Source of truth struktur entity yang dipakai pada phase ini adalah `docs/03-erd.md`.
- Source of truth struktur halaman yang dipakai pada phase ini adalah `docs/05-information-architecture.md`.
- `preferred_certificate_picture` masih direpresentasikan sebagai referensi string, belum menjadi file upload final.
- `npm run build` masih memiliki issue frontend/CSS terpisah yang bukan berasal dari fondasi profile ini.

---

## 13. Outcome

Phase 2 menghasilkan fondasi identitas student yang siap dipakai oleh domain berikutnya:
- student sudah memiliki alur profile completion yang jelas
- admin sudah memiliki visibilitas awal terhadap data student
- validation profile sudah tersedia
- onboarding student tidak buntu setelah login
- data identitas yang nantinya dibutuhkan tier, learning, assignment, dan certificate sudah mulai tersedia

Fondasi ini menjadi dependency langsung untuk:
- Tier Management
- Learning Content
- Learning Progress
- Assignment
- Certificate
