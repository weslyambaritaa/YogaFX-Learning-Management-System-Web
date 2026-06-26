# 01-prd.md
# Product Requirements Document
# YogaFX LMS

## 1. Product Overview

YogaFX LMS adalah platform pembelajaran web untuk YogaFX dengan dua area produk yang berbeda:
- **Student Side** untuk akses konten belajar
- **Admin Side** untuk operasi, konfigurasi, dan monitoring

Produk ini bukan LMS akademik tradisional. Arah pengalaman yang dijaga tetap:
- student side terasa premium, tenang, dan content-first
- admin side terasa terstruktur, efisien, dan operasional

---

## 2. Product Vision

Menyediakan fondasi LMS YogaFX yang stabil untuk:
- autentikasi dan pemisahan role
- pengelolaan tier membership
- pengelolaan konten pembelajaran
- akses konten berdasarkan tier
- monitoring progres student oleh admin
- pengelolaan email notification berbasis template

---

## 3. Current Product Scope

Dokumen ini mencerminkan **scope aktual yang sudah diimplementasikan** di repository saat ini.

### 3.1 Core Platform
- login
- logout
- forgot password
- reset password
- redirect berdasarkan role
- profile completion gate untuk student
- admin self-service account profile edit

### 3.2 User & Tier Foundation
- profile student
- edit profile oleh student
- edit profile student oleh admin
- create student oleh admin
- create admin oleh admin
- access tier CRUD
- assign tier ke student

### 3.3 Learning Content Core
- module CRUD
- lesson CRUD
- ebook CRUD
- course CRUD
- upload media lokal untuk thumbnail, workbook, dan ebook
- video dan audio berbasis URL/reference

### 3.4 Student Content Access
- student dashboard dasar
- daftar module sesuai tier
- detail module dan daftar lesson sesuai tier
- detail lesson
- lesson video harus tetap langsung play saat student membuka lesson
- workbook lesson yang tersedia harus dapat dikirim otomatis satu kali per student per lesson, dengan fallback manual download
- daftar ebook sesuai tier
- preview ebook sebelum download
- daftar course sesuai tier

### 3.5 Student Progress Admin
- directory student progress berdasarkan tier
- completed lessons view
- assignment operational view
- certificate operational view

### 3.6 Email Notification
- email template management
- send test
- automated trigger wiring
- email logs
- 11 notification types

---

## 4. User Roles

### 4.1 Admin
Super Admin bertanggung jawab untuk:
- melakukan semua yang admin biasa dapat lakukan
- membuat akun admin baru
- mengedit akun admin
- menghapus akun admin biasa

Admin bertanggung jawab untuk:
- mengelola akun admin sendiri dari profile menu
- mengelola tier
- mengelola akun student
- mengelola modules
- mengelola lessons
- mengelola ebooks
- mengelola courses
- memantau student progress
- mengelola assignment status dari area student progress
- mengelola certificate dari area student progress
- mengelola template email

### 4.2 Student
Student saat ini dapat:
- login
- melengkapi profile
- melihat dashboard dasar
- membuka modules
- membuka lessons
- membuka ebooks
- membuka courses
- memperbarui profile sendiri

---

## 5. Membership Tiers

Tier aktif:
- Starter Kit
- Online
- Master Class

Aturan implementasi saat ini:
- setiap student diharapkan memiliki satu tier aktif
- tier digunakan untuk memfilter module, lesson, ebook, dan course
- eligibility certificate saat ini dibatasi: `starter_kit` tidak eligible

---

## 6. Current Functional Requirements

### 6.1 Authentication & Authorization
- sistem harus membedakan Admin dan Student
- area admin hanya dapat diakses admin
- area student hanya dapat diakses student
- logout harus mengakhiri session auth Laravel dan mengarahkan kembali ke login

### 6.2 Student Profile
- student harus melengkapi profile sebelum masuk ke dashboard student
- admin harus dapat mengedit profile student
- profile saat ini mencakup data personal, yoga background, preferred certificate picture, dan `profile_photo`

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

### 6.5 Module, Lesson, Ebook, Course
- admin harus dapat melakukan CRUD
- module, lesson, dan ebook memakai multi-tier access
- course memakai single-tier access
- lesson harus terhubung ke module
- lesson dapat memiliki workbook, video, audio, content, dan `assessment_id` nullable
- `sort_order` module, lesson, dan ebook dibuat otomatis

### 6.6 Content Access
- student hanya boleh melihat konten yang sesuai tier
- lesson hanya boleh dibuka jika tier lesson dan tier module sama-sama mengizinkan student
- media file harus dilayani melalui protected route

### 6.7 Student Progress Admin
- admin melihat daftar student dibagi per tier:
  - Masterclass
  - Online
  - Starter Kit
- progress dihitung sebagai persentase module completed terhadap module yang relevan untuk tier student
- assignment column menampilkan `Submitted` atau `Not Submitted`
- action column memakai menu titik tiga untuk membuka:
  - Completed Lesson
  - Assignment
  - Certificate

### 6.8 Completed Lesson
- admin dapat melihat daftar lesson yang sudah complete
- admin dapat reset lesson progress dengan konfirmasi

### 6.9 Assignment Admin Operations
- admin dapat mengubah status assignment
- admin dapat menyimpan feedback
- admin dapat mengirim email manual dari halaman assignment
- admin dapat menghapus video assignment dengan konfirmasi

### 6.10 Certificate Admin Operations
- admin dapat generate certificate
- admin dapat recreate certificate
- admin dapat download certificate
- admin dapat delete certificate
- admin dapat mengirim graduation email
- certificate type yang aktif:
  - Bikram Yoga Certificate
  - Yoga Alliance Certification

### 6.11 Email Notification
- sistem harus mendukung 11 notification type:
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
- setiap template punya:
  - enable flag
  - admin recipients
  - admin subject/body
  - user subject/body
  - send test
- semua email test dan automated harus dicatat ke email logs
- reminder harus memakai inactivity login student dan hanya berlaku untuk student yang belum complete seluruh perjalanan module tier-nya
- `workbook_sent` harus dikirim sekali saat auto-download workbook pertama kali dipicu untuk kombinasi student + lesson dan workbook dikirim sebagai attachment

### 6.12 Upload Rules
- semua upload file yang relevan dibatasi maksimal 10 MB, kecuali ebook file yang dibatasi maksimal 500 MB
- validasi size dilakukan di frontend dan backend
- file oversized tidak boleh disimpan

### 6.13 Ebook Preview
- ebook harus dibuka dulu di halaman preview jika format mendukung
- download menjadi aksi eksplisit yang terpisah

---

## 7. Current Non-Functional Requirements

- stack tetap Laravel + React + Inertia + PostgreSQL + Tailwind + shadcn/ui
- ORM utama tetap Eloquent
- dokumentasi harus mengikuti migration dan implementasi aktual
- admin navigation memakai left sidebar
- delete action pada CRUD yang sudah diberi konfirmasi harus konsisten
- redirect sukses setelah create/update harus kembali ke halaman list/index
- domain user management admin mengikuti pola list -> create -> kembali ke list

---

## 8. Explicitly Out of Current Scope

Fitur berikut belum menjadi implementasi aktif saat ini:
- public self-signup flow yang dibuka ke user umum
- assessment CRUD dan assessment player
- assessment page/question/option builder
- watch progress otomatis
- sequential lesson locking
- student assignment submission UI
- student certificate page
- session duration dan cumulative access tracking khusus domain
- reporting dashboard yang kaya statistik
- pembayaran, subscription, expiry, renewal

---

## 9. Product Decisions That Override Older Assumptions

- Module, Lesson, dan Ebook memakai many-to-many tier access
- Course tetap single-tier
- Student Progress menggantikan menu Students sebagai pusat monitoring admin
- Email Notification sekarang punya 10 child menu, bukan 6
- Admin dashboard saat ini sengaja sederhana, belum berupa statistics dashboard
- Student dashboard saat ini masih foundational, belum final premium dashboard

---

## 10. Future Product Direction

Domain berikut tetap ada dalam arah produk jangka lanjut, tetapi **belum** menjadi source of truth implementasi aktif:
- assessment end-to-end
- lesson progress automation
- student assignment flow
- student certificate access flow
- richer analytics dan monitoring

Jika domain-domain ini akan dikerjakan, dokumentasi harus diperbarui lagi sebelum implementasi dimulai.
