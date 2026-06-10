# Student Progress Flow
# YogaFX LMS

## Purpose

Dokumen ini menjadi source of truth untuk fitur **Student Progress** pada sisi admin sesuai implementasi yang aktif saat ini.

Fitur ini sekarang menjadi pusat monitoring student. Menu `Students` terpisah tidak lagi menjadi entry utama di sidebar.

---

## 1. Entry Point

Admin membuka menu:
- `Student Progress`

Halaman utama Student Progress langsung menampilkan directory student, bukan child menu sidebar.

---

## 2. Directory Flow

### 2.1 Tier Sections
Directory dibagi menjadi 3 tabel berurutan:
1. Masterclass
2. Online
3. Starter Kit

### 2.2 Table Columns
Setiap tabel menampilkan:
- No
- Photo
- Name
- Progress
- Registration Date
- Assignment
- Action

### 2.3 Data Rules

#### Photo
- menggunakan `profile_photo` dari user jika ada
- jika kosong, tampil placeholder inisial nama

#### Progress
- dihitung dalam persentase `0-100%`
- basisnya adalah jumlah module yang selesai terhadap seluruh module yang relevan untuk tier student

#### Assignment
- hanya memakai dua status tampilan:
  - `Submitted`
  - `Not Submitted`
- `Submitted` berarti student sudah punya pengumpulan standing dan floor, atau legacy `graduation_video`

#### Registration Date
- diambil dari `created_at`

### 2.4 Action Pattern
Kolom `Action` memakai toggle titik tiga.

Isi menu:
- Completed Lesson
- Assignment
- Certificate

Catatan:
- action tidak ditampilkan sebagai tiga tombol langsung
- Student Progress tidak lagi memakai hamburger child menu di sidebar

---

## 3. Completed Lesson Flow

### Main Flow
1. Admin memilih `Completed Lesson` dari menu titik tiga pada student tertentu.
2. Sistem membuka halaman completed lessons untuk student tersebut.
3. Sistem menampilkan daftar `lesson_progress` yang `is_done = true`.
4. Admin dapat reset satu lesson progress.
5. Sebelum reset, sistem menampilkan konfirmasi.
6. Jika dikonfirmasi, nilai progress direset:
   - `watch_progress = 0`
   - `is_workbook_downloaded = false`
   - timestamp terkait di-null-kan
   - `is_done = false`
   - `completed_at = null`

### Empty State
Jika tidak ada completed lesson, tampilkan empty state.

---

## 4. Assignment Flow

### Main Flow
1. Admin memilih `Assignment` dari menu titik tiga.
2. Sistem membuka halaman assignment student-specific.
3. Sistem menampilkan tabel assignment dengan kolom:
   - Title
   - Video
   - Status
   - Feedback
4. Admin dapat:
   - Save
   - Send Email
   - Delete Video

### Save
- menyimpan perubahan `assignment_status`
- menyimpan `assignment_feedback`
- jika status menjadi approved atau rejected, `graded_at` diperbarui
- jika approved, event `assignment_approved` dipicu
- jika rejected, event `assignment_rejected` dipicu

### Send Email
- mengirim email manual sederhana ke student
- tidak mengganti template utama Email Notification

### Delete Video
- mengosongkan field `assignment_video`
- aksi harus melalui konfirmasi

---

## 5. Certificate Flow

### Main Flow
1. Admin memilih `Certificate` dari menu titik tiga.
2. Sistem membuka halaman certificate student-specific.
3. Sistem memeriksa eligibility certificate.
4. Admin dapat:
   - Generate Certificate
   - Send Graduation Email
   - Recreate Certificate
   - Download Certificate
   - Delete Certificate

### Certificate Types
Jenis yang aktif:
- Bikram Yoga Certificate
- Yoga Alliance Certification

### Eligibility Rule
Implementasi saat ini:
- student dengan tier `starter_kit` tidak eligible
- student dengan tier lain eligible selama punya `access_tier_id`

### Generate
- sistem membuat file HTML certificate di local storage
- sistem membuat record `certificates`
- sistem memicu event `certificate_created`

### Recreate
- membuat versi certificate baru dengan increment `version`

### Download
- file di-download dari local storage jika tersedia

### Delete
- file dihapus dari storage jika ada
- record certificate di-soft-delete

---

## 6. Student Profile Edit Route

Route edit profile student tetap ada di area Student Progress:
- admin dapat membuka detail/edit profile student
- ini bukan primary action dari kolom action directory
- fitur ini dipakai sebagai supporting route, bukan pusat flow utama Student Progress

---

## 7. Important Current Notes

- Student Progress adalah pengganti menu student monitoring lama
- directory sekarang menjadi halaman utama Student Progress
- sidebar tidak boleh auto-open section Student Progress atau Email tanpa aksi user
- detail progress dibuka sebagai halaman terpisah per area

---

## 8. Out of Scope

Belum termasuk dalam flow ini:
- student-side progress page
- progress automation dari watch progress
- assignment submission oleh student
- certificate access page di student side
- assessment progress monitoring yang penuh
