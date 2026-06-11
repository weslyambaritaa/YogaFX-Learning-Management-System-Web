# Assessment Results Flow
# YogaFX LMS

## Purpose

Dokumen ini menjadi source of truth untuk flow **Assessment Results** pada YogaFX LMS.

Flow ini mencakup:
- daftar assessment
- preview assessment
- view results
- detail hasil pengerjaan per user
- delete result

Dokumen ini digunakan sebagai acuan implementasi untuk admin side.

---

# 1. Scope

Fitur Assessment pada admin memiliki 4 aksi utama dari halaman list:

1. **Edit**
2. **Delete**
3. **Preview**
4. **View Results**

---

# 2. Assessment List Flow

## Purpose
Menampilkan semua assessment yang tersedia dan menyediakan aksi utama per assessment.

## Trigger
Admin membuka menu Assessment.

## Preconditions
- Admin sudah login
- Assessment sudah tersedia di sistem

## Main Flow
1. Admin membuka halaman Assessment.
2. Sistem menampilkan daftar assessment.
3. Setiap assessment memiliki action:
   - Edit
   - Delete
   - Preview
   - View Results
4. Admin memilih salah satu action.

## Alternative Flow
- Jika belum ada assessment, tampilkan empty state.
- Admin bisa menambah assessment baru dari halaman ini jika fitur create tersedia.

## Failure Flow
- Data assessment gagal dimuat.
- Role selain admin mencoba mengakses halaman ini.

## Success Outcome
- Admin dapat melihat daftar assessment dan memilih aksi lanjutan.

---

# 3. Preview Flow

## Purpose
Menampilkan simulasi pengerjaan assessment seperti pengalaman student, tanpa masuk ke data attempt nyata.

## Trigger
Admin menekan tombol **Preview** pada salah satu assessment.

## Preconditions
- Admin sudah login
- Assessment valid dan dapat dibuka
- Struktur question/answer assessment tersedia

## Main Flow
1. Admin membuka halaman Assessment.
2. Admin menekan **Preview** pada salah satu assessment.
3. Sistem membuka halaman preview.
4. Preview menampilkan simulasi pengerjaan assessment.
5. Admin dapat:
   - melihat urutan soal
   - melihat pilihan jawaban
   - melihat flow jump logic jika ada
   - melihat tampilan akhir/result simulasi

## Alternative Flow
- Preview bisa dibuka mulai dari soal pertama.
- Preview dapat mengikuti flow student, tetapi tidak menyimpan hasil sebagai result nyata.

## Failure Flow
- Assessment rusak / tidak lengkap
- Preview gagal memuat question
- Konfigurasi assessment belum valid

## Success Outcome
- Admin dapat mensimulasikan pengalaman pengerjaan assessment tanpa membuat result nyata.

---

# 4. View Results Flow

## Purpose
Menampilkan semua user yang telah menyelesaikan assessment tertentu.

## Trigger
Admin menekan tombol **View Results** pada salah satu assessment.

## Preconditions
- Admin sudah login
- Assessment valid
- Ada atau tidak ada result yang tersimpan

## Main Flow
1. Admin membuka halaman Assessment.
2. Admin menekan **View Results** pada salah satu assessment.
3. Sistem membuka halaman daftar result assessment tersebut.
4. Sistem menampilkan semua akun yang telah menyelesaikan assessment.
5. Setiap row result minimal dapat menampilkan:
   - Name
   - Email
   - Score / Correct Answers
   - Percentage
   - Completion info
6. Admin dapat:
   - membuka detail result
   - menghapus result tertentu

## Alternative Flow
- Jika belum ada user yang menyelesaikan assessment, tampilkan empty state.
- Daftar result dapat mendukung sorting/filtering di masa depan.

## Failure Flow
- Assessment tidak ditemukan
- Result gagal dimuat

## Success Outcome
- Admin dapat melihat semua hasil pengerjaan assessment untuk assessment tertentu.

---

# 5. Result Detail Flow

## Purpose
Menampilkan detail hasil pengerjaan satu user untuk satu assessment.

## Trigger
Admin menekan salah satu row / tombol **View** dari halaman View Results.

## Preconditions
- Admin sudah login
- Result attempt yang dipilih valid
- Data jawaban user tersedia

## Main Flow
1. Admin membuka halaman View Results.
2. Admin memilih salah satu result milik user.
3. Sistem membuka halaman detail result.
4. Sistem menampilkan ringkasan hasil:
   - Back to Results
   - Name
   - Email
   - Phone
   - Correct Answers
   - Points
   - Percentage
   - Duration (hours / minutes / seconds)
5. Sistem menampilkan review seluruh question.
6. Untuk setiap question, sistem menampilkan:
   - pertanyaan
   - pilihan jawaban
   - jawaban user
   - konteks mana yang benar / salah jika tipe soal mendukungnya
7. Admin dapat kembali ke halaman result list.

## Alternative Flow
- Untuk type non-option, tampilan review menyesuaikan jenis jawaban.
- Jika phone kosong, tampilkan kosong atau fallback yang rapi.

## Failure Flow
- Result detail tidak ditemukan
- Data answer tidak sinkron
- Assessment/attempt rusak

## Success Outcome
- Admin dapat melihat detail lengkap hasil pengerjaan satu user.

---

# 6. Delete Result Flow

## Purpose
Memungkinkan admin menghapus satu hasil assessment user tanpa menghapus assessment utamanya.

## Trigger
Admin menekan tombol **Delete** pada salah satu result.

## Preconditions
- Admin sudah login
- Result attempt valid

## Main Flow
1. Admin membuka halaman View Results.
2. Admin memilih salah satu result.
3. Admin menekan tombol **Delete**.
4. Sistem menampilkan konfirmasi delete.
5. Admin mengonfirmasi.
6. Sistem menghapus data hasil assessment user tersebut.
7. Sistem memperbarui daftar results.

## Alternative Flow
- Delete dapat dilakukan dari list result atau dari detail result, tergantung implementasi UI.

## Failure Flow
- Admin membatalkan delete
- Result tidak ditemukan
- Delete gagal karena constraint data

## Success Outcome
- Result user terhapus
- Assessment utama tetap ada
- Result user lain tidak terpengaruh

---

# 7. Summary of UI Screens

## 7.1 Assessment List
Menampilkan:
- daftar assessment
- action:
  - Edit
  - Delete
  - Preview
  - View Results

## 7.2 Assessment Preview
Menampilkan:
- simulasi pengerjaan assessment
- preview question flow
- preview result flow

## 7.3 Assessment Results List
Menampilkan:
- semua user yang telah menyelesaikan assessment
- ringkasan hasil tiap user
- action:
  - View
  - Delete

## 7.4 Assessment Result Detail
Menampilkan:
- summary:
  - Name
  - Email
  - Phone
  - Correct Answers
  - Points
  - Percentage
  - Duration
- daftar seluruh question dan jawaban user
- tombol Back to Results

---

# 8. Business Rules

1. Hanya admin yang dapat membuka Assessment Results.
2. Preview tidak membuat result nyata.
3. View Results hanya menampilkan user yang benar-benar sudah menyelesaikan assessment.
4. Delete Result hanya menghapus hasil user, bukan assessment utamanya.
5. Result detail harus menampilkan summary dan review question secara lengkap.
6. Halaman Back to Results harus kembali ke daftar result assessment yang sama.

---

# 9. Final Notes

Dokumen ini khusus untuk flow hasil assessment di admin side.

Dokumen ini tidak membahas:
- builder scoreboard
- create/edit assessment
- student assessment flow detail

Dokumen ini fokus pada:
- preview
- result listing
- result detail
- delete result