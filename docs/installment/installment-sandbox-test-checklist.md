# Installment Sandbox Test Checklist
# YogaFX LMS

Dokumen ini dipakai untuk regression pass manual phase installment pertama.

## Scope

Checklist ini hanya untuk:
- initial checkout installment
- PayPal Subscription sandbox
- webhook + scheduler + email verification

Checklist ini tidak mencakup:
- upgrade installment
- mobile Flutter
- admin analytics
- manual retry UI

## Pre-Check

- Pastikan package target `is_active = true`
- Pastikan package target punya `access_tier_id`
- Pastikan package target `installment_enabled = true`
- Pastikan package target punya:
  - `price`
  - `currency_code`
  - `allowed_billing_days` mencakup `1` dan/atau `15`
  - `installment_deadline_month = 1`
- Pastikan PayPal sandbox client, secret, dan webhook id aktif
- Pastikan webhook route publik bisa menerima event PayPal
  - Localhost tidak cukup. Gunakan public tunnel seperti `ngrok` atau `cloudflared`, lalu arahkan PayPal webhook ke `/webhooks/paypal`
- Pastikan email template berikut aktif jika ingin verifikasi email:
  - `signup`
  - `installment_payment_success`
  - `installment_payment_failed`
  - `installment_overdue_inactive`
  - `installment_payment_completed`

## Checkout Contract

1. Buka direct public package link aktif, misalnya:

```text
http://127.0.0.1:8000/p/masterclass-standard
```

2. Legacy route seperti `/masterclass`, `/online`, atau `/starter-kit` boleh diuji terpisah untuk backward compatibility, tetapi bukan contoh utama package dynamic.
3. Lanjutkan ke checkout signed URL.
4. Verifikasi checkout menampilkan:
   - opsi `Pay in full`
   - opsi `Installment` bila package eligible
   - pilihan monthly billing date:
     - `Every 1st of the month`
     - `Every 15th of the month`
   - first payment amount dari backend
   - recurring amount dari backend
   - total installment count
   - billing day sesuai pilihan checkout
   - final due date Januari mengikuti billing day yang dipilih
5. Verifikasi package yang `installment_enabled = false` hanya menampilkan full payment.

## First Payment

1. Pilih `Installment`.
2. Jalankan approval flow PayPal sandbox.
3. Setelah approval berhasil, verifikasi checkout masuk ke waiting state dan tidak membuka onboarding terlalu awal.
3. Pastikan backend membuat:
   - `invoice` dengan `payment_type = installment`
   - `payment_subscriptions`
   - `payment_subscription_events`
4. Setelah webhook success pertama masuk, verifikasi:
   - `payment_activities` success tercatat
   - `invoice.balance_due` berkurang
   - `invoice.status = installment`
   - `pending_registrations.status = payment_success`
   - onboarding state dibuat
   - subscription status menjadi `active`
   - frontend checkout status berubah menjadi onboarding ready
   - browser redirect otomatis ke enrollment/personal information

## Recurring Success

1. Simulasikan recurring payment success dari PayPal sandbox/webhook payload.
2. Verifikasi:
   - satu `payment_activities` baru tercatat
   - `installments_paid_count` naik
   - `invoice.balance_due` turun
   - email admin `installment_payment_success` tercatat di `email_logs`

## Failed Payment

1. Simulasikan webhook `BILLING.SUBSCRIPTION.PAYMENT.FAILED`.
2. Verifikasi:
   - subscription status menjadi `past_due`
   - `last_payment_failed_at` terisi
   - `grace_deadline_at = due date + 3 hari`
   - akun student belum inactive
   - email admin `installment_payment_failed` tercatat

## Overdue H+3

1. Set subscription `past_due` melewati `grace_deadline_at`.
2. Jalankan command:

```powershell
php artisan installments:sync-overdue-status
```

3. Verifikasi:
   - `users.is_active = false`
   - email admin `installment_overdue_inactive` hanya tercatat sekali
   - metadata subscription mencatat overdue deactivation

## Recovery Payment

1. Setelah akun inactive karena overdue, simulasikan recurring success baru.
2. Verifikasi:
   - akun student aktif kembali
   - metadata subscription mencatat reactivation
   - jika balance belum nol, subscription kembali `active`

## Final Payment

1. Simulasikan pembayaran terakhir sampai `invoice.balance_due = 0`.
2. Verifikasi:
   - `invoice.status = paid_full`
   - `payment_subscriptions.status = completed`
   - `completed_at` terisi
   - email student `installment_payment_completed` tercatat

## Webhook Safety

1. Kirim event provider yang sama dua kali.
2. Verifikasi:
   - event duplicate tidak diproses dua kali
   - invoice balance tidak berkurang dua kali
3. Kirim event subscription yang tidak didukung.
4. Verifikasi:
   - event tetap tersimpan
   - status event menjadi `ignored`
   - sistem tidak error

## Full Payment Regression

1. Jalankan checkout normal `pay_full`.
2. Verifikasi:
   - PayPal order/capture existing tetap jalan
   - onboarding tetap terbuka setelah success
   - tidak ada dependency ke subscription flow
