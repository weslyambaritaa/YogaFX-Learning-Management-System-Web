# 05-information-architecture.md
# Information Architecture
# YogaFX LMS

## 1. Purpose

Dokumen ini mendefinisikan struktur halaman, menu, dan grouping fitur **yang aktif saat ini** di YogaFX LMS.

Jika sebuah halaman belum ada di dokumen ini, halaman tersebut tidak boleh dianggap sebagai fitur aktif tanpa dokumentasi tambahan.

---

## 2. Global Structure

Sistem dibagi menjadi:
1. Public Auth Entry
2. Student Area
3. Admin Area

---

## 3. Public / Entry Layer

Halaman aktif:
- Login
- Forgot Password
- Reset Password

Halaman yang belum aktif:
- public Register / Signup route

---

## 4. Student Information Architecture

### 4.1 Primary Student Navigation
- Dashboard
- Modules
- Ebooks
- Courses
- Profile

### 4.2 Student Pages
- Student Dashboard
- Profile Edit
- Modules Index
- Module Detail
- Lesson Detail
- Ebooks Index
- Ebook Preview
- Courses Index

### 4.3 Current Student Notes
- Assignments page belum ada
- Certificates page belum ada
- Assessment pages belum ada
- Student dashboard masih foundation, belum discovery-heavy premium dashboard final

---

## 5. Admin Information Architecture

### 5.1 Admin Sidebar Primary Navigation
- Dashboard
- Modules
- Lessons
- Assessment
- Student Progress
- Video Lecture
- E-Book
- Email

### 5.2 Supporting Pages Group
- Access Tiers

### 5.3 Admin Topbar
Topbar hanya berisi:
- page title
- sidebar toggle
- user menu
- logout action

---

## 6. Admin Pages

### 6.1 Dashboard
- Admin Dashboard

### 6.2 Tier Management
- Access Tiers Index
- Access Tiers Create
- Access Tiers Edit

### 6.3 Learning Content
- Modules Index / Create / Edit
- Lessons Index / Create / Edit
- E-Books Index / Create / Edit / Preview
- Video Lecture Index / Create / Edit

### 6.4 Student Progress
- Student Progress Directory
- Student Profile Edit
- Completed Lessons Detail
- Assignment Detail
- Certificate Detail

### 6.5 Email Notification
- Email Notification detail page per notification type

---

## 7. Student Progress IA

### 7.1 Entry Point
Menu `Student Progress` langsung membuka halaman directory.

### 7.2 Directory Layout
Halaman directory menampilkan 3 section tabel:
1. Masterclass
2. Online
3. Starter Kit

Setiap tabel berisi kolom:
- No
- Photo
- Name
- Progress
- Registration Date
- Assignment
- Action

### 7.3 Action Pattern
Kolom `Action` memakai menu titik tiga yang membuka:
- Completed Lesson
- Assignment
- Certificate

Catatan:
- tidak ada lagi menu `Students` terpisah di sidebar
- tidak ada hamburger child menu di sidebar untuk Student Progress

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

### 8.3 Email Detail Page
Setiap child membuka satu halaman detail template yang berisi:
- Enable notification
- Admin recipients
- Admin subject
- Admin body
- User subject
- User body
- Save Changes
- Send Test
- Send To
- Available merge tags
- Trigger context

---

## 9. Learning Content IA

### 9.1 Admin Side
Menu terpisah:
- Modules
- Lessons
- E-Book
- Video Lecture

Catatan:
- lesson tidak hanya dikelola dari context module detail
- admin tetap bisa langsung membuka halaman list lesson

### 9.2 Student Side
Akses konten yang aktif:
- daftar modules sesuai tier
- detail module dengan daftar lesson sesuai tier
- detail lesson
- daftar ebooks sesuai tier
- ebook preview
- daftar courses sesuai tier

---

## 10. Navigation Rules

### 10.1 Student
- sederhana
- ringan
- tidak terlalu banyak level
- fokus pada akses konten dan profile

### 10.2 Admin
- task-first
- list -> create/edit -> kembali ke list
- sidebar sebagai anchor utama

---

## 11. Content Visibility Rules

### 11.1 Student
Student hanya boleh melihat:
- module sesuai tier
- lesson sesuai tier module dan tier lesson
- ebook sesuai tier
- course sesuai tier
- media file yang lolos pemeriksaan role dan tier

### 11.2 Admin
Admin dapat melihat:
- seluruh tier
- seluruh content
- seluruh student progress data yang sudah tersedia
- seluruh email templates dan email logs terkait

---

## 12. Current Gaps That Are Not Part of Active IA

Belum ada di IA aktif saat ini:
- assessment builder/admin pages yang benar-benar hidup
- student assignment submission page
- student certificate page
- email logs page terpisah
- report/analytics dashboard

Jika area-area ini akan diaktifkan, IA harus diperbarui lagi sebelum implementasi.
