# Email Notification Flow
# YogaFX LMS

## Purpose

Dokumen ini menjadi source of truth untuk fitur **Email Notification** yang aktif saat ini.

Dokumen ini mencakup:
- halaman admin email template
- save template
- send test
- automated trigger
- email logs

---

## 1. Active Notification Types

Saat ini sistem mendukung 10 notification type:

1. `module_completion`
2. `assignment_review`
3. `assignment_approved`
4. `assignment_rejected`
5. `certificate_created`
6. `signup`
7. `reset_password`
8. `assessment_complete`
9. `course_complete`
10. `reminder`

---

## 2. Admin UI Structure

Menu parent:
- `Email`

Child menu:
- Module Completion
- Assignments Review
- Assignments Approved
- Assignments Rejected
- Certificate Created
- Signup
- Reset Password
- Assessment Complete
- Course Complete
- Reminder

Setiap child menu membuka satu halaman detail template.

---

## 3. Template Form Structure

Setiap halaman detail notification type berisi:
- Enable notification
- Admin recipients
- Admin subject
- Admin body
- User subject
- User body
- Save Changes
- Send Test
- Send To
- Available merge tags
- Trigger context

Field dapat kosong jika template belum pernah dikonfigurasi.

---

## 4. Save Template Flow

### Main Flow
1. Admin membuka salah satu notification type.
2. Sistem memuat template dari database, atau membuat record default jika belum ada.
3. Admin mengubah isi form.
4. Admin klik `Save Changes`.
5. Sistem memvalidasi:
   - notification type valid
   - admin recipients valid jika diisi
   - subject length valid
6. Sistem menyimpan template.
7. Admin tetap berada di halaman notification type yang sama dengan flash success message.

### Validation Notes
- `admin_recipients` boleh kosong
- jika diisi, format email harus valid
- `is_enabled` wajib boolean

---

## 5. Send Test Flow

### Main Flow
1. Admin membuka halaman notification type.
2. Admin mengisi field `Send To`.
3. Admin klik `Send Test`.
4. Sistem merender template dengan sample payload sesuai notification type.
5. Sistem mengirim email test ke satu alamat tujuan.
6. Sistem mencatat email log.

### Important Notes
- test email tetap bisa dikirim walaupun trigger bisnis nyata belum terjadi
- test menggunakan sample merge tags, bukan data real dari domain terkait

---

## 6. Automated Trigger Flow

### Common Flow
1. Event bisnis terjadi.
2. Sistem mencari `EmailTemplate` berdasarkan `notification_type`.
3. Jika template tidak ada atau `is_enabled = false`, sistem tidak mengirim email.
4. Jika template aktif:
   - sistem render merge tags
   - sistem menentukan target user dan/atau admin recipients
   - sistem mengirim email
   - sistem mencatat log

### Trigger Mapping

#### Module Completion
Dipicu saat event module completion dipanggil oleh domain progres.

#### Assignments Review
Dipicu saat assignment masuk ke antrean review.

#### Assignments Approved
Dipicu saat admin mengubah status assignment menjadi approved.

#### Assignments Rejected
Dipicu saat admin mengubah status assignment menjadi rejected.

#### Certificate Created
Dipicu saat certificate dibuat atau graduation email dikirim dari area Student Progress.

#### Signup
Dipicu dari event `Registered`.

Catatan:
- public signup route belum aktif
- event ini tetap berguna untuk user yang dibuat lewat flow lain yang tetap memicu `Registered`

#### Reset Password
Dipicu saat user meminta reset password.

Jika template reset password aktif dan lengkap, sistem memakai template ini sebagai pengganti email reset default Laravel.

#### Assessment Complete
Infrastruktur template dan trigger type sudah tersedia.

Catatan:
- domain assessment penuh belum aktif, jadi trigger nyata ini belum menjadi flow harian utama

#### Course Complete
Infrastruktur template dan trigger type sudah tersedia.

Catatan:
- flow completion course penuh belum aktif

#### Reminder
Dipicu oleh command terjadwal:
- `email-notifications:send-reminders`

Scheduler:
- dijalankan harian melalui `routes/console.php`
- mengecek student yang tidak memiliki aktivitas lesson progress selama 7 hari

---

## 7. Email Log Rules

Semua pengiriman berikut harus tercatat:
- send test
- automated user email
- automated admin email
- failed deliveries

Field log yang penting:
- notification_type
- reference_type
- reference_id
- recipient_type
- recipient_email
- subject
- body_snapshot
- status
- error_message
- sent_at

---

## 8. Merge Tag Behavior

Merge tags dirender dengan penggantian string sederhana:
- `{{ key }}`

Jika key tidak tersedia di payload:
- hasil render menjadi string kosong

Dokumen source merge tags per type berasal dari `EmailNotificationTypeRegistry`.

---

## 9. Current Constraints

- email logs belum punya halaman index admin terpisah
- send test selalu diarahkan ulang kembali ke halaman detail notification type
- admin child menu Email tidak auto-open; hanya terbuka jika user membukanya sendiri

---

## 10. Out of Scope

Yang belum menjadi bagian fitur ini:
- WYSIWYG email editor kompleks
- attachment builder umum
- bulk send manual ke banyak alamat arbitrary
- analytics dashboard email
