# Installment Implementation
# YogaFX LMS

## Purpose

Dokumen ini merangkum implementasi installment aktif phase pertama di YogaFX LMS.

Dokumen ini hanya membahas:
- initial checkout package
- PayPal Subscription sebagai recurring engine
- webhook, invoice, payment ledger, scheduler, dan email yang sudah hidup

Dokumen ini tidak membahas:
- installment untuk upgrade
- PayPal subscription renewal tanpa batas
- mobile Flutter

---

## 1. Activation Rule

Installment hanya tersedia bila package memenuhi semua syarat ini:
- `packages.is_active = true`
- `packages.access_tier_id` terisi
- `packages.installment_enabled = true`
- `packages.price` valid
- `packages.currency_code` valid

Source of truth harga dan currency:
- harga checkout: `packages.price`
- currency checkout: `packages.currency_code`

`AccessTier` tidak lagi menjadi source harga installment.  
`AccessTier` hanya menjadi entitlement yang diberikan setelah payment sukses.

---

## 2. Public Checkout Rule

Checkout backend selalu mengirim `payment_options`.

Opsi yang mungkin muncul:
- `pay_full`
- `installment`

`installment` hanya dikirim jika package eligible.  
Frontend tidak menghitung cicilan sendiri dan hanya menampilkan angka dari backend.

Public entry yang aktif:
- direct package links: `/p/{package_slug}`
- legacy tier links for backward compatibility: `/starter-kit`, `/online`, `/masterclass`

Contoh utama package dynamic:

```text
http://127.0.0.1:8000/p/masterclass-standard
```

---

## 3. Installment Schedule Rule

Rule bisnis phase pertama:
- first payment dibayar saat checkout approval berhasil
- recurring charge selalu jatuh pada tanggal `15`
- deadline akhir selalu `15 Januari`
- grace deadline recurring payment adalah `due date + 3 hari`

Calculator backend membentuk schedule ini dari:
- tanggal checkout
- `Package.price`
- `Package.currency_code`
- `Package.fixed_billing_day`
- `Package.installment_deadline_month`
- `Package.installment_deadline_day`

Formula aktif:

```text
monthly_base = floor(total_amount / installment_count)
first_payment = total_amount - (monthly_base * (installment_count - 1))
recurring_payment = monthly_base
```

Contoh:
- total `300`
- 7 cycle
- first payment `48`
- recurring `42`

---

## 4. Data Flow

Arsitektur aktif:

```text
Package
-> PendingRegistration
-> Invoice
-> PaymentSubscription
-> PayPal Subscription
-> PaymentSubscriptionEvent
-> Payment Activity ledger
-> Onboarding / Account lifecycle / Email
```

Tabel penting:
- `packages`
- `pending_registrations`
- `invoices`
- `payments`
- `payment_subscriptions`
- `payment_subscription_events`

---

## 5. First Payment Flow

Saat visitor memilih `installment`:
1. Backend resolve package dan hitung plan.
2. Sistem membuat `invoice` dengan `payment_type = installment`.
3. Sistem membuat `payment_subscriptions` status `draft`.
4. Sistem membuat PayPal product/plan bila package belum punya cache id.
5. Sistem membuat PayPal subscription dan menyimpan `provider_subscription_id`.
6. Status local berubah menjadi `approval_pending`.
7. Frontend diarahkan ke approval URL PayPal.

Saat webhook payment pertama sukses:
1. `payments` success dibuat atau di-update.
2. `invoice.balance_due` dikurangi.
3. flow onboarding existing tetap dijalankan.
4. `pending_registrations.status` menjadi `payment_success`.
5. account student dibuat/ditautkan.
6. subscription menjadi `active`.

---

## 6. Recurring Success and Failure

Saat recurring payment sukses:
- ledger payment baru dicatat
- `installments_paid_count` naik
- `invoice.balance_due` turun
- `last_payment_failed_at` dibersihkan
- akun inactive karena overdue dapat aktif lagi
- admin mendapat email `installment_payment_success`

Saat recurring payment gagal:
- subscription menjadi `past_due`
- `last_payment_failed_at` terisi
- `grace_deadline_at` diisi `next_due_at + 3 hari`
- akun belum langsung inactive
- admin mendapat email `installment_payment_failed`

Saat balance due menjadi nol:
- `invoice.status = paid_full`
- `payment_subscriptions.status = completed`
- `completed_at` terisi
- student mendapat email `installment_payment_completed`

---

## 7. Overdue and Account Lifecycle

Command aktif:

```text
php artisan installments:sync-overdue-status
```

Scheduler:
- dijalankan harian lewat `routes/console.php`

Rule:
- hanya subscription `past_due` yang diproses
- jika `grace_deadline_at` sudah lewat, student di-nonaktifkan
- email admin `installment_overdue_inactive` hanya dikirim sekali
- metadata subscription menyimpan timestamp deactivation

Recovery:
- jika payment berikutnya sukses dan subscription tidak `cancelled` atau `suspended`, `users.is_active` diaktifkan lagi
- metadata subscription menyimpan `reactivated_at`

---

## 8. Email Types

Type installment yang aktif di registry:
- `installment_payment_success`
- `installment_payment_failed`
- `installment_overdue_inactive`
- `installment_payment_completed`

Catatan implementasi saat ini:
- type sudah aktif di backend registry dan route email notification
- sidebar admin `Email` masih belum menampilkan child menu khusus installment

---

## 9. Current Boundaries

Masih di luar scope phase pertama:
- installment upgrade checkout
- retry UI manual
- admin analytics dashboard subscription
- mobile installment flow
- general recurring membership renewal di luar package initial checkout
