# 00-current-project-status.md
# Current Project Status
# YogaFX LMS

## Purpose

Dokumen ini merangkum kondisi implementasi YogaFX LMS yang benar-benar aktif di repository saat ini.

Gunakan dokumen ini sebelum membaca PRD, User Flow, ERD, Design System, dan IA supaya jelas:
- domain mana yang sudah hidup end-to-end
- keputusan implementasi terbaru apa yang mengubah asumsi dokumen lama
- area mana yang masih foundation, parsial, atau belum menjadi alur aktif utama

---

## 1. Snapshot Saat Ini

<<<<<<< Updated upstream
Implementasi aktif saat ini sudah mencakup domain berikut:
- authentication foundation
- student profile foundation
- admin account profile foundation
- admin user management
- access tier management
=======
Implementasi aktif saat ini sudah mencakup:
- authentication web dengan email OTP
- forgot/reset password
- public lead registration dan checkout flow per package/tier
- onboarding pasca payment: enrollment lalu signup
- student profile dan student upgrade flow
- admin student management
- package commerce domain dan installment foundation
- access tier management sebagai entitlement layer
>>>>>>> Stashed changes
- admin learning content CRUD
- assessment builder, student assessment player, dan assessment results
- student learning journey dengan sequential lesson locking
- student assignment submission
- student certificate download foundation
- admin student progress operations
- email notification template management dan email logs
- student session tracking dan inactivity reminder
- link control settings untuk QR app dan store links student
- mobile API foundation untuk student app

Stack aktif:
- Laravel
- React
- Inertia
- PostgreSQL
- Tailwind
- shadcn/ui

Role aktif:
- Super Admin
- Admin
- Student

Tier aktif:
- `starter_kit`
- `online`
- `master_class`

---

## 2. Domain Yang Sudah Terimplementasi

### 2.1 Authentication & Access Control
- login web memakai email + password lalu verifikasi OTP email
- logout
- forgot password
- reset password
- route redirect berdasarkan role dan status profile
- middleware `role:admin` dan `role:student`
- middleware student active / inactive

Catatan:
- route register publik Laravel tidak aktif di `routes/auth.php`
- signup student yang aktif saat ini datang dari flow onboarding setelah payment berhasil

### 2.2 Public Acquisition, Checkout, dan Onboarding
- public scoreboard / lead registration page
- public landing per tier:
  - `/starter-kit`
  - `/online`
  - `/masterclass`
- direct package landing:
  - `/p/{package_slug}`
- pembuatan `pending_registrations`
- signed checkout flow
- PayPal checkout order create/capture/cancel untuk `pay_full`
- PayPal subscription checkout untuk installment initial package
- payment success continuation
- onboarding enrollment form
- onboarding signup form

Catatan:
- public self-signup tanpa checkout tidak aktif
- checkout memakai invoice, payment activity, onboarding state, dan payment subscription untuk flow installment

### 2.3 Student Profile & Account
- student wajib melengkapi profile sebelum masuk ke student dashboard
- student dapat edit profile sendiri
- student dapat request password change link
- student dapat melihat opsi tier upgrade dari halaman profile
- admin dapat edit profile student dari menu `Students`
- student home mobile dapat menampilkan CTA download app berbasis admin-managed links

<<<<<<< Updated upstream
### 2.3 Admin Account Profile
- admin dapat membuka profile dari user menu kanan atas
- admin dapat mengubah first name, last name, email, dan password sendiri
- nama admin di topbar mengikuti data akun admin yang tersimpan

### 2.3A Admin User Management
- admin dapat membuat akun student dari menu `Students`
- super admin dapat membuat akun admin dari menu `Admin`
- super admin dapat mengedit akun admin dari menu `Admin`
- super admin dapat menghapus akun admin biasa dari menu `Admin`
- super admin dapat menurunkan role `super_admin` lain atau dirinya sendiri: tidak diizinkan
- create student minimal mencakup email, password, dan access tier
- student yang dibuat admin langsung aktif dan tetap melewati profile completion gate saat login pertama kali
- create admin minimal mencakup name, email, dan password
- email user harus unik global
- admin list mendukung search, filter, dan pagination
- student list mendukung search, filter, dan pagination
- menu `Admin` tetap terlihat untuk admin biasa, tetapi aksi admin management dibatasi
- admin biasa tidak dapat membuat admin baru
- admin biasa tidak dapat edit admin lain dari domain ini
- admin biasa tidak dapat delete admin lain dari domain ini
- admin biasa tidak dapat menghapus akun admin miliknya sendiri
- delete student tetap menghapus seluruh data student terkait secara permanen
### 2.4 Access Tier Management
- CRUD tier admin
- upload thumbnail tier admin
- assign tier ke student
- seed default `Starter Kit`, `Online`, `Master Class`
- status aktif tier menggunakan `is_active`
=======
### 2.4 Admin Student Management
- menu `Students` terpisah dari `Student Progress`
- daftar student dengan access tier, status active/inactive, dan registration date
- admin dapat:
  - edit profile student
  - ubah status active/inactive
  - reset progress student
  - reset progress per scope:
    - video
    - assessment
    - lesson
    - module
  - delete student account
>>>>>>> Stashed changes

### 2.5 Package & Access Tier Commerce
- admin CRUD package
- admin CRUD access tier
- package menyimpan:
  - title
  - slug
  - description
  - image
  - price
  - currency_code
  - installment configuration
  - PayPal product/plan cache ids
- package dapat assign ke satu access tier atau `null`
- access tier tetap menjadi entitlement/access control layer
- public legacy link `/starter-kit`, `/online`, `/masterclass` tetap resolve ke package aktif yang assign ke tier terkait

Catatan aktif:
- `level` access tier tetap dipakai untuk hierarchy upgrade
- delete tier ditolak jika masih dipakai relasi aktif
- package tanpa `access_tier_id` tidak tersedia untuk checkout public

### 2.6 Learning Content Core
- admin CRUD Modules
- admin CRUD Lessons
- admin CRUD Assignments per module
- admin CRUD Ebooks
- admin CRUD Courses / Video Lecture
- admin edit Dialog content

Keputusan aktif:
- `Module`, `Lesson`, `Ebook`, dan `Course` sudah memakai relasi many-to-many ke `AccessTier`
- `Lesson` memakai `lesson_video_id` untuk Bunny Stream, bukan URL video bebas di field lama
- `Lesson` audio memakai `audio_url`
- `sort_order` module, lesson, ebook, dan assignment dibuat otomatis
- module punya flags:
  - `certificate_enabled`
  - `ebook_enabled`
  - `video_lecturer_enabled`

### 2.7 Assessment Domain
- admin CRUD assessment dari menu `Assessment`
- admin assessment builder:
  - questions
  - options
  - jump logic
  - result ranges
  - design settings
- admin assessment preview
- admin assessment results index dan detail
- student assessment intro page
- student assessment start / resume
- student assessment player
- student assessment result page

Catatan:
- assessment hanya terbuka jika lesson terkait memang punya assessment aktif dan student memenuhi unlock rule

### 2.8 Student Learning Experience
- student dashboard sekarang adalah `Home`, bukan dashboard foundation lama
- home menampilkan:
  - continue learning
  - access time summary
  - module grid
  - assignment milestone
  - certificate milestone
  - ebook resources section
  - mobile app download CTA di mobile home bila sudah dikonfigurasi admin
- modules index
- module detail
- lesson detail
- ebook index dan preview
- courses index dan detail
- instant access ke dialog:
  - full standing
  - full floor

Rule aktif:
- lesson access tier harus cocok dengan tier student
- sequential lesson locking aktif
- lesson berikutnya bisa terkunci jika lesson sebelumnya belum:
  - download workbook
  - menonton video sampai minimal 95%
  - menyelesaikan assessment aktif
- update watch progress aktif
- module / course completion email trigger aktif

### 2.9 Workbook, Media, dan Protected Access
- workbook lesson bisa dipicu otomatis satu kali per student
- workbook trigger juga mengirim email `workbook_sent`
- local file dan Bunny-backed media diakses lewat route/controller protected
- student certificate download dibatasi ownership

### 2.10 Assignment Flow
- admin CRUD assignment di bawah module
- student dapat membuka assignment aktif yang relevan
- student dapat upload / re-upload video assignment
- admin dapat review assignment dari Student Progress

Rule aktif:
- assignment flow student saat ini hanya dibuka untuk tier `online`
- submission status aktif:
  - `submitted`
  - `pending_review`
  - `under_review`
  - `approved`
  - `rejected`

### 2.11 Certificate Flow
- admin dapat generate, recreate, download, dan delete certificate
- admin dapat kirim graduation email
- student dapat download certificate miliknya sendiri
- certificate eligibility dihitung dari:
  - tier mapping
  - completion learning path
  - approval assignment yang relevan

Catatan:
- student certificate access di web masih download-focused, belum halaman index khusus

### 2.12 Student Progress Admin
- menu `Student Progress`
- directory student dibagi per tier
- completed lesson detail
- assignment review detail
- certificate detail

### 2.13 Email Notification
- parent menu `Email`
- 15 notification types aktif di backend registry:
  - `module_completion`
  - `assignment_review`
  - `assignment_approved`
  - `assignment_rejected`
  - `certificate_created`
  - `signup`
  - `reset_password`
  - `assessment_complete`
  - `course_complete`
  - `reminder`
  - `workbook_sent`
  - `installment_payment_success`
  - `installment_payment_failed`
  - `installment_overdue_inactive`
  - `installment_payment_completed`
- save template
- upload media email
- send test
- email logs ke database

Catatan aktif:
- child menu sidebar admin masih menampilkan 11 item legacy
- route notification type installment sudah tersedia di backend

### 2.14 Link Control
- admin dapat mengelola satu set global:
  - Google Play link
  - App Store link
- QR image digenerate otomatis dari link control dan mengarah ke satu public page download app
- QR image dipakai di welcome popup desktop student dan popup `Download Application`
- store links dipakai di bagian bawah `Home` student mobile
- public page download app menampilkan pilihan App Store dan Google Play dari link terbaru yang tersimpan

### 2.14 Installment Lifecycle
- `InstallmentPlanCalculator` aktif di backend
- installment source harga memakai `Package.price`
- first payment dibayar saat checkout approval berhasil
- recurring due date selalu tanggal 15
- final due date installment phase pertama selalu 15 Januari
- event PayPal subscription dicatat di `payment_subscription_events`
- recurring success membuat ledger `payments` baru dan sinkronisasi `invoice.balance_due`
- overdue H+3 dapat menonaktifkan akun student
- payment recovery dapat mengaktifkan akun lagi jika subscription tidak final cancelled/suspended

### 2.15 Session Tracking & Reminder
- student session tracking aktif
- total access duration disimpan
- user sessions disimpan
- reminder scheduler memakai inactivity login / activity window

### 2.16 Mobile Student API Foundation
- auth login + OTP
- forgot/reset password
- dashboard/home
- modules, lessons, ebooks, courses
- assessment
- assignments
- certificates
- profile

Catatan:
- dokumen inti ini tetap memprioritaskan web product model; mobile API dicatat sebagai domain aktif backend

---

## 3. Keputusan Teknis Penting Yang Sudah Aktif

<<<<<<< Updated upstream
### 3.1 Upload Constraint
Semua upload file yang relevan saat ini dibatasi maksimal 10 MB, kecuali yang disebut khusus:
- access tier thumbnail
- module thumbnail
- lesson thumbnail
- lesson workbook
- course thumbnail
- ebook file: maksimal 500 MB
=======
### 3.1 Course Access Sudah Many-to-Many
`Course` tidak lagi diperlakukan sebagai single-tier domain pada implementasi aktif. Relasi akses aktual saat ini memakai pivot `access_tier_course`.
>>>>>>> Stashed changes

### 3.2 Upload Constraint Sudah Berbeda per Jenis File
Batas upload aktif saat ini tidak lagi seragam 10 MB:
- file umum / thumbnail: 10 MB
- ebook: 100 MB
- lesson workbook: 100 MB
- lesson audio: 50 MB
- assignment video: 100 MB

### 3.3 Lesson Video Sudah Mengarah ke Bunny Stream
- lesson video utama memakai `lesson_video_id`
- sistem membangun HLS URL dan validasi readiness dari Bunny Stream

### 3.4 Sequential Learning Rules Sudah Hidup
Sequential lock bukan lagi planned-only. Unlock lesson berikutnya bergantung pada penyelesaian lesson sebelumnya sesuai rule workbook, watch progress, dan assessment.

<<<<<<< Updated upstream
### 3.4 Admin Layout
Admin saat ini memakai:
- left sidebar yang bisa collapse/expand
- topbar untuk page title, toggle sidebar, dan user menu
- tidak lagi memakai top navigation utama
- sidebar utama kini juga mencakup menu `Students` dan `Admin`
=======
### 3.5 Student Home Sudah Menggantikan Dashboard Foundation Lama
Student side aktif saat ini berfokus pada `Home` yang immersive dan momentum-driven, bukan halaman ringkas role/tier seperti fase awal.
>>>>>>> Stashed changes

### 3.6 Package Sudah Menjadi Commerce Layer
Harga dan konfigurasi checkout initial sekarang berpusat di `Package`.

`AccessTier` tetap penting untuk:
- entitlement
- content access
- student assignment
- upgrade hierarchy melalui `level`

### 3.7 Installment Phase Pertama Sudah Aktif
Repository saat ini sudah memiliki:
- `payment_subscriptions`
- `payment_subscription_events`
- PayPal subscription provider layer
- webhook handler installment
- overdue scheduler
- installment email notification types

---

## 4. Domain Yang Belum Terimplementasi Penuh

Domain berikut masih belum final atau belum menjadi flow produk lengkap:
- public self-signup tanpa checkout/onboarding
- student certificate index page khusus di web
- reporting dashboard kaya statistik
- installment untuk upgrade
- general subscription renewal tanpa batas dan expiry-based entitlement di luar initial installment package
- general commerce backoffice yang lebih lengkap
- pengalaman student discovery yang benar-benar final di semua halaman selain Home

---

## 5. Cara Membaca Dokumen Lain Setelah Ini

Urutan baca:
1. `docs/00-current-project-status.md`
2. `docs/01-prd.md`
3. `docs/02-user-flow.md`
4. `docs/03-erd.md`
5. `docs/04-design-system.md`
6. `docs/05-information-architecture.md`
7. `docs/06-modular-implementation.md`

Dokumen domain khusus:
- `docs/student-progress-flow.md`
- `docs/email-notification-flow.md`

Diagram Mermaid dipakai sebagai visual support. Jika berbeda dengan dokumen tertulis ini, prioritaskan dokumen tertulis.
