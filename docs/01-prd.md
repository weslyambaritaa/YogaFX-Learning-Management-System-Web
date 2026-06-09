# 01-prd.md
# Product Requirements Document (PRD)
# YogaFX LMS

## 1. Product Overview

### 1.1 Product Name
YogaFX LMS

### 1.2 Product Summary
YogaFX LMS adalah Learning Management System berbasis web yang dirancang untuk mendukung proses pembelajaran yoga secara digital, terstruktur, dan bertahap. Sistem ini digunakan untuk mengelola akses konten belajar, progress student, assessment, assignment video, certificate, dan email notification dalam satu alur yang terintegrasi.

Produk ini ditujukan untuk mendukung kebutuhan operasional YogaFX dalam mendistribusikan materi pembelajaran, memantau perkembangan student, mengelola proses evaluasi, dan menerbitkan certificate sesuai aturan bisnis yang berlaku.

### 1.3 Problem Statement
Saat ini dibutuhkan sebuah sistem yang mampu:
- mengelola pembelajaran digital secara terstruktur
- membatasi akses konten berdasarkan membership tier
- memastikan student belajar secara berurutan
- mengukur progress belajar dengan aturan yang konsisten
- mendukung assessment dengan timer, autosave, dan progress tracking
- mengelola submission assignment video
- mendukung proses review assignment dan feedback
- mengelola certificate
- mengirim email otomatis pada trigger penting
- menyediakan panel admin yang terpusat

### 1.4 Product Vision
Menjadi platform pembelajaran digital YogaFX yang terstruktur, mudah dikelola, dan siap dipakai untuk mendukung student sungguhan dalam perjalanan belajar dari login sampai certificate.

---

## 2. Product Goals

### 2.1 Business Goals
- Menyediakan LMS resmi untuk program pembelajaran digital YogaFX.
- Mengelola distribusi konten pembelajaran berdasarkan tier membership.
- Mengurangi proses manual dalam monitoring progress, review assignment, dan certificate.
- Menyediakan fondasi sistem yang stabil untuk operasional pembelajaran jangka panjang.
- Mengotomatisasi komunikasi melalui email notification pada event penting.

### 2.2 User Goals
- Student dapat belajar dengan alur yang jelas dan tidak membingungkan.
- Student hanya melihat konten yang sesuai dengan aksesnya.
- Student mengetahui apa yang harus dilakukan berikutnya.
- Student dapat mengerjakan assessment dengan pengalaman yang konsisten.
- Student dapat submit assignment dan menerima feedback.
- Student dapat menerima certificate bila memenuhi syarat.

### 2.3 Operational Goals
- Admin dapat mengelola seluruh konten pembelajaran dari satu dashboard.
- Admin dapat memantau progress student secara jelas.
- Admin dapat meninjau assignment dan membuat certificate.
- Admin dapat mengatur template email tanpa harus mengubah kode program.

---

## 3. Scope Phase 1

Phase 1 mencakup seluruh fitur inti yang dibutuhkan agar alur utama student dan admin dapat berjalan end-to-end.

### 3.1 Core Platform
- authentication untuk Admin dan Student
- student profile
- access tier management
- student dashboard
- admin dashboard

### 3.2 Learning Content
- module management
- lesson management
- workbook
- video lesson
- audio
- text content
- ebook management
- course management dasar

### 3.3 Learning Rules
- lesson locking
- sequential learning
- workbook requirement
- video watch progress tracking
- assessment unlock setelah watch progress 95%
- lesson completion logic
- next lesson unlocking logic

### 3.4 Assessment
- assessment CRUD
- assessment pages
- questions
- question options
- question type support:
  - single_choice
  - multiple_choice
  - text
  - true_false
- assessment timer
- assessment attempts
- autosave answers
- progress live
- auto submit saat waktu habis
- score calculation
- assessment result tracking

### 3.5 Progress Tracking
- lesson progress
- assessment progress
- session tracking
- cumulative access duration

### 3.6 Assignment
- assignment submission
- dynamic assignment type
- assignment review
- assignment approval
- assignment rejection
- reviewer feedback

### 3.7 Certificate
- certificate generation
- certificate recreate
- certificate versioning dasar
- certificate download / access

### 3.8 Email Notification
- email template management
- 6 notification sub menu
- save template
- enable/disable notification
- manual send test
- automatic trigger
- email logs

---

## 4. Out of Scope

Fitur berikut secara sengaja tidak menjadi fokus phase 1:

- mobile app native
- offline learning mode
- push notification
- advanced analytics dan BI dashboard
- randomisasi soal dan jawaban
- in-app notification center
- multi-reviewer workflow formal
- finance / payment module
- student self-upgrade tier
- public marketplace / catalog course
- multi-language system
- advanced audit trail untuk semua perubahan data
- API-first architecture untuk semua fitur
- microservice terpisah
- full role hierarchy di luar Admin dan Student

---

## 5. User Roles

## 5.1 Admin
Admin adalah pengguna internal yang mengelola sistem secara operasional.

### Tanggung Jawab Admin
- mengelola access tiers
- mengelola modules
- mengelola lessons
- mengelola assessments
- mengelola assessment pages
- mengelola questions
- mengelola question options
- mengelola ebooks
- mengelola courses
- memantau student progress
- mereview assignment
- memberikan feedback assignment
- generate certificate
- recreate certificate
- mengelola email templates
- mengirim test email
- memantau email logs

## 5.2 Student
Student adalah pengguna utama yang mengikuti pembelajaran di sistem.

### Tanggung Jawab Student
- login ke sistem
- melengkapi profile
- mengakses module dan lesson sesuai tier
- download workbook
- menonton video lesson
- mengerjakan assessment
- submit assignment
- memantau progress
- mengakses ebooks/courses yang diizinkan
- mengakses certificate jika tersedia

---

## 6. Membership Tiers

Sistem menggunakan tiga membership tier utama sebagai dasar pembatasan akses.

## 6.1 Online
Karakteristik:
- dapat mengakses semua module yang disediakan untuk tier ini
- dapat submit final assignment
- berhak menerima certificate jika memenuhi syarat

## 6.2 MasterClass
Karakteristik:
- dapat mengakses semua module yang disediakan untuk tier ini
- tidak mengikuti final assignment dalam rule awal
- tetap berhak menerima certificate sesuai rule program

## 6.3 Starter Kit
Karakteristik:
- hanya dapat mengakses sebagian module
- tidak dapat submit final assignment
- tidak berhak menerima certificate

### Catatan Membership
Tier access digunakan untuk membatasi:
- modules
- lessons
- ebooks
- courses
- assignment eligibility
- certificate eligibility

---

## 7. Feature List

## 7.1 Authentication & Access
- login
- logout
- forgot password
- reset password
- role-based dashboard routing
- access control by role
- access control by tier

## 7.2 Student Profile
- edit profile oleh student
- edit profile oleh admin
- personal information
- yoga background
- preferred certificate picture
- data validation

## 7.3 Dashboard
### Admin Dashboard
- ringkasan statistik utama
- shortcut ke modul inti
- pending assignment overview
- progress overview

### Student Dashboard
- continue learning
- progress overview
- assignment status
- certificate status
- content access shortcut

## 7.4 Access Tier Management
- create tier
- edit tier
- delete tier
- assign tier ke user
- assign tier ke content

## 7.5 Module Management
- create module
- edit module
- delete module
- define order
- assign access tier

## 7.6 Lesson Management
- create lesson
- edit lesson
- delete lesson
- attach to module
- optional assessment relation
- upload workbook
- attach video reference
- upload / attach audio
- rich text content
- lesson ordering
- tier-based access

## 7.7 Lesson Progress
- watch progress tracking
- workbook download tracking
- lesson completion state
- next lesson unlock

## 7.8 Assessment Management
- create assessment
- edit assessment
- delete assessment
- set duration_minutes
- create assessment pages
- add questions
- add question options
- reorder pages/questions/options
- preview assessment

## 7.9 Assessment Player
- start assessment
- countdown timer
- page-based navigation
- autosave answer
- final submit
- auto submit on expiry
- result display
- score calculation

## 7.10 Assignment Management
- submit assignment
- dynamic assignment type
- assignment status
- assignment feedback
- approve/reject by admin

## 7.11 Certificate Management
- generate certificate
- recreate certificate
- track version
- certificate file access
- student certificate view/download

## 7.12 Ebook Management
- create ebook
- edit ebook
- delete ebook
- assign access tier
- student ebook access

## 7.13 Course Management
- create course
- edit course
- delete course
- assign access tier
- attach video/resource
- student course access

## 7.14 Email Notification Management
Terdapat 6 sub menu utama:
1. Module Completion
2. Assignments Review
3. Assignments Approved
4. Assignments Rejected
5. Certificate Created
6. Signup

Masing-masing sub menu memiliki:
- Email Notification
- Send Test

### Email Notification
- enable / disable notification
- admin recipients
- admin subject
- admin body
- user subject
- user body

### Send Test
- input email tujuan
- render template dengan sample data
- kirim email test manual

## 7.15 Email Logs
- log email otomatis
- log email test
- subject snapshot
- body snapshot
- recipient
- status
- error message
- sent time

## 7.16 Session Tracking
- create user session saat login
- update last activity
- end session saat logout/timeout
- accumulate access duration
- continue timer from previous total

---

## 8. Student Journey

### 8.1 Login
Student login ke sistem menggunakan email dan password.

### 8.2 Profile
Jika profile belum lengkap, student diminta melengkapi data profile terlebih dahulu.

### 8.3 Dashboard
Student melihat dashboard yang menampilkan progress, lesson terakhir, assignment status, dan certificate status.

### 8.4 Access Module
Student hanya dapat melihat module yang sesuai tier.

### 8.5 Open Lesson
Student membuka lesson yang unlocked sesuai urutan.

### 8.6 Workbook Rule
Jika lesson memiliki workbook, student harus mendownload workbook terlebih dahulu.

### 8.7 Video Consumption
Student menonton video lesson dan sistem mencatat watch progress.

### 8.8 Assessment Unlock
Jika lesson memiliki assessment, assessment baru dapat dibuka setelah watch progress mencapai minimal 95%.

### 8.9 Assessment Completion
Student mengerjakan assessment, sistem autosave jawaban, lalu submit manual atau otomatis saat waktu habis.

### 8.10 Lesson Completion
Sistem menandai lesson complete jika syarat lesson terpenuhi.

### 8.11 Continue Learning
Lesson berikutnya akan terbuka setelah lesson sebelumnya complete.

### 8.12 Assignment Submission
Jika tier dan flow pembelajaran mengharuskan, student submit assignment video.

### 8.13 Review Outcome
Student menerima status assignment: review, approved, atau rejected beserta feedback jika ada.

### 8.14 Certificate Access
Jika memenuhi syarat dan admin telah generate certificate, student dapat mengakses certificate.

---

## 9. Admin Journey

### 9.1 Login
Admin login ke sistem.

### 9.2 Dashboard
Admin melihat dashboard operasional yang menampilkan statistik utama dan shortcut ke modul manajemen.

### 9.3 Manage Access Tiers
Admin mengelola tier akses untuk user dan konten.

### 9.4 Manage Modules and Lessons
Admin membuat dan memelihara struktur pembelajaran.

### 9.5 Manage Assessments
Admin membuat assessment, page, question, dan options.

### 9.6 Monitor Progress
Admin melihat progress student, assessment summary, lesson completion, dan session duration.

### 9.7 Review Assignments
Admin membuka assignment submission, mereview video, lalu approve atau reject dengan feedback.

### 9.8 Generate Certificates
Admin membuat atau recreate certificate untuk student yang memenuhi syarat.

### 9.9 Manage Email Templates
Admin mengisi subject/body template, mengaktifkan notification, dan mengirim test email.

### 9.10 Monitor Logs
Admin melihat email logs dan status pengiriman.

---

## 10. Functional Requirements

## 10.1 Authentication
- Sistem harus mendukung login dan logout untuk Admin dan Student.
- Sistem harus mendukung forgot password dan reset password.
- Sistem harus membedakan routing setelah login berdasarkan role.

## 10.2 Role Authorization
- Sistem harus membatasi area admin hanya untuk Admin.
- Sistem harus membatasi area student hanya untuk Student.
- Sistem harus membatasi akses data berdasarkan ownership untuk Student.

## 10.3 Tier Authorization
- Sistem harus memeriksa membership tier sebelum menampilkan atau mengizinkan akses content.
- Sistem harus menerapkan tier ke modules, lessons, ebooks, dan courses.

## 10.4 Student Profile
- Sistem harus menyediakan halaman profile student.
- Sistem harus memvalidasi field wajib.
- Admin harus bisa memperbarui profile student.
- Student harus bisa memperbarui profile sendiri.

## 10.5 Module & Lesson Management
- Admin harus dapat membuat, mengedit, dan menghapus module dan lesson.
- Lesson harus dapat dihubungkan ke module.
- Lesson dapat memiliki workbook, video, audio, content, dan assessment secara opsional.

## 10.6 Lesson Access Rules
- Lesson berikutnya tidak boleh diakses sebelum lesson sebelumnya selesai.
- Jika ada workbook, workbook harus didownload sebelum akses komponen lain.
- Jika ada assessment, assessment hanya terbuka setelah video ditonton minimal 95%.
- Jika lesson tidak memiliki assessment, lesson dapat dianggap selesai berdasarkan rule video completion.

## 10.7 Progress Tracking
- Sistem harus menyimpan watch progress per user per lesson.
- Sistem harus menyimpan workbook download status.
- Sistem harus menyimpan lesson completion status.
- Sistem harus menyimpan assessment progress.
- Sistem harus menyimpan total duration akses user.

## 10.8 Assessment Builder
- Admin harus dapat membuat assessment.
- Assessment harus mendukung pages.
- Setiap page harus dapat memiliki banyak question.
- Question harus memiliki type.
- Question options harus dapat dikelola untuk type yang memerlukannya.
- Assessment harus memiliki duration_minutes.

## 10.9 Assessment Player
- Student harus dapat memulai assessment.
- Sistem harus membuat assessment attempt.
- Sistem harus menghitung countdown berdasarkan duration_minutes.
- Sistem harus autosave jawaban.
- Sistem harus menampilkan progress pengerjaan.
- Sistem harus auto-submit saat waktu habis.
- Sistem harus menghitung score.
- Sistem harus menampilkan result.

## 10.10 Assignment Submission
- Student harus dapat submit assignment jika tier mengizinkan.
- Assignment harus memiliki assignment_type.
- Assignment harus memiliki status.
- Admin harus dapat approve atau reject assignment.
- Feedback harus tersedia saat assignment rejected.

## 10.11 Certificate
- Admin harus dapat generate certificate.
- Admin harus dapat recreate certificate.
- Certificate harus menyimpan file path dan version.
- Student harus dapat mengakses certificate miliknya jika diizinkan oleh flow bisnis.

## 10.12 Email Notification
- Sistem harus mendukung 6 notification type final:
  - module_completion
  - assignment_review
  - assignment_approved
  - assignment_rejected
  - certificate_created
  - signup
- Setiap template harus mendukung admin/user subject dan body.
- Setiap template harus dapat di-enable atau di-disable.
- Setiap template harus memiliki send test manual.
- Setiap email yang dikirim harus dapat dicatat.

## 10.13 Session Tracking
- Sistem harus membuat session saat login.
- Sistem harus memperbarui session selama user aktif.
- Sistem harus menutup session saat logout atau timeout.
- Sistem harus menghitung cumulative access duration.
- Timer akses harus melanjutkan dari total terakhir.

## 10.14 Media Handling
- Lesson video harus mendukung integrasi dengan video storage/streaming eksternal.
- Sistem harus menyimpan reference video aktif.
- Sistem harus mendukung replace video aktif pada lesson.
- Resource dan media harus diproteksi dari akses yang tidak sah.

---

## 11. Non-Functional Requirements

## 11.1 Usability
- UI harus jelas, konsisten, dan mudah dipahami.
- Admin dashboard harus efisien untuk pekerjaan operasional.
- Student flow harus sederhana dan tidak membingungkan.
- Form, tabel, modal, tabs, dialog, dan panel harus konsisten secara visual.

## 11.2 Performance
- Halaman utama harus memuat dengan waktu yang wajar.
- Email sebaiknya dikirim melalui queue untuk mengurangi blocking request.
- Upload dan processing media harus dijaga agar tidak mengganggu pengalaman utama.
- Progress update dan autosave harus cukup ringan untuk dipakai saat belajar.

## 11.3 Reliability
- Data progress tidak boleh mudah hilang.
- Assessment answer harus tetap tersimpan walau terjadi refresh atau interupsi kecil.
- Session duration harus akurat secara operasional.
- Email logs harus dapat menunjukkan status kirim.

## 11.4 Security
- Access control berbasis role dan ownership wajib diterapkan.
- Content access harus dilindungi oleh tier dan business rules.
- Media sensitif tidak boleh dapat diakses bebas tanpa otorisasi.
- Data user harus diproses secara aman.
- Authentication flow harus menggunakan praktik Laravel yang aman.

## 11.5 Maintainability
- Sistem harus dibangun dalam satu project Laravel yang konsisten.
- Eloquent dan migration Laravel menjadi fondasi data utama.
- Struktur fitur harus mendukung pengembangan bertahap.
- Template email harus editable tanpa mengubah source code.

## 11.6 Scalability
- Sistem harus siap untuk penambahan konten, student, dan template email.
- Assessment structure harus mendukung penambahan page dan question.
- Assignment type bersifat dinamis agar tidak terkunci ke dua kolom statis.

## 11.7 Compatibility
- Aplikasi harus berjalan baik di web browser modern.
- Student UI dan admin UI harus tetap usable di perangkat mobile web.
- Frontend harus konsisten dengan stack React + Inertia + Tailwind + shadcn/ui.

---

## 12. Constraints

- Project menggunakan satu project Laravel, bukan arsitektur multi-repo untuk app dan schema.
- ORM utama adalah Eloquent.
- Database utama adalah PostgreSQL.
- Frontend menggunakan React + Inertia, bukan TALL stack.
- UI menggunakan shadcn/ui asli, bukan imitasi manual.
- Source of truth schema database adalah Laravel migration, bukan `db::sync`.
- Role phase 1 hanya Admin dan Student.
- Mobile app native tidak masuk phase 1.
- Tidak semua fitur PRD lama diambil penuh; keputusan revisi terbaru adalah sumber kebenaran utama.
- Email trigger final phase 1 dibatasi pada 6 sub menu yang sudah disepakati.
- Assessment menggunakan page-based structure.
- Course tetap dipertahankan sebagai entitas konten independen dari struktur utama module -> lesson -> assessment.

---

## 13. Assumptions

### 13.1 Production Readiness Assumption
Sistem ditujukan untuk kebutuhan internal YogaFX yang siap dipakai student sungguhan, bukan hanya prototipe tugas.

### 13.2 Web-First Assumption
Phase 1 berfokus pada web application terlebih dahulu.

### 13.3 Role Simplicity Assumption
Tidak ada role formal lain selain Admin dan Student pada phase 1.

### 13.4 Multiple Attempt Readiness Assumption
Assessment dirancang siap untuk mendukung multiple attempts, walaupun detail rule jumlah attempt dapat ditentukan di level implementasi.

### 13.5 Email Template Assumption
Template email disimpan di database dan dapat dikelola oleh admin dari dashboard.

### 13.6 Send Test Assumption
Send test menggunakan template yang sama dengan mode otomatis, tetapi merender sample data, bukan menunggu trigger asli.

### 13.7 Certificate Access Assumption
Certificate hanya dapat diakses student yang memang berhak berdasarkan flow dan tier yang berlaku.

### 13.8 Media Protection Assumption
Media pembelajaran dan file penting tidak dianggap public asset biasa dan harus mengikuti kontrol akses sistem.

### 13.9 Course Assumption
Courses diperlakukan sebagai konten independen yang tidak secara langsung menggantikan struktur utama module -> lesson -> assessment.

### 13.10 Queue Readiness Assumption
Walaupun implementasi awal bisa bertahap, sistem harus disiapkan agar email dan background task dapat dijalankan dengan queue.