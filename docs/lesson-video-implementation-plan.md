# Lesson Video Implementation Plan
# YogaFX LMS

## Purpose

Dokumen ini menjadi source of truth implementasi untuk memastikan **video lesson yang sudah diinput dari sisi admin benar-benar tampil dan bisa diputar di student lesson page**.

Dokumen ini dibuat agar AI Coding Assistant (Codex) bisa:
- mulai dari audit awal
- melewati tahap yang sudah benar
- hanya mengerjakan tahap yang belum selesai
- melakukan verifikasi sampai video benar-benar tampil di sisi student

Dokumen ini fokus pada domain:
- admin lesson video input
- backend lesson payload
- Bunny Stream runtime config
- student lesson playback
- Video.js integration
- testing end-to-end

Dokumen ini **bukan** untuk domain lain seperti:
- email
- assessment
- lecturer video
- student progress
- scoreboard

---

# 1. Final Goal

Target akhir implementasi adalah:

1. admin dapat mengisi `lesson_video_id` pada lesson
2. nilai tersebut tersimpan benar di database
3. backend student mengambil lesson video data dengan benar
4. backend membentuk / menyediakan data playback yang benar untuk frontend
5. student lesson page menerima data video yang benar
6. Video.js memutar video Bunny Stream dengan benar
7. jika config atau data salah, sistem menampilkan warning yang jujur dan jelas
8. jika semua benar, video tampil normal di lesson student page

---

# 2. Final Architecture

## 2.1 Source of Video
Lesson video berasal dari **Bunny Stream**.

## 2.2 Admin Flow
Admin tidak upload video lesson dari panel lesson.

Admin hanya:
- mengambil `lesson_video_id` dari Bunny Stream
- memasukkan `lesson_video_id` itu ke field lesson

## 2.3 Frontend Flow
Student lesson page memutar video menggunakan:
- **Video.js**
- source HLS dari Bunny Stream
- URL berbentuk:

`{BUNNY_STREAM_CDN_BASE_URL}/{lesson_video_id}/playlist.m3u8`

---

# 3. Reference from Old Website

Website lama menunjukkan pola berikut:

1. lesson audio dirender terpisah dengan `<audio controls>`
2. Bunny video dirender menggunakan Video.js
3. source video memakai HLS playlist Bunny:
   - `.../{video_id}/playlist.m3u8`
4. poster video bisa memakai featured image / thumbnail fallback
5. playback mengandalkan Video.js, bukan embed biasa

Referensi lama ini bisa dipakai sebagai acuan implementasi behaviour, walaupun struktur Laravel + React sekarang berbeda. :contentReference[oaicite:0]{index=0}

---

# 4. Implementation Philosophy

Codex harus bekerja dengan prinsip berikut:

1. audit dulu sebelum mengubah kode
2. jika suatu tahap sudah benar, lewati
3. jika suatu tahap belum benar, perbaiki
4. jangan mengubah domain lain
5. jangan refactor besar di luar kebutuhan domain lesson video
6. fokus sampai video benar-benar tampil di student lesson page
7. lakukan test akhir dan laporkan hasilnya

---

# 5. Implementation Stages

---

## Stage 1 — Audit Data Layer

### Objective
Pastikan lesson benar-benar punya field video yang benar.

### What must be true
- tabel `lessons` memiliki field `lesson_video_id`
- nilai `lesson_video_id` tersimpan di database
- field lama seperti:
  - `video`
  - `video_url`
  - field warisan lain
  tidak lagi dipakai untuk student lesson video flow

### What Codex must check
- migration/schema lesson
- model Lesson
- fillable/cast jika relevan
- factory/seeder jika relevan
- record lesson yang sedang dites

### Skip condition
Jika `lesson_video_id` sudah ada dan data memang tersimpan benar, lewati tahap ini.

### Fix condition
Jika masih ada field lama yang dipakai, atau `lesson_video_id` belum tersimpan benar, perbaiki.

---

## Stage 2 — Audit Admin Lesson Form

### Objective
Pastikan admin memasukkan video lesson dengan cara yang benar.

### Final admin behaviour
- field yang dipakai adalah `lesson_video_id`
- input berupa text manual
- admin mengisi Bunny video GUID
- bukan URL YouTube
- bukan full HLS URL
- bukan upload file

### What Codex must check
- form create lesson
- form edit lesson
- request payload
- binding frontend form
- validation request

### Required hardening
- trim value
- empty string menjadi null
- tolak full URL
- hanya terima format Bunny-style UUID/GUID

### Skip condition
Jika admin form dan validasi sudah memakai `lesson_video_id` dengan benar, lewati.

### Fix condition
Jika admin masih bisa memasukkan URL arbitrary atau field lama, perbaiki.

---

## Stage 3 — Audit Database Content Quality

### Objective
Pastikan data `lesson_video_id` yang tersimpan memang valid.

### What must be true
- nilai field adalah Bunny video GUID
- bukan URL YouTube
- bukan URL lain
- bukan string arbitrary

### What Codex must check
- contoh record lesson aktif
- data lama yang salah format
- apakah ada lesson yang masih berisi URL lama

### Required behaviour
- data lama yang salah tidak harus dimigrasi otomatis sekarang
- tetapi sistem harus bisa mendeteksi dan memberi warning yang jujur

### Skip condition
Jika data yang dipakai sudah valid, lewati.

### Fix condition
Jika lesson yang sedang dites masih berisi URL lama/non-GUID, tandai sebagai invalid data dan arahkan admin memperbaikinya.

---

## Stage 4 — Audit Bunny Stream Runtime Config

### Objective
Pastikan Laravel runtime benar-benar membaca config Bunny Stream yang diperlukan.

### Required config
- `BUNNY_STREAM_LIBRARY_ID`
- `BUNNY_STREAM_ACCESS_KEY`
- `BUNNY_STREAM_CDN_BASE_URL`

### What Codex must check
- `.env`
- `config/bunny.php`
- runtime:
  - `config('bunny.stream.library_id')`
  - `config('bunny.stream.access_key')`
  - `config('bunny.stream.cdn_base_url')`

### Important rule
Video lesson playback di student side **tidak bisa jalan** kalau `cdn_base_url` kosong.

### Skip condition
Jika runtime config sudah terbaca benar, lewati.

### Fix condition
Jika mapping config salah atau runtime masih kosong, perbaiki.

---

## Stage 5 — Audit Student Backend Payload

### Objective
Pastikan backend student mengirim data video lesson yang benar.

### What must be true
Student controller harus:
- mengambil `lesson_video_id`
- mengevaluasi valid/tidak valid
- mengevaluasi config Bunny ada/tidak
- membentuk payload video yang jujur

### Recommended payload shape
Contoh arah payload:
- `lesson.lesson_video_id`
- `lesson.video.hls_url`
- `lesson.video.warning`
- `lesson.video.is_configured`
- `lesson.video.is_valid_id`

### Important rule
Frontend sebaiknya **tidak lagi membangun semua logic validasi sendiri**.  
Backend harus jadi source of truth untuk status video.

### Skip condition
Jika backend student sudah mengirim `lesson.video.hls_url` dan warning status dengan benar, lewati.

### Fix condition
Jika backend masih mengirim field lama atau frontend masih harus menebak-nebak sendiri, perbaiki.

---

## Stage 6 — Audit Student Lesson Page

### Objective
Pastikan halaman lesson student memakai field baru yang benar.

### What must be true
- lesson page memakai `lesson.video.hls_url`
- bukan membentuk URL dari field lama
- bukan memakai:
  - `video`
  - `video_url`
  - field lama lain

### Required behaviour
- jika `hls_url` valid → render player
- jika config kosong → tampilkan warning yang jujur
- jika `lesson_video_id` invalid → tampilkan warning yang jujur
- jangan diam saja / blank tanpa penjelasan

### Skip condition
Jika lesson page sudah memakai payload video backend dengan benar, lewati.

### Fix condition
Jika lesson page masih memakai field lama atau membentuk URL dengan cara yang salah, perbaiki.

---

## Stage 7 — Audit VideoJsPlayer Integration

### Objective
Pastikan Video.js benar-benar memutar HLS Bunny Stream.

### What must be true
- player diinisialisasi dengan aman
- source HLS diberikan dengan format yang benar
- player tidak di-dispose salah saat props berubah
- lifecycle React aman

### What Codex must check
- `VideoJsPlayer.jsx`
- prop input ke player
- source format HLS
- MIME type
- poster handling
- init/update/dispose lifecycle

### Recommended source format
Player harus menerima source HLS yang benar, misalnya object source HLS yang sesuai untuk Video.js.

### Required behaviour
- HLS URL valid harus bisa diputar
- jika URL valid tapi player gagal, audit ada di layer player/network

### Skip condition
Jika Video.js integration sudah benar dan player bisa memuat HLS valid, lewati.

### Fix condition
Jika masalah ada di lifecycle/config/source Video.js, perbaiki.

---

## Stage 8 — Audit Network-Level Playback

### Objective
Jika HLS URL valid tetapi video masih gagal tampil, audit layer network/media request.

### What Codex must check
Di browser DevTools / evidence aktual:
- request `playlist.m3u8`
- request segment `.ts` / `.m4s`
- status code
- CORS
- content-type
- response error
- console VHS/Video.js error

### Important rule
Tahap ini hanya dikerjakan jika:
- backend benar
- HLS URL valid
- Video.js integration sudah dibenahi
- tetapi video masih belum tampil

### Skip condition
Jika video sudah tampil normal sebelum tahap ini, lewati.

### Fix condition
Jika ternyata segment request gagal / header Bunny CDN bermasalah, laporkan dan perbaiki sebatas scope yang aman.

---

## Stage 9 — Audit Admin to Student End-to-End

### Objective
Pastikan video yang diinput dari admin benar-benar sampai ke student lesson page.

### What Codex must verify
1. admin mengisi `lesson_video_id`
2. database menyimpan nilai benar
3. student backend membaca nilai yang sama
4. backend membentuk `hls_url`
5. frontend menerima `hls_url`
6. player memutar video

### Important rule
Ini adalah tahap sinkronisasi akhir antara admin dan student.

### Skip condition
Jika seluruh alur sudah terbukti benar, lewati.

### Fix condition
Jika ada titik putus, perbaiki titik itu tanpa melebar ke domain lain.

---

# 6. Warning and Fallback Rules

## 6.1 Missing Bunny Stream Config
Jika `BUNNY_STREAM_CDN_BASE_URL` kosong:
- jangan crash
- jangan blank diam-diam
- tampilkan warning yang jelas

## 6.2 Invalid lesson_video_id
Jika `lesson_video_id` bukan Bunny GUID valid:
- jangan crash
- jangan kirim URL palsu
- tampilkan warning yang jelas

## 6.3 Legacy Data
Jika ada data lama berupa URL YouTube:
- jangan diperlakukan sebagai Bunny video
- tampilkan warning / invalid state
- jangan rusak total halaman lesson

---

# 7. Out of Scope

Tahap implementasi ini **tidak mencakup**:
- lecturer video
- Bunny Storage asset lain
- email
- assessment
- progress tracking baru
- redesign UI lesson secara besar
- migration massal data video lama
- queue video processing
- domain lain di luar lesson video playback

---

# 8. Required Testing

Codex wajib melakukan testing di akhir.

## 8.1 Admin-side test
Uji:
1. create lesson dengan `lesson_video_id` valid
2. edit lesson dengan `lesson_video_id` valid
3. input invalid value seperti URL YouTube → harus ditolak
4. input kosong → harus aman sesuai rule validasi

## 8.2 Backend/runtime test
Uji:
1. runtime config Bunny Stream terbaca
2. `lesson_video_id` valid dibentuk menjadi `hls_url`
3. jika config kosong → warning muncul
4. jika ID invalid → warning muncul

## 8.3 Student-side test
Uji:
1. lesson page menerima payload video yang benar
2. player muncul saat `hls_url` valid
3. video benar-benar bisa diputar
4. warning muncul jika config/ID salah
5. halaman tidak crash

## 8.4 Network-level verification
Jika video masih gagal:
1. cek request `playlist.m3u8`
2. cek request segment
3. identifikasi apakah masalah di:
   - URL
   - CORS
   - header
   - player config
   - media request

---

# 9. Definition of Done

Domain lesson video dianggap selesai jika seluruh kondisi ini terpenuhi:

1. admin mengisi `lesson_video_id` dengan benar
2. validasi admin menolak URL arbitrary
3. runtime Bunny Stream config terbaca
4. student backend mengirim payload video yang benar
5. student page memakai field video yang benar
6. Video.js memutar HLS Bunny Stream
7. video tampil normal di lesson student page
8. jika ada masalah config/data, warning tampil jujur
9. test akhir sudah dijalankan dan hasilnya dilaporkan

---

# 10. Instructions for Codex

Codex harus bekerja dengan aturan ini:

1. mulai dari audit, jangan langsung refactor besar
2. lewati tahap yang sudah benar
3. kerjakan hanya tahap yang belum benar
4. fokus sampai video lesson benar-benar tampil
5. jangan mengubah domain lain
6. lakukan test akhir
7. laporkan:
   - tahap mana yang dilewati
   - tahap mana yang diperbaiki
   - hasil test akhir

---

# 11. Final Summary

Target implementasi ini bukan sekadar “mengurangi error”, tetapi memastikan bahwa:

- video lesson yang diinput dari sisi admin
- tersimpan benar di database
- diproses benar di backend
- dikirim benar ke frontend
- dan benar-benar tampil serta bisa diputar di student lesson page

Jika ada tahap yang sudah benar, Codex harus melewatinya.
Jika ada tahap yang belum benar, Codex harus mengerjakannya sampai selesai.