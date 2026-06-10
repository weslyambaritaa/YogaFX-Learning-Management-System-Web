# 00-current-project-status.md
# Current Project Status
# YogaFX LMS

## Purpose

Dokumen ini merangkum kondisi implementasi aktual YogaFX LMS di repository saat ini.

Gunakan dokumen ini sebagai pintu masuk sebelum membaca PRD, User Flow, ERD, IA, dan Modular Implementation, supaya jelas:
- fitur mana yang sudah benar-benar aktif
- fitur mana yang masih berupa rencana
- keputusan teknis terbaru apa saja yang sudah mengubah asumsi dokumentasi lama

---

## 1. Snapshot Saat Ini

Implementasi aktif saat ini sudah mencakup domain berikut:
- authentication foundation
- student profile foundation
- access tier management
- admin learning content CRUD
- student content catalog dasar
- admin student progress
- email notification template management

Stack aktif:
- Laravel
- React
- Inertia
- PostgreSQL
- Tailwind
- shadcn/ui

Role aktif:
- Admin
- Student

Tier aktif:
- `starter_kit`
- `online`
- `master_class`

---

## 2. Domain Yang Sudah Terimplementasi

### 2.1 Authentication
- login
- logout
- forgot password
- reset password
- redirect dashboard berdasarkan role
- middleware role admin dan student

Catatan:
- route public signup saat ini tidak diaktifkan di `routes/auth.php`
- infrastruktur event `signup` tetap tersedia untuk kebutuhan seed, admin-created user, atau aktivasi signup di fase berikutnya

### 2.2 Student Profile
- student wajib melengkapi profile sebelum masuk ke student dashboard
- student dapat edit profile sendiri
- admin dapat edit profile student dari route Student Progress
- field `profile_photo` sudah tersedia, tetapi saat ini masih berupa string URL/path, belum upload file khusus profile photo

### 2.3 Access Tier Management
- CRUD tier admin
- assign tier ke student
- seed default `Starter Kit`, `Online`, `Master Class`
- status aktif tier menggunakan `is_active`

Catatan:
- implementasi delete tier saat ini masih hard delete jika tier belum dipakai oleh user
- jika tier sudah dipakai oleh user, delete ditolak

### 2.4 Learning Content
- admin CRUD Modules
- admin CRUD Lessons
- admin CRUD Ebooks
- admin CRUD Courses
- upload thumbnail untuk module, lesson, course
- upload workbook untuk lesson
- upload file ebook
- video dan audio lesson disimpan sebagai URL/reference string
- course video disimpan sebagai URL/reference string

Keputusan terbaru yang aktif:
- `Module`, `Lesson`, dan `Ebook` memakai relasi many-to-many ke `AccessTier`
- `Course` masih memakai single `access_tier_id`
- `sort_order` untuk module, lesson, dan ebook dibuat otomatis saat create
- `sort_order` tidak diedit manual dari form
- setelah create/update berhasil, admin diarahkan kembali ke halaman list
- aksi delete pada CRUD terkait memakai dialog konfirmasi

### 2.5 Student Content Catalog
- student dashboard dasar
- daftar module sesuai tier
- detail module dan daftar lesson sesuai tier
- detail lesson
- daftar ebook sesuai tier
- preview ebook, terutama PDF, sebelum download
- daftar course sesuai tier

Catatan:
- sequential locking, workbook gating, watch progress 95%, dan assessment unlock belum aktif
- assessment masih placeholder nullable di lesson
- student assignment page dan student certificate page belum ada

### 2.6 Student Progress Admin
- menu utama `Student Progress` di admin
- halaman index langsung menampilkan 3 tabel berdasarkan tier:
  - Masterclass
  - Online
  - Starter Kit
- kolom:
  - No
  - Photo
  - Name
  - Progress
  - Registration Date
  - Assignment
  - Action
- kolom Action memakai menu titik tiga berisi:
  - Completed Lesson
  - Assignment
  - Certificate

Area detail aktif:
- Completed Lesson
  - daftar lesson completed
  - reset progress dengan konfirmasi
- Assignment
  - tabel title, video, status, feedback
  - save
  - send email
  - delete video
- Certificate
  - generate certificate
  - send graduation email
  - recreate certificate
  - download certificate
  - delete certificate

### 2.7 Email Notification
- menu Email di sidebar admin
- 10 child menu:
  - Module Completion
  - Assignments Review
  - Assignments Approved
  - Assignments Rejected
  - Certificate Created
  - Signup
  - Reset Password
  - Assessment Complete
  - Course Complete
  - Reminder
- tiap child menu punya:
  - enable notification
  - admin recipients
  - admin subject
  - admin body
  - user subject
  - user body
  - save changes
  - send test
  - send to
- email logs tersimpan ke database
- reminder memiliki scheduler harian

---

## 3. Keputusan Teknis Penting Yang Sudah Aktif

### 3.1 Upload Constraint
Semua upload file yang relevan saat ini dibatasi maksimal 10 MB:
- module thumbnail
- lesson thumbnail
- lesson workbook
- course thumbnail
- ebook file

Validasi dilakukan di:
- frontend form
- backend request validation

### 3.2 E-book Preview
- file ebook tidak lagi langsung didownload
- jika format mendukung, terutama PDF, ebook dibuka di halaman preview
- tombol download tetap tersedia terpisah

### 3.3 Protected Media Access
File local diakses melalui `ContentFileController` dengan pemeriksaan role dan tier, bukan langsung sebagai public asset bebas.

### 3.4 Admin Layout
Admin saat ini memakai:
- left sidebar yang bisa collapse/expand
- topbar untuk page title, toggle sidebar, dan user menu
- tidak lagi memakai top navigation utama

### 3.5 Admin Dashboard
Konten dashboard admin saat ini sengaja disederhanakan menjadi:
- `Hai, {nama pengguna}`
- `Welcome to YogaFX Learning Management System`

---

## 4. Domain Yang Belum Terimplementasi Penuh

Domain berikut masih planned atau baru sebagian kecil disiapkan:
- assessment CRUD dan assessment player
- page/question/option builder
- autosave assessment
- timer assessment
- lesson watch progress automation
- workbook download tracking automation dari student side
- sequential lesson locking
- student assignment submission page
- student certificate page
- progress tracking end-to-end dari student learning action
- reporting dashboard yang kaya data
- cumulative session tracking khusus domain

---

## 5. Cara Membaca Dokumentasi Setelah Sinkronisasi

Urutan baca yang disarankan:
1. `docs/00-current-project-status.md`
2. `docs/01-prd.md`
3. `docs/02-user-flow.md`
4. `docs/03-erd.md`
5. `docs/04-design-system.md`
6. `docs/05-information-architecture.md`
7. `docs/06-modular-implementation.md`

Dokumen khusus dibaca saat domain terkait dikerjakan:
- `docs/student-progress-flow.md`
- `docs/email-notification-flow.md`

Diagram Mermaid dipakai sebagai visual support dan harus dianggap mengikuti status implementasi yang dijelaskan di dokumen tertulis.

Dokumen di `docs/history/` bersifat arsip fase, bukan source of truth aktif.
