# 02-user-flow.md
# User Flow Document
# YogaFX LMS

## 1. Purpose

Dokumen ini menjelaskan **flow aktual yang sudah aktif** di aplikasi saat ini.

Jika ada flow yang masih berupa rencana, flow tersebut tidak dianggap source of truth implementasi sampai didokumentasikan ulang.

---

## 2. Authentication Flow

### Scope
- login
- logout
- forgot password
- reset password

### Main Flow
1. User membuka halaman login.
2. User mengisi email dan password.
3. Sistem memvalidasi kredensial.
4. Jika valid:
   - Admin diarahkan ke `admin.dashboard`
   - Student diarahkan ke `profile.edit` jika profile belum lengkap
   - Student diarahkan ke `student.dashboard` jika profile sudah lengkap
5. Saat logout, sistem mengakhiri session auth dan mengarahkan user ke login.

### Important Notes
- public signup route tidak aktif saat ini
- forgot password dan reset password tetap aktif
- template email reset password dapat dioverride oleh Email Notification bila template diaktifkan

---

## 3. Student Onboarding Flow

### Main Flow
1. Student login.
2. Sistem memeriksa kelengkapan profile student.
3. Jika belum lengkap, student dipaksa masuk ke halaman profile edit.
4. Student mengisi semua field wajib.
5. Sistem memvalidasi data.
6. Setelah profile tersimpan, student dapat masuk ke dashboard.

### Alternative Flow
- admin dapat membuka dan mengedit profile student dari area Student Progress

---

## 4. Student Dashboard Flow

### Current State
Student dashboard saat ini masih berupa halaman foundation, belum final premium dashboard.

### Main Flow
1. Student membuka dashboard.
2. Sistem menampilkan informasi dasar user:
   - signed in as
   - role
   - access tier
3. Student menggunakan shortcut ke:
   - Modules
   - Ebooks
   - Courses
   - Profile

---

## 5. Student Learning Content Flow

### 5.1 Modules
1. Student membuka halaman modules.
2. Sistem memfilter module berdasarkan tier student.
3. Student membuka satu module.
4. Sistem menampilkan lessons yang juga sesuai tier student.
5. Student memilih lesson.

### 5.2 Lesson Detail
1. Sistem memastikan tier student cocok dengan tier module dan lesson.
2. Halaman lesson menampilkan:
   - thumbnail
   - workbook jika ada
   - video URL/reference
   - audio URL/reference
   - text content
   - `assessment_id` jika tersedia sebagai placeholder relasi

### Important Notes
- lesson locking belum aktif
- workbook gating belum aktif
- watch progress automation belum aktif
- assessment player belum aktif

---

## 6. Student Ebook Flow

### Main Flow
1. Student membuka daftar ebooks.
2. Sistem memfilter ebook berdasarkan tier student.
3. Student memilih ebook.
4. Sistem membuka halaman preview ebook.
5. Jika file dapat dipreview, terutama PDF, file ditampilkan inline.
6. Jika file tidak dapat dipreview, sistem menampilkan pesan fallback.
7. Download hanya terjadi jika student menekan tombol download secara eksplisit.

---

## 7. Student Course Flow

### Main Flow
1. Student membuka daftar courses.
2. Sistem memfilter course berdasarkan single tier milik student.
3. Student melihat title, description, thumbnail, dan video reference.

---

## 8. Admin Dashboard Flow

### Main Flow
1. Admin login.
2. Sistem mengarahkan admin ke dashboard.
3. Admin melihat halaman sambutan sederhana:
   - `Hai, {nama pengguna}`
   - `Welcome to YogaFX Learning Management System`
4. Admin menggunakan sidebar kiri untuk berpindah area kerja.

### Sidebar Structure
- Dashboard
- Modules
- Lessons
- Assessment
- Student Progress
- Video Lecture
- E-Book
- Email
- Supporting Pages -> Access Tiers

### Topbar Structure
- page title
- sidebar toggle
- user menu
- logout

---

## 9. Admin Content Management Flow

### Main Flow
1. Admin membuka salah satu area:
   - Access Tiers
   - Modules
   - Lessons
   - E-Books
   - Video Lecture
2. Admin membuka halaman list.
3. Admin memilih create, edit, preview, atau delete.
4. Saat create/edit:
   - sistem memvalidasi form
   - upload file dibatasi 10 MB
   - jika sukses, admin diarahkan kembali ke halaman list
5. Saat delete:
   - sistem selalu meminta konfirmasi
   - jika valid dan diperbolehkan, data dihapus

### Current Rules
- module, lesson, dan ebook memakai multi-tier access
- course memakai single-tier access
- lesson tier harus subset dari tier module induk
- module tidak boleh dihapus jika masih punya lesson
- ebook saat ini dapat dipreview dari admin

---

## 10. Student Progress Admin Flow

### 10.1 Directory
1. Admin membuka menu `Student Progress`.
2. Halaman index langsung menampilkan 3 tabel:
   - Masterclass
   - Online
   - Starter Kit
3. Setiap tabel menampilkan:
   - No
   - Photo
   - Name
   - Progress
   - Registration Date
   - Assignment
   - Action
4. Kolom Action memakai menu titik tiga.
5. Dari menu tersebut admin dapat membuka:
   - Completed Lesson
   - Assignment
   - Certificate

### 10.2 Completed Lesson
1. Admin membuka detail completed lesson untuk satu student.
2. Sistem menampilkan semua lesson progress yang `is_done = true`.
3. Admin dapat reset progress satu lesson dengan konfirmasi.

### 10.3 Assignment
1. Admin membuka detail assignment untuk satu student.
2. Sistem menampilkan tabel assignment:
   - title
   - video
   - status
   - feedback
3. Admin dapat:
   - save perubahan status/feedback
   - send email
   - delete video

### 10.4 Certificate
1. Admin membuka detail certificate untuk satu student.
2. Sistem memeriksa eligibility certificate.
3. Admin dapat:
   - generate certificate
   - send graduation email
   - recreate certificate
   - download certificate
   - delete certificate

---

## 11. Email Notification Flow

### Main Flow
1. Admin membuka parent menu `Email`.
2. Admin memilih salah satu child menu notification type.
3. Sistem memuat template email untuk type tersebut.
4. Admin dapat:
   - enable/disable notification
   - mengatur admin recipients
   - mengatur admin subject/body
   - mengatur user subject/body
5. Admin klik `Save Changes`.
6. Admin dapat mengirim `Send Test` ke alamat email tertentu.
7. Saat trigger bisnis terjadi, sistem:
   - memeriksa template
   - memeriksa `is_enabled`
   - merender merge tags
   - mengirim email
   - mencatat email log

### Notification Types Active
- module_completion
- assignment_review
- assignment_approved
- assignment_rejected
- certificate_created
- signup
- reset_password
- assessment_complete
- course_complete
- reminder

---

## 12. Flows That Are Not Active Yet

Flow berikut belum aktif end-to-end:
- public signup self-service
- student assignment submission
- assessment builder dan assessment player
- sequential lesson locking
- workbook download gating
- student certificate page
- watch progress automation

Dokumen lain tidak boleh lagi menggambarkan flow-flow tersebut sebagai fitur aktif saat ini.
