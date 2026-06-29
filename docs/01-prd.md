# 01-prd.md
# Product Requirements Document
# YogaFX LMS

## 1. Product Overview

YogaFX LMS adalah platform pembelajaran web untuk YogaFX dengan dua pengalaman utama:
- **Student Side** untuk perjalanan belajar, assignment, dan certificate access
- **Admin Side** untuk operasi konten, student operations, payment-linked tier setup, dan monitoring

Produk ini bukan LMS akademik tradisional. Arah produk aktif yang harus dijaga:
- student side terasa premium, tenang, dan guided
- admin side terasa rapi, jelas, dan operasional

---

## 2. Product Vision

Menyediakan fondasi produk YogaFX yang stabil untuk:
- lead-to-student conversion melalui public checkout dan onboarding
- autentikasi aman dengan OTP email
- tiered learning access
- assessment, assignment, dan certificate milestone
- admin operations untuk content, students, dan communications

---

## 3. Current Product Scope

Dokumen ini mencerminkan scope produk yang sudah aktif di repository saat ini.

### 3.1 Public Entry & Conversion
- public scoreboard / lead registration
- public product entry per tier
- direct package public entry
- checkout initial payment:
  - pay in full
  - installment bila package eligible
- onboarding enrollment
- onboarding signup

### 3.2 Authentication & Access
- login
- email OTP verification untuk login
- logout
- forgot password
- reset password
- role-based redirect
- student active/inactive restriction
- student profile completion gate

<<<<<<< Updated upstream
### 3.2 User & Tier Foundation
- profile student
- edit profile oleh student
- edit profile student oleh admin
- create student oleh admin
- create admin oleh admin
=======
### 3.3 Student Account & Tier
- student profile edit
- student password change request
- tier assignment ke student
- student upgrade checkout ke tier yang lebih tinggi

### 3.4 Admin Student Operations
- student list
- student detail/profile edit
- active/inactive toggle
- reset progress
- delete student account

### 3.5 Tier & Commerce Foundation
- package CRUD
>>>>>>> Stashed changes
- access tier CRUD
- package pricing
- package currency
- package installment configuration
- hierarchy level pada access tier
- public payment link resolution per tier/package

### 3.6 Learning Content
- module CRUD
- lesson CRUD
- assignment CRUD
- ebook CRUD
- course / video lecture CRUD
- dialog content management

### 3.7 Student Learning Flow
- student home
- modules index and detail
- lesson detail
- sequential lesson progression
- watch progress tracking
- workbook trigger/download
- ebook preview
- course access

### 3.8 Assessment
- assessment CRUD
- builder for question, option, jump rule, result range, and design
- student assessment intro, player, and result
- admin preview and result review

### 3.9 Assignment & Certificate
- student assignment submission
- admin assignment review operations
- admin certificate generation and management
- student certificate download

### 3.10 Email Notification
- template management
- upload media
- send test
- automated notification trigger
- email logs

### 3.11 Mobile Backend Support
- mobile student API untuk auth, content, assessment, assignment, certificate, dan profile

---

## 4. User Roles

### 4.1 Admin
Super Admin bertanggung jawab untuk:
- melakukan semua yang admin biasa dapat lakukan
- membuat akun admin baru
- mengedit akun admin
- menghapus akun admin biasa

Admin bertanggung jawab untuk:
<<<<<<< Updated upstream
- mengelola akun admin sendiri dari profile menu
- mengelola tier
- mengelola akun student
- mengelola modules
- mengelola lessons
- mengelola ebooks
- mengelola courses
=======
- mengelola profile admin sendiri
- mengelola access tier
- mengelola modules, lessons, assignments, ebooks, courses
- mengelola dialog content
- mengelola assessment dan results
- mengelola student accounts
>>>>>>> Stashed changes
- memantau student progress
- mengelola certificate
- mengelola template email

### 4.2 Student
Student saat ini dapat:
- login melalui OTP email flow
- menyelesaikan profile
- membuka home
- mengakses modules, lessons, ebooks, courses
- mengikuti assessment
- submit assignment jika flow tier-nya relevan
- download certificate yang sudah tersedia
- melakukan upgrade tier
- memperbarui profile sendiri

---

## 5. Membership Tiers

Tier aktif:
- Starter Kit
- Online
- Master Class

Aturan implementasi saat ini:
- setiap student memiliki satu `access_tier_id`
- tier punya `level` untuk upgrade hierarchy
- package punya `price`, `currency_code`, dan payment offer
- package dapat assign ke satu tier aktif atau `null`
- konten difilter berdasarkan tier melalui relasi access control
- assignment flow student saat ini hanya dibuka untuk tier `online`

---

## 6. Current Functional Requirements

### 6.1 Authentication & Authorization
- sistem harus membedakan Admin dan Student
- login harus melewati OTP email verification sebelum session final diberikan
- area admin hanya dapat diakses admin
- area student hanya dapat diakses student aktif
- student tanpa profile lengkap harus diarahkan ke profile edit

### 6.2 Public Registration & Checkout
- public user harus dapat mengisi lead registration untuk tier yang tersedia
- sistem harus membuat `pending_registration`
- checkout harus menghasilkan invoice dan payment activity
- checkout installment harus dapat membuat payment subscription dan recurring schedule backend
- setelah payment sukses, sistem harus melanjutkan ke enrollment lalu signup

### 6.3 Student Profile
- student harus dapat mengedit data profile lengkap
- admin harus dapat mengedit profile student
- profile photo harus didukung
- upgrade options harus tampil berdasarkan tier level yang lebih tinggi

<<<<<<< Updated upstream
### 6.3 Admin Account Profile
- admin harus dapat membuka profile dari user menu di topbar
- admin harus dapat memperbarui first name, last name, email, dan password sendiri
### 6.3A Admin User Management
- admin harus dapat membuat akun student dari menu `Students`
- create student minimal mencakup `email`, `password`, dan `access_tier_id`
- `access_tier_id` wajib dipilih saat create student
- student yang dibuat admin langsung aktif dan tetap mengikuti profile completion gate
- super admin harus dapat membuat akun admin dari menu `Admin`
- super admin harus dapat mengedit akun admin dari menu `Admin`
- super admin harus dapat menghapus akun admin biasa dari menu `Admin`
- create admin minimal mencakup `name`, `email`, dan `password`
- create admin dari menu `Admin` menghasilkan role `admin`, bukan `super_admin`
- email harus unik global lintas role
- admin list dan student list harus mendukung search, filter, dan pagination
- admin tidak dapat mengganti role akun yang sudah ada
- admin tidak dapat mengganti password user langsung dari domain ini
- admin biasa tidak dapat membuat, edit, atau delete admin dari domain ini
- menu `Admin` tetap boleh terlihat untuk admin biasa sebagai read-only directory
- super admin tidak dapat menghapus dirinya sendiri
- super admin tidak dapat menurunkan role dirinya sendiri
- role change yang diizinkan hanya `super_admin -> admin`
- delete student harus menghapus seluruh data terkait student
### 6.4 Access Tier
- admin harus dapat membuat, mengedit, dan menghapus tier jika belum dipakai student
- admin harus dapat mengunggah thumbnail untuk access tier
- tier dapat di-nonaktifkan dengan `is_active`
- student memiliki tepat satu `access_tier_id`
=======
### 6.4 Student Account Operations
- admin harus dapat mengaktifkan / menonaktifkan student
- admin harus dapat reset progress student secara penuh atau per scope
- admin harus dapat menghapus student account beserta learning data terkait
>>>>>>> Stashed changes

### 6.5 Access Tier
- admin harus dapat create, edit, dan delete tier sesuai constraint relasi
- tier harus memiliki level, active status, dan mapping entitlement
- student upgrade hanya boleh ke tier dengan level lebih tinggi

### 6.6 Package Commerce
- admin harus dapat create, edit, dan delete package sesuai constraint relasi
- package harus memiliki harga, currency, active status, slug, dan payment-facing metadata
- package installment hanya boleh aktif bila package assign ke access tier
- checkout installment harus memakai `Package.price` dan `Package.currency_code`
- recurring payment phase pertama harus mengikuti rule tanggal 15 dengan final deadline 15 Januari

### 6.7 Learning Content
- admin harus dapat CRUD module, lesson, assignment, ebook, course, dan dialog content
- module, lesson, ebook, dan course memakai many-to-many tier access
- lesson harus terhubung ke module
- assignment harus terhubung ke module
- module flags harus dapat menyatakan area pendukung seperti ebook/video lecturer/certificate

### 6.8 Lesson & Learning Progress
- student hanya boleh melihat lesson yang sesuai tier dan unlock state
- sistem harus menyimpan `watch_progress`
- lesson video dianggap complete saat watch progress mencapai minimal 95%
- workbook first trigger harus tercatat dan dapat memicu email workbook
- lesson berikutnya dapat terkunci sampai prerequisite terpenuhi

### 6.9 Assessment
- assessment aktif harus bisa dihubungkan ke lesson
- assessment harus punya builder untuk questions, options, result ranges, dan design
- assessment hanya dapat dibuka jika student lolos unlock rule lesson
- sistem harus menyimpan attempt, answers, result label, dan progress summary

### 6.10 Assignment
- student harus dapat submit video assignment untuk assignment live yang relevan
- submission berikutnya harus dapat menggantikan video sebelumnya
- admin harus dapat update status dan feedback
- admin harus dapat menghapus video assignment

### 6.11 Certificate
- admin harus dapat generate, recreate, download, dan delete certificate
- certificate eligibility harus mempertimbangkan tier mapping, module completion, dan assignment approval yang relevan
- student hanya boleh download certificate miliknya sendiri

### 6.12 Student Progress
- admin harus dapat melihat directory student progress per tier
- admin harus dapat membuka completed lessons, assignments, dan certificates per student

### 6.13 Email Notification
- sistem harus mendukung notification types aktif, termasuk lifecycle installment
- admin harus dapat save template, upload media, dan send test
- automated email harus tercatat ke email logs

<<<<<<< Updated upstream
### 6.12 Upload Rules
- semua upload file yang relevan dibatasi maksimal 10 MB, kecuali ebook file yang dibatasi maksimal 500 MB
- validasi size dilakukan di frontend dan backend
- file oversized tidak boleh disimpan

### 6.13 Ebook Preview
- ebook harus dibuka dulu di halaman preview jika format mendukung
- download menjadi aksi eksplisit yang terpisah
=======
### 6.14 Session Tracking
- sistem harus mencatat total access duration student
- sistem harus menyimpan session activity yang dipakai oleh reminder logic
>>>>>>> Stashed changes

---

## 7. Current Non-Functional Requirements

- stack tetap Laravel + React + Inertia + PostgreSQL + Tailwind + shadcn/ui
<<<<<<< Updated upstream
- ORM utama tetap Eloquent
- dokumentasi harus mengikuti migration dan implementasi aktual
- admin navigation memakai left sidebar
- delete action pada CRUD yang sudah diberi konfirmasi harus konsisten
- redirect sukses setelah create/update harus kembali ke halaman list/index
- domain user management admin mengikuti pola list -> create -> kembali ke list
=======
- protected media access wajib dipertahankan
- student side harus tetap content-first dan tidak table-heavy
- admin side harus tetap task-first
- success flow create/update mengikuti redirect yang konsisten sesuai area kerja
- dokumentasi harus mengikuti migration, model, route, dan flow aktual
>>>>>>> Stashed changes

---

## 8. Explicitly Out of Current Scope

Fitur berikut belum menjadi scope aktif final saat ini:
- public self-signup tanpa payment/onboarding
- installment untuk upgrade
- recurring membership renewal engine umum di luar initial installment package
- expiry-based entitlement management
- analytics dashboard yang kaya statistik bisnis
- certificate gallery / index page khusus di web student
- role baru di luar Admin dan Student

---

## 9. Product Decisions That Override Older Assumptions

- login web sekarang memakai OTP email
- public acquisition aktif lewat scoreboard + checkout, bukan register umum
- `Course` sekarang mengikuti many-to-many tier access
- sequential lesson locking sudah aktif
- assessment bukan lagi placeholder; builder dan player sudah hidup
- assignment submission student sudah aktif
- student dashboard lama sudah digantikan oleh `Home`
- upload limit tidak lagi seragam 10 MB untuk semua file

---

## 10. Future Product Direction

Arah berikut masih mungkin dikembangkan, tetapi belum menjadi source of truth inti tambahan:
- commerce backoffice yang lebih lengkap
- reporting dan analytics yang lebih kaya
- web student certificate center yang lebih penuh
- perluasan mobile product experience
