# Mobile Student Codex Handoff
# YogaFX LMS

## Purpose

Dokumen ini adalah handoff khusus untuk Codex yang akan mengerjakan **student mobile app**.

Fokus dokumen ini **hanya** pada 2 problem aktif:

1. fitur assessment di mobile belum bisa diakses / error saat dibuka
2. flow ganti password di mobile masih langsung dari profile, padahal harus dialihkan ke flow berbasis email

Dokumen ini sengaja ditulis detail agar Codex bisa langsung paham:
- konteks product
- batas scope
- backend yang sudah tersedia
- behavior yang wajib dijaga
- acceptance criteria yang tidak boleh salah

---

## Project Context

YogaFX LMS adalah platform pembelajaran premium dengan role aktif:
- Admin
- Student

Untuk mobile app ini, area yang dikerjakan adalah:
- **Student mobile app only**

Jangan menyentuh atau mendesain ulang:
- admin app
- admin dashboard
- web admin flow
- arsitektur backend besar-besaran
- produk menjadi LMS sekolah/kampus

Student side harus terasa:
- premium
- calm
- content-first
- clear next step guidance

---

## Mandatory Reading Order

Sebelum coding, Codex harus baca dokumen ini dulu:

1. `docs/00-current-project-status.md`
2. `docs/01-prd.md`
3. `docs/02-user-flow.md`
4. `docs/03-erd.md`
5. `docs/04-design-system.md`
6. `docs/05-information-architecture.md`
7. `docs/06-modular-implementation.md`

Lalu baca dokumen mobile yang relevan:

8. `docs/mobile/student-mobile-feature-map.md`
9. `docs/mobile/mobile-modular-implementation.md`
10. `docs/mobile-flutter-backend-integration-guide.md`

Kalau perlu validasi teknis tambahan, cek implementasi backend berikut:

- `routes/api.php`
- `app/Http/Controllers/Mobile/V1/AssessmentController.php`
- `app/Services/Mobile/V1/StudentAssessmentApiService.php`
- `app/Http/Controllers/Mobile/V1/ProfileController.php`
- `app/Http/Controllers/Mobile/V1/PasswordRecoveryController.php`
- `app/Http/Controllers/Student/ProfilePasswordController.php`

Jangan skip urutan baca.

---

## Scope For This Task

Kerjakan **hanya** 2 domain berikut:

1. assessment mobile access + assessment player handling
2. password change flow via email pada mobile student

Jangan sekaligus mengerjakan:
- assignment
- certificate
- dashboard redesign
- module refactor besar
- profile redesign besar
- admin-side assessment builder
- perubahan business rule backend yang tidak diperlukan

---

## Current Reality You Must Respect

### 1. Assessment mobile API sudah ada

Backend mobile API untuk assessment **sudah tersedia**, jadi jangan berasumsi assessment belum ada.

Endpoint yang aktif:
- `GET /api/mobile/v1/lessons/{lesson}/assessment`
- `POST /api/mobile/v1/lessons/{lesson}/assessment/start`
- `GET /api/mobile/v1/lessons/{lesson}/assessment/attempts/{attempt}`
- `POST /api/mobile/v1/lessons/{lesson}/assessment/attempts/{attempt}/answer`
- `POST /api/mobile/v1/lessons/{lesson}/assessment/attempts/{attempt}/back`
- `GET /api/mobile/v1/lessons/{lesson}/assessment/attempts/{attempt}/result`

### 2. Backend already enforces correctness gate

Untuk pertanyaan option-based yang punya jawaban benar/salah, backend **sudah memiliki gate**:
- jika student memilih jawaban salah, request answer akan gagal `422`
- student **tidak boleh lanjut** ke soal berikutnya
- backend mengembalikan pesan:
  - `Oops!!! Wrong Answer! Please refer to your workbook and try again.`

Artinya mobile app **tidak perlu menghitung jawaban benar sendiri**.
Mobile app hanya perlu:
- kirim jawaban ke backend
- baca response
- jika `422`, tampilkan error yang jelas
- tetap berada di soal yang sama
- izinkan student mencoba lagi sampai benar
- hanya lanjut jika backend mengembalikan success redirect ke soal berikutnya

### 3. Mobile password change saat ini masih direct

Saat ini mobile API masih punya endpoint:
- `POST /api/mobile/v1/profile/change-password`

Endpoint ini mengganti password langsung dengan:
- `current_password`
- `new_password`
- `new_password_confirmation`

Tetapi kebutuhan sekarang adalah:
- **ganti password harus melalui email**
- bukan langsung ubah password dari form profile biasa

### 4. Forgot/reset password API already exists

Backend mobile API yang sudah tersedia dan harus dipakai:
- `POST /api/mobile/v1/auth/forgot-password`
- `POST /api/mobile/v1/auth/reset-password`

Jadi untuk mobile app:
- jangan pakai flow direct profile password change sebagai primary UX
- gunakan flow email reset password

---

## Problem Statements

### Problem A - Assessment cannot be opened / errors on access

Gejala saat ini:
- student membuka assessment di mobile
- assessment gagal dibuka atau error
- flow belum stabil dari intro ke start ke attempt ke result

### Problem B - Wrong answer flow is critical and must not be wrong

Behavior wajib:
- jika student memilih jawaban salah
- student **tidak bisa lanjut**
- tampil tulisan bahwa jawabannya salah
- student tetap di soal yang sama
- student mencoba lagi
- setelah menemukan jawaban yang benar
- baru boleh lanjut ke soal berikutnya

Ini **tidak boleh salah implementasi**.

### Problem C - Password change still happens directly from profile

Behavior sekarang yang tidak diinginkan:
- student buka profile
- student ganti password langsung dari form di profile

Behavior yang diinginkan:
- student memilih aksi ganti password
- app mengarahkan ke flow berbasis email
- email reset / password change dikirim
- student menyelesaikan reset password lewat flow email

---

## Required Outcome

Setelah task ini selesai:

1. assessment bisa dibuka dari mobile tanpa error
2. assessment intro, start, question, back, dan result flow berjalan
3. wrong answer tidak boleh meloloskan student ke soal berikutnya
4. error wrong answer tampil jelas di UI
5. student bisa mencoba lagi sampai benar
6. password change tidak lagi mengandalkan direct change dari profile sebagai flow utama
7. dari mobile, ganti password harus memakai email-based flow

---

## Assessment Requirements

## A. Do not invent assessment rules

Backend adalah source of truth.

Flutter/mobile app tidak boleh:
- menghitung sendiri jawaban mana yang benar
- mengasumsikan semua pertanyaan bebas lanjut
- melewati response backend
- force navigate ke soal berikutnya

Mobile app harus mengikuti response backend apa adanya.

## B. Assessment access preconditions

Assessment hanya bisa diakses jika backend mengizinkan:
- lesson punya assessment
- assessment live
- assessment active
- lesson tier cocok
- module tier cocok
- assessment sudah unlocked

Unlock rule penting:
- jika lesson punya video, assessment baru unlocked saat watch progress >= 95
- jika lesson tidak punya video, assessment bisa unlocked langsung

Jadi saat mobile gagal akses assessment:
- jangan langsung crash
- baca `403`, `404`, `422`
- tampilkan state yang sesuai

## C. Assessment API response modes that must be handled

### 1. Intro

`GET /lessons/{lesson}/assessment`

UI harus bisa menampilkan:
- lesson info
- assessment info
- eligibility
- in-progress attempt jika ada
- completed attempt jika ada

### 2. Start

`POST /lessons/{lesson}/assessment/start`

Possible payload:
- `mode = completed`
- `mode = in_progress`

Behavior:
- jika `completed`, langsung arahkan ke result existing attempt
- jika `in_progress`, buka attempt screen dengan `attempt_id`

### 3. Attempt show

`GET /lessons/{lesson}/assessment/attempts/{attempt}`

Possible payload:
- `mode = result_redirect`
- `mode = question`

Behavior:
- jika `result_redirect`, buka result
- jika `question`, render question screen

### 4. Store answer

`POST /lessons/{lesson}/assessment/attempts/{attempt}/answer`

Possible success payload:
- `mode = result_redirect`
- `mode = question_redirect`

Possible validation failure:
- `422`
- `errors.option_ids`
- `errors.answer_text`
- `errors.answer_number`

Behavior:
- jika success `question_redirect`, ambil soal berikutnya
- jika success `result_redirect`, buka result
- jika `422`, tetap di soal sekarang dan tampilkan pesan error

### 5. Back

`POST /lessons/{lesson}/assessment/attempts/{attempt}/back`

Possible success payload:
- `mode = question_redirect`
- bisa juga `mode = result_redirect` jika attempt tidak lagi valid

### 6. Result

`GET /lessons/{lesson}/assessment/attempts/{attempt}/result`

Possible payload:
- `mode = attempt_redirect`
- atau payload result final

Behavior:
- jika `attempt_redirect`, kembali ke player
- jika result final, render result screen

---

## Critical Wrong Answer Rule

Ini rule paling penting untuk task assessment.

### What must happen

Jika question option-based memiliki correctness gate:
- student pilih jawaban salah
- request answer dikirim ke backend
- backend mengembalikan `422`
- app menampilkan tulisan salah
- app **tetap berada di soal yang sama**
- app **tidak** increment progress ke soal berikutnya
- app **tidak** mengganti `attempt_id`
- app **tidak** menyimpan local state seolah jawaban itu accepted
- student boleh mencoba lagi
- setelah jawaban benar dikirim dan backend success
- baru pindah ke soal berikutnya

### Minimum required UI behavior

Wajib ada:
- pesan error yang terlihat jelas
- tombol/CTA tetap bisa dipakai untuk mencoba lagi
- pilihan sebelumnya boleh tetap terpilih atau bisa dipilih ulang, tetapi flow jangan macet
- tidak boleh terjadi auto-next saat response error

### Exact backend error message that may appear

Pesan dari backend saat wrong answer option-based:

`Oops!!! Wrong Answer! Please refer to your workbook and try again.`

Minimal:
- tampilkan pesan ini apa adanya, atau
- map ke copy UI yang setara tanpa mengubah makna

Makna yang harus tersampaikan:
- jawaban salah
- belum bisa lanjut
- coba lagi

### Important implementation rule

Jangan gunakan local "correct answer checking" di Flutter kalau backend belum mengirim kontrak resmi untuk itu.

Gunakan backend sebagai satu-satunya keputusan apakah user boleh lanjut atau tidak.

---

## Password Via Email Requirements

## A. Product decision for mobile

Flow ganti password pada mobile student harus lewat email.

Artinya dari sudut UX:
- student tidak lagi mengganti password langsung di halaman profile dengan current/new password form sebagai flow utama
- student harus menggunakan email reset flow

## B. Required mobile UX direction

Direkomendasikan flow:

1. student buka profile / security area
2. student tekan `Change Password`
3. app tampilkan layar konfirmasi bahwa link/reset instruction akan dikirim ke email akun
4. app kirim request ke:
   - `POST /api/mobile/v1/auth/forgot-password`
5. jika sukses:
   - tampilkan success state
   - tampilkan email tujuan
   - beri instruksi cek inbox/spam
6. student lanjut ke screen reset password setelah membuka link/token dari email
7. app selesaikan password reset melalui:
   - `POST /api/mobile/v1/auth/reset-password`

## C. What should change in mobile app

Yang perlu diubah:
- jangan jadikan `POST /profile/change-password` sebagai flow utama di screen profile
- ganti CTA dan navigation agar mengarah ke email reset flow
- kalau screen form direct-change sudah ada, screen itu harus dihapus dari primary flow atau dinonaktifkan

## D. If keeping legacy code temporarily

Kalau masih ada repository/service lama untuk `POST /profile/change-password`:
- jangan dipakai oleh UI utama
- jangan expose sebagai button utama
- kalau tetap disimpan sementara untuk backward compatibility internal, jangan dijadikan default path

## E. Success state requirements

Setelah forgot-password sukses:
- tampilkan pesan sukses
- sebutkan bahwa email reset sudah dikirim
- arahkan student untuk cek inbox dan spam

Setelah reset-password sukses:
- tampilkan pesan bahwa password berhasil direset
- arahkan user untuk login ulang dengan password baru

---

## Recommended Mobile Implementation Plan

Kerjakan berurutan. Jangan campur semuanya sekaligus.

### Phase 1 - Audit existing mobile code

Cari dan pahami:
- screen assessment intro
- screen assessment player
- assessment repository / datasource / API client
- profile screen
- change password screen
- forgot password screen
- reset password screen
- app router / deep link handling untuk reset password

Catat:
- bagian mana sudah benar
- bagian mana yang sudah connect ke endpoint benar
- bagian mana yang crash / belum handle response mode

### Phase 2 - Fix assessment access flow

Pastikan urutan ini berjalan:
- lesson detail -> open assessment intro
- intro -> start
- start -> attempt or result
- attempt -> answer
- answer -> next question or result
- back -> previous question

Prioritas bugfix:
- response mode handling
- 403/404/422 state handling
- null-safe parsing
- no crash on inaccessible assessment

### Phase 3 - Fix wrong answer gating

Pastikan di player:
- submit answer async state aman
- saat `422`, jangan navigate
- tampilkan error
- tetap di current question
- izinkan retry
- saat success baru navigate

### Phase 4 - Replace direct password change UX

Ubah security/password area menjadi:
- request reset email
- confirm email sent
- reset password form via token/email flow

Kalau project mobile pakai deep link:
- sambungkan token/email dari link ke reset screen

Kalau belum ada deep link:
- minimal support input token + email secara manual atau dari route param yang tersedia

### Phase 5 - Verification

Lakukan end-to-end verification untuk dua domain ini.

---

## Acceptance Criteria

Task dianggap selesai hanya jika semua poin ini terpenuhi.

### Assessment

- student bisa membuka assessment intro dari lesson yang eligible
- student bisa memulai assessment
- student bisa melihat soal
- student bisa menjawab soal
- saat jawaban salah pada soal dengan correctness gate:
  - student tidak lanjut
  - ada tulisan salah / error visible
  - tetap di soal yang sama
  - bisa mencoba lagi
- saat jawaban benar:
  - student bisa lanjut ke soal berikutnya
- tombol back berjalan sesuai rule backend
- assessment result bisa dibuka saat attempt selesai
- app tidak crash pada mode:
  - `completed`
  - `in_progress`
  - `question`
  - `question_redirect`
  - `result_redirect`
  - `attempt_redirect`

### Password via Email

- dari profile/security area, student diarahkan ke email-based password flow
- app bisa mengirim forgot password request
- success state email sent tampil jelas
- app bisa menyelesaikan reset password dengan token/email/password/password_confirmation
- setelah reset sukses, user diarahkan untuk login ulang
- direct change password dari profile tidak lagi menjadi flow utama

---

## Non-Goals

Jangan kerjakan ini dalam task yang sama:

- redesign visual besar-besaran seluruh app
- refactor semua repository/data layer jika tidak perlu
- mengganti business rule backend assessment
- membuat admin assessment builder
- membuat flow assignment/certificate
- membangun analytics
- mengubah produk menjadi offline-first

---

## Technical Guardrails

### Assessment

- backend is canonical
- jangan hitung correctness di client
- jangan paksa next question kalau backend belum memberi success
- semua `422` harus dianggap valid UI state, bukan crash
- semua `403` dengan assessment locked/unavailable harus jadi state yang bisa dipahami user

### Password

- gunakan `forgot-password` dan `reset-password` endpoints yang sudah ada
- jangan mempertahankan direct password update sebagai UX utama
- jangan menambah endpoint backend baru kalau sebenarnya yang existing sudah cukup

---

## Manual Test Checklist

### Assessment Happy Path

1. login sebagai student valid
2. buka lesson yang assessment-nya unlocked
3. buka intro assessment
4. start assessment
5. jawab benar
6. lanjut ke soal berikutnya
7. selesaikan assessment
8. buka result

### Assessment Wrong Answer Path

1. buka assessment question yang punya correctness gate
2. pilih jawaban salah
3. submit
4. pastikan muncul error
5. pastikan tidak pindah ke soal berikutnya
6. pilih jawaban benar
7. submit lagi
8. pastikan baru pindah ke soal berikutnya

### Assessment Access Guard

1. buka lesson dengan assessment belum unlocked
2. pastikan UI tidak crash
3. tampilkan state yang menjelaskan belum bisa akses

### Password Email Flow

1. login
2. buka profile/security
3. tekan change password
4. kirim forgot password ke email akun
5. pastikan success state muncul
6. buka email / dapatkan token
7. submit reset password
8. login ulang dengan password baru

---

## Suggested Final Report Format For Codex

Saat selesai, Codex harus melaporkan:

1. apa yang diperbaiki pada assessment mobile
2. bagaimana wrong-answer gating sekarang bekerja
3. apa yang diubah pada password flow
4. endpoint backend apa yang dipakai
5. test/verifikasi apa yang berhasil dijalankan
6. apa yang tetap out of scope

---

## Final Instruction

Jangan menebak.
Jangan improvisasi business rule assessment.
Jangan diam-diam memperluas scope.

Perbaiki hanya:
- assessment mobile access dan player behavior
- password change via email flow

Dan yang paling penting:

**kalau jawaban salah, student tidak boleh lanjut.**
**student harus tetap di soal yang sama sampai menemukan jawaban yang benar.**

