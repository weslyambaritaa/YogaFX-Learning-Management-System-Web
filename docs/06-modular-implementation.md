````md id="h0k9s2"
# 06-modular-implementation.md
# Modular Implementation Guide
# YogaFX LMS

## 1. Purpose of This Document

Dokumen ini menjadi source of truth implementasi untuk AI Coding Assistant selama proses pembangunan YogaFX LMS.

Dokumen ini menjelaskan:
- bagaimana sistem dibangun secara bertahap
- domain apa saja yang harus dikerjakan
- urutan dependency antar domain
- fase implementasi
- aturan implementasi yang wajib dipatuhi
- cara validasi hasil setiap domain
- kapan suatu domain dianggap selesai

Dokumen ini harus digunakan bersama:
- `01-prd.md`
- `02-user-flow.md`
- `03-business-rules.md`
- `04-erd.md`
- `05-information-architecture.md`

Jika terjadi konflik, maka:
1. keputusan terbaru yang sudah disepakati adalah sumber kebenaran utama
2. PRD adalah sumber fitur
3. User Flow adalah sumber proses
4. Business Rules adalah sumber validasi dan perilaku sistem
5. ERD adalah sumber struktur entity dan relationship
6. Information Architecture adalah sumber struktur halaman dan grouping fitur

---

## 2. Implementation Philosophy

## 2.1 Build Incrementally
Sistem harus dibangun sedikit demi sedikit, bukan sekaligus. Setiap domain harus selesai, tervalidasi, lalu baru melanjutkan ke domain berikutnya.

## 2.2 Build by Domain
Pembangunan harus mengikuti domain bisnis, bukan hanya mengikuti daftar tabel atau halaman.

Contoh:
- jangan membangun seluruh halaman dulu tanpa logika domain
- jangan membangun seluruh migration dulu tanpa urutan dependency yang jelas

## 2.3 Respect Dependency Order
Domain yang menjadi fondasi harus selesai lebih dahulu sebelum domain yang bergantung kepadanya dikerjakan.

Contoh:
- Assessment tidak boleh dibangun sebelum Lesson dan User siap
- Certificate tidak boleh dibangun sebelum Assignment dan eligibility logic jelas
- Email trigger tidak boleh dibangun sebelum domain bisnis pemicunya stabil

## 2.4 Validate Before Continuing
Setiap domain harus diuji dan divalidasi sebelum lanjut ke domain berikutnya.

## 2.5 Follow Source of Truth
AI harus mengikuti dokumen requirement, bukan membuat asumsi liar.

Wajib mengikuti:
- PRD
- User Flow
- Business Rules
- ERD
- Information Architecture
- Design System

## 2.6 Avoid Premature Complexity
AI tidak boleh membangun fitur yang belum menjadi kebutuhan phase 1.

## 2.7 Build End-to-End per Domain
Jika memungkinkan, domain dibangun sampai usable secara minimal, bukan hanya separuh.

Contoh:
- jangan hanya membuat entity Assessment tanpa player atau result sama sekali
- jangan hanya membuat Email Template tanpa kemampuan save dan send test

## 2.8 Preserve Maintainability
Setiap implementasi harus:
- readable
- maintainable
- modular
- konsisten
- mudah dilanjutkan di fase berikutnya

---

## 3. Domain Breakdown

Berikut domain utama sistem YogaFX LMS.

---

## 3.1 Authentication

### Tujuan
Menangani login, logout, session entry, dan pembatasan area berdasarkan role.

### Cakupan
- login
- logout
- forgot password
- reset password
- session start
- role-based redirection

---

## 3.2 User Management

### Tujuan
Mengelola data user dan akses dasar sistem.

### Cakupan
- user data
- admin vs student visibility
- student listing
- student detail admin

---

## 3.3 Student Profile

### Tujuan
Mengelola data profile student yang dibutuhkan untuk pembelajaran dan certificate.

### Cakupan
- profile completion
- edit profile student
- edit profile oleh admin
- validation profile

---

## 3.4 Tier Management

### Tujuan
Mengelola membership tier dan pembatasan akses konten.

### Cakupan
- access tier CRUD
- assign tier ke user
- apply tier ke content

---

## 3.5 Learning Content

### Tujuan
Membangun backbone utama pembelajaran.

### Cakupan
- modules
- lessons
- workbook
- video
- audio
- content
- ebooks
- courses

---

## 3.6 Learning Progress

### Tujuan
Menyimpan progres belajar student dan mengontrol flow pembelajaran.

### Cakupan
- lesson progress
- watch progress
- workbook downloaded
- lesson completion
- sequential unlocking
- cumulative access duration
- session tracking

---

## 3.7 Assessment

### Tujuan
Mengelola evaluasi belajar dari sisi builder dan player.

### Cakupan
- assessment
- pages
- questions
- options
- attempts
- answers
- score
- result
- autosave
- timer
- auto submit

---

## 3.8 Assignment

### Tujuan
Mengelola submission video assignment dan review workflow.

### Cakupan
- assignment submission
- assignment type
- review
- approve
- reject
- feedback

---

## 3.9 Certificate

### Tujuan
Mengelola certificate yang menjadi output akhir student.

### Cakupan
- generate certificate
- recreate certificate
- versioning
- certificate access

---

## 3.10 Email Notification

### Tujuan
Mengelola template email, automatic trigger, dan send test manual.

### Cakupan
- email templates
- notification settings
- send test
- email logs
- trigger integration

---

## 3.11 Reporting & Monitoring

### Tujuan
Memberi visibilitas operasional kepada admin.

### Cakupan
- progress overview
- assessment summary
- assignment status summary
- email log visibility
- certificate visibility

---

## 4. Module Dependency Order

Urutan dependency domain yang disarankan:

```text
Authentication
↓
User Management
↓
Student Profile
↓
Tier Management
↓
Learning Content
↓
Learning Progress
↓
Assessment
↓
Assignment
↓
Certificate
↓
Email Notification
↓
Reporting & Monitoring
````

### Penjelasan Dependency

#### Authentication

Semua domain lain membutuhkan user yang bisa login.

#### User Management

Domain lain membutuhkan identitas user yang stabil.

#### Student Profile

Diperlukan sebelum student journey penuh dijalankan.

#### Tier Management

Diperlukan sebelum content filtering dan access control bisa diterapkan dengan benar.

#### Learning Content

Module, lesson, ebook, dan course harus ada sebelum progress dan assessment bisa meaningful.

#### Learning Progress

Progress logic bergantung pada Lesson dan User.

#### Assessment

Assessment bergantung pada Lesson, User, dan progress rule.

#### Assignment

Assignment bergantung pada user, tier, dan learning completion context.

#### Certificate

Certificate bergantung pada student, assignment outcome, dan eligibility.

#### Email Notification

Template boleh disiapkan lebih awal, tetapi trigger hanya meaningful setelah domain bisnis pemicunya stabil.

#### Reporting & Monitoring

Reporting baru layak dibangun setelah domain inti sudah menghasilkan data.

---

## 5. Implementation Phases

## Phase 1 — Foundation & Access

### Objective

Menyiapkan fondasi sistem agar user bisa masuk dan dipisahkan berdasarkan role.

### Scope

* Authentication
* basic user management
* role-aware access
* session entry baseline

### Dependencies

* none

### Deliverables

* login/logout berjalan
* admin dan student terpisah
* dashboard placeholder tersedia
* session awal bisa dimulai

### Validation Criteria

* Admin dan Student bisa login
* Redirect role benar
* Unauthorized access diblok
* Session aktif tercatat secara logis

---

## Phase 2 — User & Profile Foundation

### Objective

Menyediakan identitas user yang lengkap, terutama untuk student.

### Scope

* student profile
* admin view student
* edit profile
* validation profile

### Dependencies

* Phase 1

### Deliverables

* profile student dapat diisi dan disimpan
* admin bisa melihat data student
* validasi profile berjalan

### Validation Criteria

* Field wajib tervalidasi
* Student bisa update profile sendiri
* Admin bisa melihat dan mengubah profile student
* Student onboarding tidak buntu

---

## Phase 3 — Tier Management

### Objective

Membangun fondasi pembatasan akses berbasis membership tier.

### Scope

* access tier CRUD
* relasi tier ke user
* relasi tier ke content

### Dependencies

* Phase 1
* Phase 2

### Deliverables

* access tier dapat dikelola
* user punya tier
* content bisa dikaitkan dengan tier

### Validation Criteria

* tier dapat dibuat dan diedit
* user dapat memiliki tier
* sistem siap menerapkan tier ke module/lesson/ebook/course

---

## Phase 4 — Learning Content Core

### Objective

Membangun struktur konten utama sistem.

### Scope

* modules
* lessons
* ebooks
* courses
* relasi dan ordering dasar

### Dependencies

* Phase 3

### Deliverables

* module CRUD
* lesson CRUD
* ebook CRUD
* course CRUD
* relasi module -> lesson berjalan

### Validation Criteria

* admin bisa membuat module
* admin bisa membuat lesson dan menempatkannya ke module
* lesson bisa memiliki content/media reference
* ebook dan course bisa tampil sesuai tier

---

## Phase 5 — Learning Progress & Session Tracking

### Objective

Membuat flow belajar student menjadi hidup dan terkontrol.

### Scope

* lesson progress
* workbook tracking
* watch progress
* lesson completion
* next lesson unlock
* user sessions
* cumulative access duration

### Dependencies

* Phase 4

### Deliverables

* progress lesson tersimpan
* workbook status tersimpan
* watch progress tersimpan
* lesson completion logic berjalan
* session duration bertambah

### Validation Criteria

* student tidak bisa melompati lesson
* workbook rule berjalan
* assessment unlock prerequisite bisa dibaca dari progress
* total access duration terakumulasi

---

## Phase 6 — Assessment Domain

### Objective

Membangun sistem assessment end-to-end dari builder sampai result.

### Scope

* assessments
* assessment pages
* questions
* options
* attempts
* answers
* autosave
* timer
* result
* assessment progress

### Dependencies

* Phase 4
* Phase 5

### Deliverables

* admin bisa membuat assessment
* student bisa mengerjakan assessment
* score bisa dihitung
* result tersedia

### Validation Criteria

* assessment builder usable
* only unlocked assessments can start
* autosave berjalan
* timer berjalan
* auto submit berjalan
* result tersimpan

---

## Phase 7 — Assignment Domain

### Objective

Membangun submission dan review workflow untuk assignment video.

### Scope

* assignment submission
* assignment status
* assignment review
* approve/reject
* feedback

### Dependencies

* Phase 5
* Phase 6 jika assignment bergantung pada completion tertentu

### Deliverables

* student bisa submit assignment
* admin bisa mereview assignment
* approve/reject berjalan
* feedback tersimpan

### Validation Criteria

* hanya tier yang berhak dapat submit
* rejected submission punya feedback
* approved/rejected state terlihat jelas di student side

---

## Phase 8 — Certificate Domain

### Objective

Membangun certificate lifecycle.

### Scope

* generate certificate
* recreate certificate
* versioning
* student access
* admin management

### Dependencies

* Phase 7
* eligibility rules dari business rules

### Deliverables

* admin bisa generate certificate
* certificate dapat diakses student
* certificate dapat direcreate

### Validation Criteria

* certificate hanya muncul untuk student yang eligible
* recreate tidak merusak ownership
* student hanya melihat certificate miliknya

---

## Phase 9 — Email Notification Domain

### Objective

Membangun pengelolaan template email dan menghubungkannya ke trigger bisnis.

### Scope

* email templates
* 6 notification type
* save template
* send test
* email logs
* trigger integration

### Dependencies

* Phase 4 sampai Phase 8, tergantung trigger

### Deliverables

* email template dapat disimpan
* send test berjalan
* automatic trigger berjalan
* email logs tersedia

### Validation Criteria

* template bisa diedit dan disimpan
* placeholder dirender dengan benar
* automatic trigger hanya jalan jika enabled
* log email tercatat

---

## Phase 10 — Reporting & Monitoring

### Objective

Menyediakan visibilitas operasional untuk admin.

### Scope

* progress summary
* assessment summary
* assignment summary
* certificate visibility
* email log visibility

### Dependencies

* seluruh phase sebelumnya

### Deliverables

* dashboard admin lebih kaya informasi
* admin bisa memantau area kritis sistem

### Validation Criteria

* data ringkasan sesuai dengan data riil
* admin dapat melihat kondisi operasional tanpa harus membuka setiap detail satu per satu

---

## 6. Coding Guidelines For AI

AI Coding Assistant harus mematuhi aturan berikut.

## 6.1 Respect Product Scope

* Jangan membuat fitur di luar PRD.
* Jangan menambah modul baru tanpa dasar requirement.
* Jangan menambah role baru tanpa persetujuan requirement.

## 6.2 Respect Data Model

* Jangan membuat entity di luar ERD tanpa alasan yang sangat jelas dan terkonfirmasi.
* Jangan mengubah relasi utama tanpa melihat Business Rules dan User Flow.

## 6.3 Respect Business Rules

* Jangan melanggar tier access rules.
* Jangan melanggar lesson locking rules.
* Jangan melanggar workbook requirement.
* Jangan melanggar assessment unlock 95%.
* Jangan melanggar certificate eligibility.
* Jangan melanggar email trigger rules.

## 6.4 Respect Information Architecture

* Jangan membuat halaman yang tidak ada dalam struktur IA tanpa alasan yang kuat.
* Jangan mencampur mental model admin dan student.
* Student side harus content-first.
* Admin side harus operations-first.

## 6.5 Respect Design System

* Gunakan React + Inertia + Tailwind + shadcn/ui.
* Gunakan reusable component.
* Student side harus premium, calm, clean.
* Admin side harus efficient, structured, professional.
* Jangan membangun student side seperti portal akademik tradisional.

## 6.6 Maintain Readability

* Prioritaskan kode yang jelas dibaca.
* Prioritaskan struktur yang mudah dirawat.
* Jangan membuat logic besar dan rapuh dalam satu tempat.

## 6.7 Implement per Domain

* Selesaikan satu domain sebelum pindah ke domain lain.
* Jangan membangun separuh domain di banyak tempat sekaligus.

## 6.8 Validate Before Expanding

* Setiap domain harus dianggap belum selesai sampai validasi lulus.
* Jangan meneruskan domain baru hanya karena UI sudah tampil.

## 6.9 Avoid Premature Optimization

* Fokus dulu pada correctness.
* Optimasi performa dilakukan setelah flow benar dan stabil.

## 6.10 Preserve Reusability

* Gunakan pola reusable untuk form, table, card, modal, sheet, badge, status, dan action bar.
* Jangan copy-paste UI logic tanpa abstraksi saat pola sudah berulang.

---

## 7. Validation Checklist

## 7.1 Authentication

* [ ] User dapat login
* [ ] User dapat logout
* [ ] Redirect role benar
* [ ] Unauthorized access diblok
* [ ] Session dasar dimulai saat login

## 7.2 User Management

* [ ] Admin dapat melihat daftar student
* [ ] Admin dapat membuka detail student
* [ ] Student hanya bisa melihat data miliknya sendiri

## 7.3 Student Profile

* [ ] Student dapat mengisi profile
* [ ] Validasi field wajib berjalan
* [ ] Admin dapat melihat / memperbarui profile student
* [ ] Profile flow tidak memblokir sistem secara tidak perlu

## 7.4 Tier Management

* [ ] Tier dapat dibuat
* [ ] Tier dapat diedit
* [ ] Tier dapat dihubungkan ke user
* [ ] Tier dapat diterapkan ke content

## 7.5 Learning Content

* [ ] Module CRUD berjalan
* [ ] Lesson CRUD berjalan
* [ ] Lesson terhubung ke Module
* [ ] Ebook CRUD berjalan
* [ ] Course CRUD berjalan
* [ ] Content dapat dibatasi berdasarkan tier

## 7.6 Learning Progress

* [ ] Watch progress tersimpan
* [ ] Workbook downloaded state tersimpan
* [ ] Lesson completion logic berjalan
* [ ] Next lesson unlock berjalan
* [ ] Cumulative access duration berjalan

## 7.7 Assessment

* [ ] Assessment CRUD berjalan
* [ ] Assessment pages berjalan
* [ ] Questions berjalan
* [ ] Options berjalan
* [ ] Assessment player berjalan
* [ ] Timer berjalan
* [ ] Autosave berjalan
* [ ] Auto submit berjalan
* [ ] Score tersimpan
* [ ] Result tersedia

## 7.8 Assignment

* [ ] Student dapat submit assignment
* [ ] Tier restriction berjalan
* [ ] Admin dapat review assignment
* [ ] Approve berjalan
* [ ] Reject berjalan
* [ ] Feedback wajib saat reject

## 7.9 Certificate

* [ ] Admin dapat generate certificate
* [ ] Admin dapat recreate certificate
* [ ] Student hanya melihat certificate miliknya
* [ ] Eligibility rule berjalan

## 7.10 Email Notification

* [ ] Template dapat disimpan
* [ ] Template dapat diedit
* [ ] Send test berjalan
* [ ] Trigger otomatis berjalan
* [ ] Email log tercatat
* [ ] Placeholder dirender dengan benar

## 7.11 Reporting & Monitoring

* [ ] Admin dapat melihat progress summary
* [ ] Admin dapat melihat assignment summary
* [ ] Admin dapat melihat certificate status
* [ ] Admin dapat melihat email log summary

---

## 8. Suggested Build Order

Setiap domain idealnya dikerjakan dalam urutan lapisan berikut:

### 1. Database Layer

Bangun entity dan relasi yang dibutuhkan domain.

### 2. Backend Layer

Bangun logic utama, validasi, authorization, dan rule penerapan domain.

### 3. Frontend Layer

Bangun halaman, state, action UI, dan interaksi user.

### 4. Integration Layer

Hubungkan domain dengan domain lain atau external trigger bila dibutuhkan.

---

## Suggested Build Order per Domain

## 8.1 Authentication

1. Database layer (jika ada extension user/session)
2. Backend auth logic
3. Frontend auth pages
4. Session integration

## 8.2 User Management

1. Database layer
2. Backend query and visibility rules
3. Frontend student/admin pages
4. Role-based integration

## 8.3 Student Profile

1. Database layer
2. Validation logic
3. Profile UI
4. Onboarding integration

## 8.4 Tier Management

1. Database layer
2. Backend CRUD
3. Admin management UI
4. Access control integration

## 8.5 Learning Content

1. Database layer
2. Backend CRUD and relationship logic
3. Admin content UI
4. Student content visibility integration

## 8.6 Learning Progress

1. Database layer
2. Backend progress logic
3. Student progress UI
4. Lesson flow integration

## 8.7 Assessment

1. Database layer
2. Backend builder and player rules
3. Admin builder UI
4. Student player UI
5. Progress integration

## 8.8 Assignment

1. Database layer
2. Backend submission and review logic
3. Student submission UI
4. Admin review UI
5. Email integration later

## 8.9 Certificate

1. Database layer
2. Backend generation logic
3. Admin management UI
4. Student access UI
5. Email integration later

## 8.10 Email Notification

1. Database layer
2. Template management logic
3. Admin email settings UI
4. Send test integration
5. Trigger wiring

## 8.11 Reporting & Monitoring

1. Data aggregation logic
2. Backend visibility rules
3. Admin dashboard/report UI
4. Cross-domain integration

---

## 9. Risks & Common Mistakes

## 9.1 Building Assessment Too Early

Assessment adalah domain kompleks. Jika dibangun sebelum Lesson, Progress, dan Tier logic stabil, revisi besar hampir pasti terjadi.

## 9.2 Ignoring Business Rules During UI Build

Jika UI dibangun duluan tanpa mengunci business rules seperti:

* workbook requirement
* 95% watch progress
* lesson locking
  maka banyak halaman harus direvisi.

## 9.3 Mixing Student and Admin Mental Models

Student side dan admin side punya tujuan berbeda. Mencampur pendekatan visual atau struktur navigasi keduanya akan merusak UX.

## 9.4 Building Email Triggers Too Soon

Trigger email jangan dihubungkan terlalu awal. Template management boleh dibuat lebih dulu, tetapi trigger sebaiknya baru dihubungkan setelah domain pemicunya stabil.

## 9.5 Building Reports Before Data Flows Stabilize

Report dan dashboard ringkasan harus datang setelah data inti sudah benar. Jika dikerjakan terlalu awal, hasilnya cepat usang dan perlu refactor.

## 9.6 Overbuilding Assignment Workflow

Jangan menambah reviewer hierarchy, audit trail kompleks, atau approval workflow tambahan sebelum phase 1 selesai.

## 9.7 Treating Course as Main Learning Backbone

Struktur utama pembelajaran tetap:
Module -> Lesson -> Assessment

Course adalah content independen. Jika ini dicampur terlalu dini, IA dan business rule bisa kacau.

## 9.8 Ignoring Cumulative Session Logic

Total access duration harus dianggap domain serius. Jika ditunda terlalu lama, integrasi dashboard dan monitoring akan membingungkan.

## 9.9 Creating Extra Entities Without Source

AI tidak boleh membuat entity baru hanya karena “terasa berguna” tanpa dukungan dari PRD, Flow, Business Rules, atau ERD.

## 9.10 Solving by Technical Preference Instead of Product Need

AI tidak boleh menyimpang ke solusi yang “secara teknis keren” tapi tidak dibutuhkan oleh produk.

---

## 10. Definition of Done

Suatu domain dianggap selesai jika seluruh poin berikut terpenuhi.

## 10.1 Requirement Alignment

* sesuai PRD
* sesuai User Flow
* sesuai Business Rules
* sesuai ERD
* sesuai Information Architecture

## 10.2 Functional Completion

* use case utama domain berjalan
* aksi utama domain dapat dipakai end-to-end
* state penting domain tersimpan dan terlihat

## 10.3 Validation Completion

* validasi data berjalan
* business rule domain berjalan
* error state ditangani
* unauthorized access diblok

## 10.4 UX Completion

* halaman utama domain tersedia
* UI cukup usable
* alur tidak buntu
* user dapat menyelesaikan tugas utamanya

## 10.5 Cross-Domain Readiness

* domain siap dipakai sebagai dependency untuk domain berikutnya
* tidak ada blocker besar yang belum diselesaikan
* data yang dibutuhkan domain berikutnya sudah tersedia

## 10.6 Maintainability Check

* implementasi cukup rapi
* tidak ada shortcut berbahaya yang akan menyulitkan phase berikutnya
* reusable pattern mulai terbentuk bila dibutuhkan

---

## 11. Final Implementation Strategy Summary

Urutan implementasi utama YogaFX LMS yang disarankan:

```text
Foundation & Access
↓
User & Profile
↓
Tier Management
↓
Learning Content
↓
Learning Progress & Sessions
↓
Assessment
↓
Assignment
↓
Certificate
↓
Email Notification
↓
Reporting & Monitoring
```

### Strategic Rule

* Bangun yang paling mendasar dulu
* Bangun domain satu per satu
* Validasi setiap domain sebelum lanjut
* Jangan lompat ke fitur puncak sebelum fondasi stabil
* Jadikan requirement documents sebagai sumber kebenaran utama

Dokumen ini menjadi panduan implementasi bertahap agar AI Coding Assistant dapat membangun YogaFX LMS secara konsisten, terarah, dan tidak menyimpang dari kebutuhan produk.
