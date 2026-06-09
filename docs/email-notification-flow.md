# Email Notification Flow
# YogaFX LMS

## Purpose

Dokumen ini menjadi source of truth flow untuk fitur **Email Notification** pada YogaFX LMS, khusus untuk 6 menu utama:

1. Module Completion
2. Assignments Review
3. Assignments Approved
4. Assignments Rejected
5. Certificate Created
6. Signup

Dokumen ini dibuat berdasarkan PRD YogaFX LMS, terutama bagian Email Notification, dan dipakai sebagai acuan implementasi untuk sisi Admin dan backend trigger email.

---

# 1. Scope

Fitur Email Notification pada YogaFX LMS memiliki 2 mode operasional utama:

## 1.1 Automated Notification
Email dikirim otomatis oleh sistem saat trigger bisnis tertentu terpenuhi.

## 1.2 Send Test (Manual)
Admin dapat mengirim email percobaan dengan memasukkan alamat email tujuan untuk menguji template dan SMTP flow.

---

# 2. Common Flow for All Email Types

## Purpose
Menjelaskan alur umum yang dipakai oleh semua jenis email.

## Trigger
- Admin membuka salah satu menu email notification
- Admin menyimpan konfigurasi template
- Trigger bisnis terjadi
- Admin menekan tombol Send Test

## Preconditions
- Admin sudah login
- Template email tersedia atau siap dibuat
- Notification type valid
- Sistem email/SMTP tersedia

## Main Flow
1. Admin membuka menu Email Notification.
2. Admin memilih salah satu notification type.
3. Sistem memuat konfigurasi template untuk notification tersebut.
4. Admin dapat:
   - mengaktifkan atau menonaktifkan notification
   - mengisi admin recipients
   - mengisi subject dan body untuk Admin
   - mengisi subject dan body untuk User
5. Admin menekan tombol **Save Changes**.
6. Sistem menyimpan template email.
7. Saat trigger bisnis terjadi, sistem:
   - mengecek apakah template aktif
   - merender placeholder dengan data aktual
   - menentukan penerima email
   - mengirim email
8. Sistem menyimpan log pengiriman.
9. Jika admin menekan **Send Test**, sistem:
   - mengambil template aktif
   - merender sample data
   - mengirim email ke alamat yang dimasukkan admin
   - menyimpan log pengiriman test

## Alternative Flow
- Template belum pernah dibuat, sehingga field awal kosong.
- Template tersimpan tapi `is_enabled = false`, sehingga automated notification tidak dikirim.
- Admin hanya melakukan test tanpa mengubah template.

## Failure Flow
- Template gagal dimuat
- Save gagal
- Placeholder gagal dirender
- Recipient tidak valid
- SMTP gagal
- Send Test gagal

## Success Outcome
- Template tersimpan dengan benar
- Automated notification dapat berjalan
- Send test dapat dijalankan
- Email log tercatat

---

# 3. Module Completion Flow

## Purpose
Mengirim email otomatis saat student menyelesaikan seluruh lesson dalam satu modul. PRD menyebut email ini dikirim ke Admin sebagai laporan dan ke User sebagai apresiasi. :contentReference[oaicite:1]{index=1}

## Trigger
- Student menyelesaikan seluruh lesson dalam satu module

## Preconditions
- Module completion rule telah terpenuhi
- Template `module_completion` tersedia
- Notification diaktifkan

## Main Flow
1. Sistem mendeteksi student telah menyelesaikan seluruh lesson pada satu module.
2. Sistem membentuk payload email menggunakan data:
   - module_title
   - completion_date
   - module_progress
   - course_progress
   - study_time
   - user_name
   - user_email
3. Sistem mengambil template Module Completion.
4. Sistem merender subject dan body untuk Admin dan User.
5. Sistem mengirim email ke:
   - admin recipients
   - student/user
6. Sistem menyimpan log pengiriman.

## Alternative Flow
- Template hanya diisi untuk user atau admin saja.
- Trigger terjadi, tetapi notification dimatikan.

## Failure Flow
- Completion event gagal terdeteksi
- Placeholder tidak lengkap
- SMTP gagal
- Admin recipients kosong padahal dibutuhkan

## Success Outcome
- Admin menerima notifikasi laporan
- Student menerima email apresiasi
- Log pengiriman tersimpan

---

# 4. Assignments Review Flow

## Purpose
Mengirim email konfirmasi bahwa video assignment telah masuk ke antrean review. PRD menyebut fungsi email ini adalah mengonfirmasi video tugas telah masuk ke sistem antrean review. :contentReference[oaicite:2]{index=2}

## Trigger
- Student submit assignment / graduation video

## Preconditions
- Assignment submission berhasil dibuat
- Template `assignment_review` tersedia
- Notification diaktifkan

## Main Flow
1. Student submit assignment video.
2. Sistem membuat record assignment submission.
3. Sistem memicu event assignment review.
4. Sistem mengambil template Assignments Review.
5. Sistem merender email menggunakan data:
   - user_name
   - user_email
   - admin_email atau admin recipient context jika dipakai
6. Sistem mengirim email ke penerima sesuai konfigurasi.
7. Sistem menyimpan email log.

## Alternative Flow
- Email hanya dikirim ke user
- Email hanya dikirim ke admin
- Send Test dipakai tanpa submission nyata

## Failure Flow
- Assignment submission gagal
- Trigger tidak berjalan
- Template tidak ditemukan
- SMTP gagal

## Success Outcome
- User dan/atau Admin menerima konfirmasi bahwa assignment masuk antrean review
- Email log tercatat

---

# 5. Assignments Approved Flow

## Purpose
Mengirim email bahwa assignment akhir student telah disetujui. PRD menyebut email ini adalah pemberitahuan kelulusan video tugas akhir dan memiliki parameter tambahan `assignment_type`. :contentReference[oaicite:3]{index=3}

## Trigger
- Admin menyetujui assignment

## Preconditions
- Assignment status berhasil berubah menjadi approved
- Template `assignment_approved` tersedia
- Notification diaktifkan

## Main Flow
1. Admin membuka detail assignment student.
2. Admin memilih aksi approve.
3. Sistem menyimpan status assignment sebagai approved.
4. Sistem memicu event assignment approved.
5. Sistem mengambil template Assignments Approved.
6. Sistem merender email menggunakan data:
   - user_name
   - user_email
   - assignment_type
7. Sistem mengirim email ke penerima yang dikonfigurasi.
8. Sistem menyimpan log pengiriman.

## Alternative Flow
- Email hanya dikirim ke student
- Admin juga menerima copy jika template diatur begitu
- Send Test dipakai dengan sample assignment_type

## Failure Flow
- Status assignment gagal diperbarui
- assignment_type tidak tersedia
- Template tidak ada
- Email gagal dikirim

## Success Outcome
- Student menerima notifikasi kelulusan assignment
- Log pengiriman tersimpan

---

# 6. Assignments Rejected Flow

## Purpose
Mengirim email bahwa assignment ditolak dan student perlu melakukan re-upload. PRD menegaskan bahwa email ini wajib menyertakan parameter `feedback`. :contentReference[oaicite:4]{index=4}

## Trigger
- Admin menolak assignment

## Preconditions
- Assignment status berhasil berubah menjadi rejected
- Feedback tersedia
- Template `assignment_rejected` tersedia
- Notification diaktifkan

## Main Flow
1. Admin membuka detail assignment.
2. Admin memilih aksi reject.
3. Admin mengisi feedback.
4. Sistem menyimpan status assignment sebagai rejected.
5. Sistem memicu event assignment rejected.
6. Sistem mengambil template Assignments Rejected.
7. Sistem merender email menggunakan data:
   - user_name
   - user_email
   - assignment_type
   - feedback
8. Sistem mengirim email ke penerima.
9. Sistem menyimpan log pengiriman.

## Alternative Flow
- Email hanya dikirim ke student
- Send Test dilakukan dengan sample feedback

## Failure Flow
- Admin reject tanpa feedback
- Status assignment gagal diperbarui
- Template tidak tersedia
- SMTP gagal

## Success Outcome
- Student menerima informasi penolakan assignment
- Student menerima feedback untuk re-upload
- Log pengiriman tercatat

---

# 7. Certificate Created Flow

## Purpose
Mengirim email otomatis setelah Admin menekan Generate Certificate atau Send Graduation Email. PRD menegaskan bahwa email ini dipicu setelah aksi admin tersebut dan backend wajib melampirkan file PDF sertifikat secara otomatis. :contentReference[oaicite:5]{index=5}

## Trigger
- Admin menekan Generate Certificate
- Admin menekan Send Graduation Email

## Preconditions
- Certificate berhasil dibuat atau tersedia
- Template `certificate_created` tersedia
- Notification diaktifkan
- File certificate/PDF tersedia untuk lampiran jika implementasi lampiran diaktifkan

## Main Flow
1. Admin membuka area certificate.
2. Admin memilih jenis certificate dan generate certificate, atau menekan send graduation email.
3. Sistem membuat atau menyiapkan certificate.
4. Sistem memicu event certificate created.
5. Sistem mengambil template Certificate Created.
6. Sistem merender email menggunakan data:
   - user_name
   - user_email
7. Sistem melampirkan file PDF certificate jika flow lampiran diimplementasikan.
8. Sistem mengirim email ke penerima.
9. Sistem menyimpan log pengiriman.

## Alternative Flow
- Certificate sudah ada dan hanya email yang dikirim
- Certificate direcreate terlebih dahulu lalu email dikirim

## Failure Flow
- Certificate gagal dibuat
- File PDF gagal dihasilkan
- Template tidak tersedia
- Lampiran gagal ditambahkan
- Email gagal dikirim

## Success Outcome
- Student menerima email bahwa certificate siap
- Certificate PDF terlampir jika implementasi lampiran aktif
- Log email tercatat

---

# 8. Signup Flow

## Purpose
Mengirim email setelah custom signup berhasil. PRD menyebut email ini memiliki konfigurasi khusus untuk admin/user subject dan body, admin recipients, serta placeholder signup. :contentReference[oaicite:6]{index=6}

## Trigger
- User berhasil signup / custom signup

## Preconditions
- Akun user berhasil dibuat
- Template `signup` tersedia
- Notification diaktifkan

## Main Flow
1. User menyelesaikan proses signup.
2. Sistem membuat akun user.
3. Sistem memicu event signup.
4. Sistem mengambil template Signup.
5. Sistem merender email menggunakan data:
   - user_name
   - user_email
   - admin_email
   - access_tier
   - access_tier_label
   - registration_date
   - dashboard_url
   - login_url
6. Sistem mengirim email ke:
   - user
   - admin recipients sesuai konfigurasi
7. Sistem menyimpan log pengiriman.

## Alternative Flow
- Email hanya dikirim ke user
- Email hanya dikirim ke admin
- Send Test dijalankan dengan sample signup data

## Failure Flow
- Signup gagal
- Template signup tidak ditemukan
- Placeholder tidak lengkap
- SMTP gagal

## Success Outcome
- User menerima email selamat datang atau onboarding
- Admin menerima notifikasi signup jika dikonfigurasi
- Email log tercatat

---

# 9. Notification Types Covered

Dokumen ini mencakup notification type berikut:

1. `module_completion`
2. `assignment_review`
3. `assignment_approved`
4. `assignment_rejected`
5. `certificate_created`
6. `signup`

---

# 10. Common Admin UI Notes

## Email Notification Menu
Admin memiliki menu Email Notification yang berisi 6 sub menu:
- Module Completion
- Assignments Review
- Assignments Approved
- Assignments Rejected
- Certificate Created
- Signup

## Setiap Sub Menu Minimal Memiliki
- Enable notification
- Admin recipients
- Admin subject
- Admin body
- User subject
- User body
- Available merge tags
- Save Changes
- Send Test
- Field Send To untuk test

---

# 11. Common Validation Notes

## Save Template
Saat admin menyimpan template:
- notification type harus valid
- field yang dibutuhkan harus lolos validasi
- data template harus tersimpan dengan aman

## Send Test
Saat admin mengirim test:
- alamat email tujuan harus valid
- template harus bisa dirender
- sample placeholder harus tersedia

## Automated Trigger
Saat trigger bisnis terjadi:
- sistem harus cek `is_enabled`
- sistem harus cek apakah template tersedia
- sistem harus merender placeholder dengan data riil
- sistem harus mencatat hasil pengiriman ke email logs

---

# 12. Final Notes

Dokumen ini hanya mencakup flow **Email Notification** untuk 6 tipe email utama.

Dokumen ini dipakai sebagai source of truth implementasi untuk:
- email notification settings page
- save template flow
- send test flow
- backend automated trigger flow
- email logs flow

Jika ada konflik implementasi, gunakan PRD dan keputusan sistem terbaru sebagai acuan utama.