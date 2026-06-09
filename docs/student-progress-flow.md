# Student Progress Flow
# YogaFX LMS

## Purpose

Dokumen ini menjadi source of truth flow untuk fitur **Student Progress** pada sisi Admin, khususnya untuk 3 area utama:

1. Completed Lesson
2. Assignment
3. Certificate

Dokumen ini dibuat berdasarkan PRD YogaFX LMS, terutama bagian Student Progress, serta keputusan sistem yang sudah disepakati sebelumnya.

---

# 1. Scope

Fitur Student Progress pada halaman detail siswa memiliki 3 kelompok aksi utama:

## 1.1 Completed Lesson
Admin dapat:
- melihat daftar lesson yang sudah diselesaikan student
- melakukan reset progress jika diperlukan

## 1.2 Assignment
Admin dapat:
- melihat daftar assignment / graduation video
- melihat status assignment
- melihat feedback jika status rejected
- melakukan aksi:
  - Save
  - Send Email
  - Delete Video

## 1.3 Certificate
Admin dapat:
- generate certificate
- send graduation email
- memilih 2 jenis certificate:
  - Bikram Yoga Certificate
  - Yoga Alliance Certification
- melakukan aksi pada certificate:
  - Recreate Certificate
  - Download Certificate
  - Delete Certificate

---

# 2. Completed Lesson Flow

## Purpose
Memungkinkan admin melihat lesson yang telah diselesaikan student dan melakukan reset progress bila dibutuhkan.

## Trigger
- Admin membuka halaman detail progress student
- Admin menekan tombol **Completed Lesson**

## Preconditions
- Admin sudah login
- Student tersedia dan valid
- Data lesson progress student tersedia

## Main Flow
1. Admin membuka detail student.
2. Admin memilih tab atau tombol **Completed Lesson**.
3. Sistem menampilkan daftar lesson yang statusnya completed untuk student tersebut.
4. Admin meninjau daftar completed lesson.
5. Jika diperlukan, admin menekan tombol **Reset Progress** pada lesson tertentu atau sesuai scope reset yang disediakan sistem.
6. Sistem meminta konfirmasi reset.
7. Admin mengonfirmasi reset.
8. Sistem menghapus atau mengubah status progress lesson terkait.
9. Sistem memperbarui tampilan daftar completed lesson.

## Alternative Flow
- Jika student belum menyelesaikan lesson apa pun, sistem menampilkan empty state.
- Jika reset berlaku per lesson, admin hanya mengubah 1 lesson.
- Jika reset berlaku lebih luas, sistem dapat meminta konfirmasi tambahan.

## Failure Flow
- Student tidak ditemukan.
- Data progress gagal dimuat.
- Reset gagal dilakukan.
- Admin membatalkan konfirmasi reset.

## Success Outcome
- Admin dapat melihat daftar lesson selesai.
- Reset progress berhasil diterapkan bila dikonfirmasi.
- Data progress student ter-update.

---

# 3. Assignment Flow

## Purpose
Memungkinkan admin memantau assignment / graduation video student dan menjalankan aksi operasional yang tersedia.

## Trigger
- Admin membuka halaman detail progress student
- Admin menekan tombol **Assignment**

## Preconditions
- Admin sudah login
- Student valid
- Data assignment student tersedia atau sistem siap menampilkan empty state

## Main Flow
1. Admin membuka detail student.
2. Admin memilih tab atau tombol **Assignment**.
3. Sistem menampilkan tabel assignment dengan kolom:
   - Title
   - Video
   - Status
   - Feedback (khusus jika rejected)
4. Admin memilih assignment tertentu untuk ditinjau.
5. Admin dapat melihat video assignment.
6. Admin dapat menjalankan aksi sesuai kebutuhan:
   - Save
   - Send Email
   - Delete Video
7. Jika admin memilih **Save**, sistem menyimpan perubahan data assignment yang relevan.
8. Jika admin memilih **Send Email**, sistem memicu proses pengiriman email sesuai template assignment yang relevan.
9. Jika admin memilih **Delete Video**, sistem meminta konfirmasi.
10. Setelah konfirmasi, sistem menghapus video assignment terkait dan memperbarui status tampilan.

## Alternative Flow
- Jika assignment belum ada, sistem menampilkan empty state.
- Jika assignment rejected, feedback tampil dan dapat menjadi dasar follow-up.
- Jika email template tidak aktif, aksi send email dapat ditolak atau memberi peringatan.

## Failure Flow
- Student tidak ditemukan.
- Assignment gagal dimuat.
- Video gagal diputar.
- Save gagal.
- Send Email gagal.
- Delete Video gagal atau dibatalkan.

## Success Outcome
- Admin dapat melihat status assignment student.
- Admin dapat melakukan aksi operasional terhadap assignment.
- Perubahan assignment tersimpan dengan benar.

---

# 4. Certificate Flow

## Purpose
Memungkinkan admin mengelola certificate student sebagai hasil akhir proses pembelajaran.

## Trigger
- Admin membuka halaman detail progress student
- Admin menekan tombol **Certificate**

## Preconditions
- Admin sudah login
- Student valid
- Student memenuhi syarat certificate sesuai rule bisnis yang berlaku
- Sistem mendukung generate certificate dan graduation email

## Main Flow
1. Admin membuka detail student.
2. Admin memilih tab atau tombol **Certificate**.
3. Sistem menampilkan area certificate management.
4. Admin memilih aksi **Generate Certificate**.
5. Admin memilih jenis certificate:
   - Bikram Yoga Certificate
   - Yoga Alliance Certification
6. Sistem membuat certificate.
7. Setelah certificate berhasil dibuat, sistem menyimpan data certificate student.
8. Admin dapat menjalankan aksi lanjutan:
   - Recreate Certificate
   - Download Certificate
   - Delete Certificate
9. Jika admin memilih **Send Graduation Email**, sistem memicu email certificate created / graduation email sesuai template yang aktif.
10. Sistem memperbarui tampilan daftar/status certificate.

## Alternative Flow
- Student dapat memiliki lebih dari satu jenis certificate jika rule bisnis mengizinkan.
- Recreate menghasilkan versi certificate baru.
- Download hanya tersedia jika file certificate sudah ada.

## Failure Flow
- Student tidak ditemukan.
- Student belum eligible.
- Certificate gagal dibuat.
- Download gagal.
- Delete gagal.
- Graduation email gagal dikirim.

## Success Outcome
- Certificate berhasil dibuat dan tersimpan.
- Admin dapat mengelola certificate student.
- Student siap menerima atau mengakses certificate sesuai flow sistem.

---

# 5. Key Business Rules Referenced

## Completed Lesson
- Lesson completion berasal dari progress belajar student.
- Lesson dapat dianggap selesai berdasarkan rule lesson yang berlaku.
- Reset Progress adalah aksi admin-level dan harus melalui konfirmasi.

## Assignment
- Assignment merupakan graduation video.
- Status assignment harus terlihat jelas.
- Feedback hanya relevan terutama saat status rejected.
- Send Email harus mengikuti template notifikasi yang aktif.

## Certificate
- Ada 2 jenis certificate:
  - Bikram Yoga Certificate
  - Yoga Alliance Certification
- Certificate dibuat dari sisi admin.
- Certificate dapat direcreate, didownload, atau dihapus.
- Graduation email / certificate created email dipicu dari aksi admin.

---

# 6. UI Notes

## Student Progress Detail Page
Halaman detail student progress minimal memiliki 3 entry point utama:
- Completed Lesson
- Assignment
- Certificate

## Completed Lesson UI
- Tampilkan list completed lesson
- Tampilkan tombol Reset Progress
- Gunakan konfirmasi sebelum reset

## Assignment UI
- Gunakan tabel
- Kolom minimal:
  - Title
  - Video
  - Status
  - Feedback
- Tampilkan aksi:
  - Save
  - Send Email
  - Delete Video

## Certificate UI
- Tampilkan area aksi utama:
  - Generate Certificate
  - Send Graduation Email
- Tampilkan jenis certificate
- Tampilkan aksi per certificate:
  - Recreate
  - Download
  - Delete

---

# 7. Final Notes

Flow ini hanya mencakup area Student Progress di sisi Admin, bukan seluruh student learning flow.

Dokumen ini akan dipakai sebagai source of truth implementasi untuk:
- halaman detail student progress
- aksi completed lesson
- aksi assignment
- aksi certificate
- integrasi notifikasi terkait