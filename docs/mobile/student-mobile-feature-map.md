# Student Mobile Feature Map
# YogaFX LMS

## 1. Purpose

Dokumen ini adalah panduan teknis untuk implementasi **student mobile app** di YogaFX LMS.

Fokus dokumen ini:
- screen apa saja yang perlu dibuat di mobile
- endpoint backend apa yang dipakai per screen
- data utama yang dibutuhkan per screen
- dependency dari admin ke student
- mana yang sudah aman dipakai sekarang
- mana yang masih perlu hati-hati karena belum final secara product scope

Dokumen ini melengkapi:
- [student-feature-guide.md](/d:/Documents/SEMESTER6/SPT/YogaFX-Learning-Management-System-Web/docs/student-feature-guide.md:1)
- [mobile-system-overview.md](/d:/Documents/SEMESTER6/SPT/YogaFX-Learning-Management-System-Web/docs/mobile/mobile-system-overview.md:1)
- [mobile-api-requirements.md](/d:/Documents/SEMESTER6/SPT/YogaFX-Learning-Management-System-Web/docs/mobile/mobile-api-requirements.md:1)

---

## 2. Student Mobile Priority

Urutan implementasi mobile yang paling aman:
1. auth
2. me/profile
3. dashboard/home
4. modules index
5. module detail
6. lesson detail
7. ebook list/detail
8. course list/detail
9. dialogs
10. assignment
11. certificate
12. assessment

Catatan:
- `assignment`, `certificate`, dan `assessment` sudah ada endpoint backend-nya
- tetapi secara product documentation aktif, ketiganya perlu diperlakukan lebih hati-hati dibanding dashboard/modules/lessons/ebooks/courses

---

## 3. Global Student Rules

Semua screen student mobile harus mengikuti rule ini:
- hanya student yang boleh akses
- student harus aktif
- content harus lolos tier check
- file harus lewat protected/signed access
- ownership check berlaku untuk certificate dan data student-private

Dependensi admin global:
- admin assign tier ke student
- admin membuat content
- admin mengatur dialog
- admin mereview assignment
- admin generate certificate

---

## 4. Screen Map

### 4.1 Login Screen

Tujuan:
- login student ke mobile app

Endpoint:
- `POST /api/mobile/v1/auth/login`

Payload request:
- `email`
- `password`
- `device_name`

Response utama:
- bearer token
- user basic info
- access tier

Dependency admin:
- akun student harus ada
- student harus aktif

Status:
- aman dipakai sekarang

---

### 4.2 Forgot Password Screen

Tujuan:
- kirim email reset password

Endpoint:
- `POST /api/mobile/v1/auth/forgot-password`

Payload request:
- `email`

Dependency admin:
- email account student harus valid
- email template reset password bisa dipengaruhi admin dari Email Notification

Status:
- aman dipakai sekarang

---

### 4.3 Reset Password Screen

Tujuan:
- reset password dari token

Endpoint:
- `POST /api/mobile/v1/auth/reset-password`

Payload request:
- `token`
- `email`
- `password`
- `password_confirmation`

Status:
- aman dipakai sekarang

---

### 4.4 Authenticated Student Context

Tujuan:
- ambil info siapa student yang sedang login

Endpoint:
- `GET /api/mobile/v1/me`

Data utama:
- `id`
- `name`
- `email`
- `role`
- `first_name`
- `last_name`
- `profile_completed`
- `access_tier`

Kegunaan:
- splash decision
- profile completion decision
- drawer/header mobile

Status:
- aman dipakai sekarang

---

### 4.5 Dashboard / Home Screen

Tujuan:
- halaman utama student mobile

Endpoint utama:
- `GET /api/mobile/v1/dashboard`

Data utama yang sekarang sudah tersedia:
- `student`
- `student_context`
- `access_time_summary`
- `continue_learning`
- `continue_learning_section`
- `progress_summary`
- `progress_summary_section`
- `module_highlights`
- `dialogs`
- `ebook_resources`
- `next_step`
- `sequential_awareness`
- `available_modules_section`
- `assignment_summary`
- `assignment_milestone`
- `certificate_summary`
- `certificate_milestone`
- `ebook_resources_section`
- `home_experience`
- `home_stage`

Sub-feature yang bisa dibangun dari endpoint ini:
- hero dashboard
- continue learning card
- progress summary card
- next step card
- sequential awareness card
- available modules section
- assignment milestone section
- certificate milestone section
- ebook resource section
- quick access dialog section
- access time summary

Dependency admin:
- tier student
- modules dan lessons yang dibuat admin
- dialogs yang diisi admin
- ebook yang dibuat admin
- assignment review status dari admin
- certificate generated dari admin

Status:
- aman dipakai sekarang

Catatan implementasi mobile:
- beberapa `cta_url` masih berbasis route web
- untuk mobile, lebih aman gunakan object data seperti `id`, `slug`, `state`, `key`, dan summary payload

---

### 4.6 Dialog List / Shortcut

Tujuan:
- tampilkan quick access dialog penting

Endpoint:
- `GET /api/mobile/v1/dialogs`

Data utama:
- `key`
- `title`
- `content`
- `has_content`

Route key aktif:
- `full-standing`
- `full-floor`

Dependency admin:
- admin mengisi title/content dialog

Status:
- aman dipakai sekarang

---

### 4.7 Dialog Detail Screen

Tujuan:
- tampilkan konten satu dialog

Endpoint:
- `GET /api/mobile/v1/dialogs/{key}`

Path aktif:
- `/api/mobile/v1/dialogs/full-standing`
- `/api/mobile/v1/dialogs/full-floor`

Data utama:
- `key`
- `title`
- `content`
- `has_content`

Dependency admin:
- admin isi dialog content

Status:
- aman dipakai sekarang

---

### 4.8 Profile Screen

Tujuan:
- tampilkan profile student

Endpoint:
- `GET /api/mobile/v1/profile`

Data utama:
- seluruh field profile student
- `role`
- `profile_completed`
- `access_tier`

Dependency admin:
- admin bisa mengubah profile student dari Student Progress

Status:
- aman dipakai sekarang

---

### 4.9 Edit Profile Screen

Tujuan:
- update profile student

Endpoint:
- `PATCH /api/mobile/v1/profile`

Data yang biasa diubah:
- first name
- last name
- email
- whatsapp
- preferred certificate picture
- profile photo path/string
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

Status:
- aman dipakai sekarang

Catatan:
- `profile_photo` saat ini masih string path/url, bukan upload profile image khusus yang finalized

---

### 4.10 Change Password Screen

Tujuan:
- ganti password saat sudah login

Endpoint:
- `POST /api/mobile/v1/profile/change-password`

Payload:
- `current_password`
- `new_password`
- `new_password_confirmation`

Status:
- aman dipakai sekarang

---

### 4.11 Modules Index Screen

Tujuan:
- tampilkan semua module sesuai tier student

Endpoint:
- `GET /api/mobile/v1/modules`

Data utama:
- `items`
- `summary`

Per item module biasanya ada:
- `id`
- `title`
- `slug`
- `description`
- `sort_order`
- `lesson_count`
- `assignments_count`
- `completed_lessons`
- `progress_percentage`
- `status`
- `is_visible`
- `is_complete`
- `certificate_enabled`
- `ebook_enabled`
- `video_lecturer_enabled`
- `thumbnail_url`

Dependency admin:
- module dibuat admin
- module tier access diatur admin
- thumbnail diupload admin

Status:
- aman dipakai sekarang

---

### 4.12 Module Detail Screen

Tujuan:
- tampilkan satu module dan seluruh child resource penting

Endpoint:
- `GET /api/mobile/v1/modules/{module}`

Data utama:
- metadata module
- `view_type`
- `lessons`
- `assignments`
- `ebooks`
- `video_lecturers`
- `certificates`
- `certificate_summary`

Arti `view_type`:
- `learning`
- `certificate_download`

Dependency admin:
- lesson dibuat admin
- assignment dibuat admin
- ebook dibuat admin
- course dibuat admin
- module flags diatur admin:
  - `certificate_enabled`
  - `ebook_enabled`
  - `video_lecturer_enabled`

Status:
- aman dipakai sekarang

Catatan:
- screen ini bisa jadi pusat detail module mobile
- tidak perlu endpoint tambahan untuk memuat ebook/video lecturer/certificate yang terkait module

---

### 4.13 Lesson Detail Screen

Tujuan:
- tampilkan satu lesson

Endpoint:
- `GET /api/mobile/v1/lessons/{lesson}`

Data utama:
- `id`
- `title`
- `content`
- `thumbnail_url`
- `video`
- `audio`
- `workbook`
- `progress`
- `module`
- `assessment`
- `navigation`
- `next_lesson`

Dependency admin:
- lesson dibuat admin
- workbook/video/audio/content diisi admin
- lesson tier diatur admin
- module tier induk juga memengaruhi visibility

Status:
- aman dipakai sekarang

---

### 4.14 Lesson Progress Update

Tujuan:
- kirim progress watch dari mobile

Endpoint:
- `POST /api/mobile/v1/lessons/{lesson}/progress`

Payload:
- `watch_progress`

Response utama:
- `watch_progress`
- `is_done`
- `assessment_unlocked`

Status:
- aman dipakai sekarang

Catatan:
- walau dokumentasi product menyebut automation belum final penuh, endpoint ini sudah bisa dipakai untuk integrasi mobile

---

### 4.15 Lesson Audio / Workbook Access

Tujuan:
- buka audio
- buka workbook
- download workbook

Endpoint media:
- `GET /api/mobile/v1/media/lessons/{lesson}/audio`
- `GET /api/mobile/v1/media/lessons/{lesson}/workbook`
- `GET /api/mobile/v1/media/lessons/{lesson}/workbook/download`

Catatan:
- endpoint ini signed URL based
- URL signed biasanya sudah disuplai di payload lesson

Status:
- aman dipakai sekarang

---

### 4.16 Ebooks Index Screen

Tujuan:
- tampilkan daftar ebook sesuai tier

Endpoint:
- `GET /api/mobile/v1/ebooks`

Data utama:
- `items`

Per item umumnya ada:
- `id`
- `title`
- `sort_order`
- `file_name`
- `preview_url`
- `download_url`
- `file`
- `preview_supported`
- `preview_message`
- `mime_type`

Dependency admin:
- ebook dibuat admin
- file diupload admin
- tier ebook diatur admin

Status:
- aman dipakai sekarang

---

### 4.17 Ebook Detail / Preview Screen

Tujuan:
- tampilkan detail ebook dan preview/download resource

Endpoint:
- `GET /api/mobile/v1/ebooks/{ebook}`

Data utama:
- `id`
- `title`
- `preview_url`
- `download_url`
- `file`
- `preview_supported`
- `preview_message`
- `mime_type`
- `file_name`

Status:
- aman dipakai sekarang

Catatan:
- preview PDF adalah pola utama
- download tetap aksi eksplisit terpisah

---

### 4.18 Ebook Open / Download Media

Endpoint signed:
- `GET /api/mobile/v1/media/ebooks/{ebook}/open`
- `GET /api/mobile/v1/media/ebooks/{ebook}/download`

Catatan:
- gunakan URL signed dari payload ebook jika ada

---

### 4.19 Courses Index Screen

Tujuan:
- tampilkan daftar course/video lecturer sesuai tier

Endpoint:
- `GET /api/mobile/v1/courses`

Data utama:
- `items`

Per item biasanya ada:
- `id`
- `title`
- `url_slug`
- `description`
- `index`
- `status`
- `thumbnail_url`
- `thumbnail`
- `video`

Dependency admin:
- course dibuat admin
- tier course diatur admin
- thumbnail/video diisi admin

Status:
- aman dipakai sekarang

---

### 4.20 Course Detail Screen

Tujuan:
- tampilkan detail satu course/video lecturer

Endpoint:
- `GET /api/mobile/v1/courses/{course}`

Data utama:
- detail metadata course
- state video
- thumbnail

Status:
- aman dipakai sekarang

---

### 4.21 Assignment Detail Screen

Tujuan:
- tampilkan satu assignment dan status submission student

Endpoint:
- `GET /api/mobile/v1/assignments/{assignment}`

Data utama:
- assignment info
- module context
- submission state
- upload constraints
- lock state

Dependency admin:
- assignment dibuat admin
- assignment harus live
- assignment review status ditentukan admin

Status:
- tersedia secara teknis
- gunakan dengan hati-hati karena student assignment page belum dianggap fully active di IA utama

---

### 4.22 Assignment Submit Action

Tujuan:
- upload video assignment

Endpoint:
- `POST /api/mobile/v1/assignments/{assignment}/submit`

Payload:
- multipart file `video`

Response utama:
- assignment id
- submission payload terbaru

Dependency admin:
- assignment harus live
- admin nanti mereview hasil submission

Status:
- tersedia secara teknis
- gunakan dengan hati-hati terhadap scope product aktif

---

### 4.23 Certificate List Screen

Tujuan:
- tampilkan certificate milik student

Endpoint:
- `GET /api/mobile/v1/certificates`

Data utama:
- `summary`
- `items`

Per item certificate biasanya ada:
- `id`
- `type`
- `type_label`
- `file_name`
- `version`
- `generated_at`
- `generated_by`
- `download_url`
- `open_url`
- `file`

Dependency admin:
- admin generate certificate
- student harus owner

Status:
- tersedia secara teknis
- tetapi student certificate page belum dianggap area utama yang fully active di IA inti

---

### 4.24 Certificate Detail Screen

Tujuan:
- lihat detail satu certificate

Endpoint:
- `GET /api/mobile/v1/certificates/{certificate}`

Data utama:
- `certificate`
- `summary`

Status:
- tersedia secara teknis

---

### 4.25 Certificate Download Action

Tujuan:
- download certificate file

Endpoint:
- `GET /api/mobile/v1/certificates/{certificate}/download`

Juga ada media signed endpoint:
- `GET /api/mobile/v1/media/certificates/{certificate}/open`
- `GET /api/mobile/v1/media/certificates/{certificate}/download`

Rule:
- hanya owner student yang boleh akses

Status:
- tersedia secara teknis

---

### 4.26 Assessment Intro Screen

Tujuan:
- tampilkan intro assessment untuk lesson

Endpoint:
- `GET /api/mobile/v1/lessons/{lesson}/assessment`

Status:
- tersedia secara teknis
- tetapi assessment domain belum dianggap final penuh di dokumentasi inti

---

### 4.27 Assessment Play Flow

Endpoint:
- `POST /api/mobile/v1/lessons/{lesson}/assessment/start`
- `GET /api/mobile/v1/lessons/{lesson}/assessment/attempts/{attempt}`
- `POST /api/mobile/v1/lessons/{lesson}/assessment/attempts/{attempt}/answer`
- `POST /api/mobile/v1/lessons/{lesson}/assessment/attempts/{attempt}/back`
- `GET /api/mobile/v1/lessons/{lesson}/assessment/attempts/{attempt}/result`

Status:
- tersedia secara teknis
- gunakan hati-hati karena domain assessment belum final penuh dalam source of truth utama

---

## 5. Admin-to-Student Dependency Map

### 5.1 Profile

Admin action:
- edit student profile

Mempengaruhi mobile:
- profile screen
- dashboard student context

---

### 5.2 Tier Assignment

Admin action:
- assign tier ke student

Mempengaruhi mobile:
- modules
- lessons
- ebooks
- courses
- certificate eligibility
- dashboard sections

---

### 5.3 Module CRUD

Admin action:
- create/edit/delete module
- set thumbnail
- set multi-tier access
- set module flags

Mempengaruhi mobile:
- modules index
- module detail
- dashboard module sections

---

### 5.4 Lesson CRUD

Admin action:
- create/edit/delete lesson
- set content
- set workbook
- set audio/video
- set multi-tier access

Mempengaruhi mobile:
- lesson detail
- module detail
- continue learning
- progress summary
- sequential awareness

---

### 5.5 Ebook CRUD

Admin action:
- create/edit/delete ebook
- upload file
- set multi-tier access

Mempengaruhi mobile:
- ebook list
- ebook detail
- dashboard ebook resources
- module resource section

---

### 5.6 Course CRUD

Admin action:
- create/edit/delete course
- upload thumbnail
- set video
- set single tier

Mempengaruhi mobile:
- course list
- course detail
- module video lecturer section

---

### 5.7 Dialog Management

Admin action:
- isi full standing dialog
- isi full floor dialog

Mempengaruhi mobile:
- dialogs list
- dialog detail
- dashboard quick dialog area

---

### 5.8 Assignment Review

Admin action:
- review assignment
- set approved/rejected/pending
- tulis feedback

Mempengaruhi mobile:
- assignment detail
- assignment summary
- assignment milestone
- module assignment state

---

### 5.9 Certificate Generation

Admin action:
- generate certificate
- recreate certificate
- delete certificate

Mempengaruhi mobile:
- certificate summary
- certificate milestone
- certificate list/detail/download
- module certificate context

---

## 6. Safe Mobile Build Recommendation

### 6.1 Safe To Build Now

Area ini aman dijadikan prioritas:
- login
- forgot/reset password
- me
- profile
- dashboard
- dialogs
- modules
- module detail
- lessons
- lesson progress
- ebooks
- courses

### 6.2 Build With Caution

Area ini ada implementasi teknis, tapi jangan diasumsikan final product area:
- assignment screens
- certificate screens
- assessment screens

### 6.3 Do Not Assume Active Product Scope

Jangan mempromosikan sebagai fitur utama final tanpa sinkronisasi dokumentasi:
- public signup
- dedicated student certificate area sebagai pusat navigasi utama
- dedicated student assignment area sebagai pusat navigasi utama
- full assessment product domain

---

## 7. Recommended Mobile Screen Checklist

Checklist minimum student mobile:
- Login
- Forgot Password
- Reset Password
- Home / Dashboard
- Dialog Detail
- Profile
- Edit Profile
- Change Password
- Modules Index
- Module Detail
- Lesson Detail
- Ebook List
- Ebook Detail / Preview
- Course List
- Course Detail

Checklist optional berdasarkan kebutuhan implementasi sekarang:
- Assignment Detail
- Assignment Upload
- Certificate List
- Certificate Detail
- Assessment Intro
- Assessment Player
- Assessment Result

---

## 8. Summary

Jika fokus tim adalah mobile student, maka pegangan paling aman adalah:
- gunakan `dashboard` sebagai sumber utama home
- gunakan `modules`, `lessons`, `ebooks`, `courses`, dan `dialogs` sebagai tulang punggung app
- treat assignment, certificate, dan assessment sebagai area existing-but-cautious

Secara backend, student mobile saat ini sudah punya fondasi yang cukup lengkap untuk membangun:
- auth flow
- profile flow
- premium home flow
- module/lesson learning flow
- ebook flow
- course flow
- dialog flow

Dan semua itu tetap bergantung pada admin yang:
- assign tier
- buat konten
- kelola dialog
- review assignment
- generate certificate

