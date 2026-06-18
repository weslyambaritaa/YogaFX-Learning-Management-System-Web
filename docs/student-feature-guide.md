# Student Feature Guide
# YogaFX LMS

## 1. Purpose

Dokumen ini merangkum **seluruh fitur aktif yang relevan untuk student** di YogaFX LMS saat ini, dengan fokus pada:
- apa saja yang bisa diakses student
- data apa saja yang datang dari admin
- bagaimana admin menambahkan atau mengubah data hingga terlihat di student side
- batas mana yang sudah aktif dan mana yang masih out of scope

Dokumen ini disusun untuk membantu implementasi student web dan mobile.

---

## 2. Student Scope Aktif Saat Ini

Fitur student yang aktif saat ini:
- login
- logout
- forgot password
- reset password
- profile completion gate
- edit profile sendiri
- dashboard student
- akses modules sesuai tier
- akses module detail sesuai tier
- akses lesson detail sesuai tier
- akses ebook list sesuai tier
- preview ebook sebelum download
- akses course list sesuai tier
- akses dialog:
  - Full Standing Dialog
  - Full Floor Dialog

Fitur student yang **belum aktif penuh**:
- student assignment submission page sebagai halaman penuh terpisah
- student certificate page terpisah
- public signup
- lesson watch progress automation penuh
- workbook gating
- sequential locking sebagai source of truth dokumentasi aktif
- assessment domain penuh

Catatan:
- beberapa logic backend untuk progress, assignment, certificate, dan assessment sudah ada untuk kebutuhan integrasi mobile atau flow lanjutan
- tetapi secara product scope aktif, student side tetap harus dianggap fokus pada dashboard, modules, lessons, ebooks, courses, dialogs, dan profile

---

## 3. Student Navigation Aktif

Primary student navigation:
- Dashboard
- Modules
- Ebooks
- Courses
- Profile

Instant access:
- Full Standing Dialog
- Full Floor Dialog

---

## 4. Student Features

### 4.1 Authentication

Student dapat:
- login
- logout
- forgot password
- reset password

Aturan:
- setelah login, jika profile belum lengkap, student dipaksa ke halaman edit profile
- jika profile sudah lengkap, student masuk ke student dashboard

Admin yang berpengaruh:
- admin tidak mengelola login student secara langsung dari student side
- tetapi admin dapat membuat atau mengaktifkan akun student dari sisi data/admin flow yang sudah ada di sistem

---

### 4.2 Student Profile

Student dapat:
- melihat profile sendiri
- mengedit profile sendiri
- mengganti password

Field profile yang aktif:
- first name
- last name
- email
- whatsapp
- preferred certificate picture
- profile photo
- instagram
- country
- birth date
- gender
- practicing yoga for
- yoga sequence experience
- hours per week
- current fitness level
- flexibility rating
- motivation
- why yogafx
- how did you find us

Admin yang berpengaruh:
- admin dapat mengedit profile student dari area Student Progress
- jika admin mengubah data profile student, perubahan itu langsung memengaruhi data yang dibaca student side

Masuk ke student side:
- data ini muncul di profile student
- sebagian context muncul di dashboard, misalnya nama student dan tier

---

### 4.3 Access Tier

Student tidak mengelola tier sendiri.

Student hanya menerima hasil dari admin:
- student memiliki tepat satu `access_tier_id`
- tier aktif:
  - `starter_kit`
  - `online`
  - `master_class`

Admin yang berpengaruh:
- admin membuat tier
- admin mengedit tier
- admin assign tier ke student
- admin dapat menonaktifkan tier dengan `is_active`

Dampak langsung ke student:
- menentukan module yang terlihat
- menentukan lesson yang boleh dibuka
- menentukan ebook yang terlihat
- menentukan course yang terlihat
- memengaruhi eligibility certificate

Rule penting:
- `Module`, `Lesson`, dan `Ebook` memakai multi-tier access
- `Course` memakai single-tier access

---

### 4.4 Student Dashboard / Home

Student dashboard aktif saat ini adalah halaman home student.

Data yang tampil di dashboard saat ini bisa mencakup:
- student context
- access tier
- total access time summary
- continue learning
- progress summary
- next step
- sequential awareness
- available modules section
- assignment milestone
- certificate milestone
- ebook resources section
- home experience
- quick access ke dialog

Admin yang berpengaruh:
- admin tidak mengedit dashboard secara langsung
- dashboard membaca hasil dari:
  - tier assignment admin
  - module/lesson/ebook/course yang dibuat admin
  - dialog content yang diisi admin
  - certificate yang digenerate admin
  - assignment review/status yang diubah admin

Masuk ke student side:
- jika admin menambah module, lesson, ebook, course, atau dialog yang eligible ke tier student, dashboard dapat ikut berubah
- jika admin mengubah status assignment atau certificate, milestone di dashboard dapat ikut berubah

---

### 4.5 Dialog

Dialog student aktif:
- Full Standing Dialog
- Full Floor Dialog

Student dapat:
- membuka Full Standing Dialog
- membuka Full Floor Dialog

Admin yang berpengaruh:
- admin mengisi dan mengubah konten dialog dari menu `Dialog`
- admin hanya mengelola dua key tetap:
  - `full_standing`
  - `full_floor`

Masuk ke student side:
- student melihat title dan content dialog
- jika admin belum mengisi konten, student tetap melihat fallback title dan content kosong

---

### 4.6 Modules

Student dapat:
- melihat daftar module sesuai tier
- melihat progress per module
- membuka module yang visible

Data module yang relevan ke student:
- title
- description
- url slug
- thumbnail
- sort order
- lesson count
- assignments count
- progress percentage
- status
- flags:
  - `certificate_enabled`
  - `ebook_enabled`
  - `video_lecturer_enabled`

Admin yang berpengaruh:
- admin membuat module
- admin mengedit module
- admin menghapus module jika diizinkan
- admin mengatur tier access module
- admin mengatur thumbnail
- admin mengatur flag pendukung seperti certificate, ebook, dan video lecturer pada module

Masuk ke student side:
- module muncul jika tier student termasuk dalam tier access module
- urutan tampil mengikuti `sort_order`
- thumbnail tampil sesuai upload admin

---

### 4.7 Module Detail

Student dapat:
- membuka satu module
- melihat daftar lesson dalam module
- melihat assignment terkait module
- melihat resource tambahan jika module mengaktifkan:
  - ebooks
  - video lecturers
  - certificates

Admin yang berpengaruh:
- lesson di dalam module dibuat admin
- assignment di dalam module dibuat admin
- ebook dan course yang eligible ditautkan lewat tier dan flag module
- certificate yang sudah dibuat admin akan ikut muncul jika context module mendukung

Masuk ke student side:
- module detail membaca:
  - lesson yang sesuai tier
  - assignment live
  - ebook yang sesuai tier jika `ebook_enabled = true`
  - video lecturer sesuai tier jika `video_lecturer_enabled = true`
  - certificate generated milik student jika `certificate_enabled = true`

---

### 4.8 Lessons

Student dapat:
- membuka lesson yang sesuai tier
- melihat video state
- melihat audio
- membaca content
- membuka workbook jika ada
- melihat assessment placeholder atau data assessment yang tersedia
- melihat navigasi lesson lain dalam module

Data lesson yang relevan:
- title
- thumbnail
- workbook
- video reference
- audio reference
- content
- sort order
- `assessment_id` nullable

Admin yang berpengaruh:
- admin membuat lesson
- admin mengedit lesson
- admin mengatur lesson masuk ke module mana
- admin mengatur tier access lesson
- admin mengatur workbook
- admin mengatur thumbnail
- admin mengatur video/audio/content

Masuk ke student side:
- lesson hanya tampil jika:
  - tier lesson mengizinkan student
  - tier module induk juga mengizinkan student

Catatan penting:
- dokumentasi aktif menyebut locking, gating, dan automation belum dianggap final source of truth penuh
- tetapi beberapa logic progress dan unlock sudah ada di backend untuk kebutuhan integrasi

---

### 4.9 Progress Lesson

Student side saat ini sudah punya progress data backend untuk lesson.

Data progress yang aktif di data model:
- `watch_progress`
- `is_workbook_downloaded`
- `workbook_downloaded_at`
- `video_completed_at`
- `is_done`
- `completed_at`

Admin yang berpengaruh:
- admin tidak menginput progress secara manual
- admin dapat melihat completed lessons dari Student Progress
- admin dapat reset progress lesson tertentu

Masuk ke student side:
- progress dipakai untuk status lesson
- progress dipakai untuk continue learning
- progress dipakai untuk progress summary
- progress dipakai untuk sequential awareness dan milestone tertentu

---

### 4.10 Ebooks

Student dapat:
- melihat daftar ebook sesuai tier
- membuka preview ebook
- download ebook secara eksplisit

Data ebook yang relevan:
- title
- file
- sort order

Admin yang berpengaruh:
- admin membuat ebook
- admin mengedit ebook
- admin upload file ebook
- admin mengatur tier access ebook

Masuk ke student side:
- ebook muncul jika tier student termasuk dalam tier access ebook
- preview tersedia jika file mendukung, terutama PDF
- download tetap menjadi aksi terpisah

---

### 4.11 Courses / Video Lecturer

Student dapat:
- melihat daftar course sesuai tier
- melihat title, description, thumbnail, dan video reference

Data course yang relevan:
- title
- url slug
- description
- thumbnail
- video
- `access_tier_id`

Admin yang berpengaruh:
- admin membuat course
- admin mengedit course
- admin upload thumbnail
- admin mengatur video reference
- admin mengatur single tier course

Masuk ke student side:
- course muncul jika `access_tier_id` course sama dengan tier student

---

### 4.12 Assignment

Secara dokumentasi produk aktif:
- student assignment page belum dianggap fitur aktif penuh di IA utama

Tetapi secara backend:
- sudah ada data assignment dan submission
- sudah ada detail assignment dan submit assignment pada mobile API

Admin yang berpengaruh:
- admin membuat assignment pada module
- admin menentukan assignment status live atau tidak
- admin me-review submission student
- admin mengubah:
  - `assignment_status`
  - `assignment_feedback`
- admin dapat menghapus video submission
- admin dapat mengirim email assignment

Masuk ke student side:
- assignment summary/milestone dapat muncul di dashboard
- assignment dapat muncul di module detail jika assignment live dan module visible
- status assignment student dipengaruhi review admin

Catatan:
- untuk product scope student side saat ini, assignment belum boleh diasumsikan sebagai halaman utama student yang stabil seperti Modules atau Ebooks

---

### 4.13 Certificate

Secara dokumentasi produk aktif:
- student certificate page belum dianggap fitur aktif penuh di IA utama

Tetapi secara backend:
- sudah ada data certificate student
- sudah ada list/detail/download certificate di mobile API
- dashboard/home bisa menampilkan certificate milestone

Admin yang berpengaruh:
- admin generate certificate
- admin recreate certificate
- admin download certificate
- admin delete certificate
- admin send graduation email

Masuk ke student side:
- certificate milestone dapat muncul di dashboard
- generated certificate dapat muncul sebagai downloadable resource untuk owner student
- eligibility dipengaruhi tier dan rule completion

Rule penting:
- `starter_kit` tidak eligible
- certificate record dimiliki student tertentu
- akses file harus lolos ownership check

---

### 4.14 Assessment

Assessment belum boleh dianggap domain student yang fully active menurut source of truth utama.

Yang aktif saat ini:
- `assessment_id` di lesson masih bisa ada sebagai placeholder
- ada implementation backend/API untuk assessment intro, start, answer, back, result

Yang belum boleh dianggap final scope aktif:
- assessment CRUD penuh sebagai domain final
- assessment builder final
- student assessment experience sebagai fitur yang sudah stabil sepenuhnya

Jika dipakai untuk mobile:
- harus dianggap sebagai area yang sudah ada implementasi teknis
- tetapi masih perlu hati-hati karena dokumentasi inti masih menandainya sebagai domain yang belum final penuh

---

## 5. Bagaimana Admin Menambahkan Sesuatu Sampai Terlihat di Student

### 5.1 Menambahkan Module

Admin melakukan:
1. create module
2. isi title, description, thumbnail
3. pilih tier access module

Agar terlihat di student:
- student harus punya tier yang cocok
- module harus termasuk dalam tier student

Student melihat hasilnya di:
- dashboard modules section
- modules index
- module detail jika dibuka

---

### 5.2 Menambahkan Lesson

Admin melakukan:
1. create lesson
2. pilih module induk
3. isi title, content, workbook, video, audio, thumbnail
4. pilih tier access lesson

Agar terlihat di student:
- tier lesson harus cocok dengan student
- tier module induk juga harus cocok dengan student

Student melihat hasilnya di:
- module detail
- lesson detail
- continue learning atau progress context jika lesson sudah diakses

---

### 5.3 Menambahkan Ebook

Admin melakukan:
1. create ebook
2. isi title
3. upload file ebook
4. pilih tier access ebook

Agar terlihat di student:
- tier ebook harus cocok dengan tier student

Student melihat hasilnya di:
- ebooks index
- ebook resources section di dashboard jika ada
- module detail jika module mengaktifkan resource context

---

### 5.4 Menambahkan Course

Admin melakukan:
1. create course
2. isi title, description, thumbnail, video
3. pilih single tier course

Agar terlihat di student:
- `access_tier_id` course harus sama dengan tier student

Student melihat hasilnya di:
- courses index
- module detail jika module mengaktifkan video lecturer context

---

### 5.5 Menambahkan Dialog

Admin melakukan:
1. buka menu Dialog
2. isi Full Standing Dialog
3. isi Full Floor Dialog
4. save

Agar terlihat di student:
- tidak tergantung tier khusus
- student tinggal membuka dialog terkait

Student melihat hasilnya di:
- Full Standing Dialog
- Full Floor Dialog
- ringkasan dialog di dashboard mobile API

---

### 5.6 Menambahkan Assignment

Admin melakukan:
1. buat assignment pada module
2. set assignment live
3. student submit video
4. admin review submission
5. admin set approved / rejected / under review

Agar terlihat di student:
- module assignment harus visible untuk student
- assignment harus live

Student melihat hasilnya di:
- module detail
- assignment summary
- assignment milestone

---

### 5.7 Menambahkan Certificate

Admin melakukan:
1. buka Student Progress
2. pilih student
3. buka Certificate
4. generate certificate

Agar terlihat di student:
- certificate dibuat untuk student tertentu
- student harus owner dari certificate

Student melihat hasilnya di:
- certificate milestone di dashboard
- certificate list/detail/download pada mobile API
- certificate resource pada module tertentu yang mendukung

---

## 6. Mapping Admin Domain ke Student Domain

### 6.1 Admin Input

Admin mengelola:
- access tiers
- modules
- lessons
- ebooks
- courses
- dialogs
- assignment review
- certificate generation
- student profile edits

### 6.2 Student Output

Student menerima hasilnya sebagai:
- akses konten sesuai tier
- urutan module/lesson
- file workbook/ebook
- dialog content
- assignment state
- certificate availability
- dashboard milestone dan summary

---

## 7. Mobile API Student Yang Sudah Tersedia

Endpoint mobile student yang relevan saat ini:
- `POST /api/mobile/v1/auth/login`
- `POST /api/mobile/v1/auth/logout`
- `POST /api/mobile/v1/auth/forgot-password`
- `POST /api/mobile/v1/auth/reset-password`
- `GET /api/mobile/v1/me`
- `GET /api/mobile/v1/dashboard`
- `GET /api/mobile/v1/dialogs`
- `GET /api/mobile/v1/dialogs/{key}`
- `GET /api/mobile/v1/modules`
- `GET /api/mobile/v1/modules/{module}`
- `GET /api/mobile/v1/lessons/{lesson}`
- `POST /api/mobile/v1/lessons/{lesson}/progress`
- `GET /api/mobile/v1/ebooks`
- `GET /api/mobile/v1/ebooks/{ebook}`
- `GET /api/mobile/v1/courses`
- `GET /api/mobile/v1/courses/{course}`
- `GET /api/mobile/v1/profile`
- `PATCH /api/mobile/v1/profile`
- `POST /api/mobile/v1/profile/change-password`

Endpoint mobile yang juga sudah ada secara teknis tetapi perlu kehati-hatian scope:
- assignment detail / submit
- certificate list / detail / download
- assessment flow

---

## 8. Yang Tidak Boleh Dianggap Sudah Aktif Penuh

Jangan menganggap hal berikut sudah final aktif hanya karena ada field atau sebagian logic:
- public signup
- student assignment page penuh sebagai area student utama
- student certificate page penuh sebagai area student utama
- assessment domain penuh
- sequential lesson locking sebagai source of truth final
- workbook gating final
- rich analytics student

Jika mobile akan mengangkat area-area ini menjadi fitur utama, dokumentasi aktif perlu disinkronkan lagi.

---

## 9. Rekomendasi Fokus Untuk Student Development

Jika fokus implementasi ada di student web atau mobile, urutan paling aman:
1. authentication
2. profile
3. dashboard/home
4. modules index
5. module detail
6. lesson detail
7. ebooks
8. courses
9. dialogs
10. assignment/certificate/assessment hanya jika benar-benar dibutuhkan oleh flow saat ini

---

## 10. Summary Singkat

Student saat ini pada dasarnya adalah pengalaman untuk:
- login
- melengkapi profile
- melihat home/dashboard
- membuka modules
- membuka lessons
- membuka ebooks
- membuka courses
- membuka dialog

Semua yang tampil di student side berasal dari admin melalui:
- tier assignment
- content CRUD
- dialog management
- assignment review
- certificate generation

Jadi pola utamanya adalah:
- admin menambahkan dan mengatur data
- student hanya melihat data yang lolos role, tier, ownership, dan visibility rule

