# Installment Admin Operations
# YogaFX LMS

## Purpose

Dokumen ini membantu admin operasional memeriksa setup package installment, status akun student, dan verifikasi dasar setelah payment berjalan.

---

## 1. Enable Installment on Package

Masuk ke:
- `Admin`
- `Supporting Pages`
- `Packages`

Field yang harus benar pada package:
- `Status`: aktif
- `Access Tier`: terisi
- `Price`: sesuai offer
- `Currency Code`: sesuai offer
- `Installment Ready`: `Yes`
- `Billing Interval Unit`: `MONTH`
- `Billing Interval Count`: `1`
- `Fixed Billing Day`: `15`
- `Installment Deadline Month`: `1`
- `Installment Deadline Day`: `15`

Checklist cepat:
- package tanpa `access_tier_id` tidak boleh dipakai checkout public
- package nonaktif tidak boleh dipakai checkout public
- package dengan `Installment Ready = No` hanya boleh menawarkan `Pay in full`

---

## 2. Public Link Verification

Link public yang perlu dicek:
- `/p/{package_slug}`
- legacy routes:
  - `/starter-kit`
  - `/online`
  - `/masterclass`

Yang harus diverifikasi:
- halaman resolve ke package aktif yang benar
- checkout signed URL terbuka
- opsi `Installment` muncul hanya jika package eligible
- angka first payment dan recurring amount tampil dari backend

Contoh utama untuk package dynamic:

```text
http://127.0.0.1:8000/p/masterclass-standard
```

Legacy route tetap boleh dipakai untuk backward compatibility, tetapi bukan contoh utama source public link package.

---

## 3. First Payment Verification

Setelah student approval checkout installment:
- pastikan invoice baru dibuat
- pastikan `payment_type = installment`
- pastikan row `payment_subscriptions` dibuat
- pastikan `provider_subscription_id` terisi
- pastikan setelah webhook sukses pertama:
  - onboarding continuation terbuka
  - student bisa lanjut enrollment dan signup
  - account/student relation terbentuk

Jika checkout dibatalkan sebelum approval:
- subscription lokal dapat berstatus `cancelled`
- student belum boleh masuk onboarding

---

## 4. Failed Payment and H+3 Rule

Rule aktif:
- recurring payment gagal tidak langsung menonaktifkan akun
- sistem memberi grace period sampai `due date + 3 hari`
- recurring due date selalu tanggal `15`

Contoh:
- due date `2027-10-15`
- grace deadline `2027-10-18`

Saat gagal, admin perlu cek:
- subscription status `past_due`
- `last_payment_failed_at` terisi
- `grace_deadline_at` benar
- email admin `installment_payment_failed` tercatat

---

## 5. Inactive and Reactivation Rule

Akun student menjadi inactive jika:
- subscription `past_due`
- `grace_deadline_at` sudah lewat
- command overdue harian sudah berjalan

Yang berubah:
- `users.is_active = false`
- student akan diarahkan ke halaman inactive saat login
- email admin `installment_overdue_inactive` dikirim sekali

Akun aktif kembali jika:
- payment recovery sukses
- subscription tidak berada pada status final `cancelled` atau `suspended`

Yang berubah:
- `users.is_active = true`
- metadata subscription menyimpan info reactivation

---

## 6. Daily Admin Checks

Periksa ini secara rutin:
- package installment target masih aktif dan tetap assign ke tier yang benar
- webhook PayPal masih masuk
- tidak ada subscription `past_due` yang melewati grace tanpa tindak lanjut
- email template installment masih enabled bila memang dipakai operasional

Jika ada student komplain akun inactive:
1. cek `payment_subscriptions.status`
2. cek `grace_deadline_at`
3. cek `email_logs` untuk failed atau overdue notice
4. cek apakah payment recovery sudah masuk sebagai webhook success

---

## 7. Manual Command

Command operasional:

```powershell
php artisan installments:sync-overdue-status
```

Output command menampilkan:
- total processed
- total deactivated
- total notifications sent
- total skipped

Command ini aman dijalankan ulang karena flow overdue dirancang idempotent untuk notifikasi admin.

---

## 8. Email Template Note

Template installment aktif di backend:
- `installment_payment_success`
- `installment_payment_failed`
- `installment_overdue_inactive`
- `installment_payment_completed`

Catatan:
- route detail template tersedia di `/admin/email-notifications/{notificationType}`
- sidebar admin saat ini belum menampilkan child menu installment secara eksplisit

---

## 9. Sandbox Regression

Checklist manual yang dipakai tim ada di:
- `docs/installment-sandbox-test-checklist.md`

Gunakan checklist itu untuk verifikasi:
- first payment
- recurring success
- failed payment
- overdue H+3
- recovery payment
- final payment
- duplicate webhook
- full payment regression
