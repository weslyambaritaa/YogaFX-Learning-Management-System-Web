# Lesson Media & Bunny Integration
# YogaFX LMS

## Purpose

Dokumen ini menjadi source of truth untuk domain **lesson media** yang berada di scope menu Lesson, khususnya:
- thumbnail
- lesson video
- audio mp3
- workbook
- file/image/asset lesson lain yang relevan
- integrasi Bunny Stream untuk lesson video reference
- integrasi Bunny Storage untuk asset non-video
- cara frontend memanggil video dan audio di halaman lesson student

Dokumen ini **tidak** mencakup domain/menu Lecturer Video.
Jika ada kebutuhan lecturer video, itu harus berada di domain terpisah dan tidak boleh dicampur ke scope Lesson.

Jika ada konflik dengan implementasi lama, maka dokumen ini yang dipakai untuk domain media lesson.

---

# 1. Main Media Architecture

Domain media lesson sekarang dibagi menjadi beberapa jalur:

1. **Lesson Video**
   - memakai **Bunny Stream**
   - admin hanya memasukkan `lesson_video_id`

2. **Audio**
   - upload file `.mp3`
   - disimpan ke **Bunny Storage**

3. **Thumbnail / image**
   - upload manual admin
   - disimpan ke **Bunny Storage**

4. **Workbook / file lesson lain**
   - disimpan ke **Bunny Storage**

---

# 2. Lesson Video Architecture

## Final Rule
Lesson video **sudah ada lebih dulu di Bunny Stream**.

## Admin Behaviour
Admin tidak upload ulang video lesson.

Admin hanya:
1. mengambil `video_id` dari Bunny Stream
2. memasukkan `video_id` tersebut ke form lesson secara manual

## Data Stored
Sistem menyimpan:
- `lesson_video_id`

## Frontend Playback
Frontend membentuk HLS URL dari:
- config server / `.env`
- `lesson_video_id`

Lalu video diputar dengan **Video.js**.

## Important Note
Lesson video adalah model **reference-only**, bukan upload-from-admin flow.

---

# 3. Audio Architecture

## Final Rule
Audio **bukan link manual**.

Audio sekarang:
- diupload sebagai file `.mp3`
- disimpan ke **Bunny Storage**

## Admin Behaviour
Pada form lesson:
- admin upload file audio `.mp3`

## Environment Note
Audio upload tidak akan pernah masuk ke validasi Bunny Storage bila PHP/server belum menerima file sebagai uploaded file yang valid.

Untuk lesson audio saat ini, environment sebaiknya minimal sinkron dengan target berikut:
- `upload_max_filesize >= 64M`
- `post_max_size >= 72M`

Jika limit PHP lebih kecil dari ukuran request audio:
- `hasFile` bisa bernilai `false`
- Laravel tidak menerima `UploadedFile` yang valid
- error yang terlihat harus dipahami sebagai blocker environment, bukan blocker Bunny Storage

## Data Stored
Sistem menyimpan:
- `audio_url`

Makna `audio_url`:
- final asset reference hasil upload ke Bunny Storage
- bukan lagi input URL manual

## Frontend Playback
Saat student membuka halaman lesson:
- audio tampil sebagai **player audio terpisah**
- audio dapat diputar sendiri, terpisah dari video

---

# 4. Thumbnail / Image

## Final Rule
Thumbnail tetap dipilih / diupload manual oleh admin, tetapi file-nya disimpan ke **Bunny Storage**.

## Data Stored
Sistem menyimpan:
- `thumbnail`

## Important Note
“Manual upload admin” artinya admin yang memilih file, bukan berarti file harus masuk local storage.

---

# 5. Workbook / Files

## Final Rule
Semua file non-video di scope lesson diarahkan ke **Bunny Storage**.

## Applies To
- workbook lesson
- file lesson lain yang relevan

## Data Stored
Sistem menyimpan:
- `workbook`

## Important Note
Setelah implementasi baru:
- data baru mengikuti Bunny Storage
- data lama di local storage tidak wajib dimigrasikan sekarang

---

# 6. Bunny Storage Rules

## Final Rule
Gunakan **Bunny Storage API/HTTP dengan AccessKey**.

## Applies To
- thumbnail
- audio mp3
- workbook
- image / file lesson non-video lain

## URL Handling
URL asset yang dipakai frontend/admin harus:
- dibentuk lewat **backend app dari helper resolver yang sama**
- jangan di-hardcode di banyak tempat frontend
- jangan dibentuk manual per-view

Artinya:
- backend memegang source of truth untuk base URL / CDN URL
- frontend menerima URL final yang sudah diproses backend

---

# 7. Admin Form Requirements

## Lesson Form
Form lesson minimal harus mendukung field berikut:
- thumbnail upload
- lesson video id
- audio upload (`.mp3`)
- workbook upload

### Final Behaviour
- `thumbnail` = upload manual admin ke Bunny Storage
- `lesson_video_id` = input text manual
- `audio` = upload file `.mp3` ke Bunny Storage
- `workbook` = upload file ke Bunny Storage

---

# 8. Create vs Edit Behaviour

## 8.1 Lesson Video
- admin boleh mengedit `lesson_video_id` secara manual kapan saja saat create/edit lesson

## 8.2 Audio / Thumbnail / Workbook
- admin boleh mengganti file saat create/edit lesson
- saat diganti, cukup update field di database
- **jangan hapus file lama otomatis** dari Bunny Storage dulu

---

# 9. Database Direction

Field media final di tabel `lessons` adalah:
- `thumbnail`
- `lesson_video_id`
- `audio_url`
- `workbook`

Dokumen ini tidak lagi memasukkan `lecturer_video_id` ke scope Lesson.

---

# 10. Frontend Behaviour

## 10.1 Video
Frontend memakai **Video.js** untuk lesson video.

### Source Pattern
Frontend membentuk HLS URL dari:
- config server / `.env`
- `lesson_video_id`

## 10.2 Student Lesson Page
Saat student membuka halaman lesson:
- ada **video player** untuk lesson video
- ada **audio player** terpisah

## 10.3 Audio
Audio cukup dirender sebagai:
- audio player HTML sederhana
- atau UI audio sederhana yang rapi

Belum perlu UX audio kompleks.

## 10.4 Workbook / Thumbnail
Frontend menerima URL final dari backend helper resolver yang sama.

---

# 11. Compatibility Rule

Untuk masa transisi:
- asset lama di local storage tetap harus terbaca
- asset baru di Bunny Storage juga harus terbaca

Jadi helper resolver asset harus support:
- local path lama
- Bunny path baru
- legacy URL jika memang masih ada

---

# 12. Product Logic Summary

## Lesson Video
- video sudah ada di Bunny Stream
- admin isi `lesson_video_id`

## Audio
- admin upload file `.mp3`
- file disimpan ke Bunny Storage
- student melihat audio player terpisah

## Thumbnail
- admin upload manual
- file disimpan ke Bunny Storage

## Workbook
- file disimpan ke Bunny Storage

## Out of Scope
- lecturer video upload
- `lecturer_video_id`
- UI atau flow lecturer video

---

# 13. Implementation Notes for Codex

Codex harus memahami hal-hal berikut:

1. Jangan campur Lesson dengan domain Lecturer Video.
2. `lesson_video_id` adalah reference-only field dari Bunny Stream.
3. Audio bukan link manual lagi.
4. Audio sekarang upload `.mp3` ke Bunny Storage.
5. Thumbnail dan workbook diarahkan ke Bunny Storage.
6. Frontend video playback harus memakai Video.js.
7. Frontend video source dibentuk dari config server + `lesson_video_id`.
8. Student lesson page harus punya video player dan audio player terpisah.
9. URL helper asset harus jadi satu pintu untuk local lama dan Bunny baru.

---

# 14. Final Summary

Arsitektur media final untuk scope Lesson adalah:

- **Lesson Video**
  - sudah ada di Bunny Stream
  - admin hanya input `lesson_video_id`

- **Audio**
  - admin upload file `.mp3`
  - file disimpan ke Bunny Storage

- **Thumbnail**
  - upload manual admin
  - file disimpan ke Bunny Storage

- **Workbook**
  - file disimpan ke Bunny Storage

- **Frontend**
  - lesson video diputar dengan Video.js
  - source video memakai Bunny Stream HLS playlist URL berdasarkan `lesson_video_id`
  - audio diputar terpisah dari video

Dokumen ini menjadi source of truth untuk domain lesson media dalam scope menu Lesson.
