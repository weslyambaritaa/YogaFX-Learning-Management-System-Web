# 07-phase-1-auth-foundation.md
# Phase 1 Documentation
# Authentication & Authorization Foundation

## 1. Purpose

Dokumen ini merangkum pekerjaan yang sudah diselesaikan pada Phase 1 untuk fondasi Authentication & Authorization YogaFX LMS.

Phase ini hanya berfokus pada fondasi akses sistem dan tidak menyentuh domain LMS lain seperti:
- student profile
- access tier
- modules
- lessons
- assessments
- assignments
- certificates

---

## 2. Phase Objective

Tujuan Phase 1 adalah menyiapkan fondasi agar:
- user dapat login dan logout
- sistem mendukung forgot password dan reset password
- session web Laravel aktif dan stabil
- sistem mengenali dua role utama:
  - admin
  - student
- redirect setelah login berjalan berdasarkan role
- area admin dan student dapat dipisahkan secara aman

---

## 3. Scope Implemented

Fitur yang diimplementasikan pada phase ini:
- login
- logout
- forgot password
- reset password
- session management dasar berbasis Laravel web session
- role `admin`
- role `student`
- role-based redirect
- role-based route protection

Fitur yang sengaja tidak diaktifkan pada phase ini:
- registration
- student profile management
- access tier management
- email verification flow
- confirm password flow
- authenticated password update page

---

## 4. Technical Direction

Fondasi auth menggunakan:
- Laravel session-based authentication
- guard `web`
- React + Inertia auth pages dari Breeze
- password reset token bawaan Laravel
- middleware `auth` dan middleware custom `role`

Alasan arah ini dipilih:
- paling sesuai dengan stack final proyek
- ringan untuk phase foundation
- aman untuk aplikasi web Laravel tunggal
- mudah diperluas ke domain berikutnya

---

## 5. Data Changes

Perubahan data utama pada phase ini:

### `users`
Kolom baru:
- `role`

Nilai role yang dipakai:
- `admin`
- `student`

### Tabel framework yang dipakai
- `users`
- `password_reset_tokens`
- `sessions`
- `cache`
- `jobs`
- `failed_jobs`

Catatan:
- role tidak dibuat sebagai tabel terpisah
- session tracking operasional versi LMS belum dibuat pada phase ini

---

## 6. Authorization Foundation

Authorization phase ini dibangun dengan:
- middleware `auth`
- middleware custom `role`

Penggunaan:
- `role:admin`
- `role:student`

Tujuan:
- membatasi dashboard admin hanya untuk admin
- membatasi dashboard student hanya untuk student
- menjadi fondasi untuk authorization domain berikutnya

---

## 7. Route Structure

Route yang aktif untuk auth foundation:
- `/login`
- `/logout`
- `/forgot-password`
- `/reset-password/{token}`
- `/dashboard`
- `/admin/dashboard`
- `/student/dashboard`

Perilaku route:
- `/dashboard` bertindak sebagai redirector ke dashboard sesuai role
- admin diarahkan ke `/admin/dashboard`
- student diarahkan ke `/student/dashboard`

Route yang dinonaktifkan dari scope phase ini:
- `/register`
- `/profile`
- `/verify-email`
- `/confirm-password`
- `/password` untuk authenticated password update

---

## 8. Files Changed In Phase 1

### Backend
- `database/migrations/0001_01_01_000000_create_users_table.php`
- `app/Models/User.php`
- `database/factories/UserFactory.php`
- `app/Http/Middleware/EnsureUserHasRole.php`
- `bootstrap/app.php`
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- `routes/auth.php`
- `routes/web.php`

### Frontend
- `resources/js/Layouts/AuthenticatedLayout.jsx`
- `resources/js/Pages/Auth/Login.jsx`
- `resources/js/Pages/Auth/ForgotPassword.jsx`
- `resources/js/Pages/Auth/ResetPassword.jsx`
- `resources/js/Pages/Admin/Dashboard.jsx`
- `resources/js/Pages/Student/Dashboard.jsx`

### Testing
- `tests/TestCase.php`
- `tests/Feature/Auth/AuthenticationTest.php`
- `tests/Feature/Auth/RegistrationTest.php`
- `tests/Feature/Auth/EmailVerificationTest.php`
- `tests/Feature/Auth/PasswordConfirmationTest.php`
- `tests/Feature/Auth/PasswordUpdateTest.php`
- `tests/Feature/ProfileTest.php`

---

## 9. Validation Completed

Validasi yang berhasil dijalankan pada phase ini:
- migration Laravel berhasil dijalankan di PostgreSQL
- route auth foundation tampil sesuai scope
- login student redirect ke student dashboard
- login admin redirect ke admin dashboard
- student tidak bisa mengakses admin dashboard
- admin tidak bisa mengakses student dashboard
- forgot password flow tetap aktif
- reset password flow tetap aktif
- test suite auth foundation lulus

---

## 10. Development Account

Akun admin development yang dibuat untuk verifikasi lokal:
- email: `admin@yogafx.test`
- role: `admin`

Catatan:
- password development tidak dicantumkan di dokumen ini
- akun ini hanya untuk kebutuhan local development

---

## 11. Known Notes

- Dokumen `03-business-rules.md` tidak tersedia di repo saat phase ini dikerjakan.
- Dokumen ERD yang tersedia dan dipakai adalah `03-erd.md`.
- Information Architecture sempat kosong di awal, lalu kemudian tersedia dan dibaca ulang.
- `npm run build` masih memiliki issue frontend/CSS terpisah yang bukan berasal dari fondasi auth ini.

---

## 12. Outcome

Phase 1 menghasilkan fondasi akses sistem yang siap dipakai untuk domain berikutnya:
- user sudah bisa masuk ke sistem
- role utama sudah dikenali
- pemisahan admin dan student sudah berjalan
- route protection dasar sudah tersedia
- flow reset password sudah siap

Fondasi ini menjadi dependency langsung untuk:
- User & Profile Foundation
- Tier Management
- domain lain yang membutuhkan user identity dan authorization dasar
