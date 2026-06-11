# Email Send Test Behaviour
# YogaFX LMS

## Purpose

Dokumen ini menjadi source of truth untuk fitur **Send Test Email** pada domain email/template di YogaFX LMS.

Tujuan fitur ini adalah memastikan bahwa saat admin menekan tombol **Send Test**, sistem benar-benar:

1. merender template email yang sedang aktif / sedang diedit
2. memakai konfigurasi SMTP dari `.env`
3. mengirim email sungguhan ke alamat tujuan test
4. tidak hanya menampilkan status seperti `sent` atau `logged` secara palsu
5. tidak fallback ke log-only behaviour jika SMTP sudah aktif dan valid

---

# 1. Current Problem

Saat ini fitur **Send Test Email** belum berhasil sesuai harapan.

Gejala:
- UI/status menunjukkan sesuatu seperti:
  - `sent`
  - `logged`
- tetapi email test tidak benar-benar masuk ke inbox penerima
- akibatnya fitur ini menyesatkan karena terlihat sukses padahal email belum benar-benar terkirim

---

# 2. Final Expected Behaviour

Saat admin menekan **Send Test Email**, sistem harus:

1. mengambil template email yang sedang dipilih / sedang diedit
2. merender template tersebut dengan data placeholder test yang valid
3. menggunakan konfigurasi mailer SMTP aktif dari `.env`
4. benar-benar mengirim email ke alamat tujuan test
5. hanya menampilkan status sukses jika pengiriman nyata berhasil
6. menampilkan error yang jelas jika SMTP gagal, template gagal dirender, atau proses kirim gagal

---

# 3. Source of Truth for Delivery

## Final Rule
Jika SMTP sudah diset di `.env`, maka **Send Test Email harus benar-benar mengirim email**, bukan hanya mencatat ke log.

## Important Rule
Mode `log` atau `array` tidak boleh dianggap sukses kirim sungguhan.

Jika mailer aktif masih:
- `log`
- `array`
- `failover` yang ujungnya tidak benar-benar mengirim
- atau konfigurasi lain yang tidak mengirim email nyata

maka UI/response harus menjelaskan itu dengan jujur.

---

# 4. Required Delivery Behaviour

## 4.1 Mail Transport
Fitur Send Test harus memakai **Laravel Mail** dengan mailer yang aktif dari konfigurasi aplikasi.

## 4.2 SMTP from .env
Konfigurasi SMTP harus dibaca dari `.env`, misalnya:
- `MAIL_MAILER`
- `MAIL_HOST`
- `MAIL_PORT`
- `MAIL_USERNAME`
- `MAIL_PASSWORD`
- `MAIL_ENCRYPTION`
- `MAIL_FROM_ADDRESS`
- `MAIL_FROM_NAME`

## 4.3 No Fake Success
Jangan pernah menandai test email sebagai berhasil hanya karena:
- template berhasil dirender
- request berhasil diproses
- email ditulis ke log
- tidak ada exception yang dilempar di layer tertentu

Sukses berarti:
- proses kirim sungguhan lewat mailer SMTP selesai tanpa error

---

# 5. Template Behaviour

## Final Rule
Send Test Email harus memakai **template yang ada**, bukan template dummy lain.

Artinya:
- subject test mengikuti template aktif
- body test mengikuti template aktif
- styling/email HTML mengikuti template aktif
- placeholder test tetap dirender di dalam template yang sama

## Applies To
- subject
- heading
- body content
- footer
- CTA bila ada
- placeholder variables

---

# 6. Test Data Rendering

Karena ini test email, sistem boleh memakai **dummy/test data** untuk placeholder.

Contoh test data:
- student_name = `Test Student`
- lesson_title = `Sample Lesson`
- module_title = `Sample Module`
- certificate_name = `YogaFX Certificate`
- assessment_name = `Sample Assessment`
- score = `85`
- date = current date
- app_name = `YogaFX LMS`

## Final Rule
Jika template punya placeholder, Send Test harus tetap bisa merender template secara utuh dengan data test yang aman.

## Error Rule
Jika placeholder wajib tidak bisa dirender:
- tampilkan error yang jelas
- jangan diam-diam kirim template rusak

---

# 7. Admin Input for Test Email

Fitur Send Test Email minimal harus punya:
- alamat email tujuan test

Opsional:
- nama penerima test
- pilihan template
- preview data test

Tetapi minimal yang wajib:
- admin bisa memasukkan email tujuan
- lalu klik **Send Test**

---

# 8. Success Criteria

Send Test Email dianggap berhasil hanya jika:
1. template berhasil dirender
2. mailer aktif valid
3. email benar-benar diproses oleh SMTP
4. tidak ada exception dari proses kirim

Jika semua itu terpenuhi:
- tampilkan status sukses yang jujur, misalnya:
  - `Test email sent successfully.`
- bukan status ambigu seperti:
  - `logged`
  - `processed`

---

# 9. Failure Criteria

Send Test Email dianggap gagal jika salah satu dari ini terjadi:
1. mailer aktif bukan mailer kirim sungguhan
2. konfigurasi SMTP salah / kosong
3. koneksi SMTP gagal
4. autentikasi SMTP gagal
5. template gagal dirender
6. placeholder test gagal disubstitusi
7. terjadi exception saat `Mail::send` / `Mail::to(...)->send(...)`

## Final Rule
Jika gagal:
- tampilkan error yang jelas
- jangan tandai sukses
- jangan fallback diam-diam ke log tanpa memberi tahu admin

---

# 10. Logged vs Sent

## Important Clarification

### `logged`
Artinya email hanya ditulis ke log/debug transport.  
Ini **bukan** email sungguhan.

### `sent`
Baru boleh dipakai jika email benar-benar dikirim melalui transport SMTP aktif.

## Final Rule
Jika sistem saat ini masih menampilkan `logged`, maka itu harus dianggap:
- belum memenuhi requirement
- belum benar-benar selesai

---

# 11. Implementation Requirements for Codex

Codex harus memastikan hal-hal berikut:

1. Audit route/controller/action untuk Send Test Email.
2. Audit apakah fitur saat ini memakai:
   - `Mail::fake()`
   - `log` mailer
   - `array` mailer
   - atau transport non-SMTP lain
3. Pastikan Send Test memakai mailer aktif dari config nyata.
4. Pastikan subject dan body berasal dari template aktif yang benar.
5. Pastikan placeholder test dirender ke HTML final.
6. Pastikan hasil response membedakan:
   - sukses kirim nyata
   - hanya logged
   - gagal render
   - gagal SMTP
7. Tambahkan error handling yang jujur dan mudah dipahami admin.
8. Jangan ubah domain lain di luar email/template behaviour.

---

# 12. Debugging Requirements

Saat development, Codex harus memeriksa minimal:
- mailer aktif saat runtime
- apakah config cache masih memakai nilai lama
- apakah `.env` benar-benar terbaca
- apakah queue dipakai atau send sync
- apakah exception SMTP tertangkap
- apakah template benar-benar dirender sebelum dikirim

## Important Note
Jika `.env` sudah benar tetapi sistem masih tidak mengirim, kemungkinan yang harus dicek:
- `php artisan config:clear`
- `php artisan cache:clear`
- `MAIL_MAILER` aktif saat runtime
- credentials SMTP
- from address
- firewall / provider restrictions
- queue worker jika email dikirim via queue

---

# 13. Recommended Runtime Behaviour

## Preferred Behaviour
Untuk Send Test Email:
- kirim secara langsung / synchronous terlebih dahulu
- jangan bergantung ke queue dulu untuk test
- tujuan utamanya adalah verifikasi kirim nyata sesaat setelah admin menekan tombol

## Why
Karena untuk test email, admin perlu kepastian cepat:
- terkirim atau tidak
- error nyata apa

---

# 14. UI Feedback Requirements

## Success Message
Contoh:
- `Test email sent successfully to example@domain.com.`

## Failure Message
Contoh:
- `SMTP connection failed. Please check MAIL_HOST, MAIL_PORT, and credentials.`
- `The email template could not be rendered.`
- `The active mailer is set to log, so no real email was sent.`

## Final Rule
Pesan harus spesifik dan jujur.

---

# 15. Out of Scope

Dokumen ini tidak membahas:
- bulk email sending
- campaign sending
- queue scaling
- email analytics
- open/click tracking
- inbox placement optimisation

Dokumen ini hanya fokus pada:
- **Send Test Email benar-benar terkirim sesuai template**

---

# 16. Final Summary

Fitur **Send Test Email** harus bekerja seperti ini:

- admin memilih / memakai template email yang aktif
- admin memasukkan alamat email tujuan test
- sistem merender template dengan data test
- sistem memakai SMTP dari `.env`
- sistem benar-benar mengirim email
- UI hanya menampilkan sukses jika email sungguhan berhasil terkirim
- status `logged` tidak boleh dianggap sukses kirim nyata

Dokumen ini menjadi source of truth untuk perbaikan fitur Send Test Email.