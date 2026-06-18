# Mobile Flutter Backend Integration Guide

## Purpose

Dokumen ini adalah handoff teknis untuk implementasi Flutter yang akan berkomunikasi dengan backend Laravel pada repository ini.

Tujuan dokumen:
- menjelaskan endpoint mobile yang benar-benar aktif
- menjelaskan kontrak request dan response
- menjelaskan aturan auth, tier, lock state, media, dan file download
- menandai area yang sudah aman dipakai Flutter
- menandai area yang masih perlu validasi tambahan

Dokumen ini mengikuti implementasi kode aktual, terutama:
- `routes/api.php`
- `app/Http/Controllers/Mobile/V1/*`
- `app/Services/Mobile/V1/*`
- hasil test mobile yang sudah dijalankan di repo ini

Dokumen ini harus diprioritaskan untuk handoff Flutter dibanding asumsi dari halaman web/Inertia.

---

## Executive Summary

Backend Laravel ini **sudah memiliki mobile API student yang cukup lengkap** di prefix:

- `/api/mobile/v1`

Area yang sudah tersedia saat ini:
- auth login
- auth logout
- forgot password
- reset password
- `me`
- dashboard
- modules
- module detail
- ebooks
- ebook detail
- ebook open/download media
- courses
- course detail
- lesson detail
- lesson progress update
- lesson workbook/audio media
- assessment intro/start/player/result
- assignment detail
- assignment submit
- certificates list/detail/download
- certificate open/download media
- profile show/update
- change password

Artinya untuk student app Flutter, backend mobile yang tersedia sekarang **sudah jauh lebih lengkap** daripada dugaan awal.

Yang tetap perlu perhatian:
- beberapa dokumen inti project belum sinkron penuh dengan implementasi mobile terbaru
- media signed URL harus diuji baik di emulator maupun device nyata
- video Bunny/HLS harus divalidasi pada environment target
- upload assignment multipart harus diuji dari client mobile

---

## Integration Principles

### 1. Flutter hanya boleh bicara ke Mobile API

Gunakan hanya endpoint:
- `/api/mobile/v1/...`

Jangan konsumsi:
- route web/Inertia
- response HTML
- redirect flow web
- struktur props React/Inertia

### 2. Backend memegang business rules

Flutter hanya membaca dan menampilkan hasil keputusan backend.

Rule yang harus tetap diputuskan backend:
- role restriction
- active student restriction
- tier visibility
- locked module
- locked lesson
- progress minimum 95%
- assessment unlock
- assignment lock
- certificate ownership

Flutter jangan menghitung ulang rule ini secara lokal.

### 3. Flutter harus percaya payload API, bukan asumsi schema

Contoh:
- video lesson/course tidak selalu siap diputar
- ebook preview belum tentu didukung
- assignment lock reason harus diambil dari backend
- certificate download bisa berupa signed media URL atau redirect

### 4. Response parsing harus seragam

Sebagian besar JSON mobile mengikuti pola:
- `success`
- `message`
- `data`

Untuk error:
- `success`
- `message`
- `errors`

Flutter disarankan punya wrapper response umum.

Catatan:
- endpoint media file tidak selalu mengembalikan JSON, karena beberapa akan return file stream atau redirect

---

## Base API Contract

### Base URL

Contoh production:

```text
https://your-domain.com/api/mobile/v1
```

Contoh local:

```text
http://127.0.0.1:8000/api/mobile/v1
```

### Default headers

JSON request:

```http
Accept: application/json
Content-Type: application/json
```

Authenticated JSON request:

```http
Accept: application/json
Authorization: Bearer {token}
```

Multipart request:

```http
Accept: application/json
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

### Success JSON shape

```json
{
  "success": true,
  "message": "OK",
  "data": {}
}
```

### Error JSON shape

```json
{
  "success": false,
  "message": "The payload is invalid.",
  "errors": {
    "field": [
      "Validation message"
    ]
  }
}
```

### Minimum HTTP statuses Flutter must handle

- `200`
- `401`
- `403`
- `404`
- `422`

Interpretasi umum:
- `401`: token missing/invalid
- `403`: authenticated but forbidden
- `404`: resource tidak ada untuk student ini
- `422`: payload salah

---

## Authentication Model

### Auth type

Mobile auth memakai Laravel Sanctum personal access token.

Flow:
1. Flutter login dengan email/password
2. backend return bearer token
3. Flutter simpan token secara aman
4. Flutter kirim token ke semua endpoint protected
5. logout menghapus current access token

### Role restriction

Mobile API ini hanya untuk student account.

Jika admin login ke mobile API:
- login ditolak `403`
- protected student endpoint juga ditolak `403`

### Active student restriction

Student harus aktif.

Jika student inactive:
- login ditolak `403`
- akses student endpoint juga ditolak `403`

### Rate limiting

Login memiliki throttle:
- 5 percobaan sebelum kena throttle

Flutter harus menampilkan pesan throttle dari backend apa adanya.

---

## Route Summary

Route aktif saat ini terlihat di [routes/api.php](/d:/Documents/SEMESTER6/SPT/YogaFX-Learning-Management-System-Web/routes/api.php:1).

### Public / non-auth routes

- `POST /auth/login`
- `POST /auth/forgot-password`
- `POST /auth/reset-password`
- `GET /media/lessons/{lesson}/audio` with signed URL
- `GET /media/lessons/{lesson}/workbook` with signed URL
- `GET /media/lessons/{lesson}/workbook/download` with signed URL
- `GET /media/ebooks/{ebook}/open` with signed URL
- `GET /media/ebooks/{ebook}/download` with signed URL
- `GET /media/certificates/{certificate}/open` with signed URL
- `GET /media/certificates/{certificate}/download` with signed URL

### Authenticated student routes

- `GET /me`
- `GET /dashboard`
- `GET /modules`
- `GET /modules/{module}`
- `GET /ebooks`
- `GET /ebooks/{ebook}`
- `GET /courses`
- `GET /courses/{course}`
- `GET /lessons/{lesson}`
- `POST /lessons/{lesson}/progress`
- `GET /lessons/{lesson}/assessment`
- `POST /lessons/{lesson}/assessment/start`
- `GET /lessons/{lesson}/assessment/attempts/{attempt}`
- `POST /lessons/{lesson}/assessment/attempts/{attempt}/answer`
- `POST /lessons/{lesson}/assessment/attempts/{attempt}/back`
- `GET /lessons/{lesson}/assessment/attempts/{attempt}/result`
- `GET /assignments/{assignment}`
- `POST /assignments/{assignment}/submit`
- `GET /certificates`
- `GET /certificates/{certificate}`
- `GET /certificates/{certificate}/download`
- `GET /profile`
- `PATCH /profile`
- `POST /profile/change-password`

### Authenticated logout route

- `POST /auth/logout`

---

## Endpoint Details

## 1. Auth Login

`POST /auth/login`

Request:

```json
{
  "email": "student@example.com",
  "password": "password",
  "device_name": "Pixel 9"
}
```

Notes:
- `device_name` optional
- default backend value: `mobile-app`

Success data:
- `token`
- `token_type`
- `user`

`user` contains:
- `id`
- `name`
- `email`
- `role`
- `first_name`
- `last_name`
- `profile_completed`
- `access_tier`

Failure possibilities:
- wrong credentials -> `422`
- throttled -> `422`
- admin account -> `403`
- inactive student -> `403`

## 2. Forgot Password

`POST /auth/forgot-password`

Request:

```json
{
  "email": "student@example.com"
}
```

Success data:

```json
{
  "email": "student@example.com"
}
```

Success message:
- `Password reset email sent successfully.`

Failure:
- invalid email payload -> `422`
- reset link not sent -> `422`

## 3. Reset Password

`POST /auth/reset-password`

Request:

```json
{
  "token": "reset-token",
  "email": "student@example.com",
  "password": "new-password",
  "password_confirmation": "new-password"
}
```

Success data:

```json
{
  "email": "student@example.com",
  "password_reset": true
}
```

## 4. Logout

`POST /auth/logout`

Auth required: yes

Behavior:
- deletes current token only

## 5. Me

`GET /me`

Auth required: yes

Purpose:
- restore session
- bootstrap current student state

## 6. Dashboard

`GET /dashboard`

Auth required: yes

Contains:
- `student`
- `continue_learning`
- `progress_summary`
- `module_highlights`
- `assignment_summary`
- `certificate_summary`

Very suitable as Flutter home screen bootstrap endpoint.

## 7. Modules Index

`GET /modules`

Auth required: yes

Returns:
- `items`
- `summary`

Each module item can include:
- `id`
- `title`
- `slug`
- `description`
- `sort_order`
- `lesson_count`
- `assignments_count`
- `completed_lessons`
- `progress_percentage`
- `show_progress`
- `status`
- `is_visible`
- `is_complete`
- `certificate_enabled`
- `ebook_enabled`
- `video_lecturer_enabled`
- `thumbnail_url`

Observed module statuses:
- `locked`
- `available`
- `active`
- `completed`

## 8. Module Detail

`GET /modules/{module}`

Auth required: yes

Contains:
- module metadata
- lessons
- assignments
- optional certificate summary

Important:
- locked module -> `403`
- `lock_reason` may be returned in error payload

Each lesson in module detail can include:
- `id`
- `title`
- `sort_order`
- `has_workbook`
- `has_video`
- `has_audio`
- `has_content`
- `is_locked`
- `lock_reason`
- `status`
- `progress_percentage`
- `thumbnail_url`

Each assignment in module detail can include:
- `id`
- `title`
- `description`
- `sort_order`
- `status`
- `submission_status`
- `submission_feedback`
- `submitted_at`

## 9. Ebooks Index

`GET /ebooks`

Auth required: yes

Returned by `StudentEbookApiService`.

Contains:
- `items`

Each ebook item contains:
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
- `detail_ready`

Important notes:
- preview is only supported for PDF-like files
- `preview_url` can be null
- `download_url` is still available even if preview unsupported

## 10. Ebook Detail

`GET /ebooks/{ebook}`

Auth required: yes

If ebook is not accessible for student tier:
- `404`

Payload shape is similar to index but more suitable for detail page.

## 11. Ebook Media Open

`GET /media/ebooks/{ebook}/open`

Signed route, not bearer-token route.

Behavior:
- validates URL signature
- validates `student` query parameter
- validates student tier access
- serves file directly or redirects if URL/Bunny asset

Flutter usage:
- open with browser/PDF viewer or in-app webview depending UX

## 12. Ebook Media Download

`GET /media/ebooks/{ebook}/download`

Signed route.

Behavior:
- direct download or redirect depending storage type

## 13. Courses Index

`GET /courses`

Auth required: yes

Returned by `StudentCourseApiService`.

Contains:
- `items`

Each course item can contain:
- `id`
- `title`
- `url_slug`
- `description`
- `index`
- `status`
- `thumbnail_url`
- `thumbnail`
- `video`

Observed course status:
- `ready`
- `unavailable`

## 14. Course Detail

`GET /courses/{course}`

Auth required: yes

Accessible only if course belongs to student tier.

If not accessible:
- `404`

## 15. Lesson Detail

`GET /lessons/{lesson}`

Auth required: yes

Contains:
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

### Lesson video payload

```json
{
  "video_id": "uuid-or-null",
  "hls_url": "https://...",
  "is_ready": true,
  "is_configured": true,
  "is_valid_id": true,
  "is_found_in_library": true,
  "warning_message": null
}
```

Important:
- `hls_url` may be null
- `is_ready` may be false
- warning should be shown gracefully

### Lesson audio payload

- `url`
- `is_available`

### Lesson workbook payload

- `url`
- `file_name`
- `is_available`

### Lesson progress payload

- `watch_progress`
- `is_workbook_downloaded`
- `workbook_downloaded_at`
- `is_done`

### Lesson assessment payload

- `id`
- `title`
- `is_unlocked`
- `is_completed`
- `current_attempt_id`

Observed lesson statuses:
- `locked`
- `available`
- `current`
- `completed`

## 16. Lesson Progress Update

`POST /lessons/{lesson}/progress`

Auth required: yes

Request:

```json
{
  "watch_progress": 95
}
```

Rules:
- required
- numeric
- min `0`
- max `100`

Observed behavior:
- backend keeps the maximum progress reached
- progress does not go backwards
- lesson completion can be triggered by backend rules

Success payload:
- `watch_progress`
- `is_done`
- `assessment_unlocked`

## 17. Lesson Audio Media

`GET /media/lessons/{lesson}/audio`

Signed route.

Used for direct audio playback/download access depending file source.

## 18. Lesson Workbook Open

`GET /media/lessons/{lesson}/workbook`

Signed route.

For inline/open access.

## 19. Lesson Workbook Download

`GET /media/lessons/{lesson}/workbook/download`

Signed route.

For explicit file download.

## 20. Assessment Intro

`GET /lessons/{lesson}/assessment`

Auth required: yes

Contains:
- lesson info
- assessment info
- eligibility info
- current in-progress attempt
- completed attempt if any

## 21. Assessment Start

`POST /lessons/{lesson}/assessment/start`

Possible response modes:
- `completed`
- `in_progress`

## 22. Assessment Attempt

`GET /lessons/{lesson}/assessment/attempts/{attempt}`

Possible response modes:
- `result_redirect`
- `question`

When `question`, payload contains:
- lesson
- assessment
- attempt
- question
- `can_go_back`
- `is_last_question`

## 23. Assessment Answer

`POST /lessons/{lesson}/assessment/attempts/{attempt}/answer`

Payload depends on question type:
- `option_id`
- `option_ids`
- `answer_text`
- `answer_number`

Possible response modes:
- `result_redirect`
- `question_redirect`

## 24. Assessment Back

`POST /lessons/{lesson}/assessment/attempts/{attempt}/back`

Possible response:
- `question_redirect`

## 25. Assessment Result

`GET /lessons/{lesson}/assessment/attempts/{attempt}/result`

Possible modes:
- `attempt_redirect`
- final result payload

## 26. Assignment Detail

`GET /assignments/{assignment}`

Auth required: yes

Contains:
- assignment metadata
- module metadata
- current submission
- upload constraints

Important fields:
- `is_locked`
- `lock_reason`
- `can_submit`
- `submission.status`
- `submission.feedback`
- `submission.video_url`

Important note from testing:
- lock reason can inherit wording from module lock flow

## 27. Assignment Submit

`POST /assignments/{assignment}/submit`

Auth required: yes
Content type: multipart/form-data

Field:
- `video`

Accepted extensions:
- `mp4`
- `mov`
- `webm`
- `avi`
- `m4v`

## 28. Certificates Index

`GET /certificates`

Auth required: yes

Contains:
- `summary`
- `items`

Summary fields:
- `learning_eligible`
- `has_required_name`
- `message`
- `tier`
- `available_types`
- `requirements`
- `generated_count`

Item fields:
- `id`
- `type`
- `type_label`
- `file_name`
- `version`
- `generated_at`
- `generated_by`
- `download_url`

## 29. Certificate Detail

`GET /certificates/{certificate}`

Auth required: yes

Ownership enforced.

If another student certificate requested:
- `404`

## 30. Certificate Download

`GET /certificates/{certificate}/download`

Auth required: yes

Backend may return:
- file response
- redirect

## 31. Certificate Media Open

`GET /media/certificates/{certificate}/open`

Signed route.

## 32. Certificate Media Download

`GET /media/certificates/{certificate}/download`

Signed route.

## 33. Profile Show

`GET /profile`

Auth required: yes

Contains detailed student profile fields, including:
- name data
- email
- whatsapp
- preferred certificate picture
- profile photo path/url string
- instagram
- country
- birth date
- gender
- yoga-related fields
- role
- profile completion
- access tier

## 34. Profile Update

`PATCH /profile`

Auth required: yes

Uses backend profile update validation.

## 35. Change Password

`POST /profile/change-password`

Auth required: yes

Request:

```json
{
  "current_password": "old-password",
  "new_password": "new-password",
  "new_password_confirmation": "new-password"
}
```

---

## Domain Values Flutter Should Treat as Enums

### Module status

- `locked`
- `available`
- `active`
- `completed`

### Lesson status

- `locked`
- `available`
- `current`
- `completed`

### Assignment master status

- `draft`
- `live`
- `archived`

### Assignment submission status

- `submitted`
- `under_review`
- `pending_review`
- `approved`
- `rejected`

### Course status

- `ready`
- `unavailable`

### Assessment attempt status

- `in_progress`
- `completed`
- `expired`

### Certificate types

- `bikram_yoga_certificate`
- `yoga_alliance_certification`

---

## Protected Media Rules

Backend menggunakan dua pola media:

### 1. Direct payload URL fields

Contoh:
- lesson `audio.url`
- lesson `workbook.url`
- assignment `video_url`
- certificate `download_url`

### 2. Signed media routes

Contoh:
- ebook open/download
- lesson workbook open/download
- lesson audio
- certificate open/download

### Integration implications for Flutter

1. Jangan asumsikan semua file adalah public static asset.
2. Selalu gunakan URL dari backend.
3. Signed URL biasanya sudah membawa akses temporary sendiri, jadi bearer token belum tentu dibutuhkan pada request media tersebut.
4. Flutter harus siap menangani redirect response untuk file tertentu.
5. Untuk video, prioritaskan `hls_url` saat `is_ready = true`.

---

## What Has Been Validated Already

Feature tests mobile yang sudah dijalankan di repo ini lulus.

Sudah tervalidasi:
- mobile auth foundation
- `me` access rules
- role restriction
- inactive student restriction
- module visible vs locked state
- locked module detail
- locked lesson detail
- progress update tidak menurunkan progress
- assignment mengikuti module gate
- certificate ownership restriction

Hasil terakhir:
- `16 tests`
- `58 assertions`
- `passed`

Ini belum berarti semua endpoint media dan upload sudah tervalidasi dari client mobile nyata, tapi kontrak dasar API mobile sudah cukup stabil.

---

## Remaining Validation Before Flutter Handoff

Sebelum menyuruh Codex lain membuat Flutter, saya sangat sarankan validasi poin ini:

### A. Auth validation

- login valid
- login invalid
- forgot password
- reset password
- logout

### B. Media validation

- ebook preview URL benar-benar terbuka
- ebook download benar-benar bisa diunduh
- lesson workbook open/download berjalan
- lesson audio berjalan
- certificate open/download berjalan

### C. Video validation

- lesson HLS playable di environment target
- course HLS playable di environment target
- Bunny config valid
- invalid video IDs menghasilkan warning yang jelas

### D. Upload validation

- assignment multipart upload berhasil dari client mobile
- invalid extension ditolak
- oversized file ditolak

### E. Response consistency validation

Pastikan endpoint JSON tetap konsisten di:
- `success`
- `message`
- `data`
- `errors`

### F. Device networking validation

Pastikan:
- Android emulator base URL benar
- iOS simulator base URL benar jika dipakai
- HTTP vs HTTPS policy sesuai
- signed media URL bisa diakses dari device

---

## Recommended Flutter Integration Structure

Disarankan minimal ada:
- `api_client`
- `auth_repository`
- `dashboard_repository`
- `module_repository`
- `ebook_repository`
- `course_repository`
- `lesson_repository`
- `assessment_repository`
- `assignment_repository`
- `certificate_repository`
- `profile_repository`

Shared pieces:
- `ApiResponse<T>`
- `ApiError`
- `AuthInterceptor`
- `TokenStorage`
- `SignedMediaOpener`

Rules:
1. Semua endpoint dibungkus di repository.
2. Semua enum string backend dipetakan ke enum/domain Flutter.
3. Semua response mode assessment seperti `question_redirect` diperlakukan eksplisit.
4. Semua `403` dengan `lock_reason` dianggap UI state, bukan crash.
5. Media endpoint dipisahkan dari endpoint JSON saat desain networking layer Flutter.

---

## Suggested Flutter Build Order

### Phase 1

- login
- forgot password
- reset password
- token persistence
- me
- logout

### Phase 2

- dashboard
- modules list
- module detail
- ebooks list/detail/open/download
- courses list/detail

### Phase 3

- lesson detail
- lesson progress update
- workbook open/download
- audio playback
- video playback

### Phase 4

- assessment intro
- assessment player
- assessment result

### Phase 5

- assignment detail
- assignment upload
- certificate list/detail/download
- profile update
- change password

---

## Final Recommendations

1. Gunakan file ini sebagai source of truth handoff Flutter.
2. Abaikan asumsi lama yang menyebut ebooks/courses/mobile password recovery belum ada, karena di kode aktual endpoint tersebut sudah tersedia.
3. Prioritaskan validasi media dan video lebih awal, karena area ini paling sering pecah di mobile.
4. Jika ingin handoff yang lebih aman lagi, langkah terbaik berikutnya adalah membuat Postman collection atau API examples per endpoint.
