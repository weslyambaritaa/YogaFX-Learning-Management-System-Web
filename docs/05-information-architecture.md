# 05-information-architecture.md
# Information Architecture
# YogaFX LMS

## 1. Purpose

Dokumen ini mendefinisikan struktur halaman, menu, dan grouping fitur yang aktif saat ini di YogaFX LMS.

Jika sebuah halaman belum tercatat di sini, halaman itu tidak boleh dianggap sebagai bagian dari IA aktif tanpa dokumentasi tambahan.

---

## 2. Global Structure

Sistem saat ini terbagi menjadi:
1. Public Entry & Conversion Layer
2. Student Web Area
3. Admin Web Area
4. Mobile Student API Layer

Catatan:
- IA ini memfokuskan penempatan halaman web
- mobile API dicatat sebagai layer aktif, tetapi bukan menu navigasi web

---

## 3. Public / Entry Layer

### 3.1 Active Public Pages
- Login
- OTP Verification
- Forgot Password
- Reset Password
- Scoreboard / Lead Registration
- Tier-specific public registration pages:
  - `/starter-kit`
  - `/online`
  - `/masterclass`
- Direct package public page:
  - `/p/{package_slug}`
- App Download:
  - `/download-app`
- Checkout
- Checkout Status
- Payment Success Continuation
- Enrollment
- Signup Completion

### 3.2 Public Pages That Are Not Active
- public register / signup bebas tanpa checkout

---

## 4. Student Information Architecture

### 4.1 Primary Student Navigation
- Home
- Modules

### 4.2 Instant Access Area
- Full Standing Dialog
- Full Floor Dialog

### 4.3 User Menu Access
- Profile
- Download Application
- Logout

### 4.4 Student Pages
- Home
- Profile Edit
- Password Change Request Flow
- Modules Index
- Module Detail
- Lesson Detail
- Assessment Intro
- Assessment Player
- Assessment Result
- Assignment Detail / Submit
- Ebooks Index
- Ebook Preview
- Courses Index
- Course Detail
- Upgrade Checkout
- Upgrade Success
- Student Inactive Page

### 4.5 Current Student Notes
- web student certificate access saat ini bersifat download-only dari Home milestone, belum halaman index khusus
- student IA tetap sengaja ringan dan tidak bercabang terlalu dalam

---

## 5. Admin Information Architecture

### 5.1 Admin Sidebar Primary Navigation
- Dashboard
- Modules
- Lessons
- Assessment
- Student Progress
- Students
<<<<<<< Updated upstream
- Admin
=======
- Dialog
>>>>>>> Stashed changes
- Video Lecture
- E-Book
- Email

### 5.2 Supporting Pages Group
- Packages
- Access Tiers
- Link Control

### 5.3 Admin Topbar
Topbar berisi:
- page title
- sidebar toggle
- user menu

### 5.4 Admin User Menu
- Profile
- Logout

---

## 6. Admin Pages

### 6.1 Dashboard
- Admin Dashboard
- Admin Profile
- Admin Index
- Admin Create
- Admin Edit

### 6.2 Students
- Students Index
- Student Detail / Edit

### 6.3 Packages
- Packages Index
- Packages Create
- Packages Edit

### 6.4 Access Tiers
- Access Tiers Index
- Access Tiers Create
- Access Tiers Edit

### 6.4A Link Control
- Link Control Show / Update

### 6.5 Learning Content
- Modules Index / Create / Edit
- Module Assignments Index / Create / Edit
- Lessons Index / Create / Edit
- E-Books Index / Create / Edit / Preview
- Video Lecture Index / Create / Edit
- Dialog Edit

### 6.6 Assessment
- Assessment Index
- Assessment Create
- Assessment Edit
- Assessment Builder
- Assessment Preview
- Assessment Preview Result
- Assessment Results Index
- Assessment Result Detail

### 6.7 Student Progress
- Student Progress Directory
- Completed Lessons Detail
- Assignment Detail
- Certificate Detail

<<<<<<< Updated upstream
### 6.5 Student Account Management
- Students Index
- Students Create
- Students Edit

### 6.6 Email Notification
=======
### 6.8 Email Notification
>>>>>>> Stashed changes
- Email Notification detail page per notification type

---

## 7. Student Progress IA

### 7.1 Entry Point
Menu `Student Progress` membuka halaman directory.

### 7.2 Directory Layout
Directory menampilkan 3 section tabel:
1. Masterclass
2. Online
3. Starter Kit

Setiap row menampilkan:
- No
- Photo
- Name
- Progress
- Registration Date
- Assignment
- Action

### 7.3 Action Pattern
Kolom `Action` memakai menu titik tiga:
- Completed Lesson
- Assignment
- Certificate

Catatan:
- `Student Progress` dan `Students` adalah dua area berbeda
- `Students` dipakai untuk account administration
- `Student Progress` dipakai untuk learning operations

---

## 8. Email IA

### 8.1 Parent Menu
Sidebar admin memiliki parent menu `Email`.

### 8.2 Child Menu
Child aktif:
1. Module Completion
2. Assignments Review
3. Assignments Approved
4. Assignments Rejected
5. Certificate Created
6. Signup
7. Reset Password
8. Assessment Complete
9. Course Complete
10. Reminder
11. Workbook Sent

Catatan implementasi saat ini:
- template installment sudah aktif di backend registry
- route detail installment bisa dibuka langsung lewat `admin.email-notifications.show`
- sidebar child menu untuk installment belum diekspos

### 8.3 Email Detail Page
Setiap child membuka halaman detail template yang berisi:
- enable notification
- admin recipients
- admin subject
- admin body
- user subject
- user body
- save changes
- send test
- media upload
- send to
- available merge tags
- trigger context

---

## 9. Navigation Rules

### 9.1 Student
- ringan
- sedikit level
- fokus pada next learning action
- profile tetap keluar dari user menu, bukan item menu utama

### 9.2 Admin
- task-first
- sidebar sebagai anchor utama
- list -> detail/create/edit -> kembali ke list
- detail student operations dipisah dari progress operations

---

## 10. Content Visibility Rules

### 10.1 Student
<<<<<<< Updated upstream
- sederhana
- ringan
- tidak terlalu banyak level
- fokus pada akses konten dan profile

### 10.2 Admin
- task-first
- list -> create/edit -> kembali ke list
- sidebar sebagai anchor utama
- menu `Admin` memakai halaman yang sama untuk admin biasa dan super admin, tetapi aksi berbeda berdasarkan role

---

## 11. Content Visibility Rules

### 11.1 Student
=======
>>>>>>> Stashed changes
Student hanya boleh melihat:
- module sesuai tier
- lesson sesuai tier dan sequential unlock
- assessment yang terhubung ke lesson yang boleh diakses
- assignment yang relevan dengan path student
- ebook sesuai tier
- course sesuai tier
- certificate miliknya sendiri

### 10.2 Admin
Admin dapat melihat:
- seluruh student
- seluruh content
- seluruh assessment
- seluruh tier
- seluruh student progress data yang tersedia
- seluruh email templates dan email logs terkait

---

## 11. Current Gaps That Are Not Part of Active IA

Belum menjadi bagian IA web aktif saat ini:
- public self-signup tanpa checkout
- student certificate index page terpisah
- analytics/reporting dashboard kaya statistik
- commerce backoffice yang lebih lengkap
