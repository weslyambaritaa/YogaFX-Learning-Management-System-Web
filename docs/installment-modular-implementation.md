# Installment Modular Implementation
# YogaFX LMS

## Purpose

Dokumen ini menjadi roadmap implementasi bertahap untuk fitur **installment/cicilan** pada YogaFX LMS.

Dokumen ini khusus membahas phase installment setelah perombakan commerce domain selesai, yaitu setelah:

- `AccessTier` sudah fokus menjadi entitlement/access layer.
- `Package` sudah menjadi commerce/payment offer layer.
- Checkout initial sudah membaca harga dari `Package`, bukan lagi dari `AccessTier`.
- Public route lama seperti `/masterclass`, `/online`, dan `/starter-kit` tetap hidup, tetapi resolve ke package aktif yang sedang assign ke tier terkait.
- Direct package route seperti `/p/{package_slug}` tersedia atau disiapkan.

Dokumen ini **tidak membahas ulang implementasi package domain**.  
Dokumen ini mengasumsikan package foundation sudah stabil.

---

## 1. Core Principle

Installment tidak boleh diimplementasikan sebagai patch UI checkout.

Installment harus menjadi perluasan domain payment backend yang canonical:

```text
Package
↓
Invoice
↓
Payment Subscription
↓
Payment Activities
↓
Webhook / Scheduler / Email
```

Backend tetap menjadi source of truth untuk:

- installment plan
- payment schedule
- PayPal subscription id
- payment success/failure
- invoice balance
- account active/inactive status
- admin/student notification

Frontend hanya membaca hasil kalkulasi dan menjalankan flow checkout yang disediakan backend.

---

## 2. Business Rules Lock

Rule installment yang sudah dikunci:

1. Installment phase pertama hanya untuk **initial checkout**.
2. Upgrade installment tidak masuk phase pertama.
3. Installment dihitung dari `Package.price`, bukan `AccessTier.price`.
4. Currency dihitung dari `Package.currency_code`.
5. Package harus memiliki `installment_enabled = true` agar cicilan tersedia.
6. Pembayaran pertama terjadi langsung saat checkout/setup berhasil.
7. Setelah pembayaran pertama sukses, student boleh lanjut:
   - payment success continuation
   - enrollment
   - create password
   - memakai sistem
8. Tanggal penarikan cicilan bulanan selalu tanggal **15**.
9. Deadline terakhir cicilan adalah **15 Januari**.
10. Jumlah installment dihitung dinamis dari bulan checkout sampai 15 Januari.
11. Rumus pembulatan:
    - `monthly_base = floor(total_amount / installment_count)`
    - `first_payment = total_amount - (monthly_base * (installment_count - 1))`
    - recurring berikutnya memakai `monthly_base`
12. Rule pembulatan berlaku untuk semua currency.
13. Jika pembayaran gagal, grace period adalah **H+3 setelah tanggal 15**.
14. Jika masih gagal setelah H+3, akun student otomatis menjadi inactive.
15. Jika pembayaran berhasil setelah inactive, akun otomatis active lagi.
16. Admin mendapat notifikasi untuk:
    - installment payment success
    - installment payment failed
    - overdue H+3 dan account dinonaktifkan
    - semua cicilan lunas
17. Saat semua cicilan lunas, student mendapat email `payment completed`.

---

## 3. Current System Assumptions

Sebelum modul installment dimulai, kondisi yang diasumsikan sudah benar:

- `packages` table sudah ada.
- `packages.access_tier_id` nullable.
- Package dengan `access_tier_id = null` tidak tersedia untuk checkout public.
- Setiap tier hanya boleh di-assign oleh satu package aktif pada satu waktu.
- Package memiliki minimal field:
  - `title`
  - `slug`
  - `description`
  - `image`
  - `price`
  - `currency_code`
  - `installment_enabled`
  - `billing_interval_unit`
  - `billing_interval_count`
  - `fixed_billing_day`
  - `installment_deadline_month`
  - `installment_deadline_day`
  - `is_active`
- `pending_registrations` sudah dapat menyimpan package context.
- `invoices` sudah dapat menyimpan package context.
- Initial checkout sudah menggunakan package sebagai source harga.
- Existing PayPal one-time order/capture flow tetap berjalan untuk full payment.

---

## 4. Default Execution Order

Urutan implementasi installment yang direkomendasikan:

1. Installment Schema Foundation
2. Installment Plan Calculator
3. Backend Checkout Contract
4. PayPal Subscription Provider Layer
5. Initial Checkout Subscription Orchestration
6. Subscription Webhook Event Log
7. Subscription Webhook Handler
8. Payment Ledger and Invoice Balance Sync
9. Overdue Grace Period Scheduler
10. Account Deactivation and Reactivation
11. Email Notification Types
12. Frontend Public Checkout Update
13. Tests and Regression Pass
14. Documentation and Admin Verification

Kerjakan satu modul pada satu waktu.

---

# Module 1 — Installment Schema Foundation

## Objective

Menambahkan database foundation untuk lifecycle installment/subscription tanpa merusak `invoices` dan `payment_activities`.

## Scope

Backend only.

## Main Scope

Tambahkan tabel baru:

- `payment_subscriptions`
- `payment_subscription_events`

Tabel ini menjadi canonical lifecycle untuk cicilan otomatis.

## Dependencies

- Package domain sudah selesai.
- Invoice sudah memakai `package_id`.
- Payment activity tetap memakai tabel `payment_activities`.

## Likely Touched Areas

- `database/migrations/*create_payment_subscriptions_table.php`
- `database/migrations/*create_payment_subscription_events_table.php`
- `app/Models/PaymentSubscription.php`
- `app/Models/PaymentSubscriptionEvent.php`
- `app/Models/Invoice.php`
- `app/Models/Payment.php`
- `app/Models/Package.php`
- `app/Models/User.php`
- `app/Models/PendingRegistration.php`

## Suggested `payment_subscriptions` Columns

```text
id
invoice_id
package_id
pending_registration_id nullable
user_id nullable
access_tier_id
provider
provider_product_id nullable
provider_plan_id nullable
provider_subscription_id nullable unique
status
installment_count
installments_paid_count
currency_code
total_amount
monthly_base_amount
first_payment_amount
next_billing_amount nullable
started_at nullable
first_payment_paid_at nullable
next_due_at nullable
final_due_at nullable
grace_deadline_at nullable
completed_at nullable
suspended_at nullable
cancelled_at nullable
last_payment_failed_at nullable
last_synced_at nullable
metadata json nullable
created_at
updated_at
```

## Suggested `payment_subscription_events` Columns

```text
id
payment_subscription_id nullable
invoice_id nullable
payment_activity_id nullable
provider
provider_event_id nullable unique
provider_event_type
provider_subscription_id nullable
provider_order_id nullable
provider_capture_id nullable
occurred_at nullable
processed_at nullable
status
payload json
notes nullable
created_at
updated_at
```

## Status Suggestions

### `payment_subscriptions.status`

```text
draft
approval_pending
active
past_due
suspended
cancelled
completed
failed
```

### `payment_subscription_events.status`

```text
received
processed
ignored
failed
```

## Definition of Done

- Migration berjalan.
- Model dan relation tersedia.
- Existing one-time payment tetap tidak berubah.
- Tidak ada UI change.
- Test basic model relationship tersedia.

## Non-Goals

- Belum membuat PayPal subscription.
- Belum mengubah frontend checkout.
- Belum menjalankan webhook.

---

# Module 2 — Installment Plan Calculator

## Objective

Membuat service kalkulasi installment yang canonical di backend.

## Scope

Backend only.

## Main Scope

Buat service:

```text
app/Services/Installments/InstallmentPlanCalculator.php
```

Service ini menghitung:

- jumlah installment dari tanggal checkout sampai 15 Januari
- first payment
- monthly recurring amount
- due schedule tanggal 15
- final due date
- grace deadline per cycle

## Business Formula

```text
monthly_base = floor(total_amount / installment_count)
first_payment = total_amount - (monthly_base * (installment_count - 1))
recurring_payment = monthly_base
```

## Date Rule

- Fixed billing day: 15
- Deadline: 15 January
- Jika checkout sebelum atau pada tanggal 15 bulan berjalan, bulan berjalan dapat dihitung sebagai cycle pertama.
- Jika checkout setelah tanggal 15, pastikan rule cycle pertama tetap jelas:
  - pembayaran pertama tetap saat checkout
  - recurring berikutnya mulai tanggal 15 bulan berikutnya
  - final cycle tetap 15 Januari

## Important Decision to Encode

Karena pembayaran pertama terjadi saat checkout, calculator harus membedakan:

```text
first_payment_date = checkout date
recurring_due_dates = tanggal 15 bulan berikutnya sampai 15 Januari
```

Contoh checkout September:

```text
first payment = saat checkout September
recurring = 15 Oct, 15 Nov, 15 Dec, 15 Jan
installment_count = 5
```

## Suggested Output DTO

```text
total_amount
currency_code
installment_count
first_payment_amount
monthly_base_amount
recurring_payment_amount
first_payment_date
recurring_due_dates[]
final_due_at
grace_deadlines[]
```

## Tests Required

- checkout sebelum tanggal 15
- checkout tepat tanggal 15
- checkout setelah tanggal 15
- checkout September sampai Januari
- checkout December sampai January
- checkout January
- price 300, 7 cycles => first 48, recurring 42
- different currencies

## Definition of Done

- Calculator punya unit tests.
- Semua angka berasal dari backend.
- Tidak ada logic kalkulasi di frontend.

## Non-Goals

- Belum PayPal API.
- Belum webhook.
- Belum email.

---

# Module 3 — Backend Checkout Contract

## Objective

Mengubah checkout backend agar bisa menampilkan opsi installment berdasarkan package.

## Scope

Backend first.

## Main Scope

Checkout payload harus menyediakan:

- `payment_options`
- full payment option
- installment option jika package `installment_enabled = true`
- installment summary hasil calculator

## Likely Touched Areas

- `PaymentCheckoutService`
- `CheckoutController`
- checkout resource/payload builder
- package resolver service jika ada

## Suggested Payload Shape

```json
{
  "package": {
    "id": 1,
    "title": "Masterclass Standard",
    "slug": "masterclass-standard",
    "price": "300",
    "currency_code": "USD"
  },
  "payment_options": [
    {
      "type": "pay_full",
      "label": "Pay in full",
      "amount_due_today": "300"
    },
    {
      "type": "installment",
      "label": "Installment",
      "amount_due_today": "48",
      "installment_count": 7,
      "recurring_amount": "42",
      "billing_day": 15,
      "final_due_at": "2027-01-15"
    }
  ]
}
```

## Validation Rule

Installment hanya boleh dipilih jika:

- package aktif
- package assign ke tier
- package installment enabled
- calculated installment count lebih dari 1
- currency valid
- amount valid

## Definition of Done

- Checkout page menerima payload installment summary.
- Existing full payment payload tetap berjalan.
- Backend menolak installment jika package tidak eligible.
- Belum ada PayPal subscription creation di modul ini jika ingin dipisah ketat.

## Non-Goals

- Belum frontend final.
- Belum subscription webhook.

---

# Module 4 — PayPal Subscription Provider Layer

## Objective

Menambahkan provider integration untuk PayPal Subscriptions tanpa mencampur business logic LMS ke PayPal service.

## Scope

Backend provider layer.

## Main Scope

Buat service baru:

```text
app/Services/Payments/PayPalSubscriptionService.php
```

atau namespace sejenis.

Service ini bertugas untuk:

- create product
- create plan
- create subscription
- get subscription
- cancel subscription
- suspend subscription
- activate subscription jika diperlukan
- extract subscription webhook references

## Provider Boundary Rule

Provider service hanya bicara ke PayPal.

Provider service tidak boleh:

- membuat user
- mengubah invoice
- mengubah account active/inactive
- mengirim email business event
- menghitung entitlement

## PayPal Data Mapping

Internal installment:

```text
first_payment_amount
monthly_base_amount
installment_count
recurring_due_dates
```

PayPal mapping yang kemungkinan dipakai:

```text
setup_fee = first_payment_amount
regular billing cycle fixed_price = monthly_base_amount
total_cycles = installment_count - 1
frequency.interval_unit = MONTH
frequency.interval_count = 1
```

Catatan:

- Karena first payment terjadi saat setup/checkout, backend harus memastikan event sukses pertama tercatat sebagai payment pertama.
- PayPal plan dapat perlu dibuat/cache per package + currency + schedule.

## Definition of Done

- Provider method tersedia.
- Sandbox config tetap memakai env yang sudah ada.
- Existing PayPal order/capture tidak rusak.
- Unit/feature test menggunakan fake HTTP PayPal.

## Non-Goals

- Belum webhook business processing.
- Belum frontend button subscription.

---

# Module 5 — Initial Checkout Subscription Orchestration

## Objective

Menghubungkan pilihan installment checkout ke pembuatan invoice, payment subscription, dan PayPal subscription.

## Scope

Backend checkout flow.

## Main Scope

Saat user memilih `payment_type = installment`:

1. resolve package
2. hitung installment plan
3. create invoice
4. create payment subscription aggregate
5. create PayPal subscription
6. simpan provider ids
7. return subscription approval data ke frontend

## Likely Touched Areas

- `CheckoutController`
- `PaymentCheckoutService`
- `PaymentSubscriptionService`
- `PayPalSubscriptionService`
- `PaymentSubscription` model
- tests checkout

## Important Rule

Untuk full payment, flow existing PayPal order/capture tetap dipakai.

Untuk installment, jangan pakai order/capture one-time sebagai recurring engine.

## Suggested New Service

```text
app/Services/Payments/PaymentSubscriptionService.php
```

Responsibilities:

- create local subscription aggregate
- coordinate with calculator
- coordinate with provider service
- persist snapshot
- update status from `draft` to `approval_pending`

## Definition of Done

- Backend bisa membuat subscription checkout session.
- Invoice dibuat dengan `payment_type = installment`.
- `payment_subscriptions` row dibuat.
- PayPal subscription id tersimpan.
- Existing pay full tetap lolos regression.

## Non-Goals

- Belum deactivate overdue.
- Belum final frontend polish.

---

# Module 6 — Subscription Webhook Event Log

## Objective

Membuat semua event PayPal subscription masuk ke event log idempotent sebelum diproses.

## Scope

Backend webhook foundation.

## Main Scope

Webhook controller harus bisa:

- verify signature
- identify subscription event
- save raw event ke `payment_subscription_events`
- prevent duplicate processing by provider event id
- return success response untuk duplicate yang sudah diproses

## Likely Touched Areas

- `PayPalWebhookController`
- `PayPalService`
- `PayPalSubscriptionService`
- `PaymentSubscriptionEvent` model
- webhook tests

## Event Types to Consider

Minimal:

```text
BILLING.SUBSCRIPTION.CREATED
BILLING.SUBSCRIPTION.ACTIVATED
BILLING.SUBSCRIPTION.CANCELLED
BILLING.SUBSCRIPTION.SUSPENDED
BILLING.SUBSCRIPTION.EXPIRED
PAYMENT.SALE.COMPLETED
PAYMENT.SALE.DENIED
BILLING.SUBSCRIPTION.PAYMENT.FAILED
```

Event list final harus disesuaikan dengan payload PayPal sandbox aktual.

## Definition of Done

- Event duplicate tidak diproses dua kali.
- Raw payload tersimpan.
- Existing order webhook tetap bekerja.
- Belum wajib mengubah invoice balance pada modul ini jika dipisahkan.

## Non-Goals

- Belum scheduler overdue.
- Belum email business notification.

---

# Module 7 — Subscription Webhook Handler

## Objective

Memproses event subscription menjadi perubahan domain YogaFX.

## Scope

Backend business processing.

## Main Scope

Buat service:

```text
app/Services/Payments/InstallmentWebhookHandler.php
```

Tanggung jawab:

- resolve subscription dari provider subscription id
- update payment subscription status
- append payment activity saat payment success
- mark failed/past_due saat payment gagal
- set grace deadline
- trigger finalizer/business continuation saat first payment sukses
- mark completed saat lunas

## Important Flow: First Payment Success

Saat pembayaran pertama sukses:

1. create/update payment activity success
2. update invoice balance
3. invoice menjadi `installment`
4. pending registration menjadi `payment_success`
5. user dibuat/dihubungkan
6. onboarding state dibuat
7. continuation email dikirim
8. subscription menjadi `active`

Ini harus tetap konsisten dengan existing initial checkout finalization behavior.

## Important Flow: Recurring Payment Success

Saat pembayaran bulanan sukses:

1. append payment activity
2. reduce invoice balance
3. increment paid count
4. clear failed state if any
5. if user inactive because installment overdue, reactivate user
6. send admin installment success notification
7. if balance due <= 0, mark invoice `paid_full`, subscription `completed`, send payment completed email

## Important Flow: Payment Failed

Saat payment gagal:

1. mark subscription `past_due`
2. set `last_payment_failed_at`
3. set `grace_deadline_at` based on due date + 3 days
4. send admin failed payment notification
5. do not immediately deactivate user

## Definition of Done

- Payment success creates ledger row.
- Invoice balance updates correctly.
- First payment opens onboarding.
- Recurring success can reactivate inactive user.
- Payment failed does not deactivate before grace deadline.
- Idempotency safe.

## Non-Goals

- Belum UI admin dashboard subscription.
- Belum manual retry UI.

---

# Module 8 — Payment Ledger and Invoice Balance Sync

## Objective

Memastikan `payment_activities` tetap menjadi ledger pembayaran dan invoice balance selalu benar.

## Scope

Backend domain consistency.

## Main Scope

Tambahkan aturan:

- setiap successful installment charge = satu `payment_activities` row
- `payment_reference` harus memakai provider transaction/capture/sale id yang unik
- invoice `balance_due` dikurangi berdasarkan amount payment
- invoice `status`:
  - `installment` jika balance masih ada
  - `paid_full` jika balance lunas
- duplicate provider transaction tidak boleh double reduce balance

## Likely Touched Areas

- `PaymentFinalizerService`
- `PaymentSubscriptionService`
- `InstallmentWebhookHandler`
- `Payment` model
- tests

## Definition of Done

- Ledger akurat untuk first payment dan recurring payment.
- Invoice tidak double paid saat webhook duplicate.
- Existing pay full finalizer tetap aman.

## Non-Goals

- Belum analytics/reporting.

---

# Module 9 — Overdue Grace Period Scheduler

## Objective

Menonaktifkan akun student otomatis jika cicilan gagal melewati H+3 setelah tanggal 15.

## Scope

Backend command + scheduler.

## Main Scope

Buat command baru:

```text
installments:sync-overdue-status
```

Command berjalan harian.

Tugas command:

- cari subscription `past_due`
- cek `grace_deadline_at`
- jika sekarang melewati grace deadline dan belum paid
- set user inactive
- update subscription status jika perlu
- send admin overdue/inactive notification
- idempotent agar tidak mengirim notifikasi berkali-kali

## Likely Touched Areas

- `app/Console/Commands/SyncOverdueInstallmentsCommand.php`
- `routes/console.php`
- `OverdueInstallmentService`
- `User` model
- email notification service
- tests

## Important Rule

Jangan hardcode command hanya berjalan tanggal 18.

Walaupun grace deadline umumnya tanggal 18, command harus berjalan harian agar aman terhadap:

- delayed webhook
- retry
- timezone
- manual fix
- failed job

## Definition of Done

- Command bisa dijalankan manual.
- Scheduler harian aktif.
- Overdue account menjadi inactive.
- Notifikasi admin terkirim sekali.
- Belum overdue tidak dinonaktifkan.

## Non-Goals

- Tidak membuat UI manual retry.
- Tidak mengubah login middleware selain memakai `is_active` existing.

---

# Module 10 — Account Deactivation and Reactivation

## Objective

Menghubungkan lifecycle installment ke status akun student.

## Scope

Backend business rule.

## Main Scope

Rules:

- first payment success membuat account/onboarding seperti existing.
- failed payment tidak langsung inactive.
- overdue after grace membuat `users.is_active = false`.
- later payment success membuat `users.is_active = true`.
- reactivation harus dicatat di subscription metadata/event log.

## Likely Touched Areas

- `OverdueInstallmentService`
- `InstallmentWebhookHandler`
- `User` model
- login/student active middleware jika perlu dicek
- tests

## Definition of Done

- User inactive tidak bisa mengakses student area.
- Payment recovery otomatis mengaktifkan user.
- Tidak mengaktifkan user jika subscription masih cancelled/suspended final.
- Test active/inactive lifecycle tersedia.

## Non-Goals

- Tidak membuat entitlement expiry baru.
- Tidak membuat multi-tier entitlement.

---

# Module 11 — Email Notification Types

## Objective

Menambahkan email notification untuk lifecycle installment.

## Scope

Backend email domain.

## Main Scope

Tambahkan notification types:

```text
installment_payment_success
installment_payment_failed
installment_overdue_inactive
installment_payment_completed
```

## Recipients

- `installment_payment_success`: admin
- `installment_payment_failed`: admin
- `installment_overdue_inactive`: admin
- `installment_payment_completed`: student, optional admin

## Likely Touched Areas

- `EmailNotificationTypeRegistry`
- `EmailNotificationTemplateDefaults`
- `EmailNotificationService`
- jobs/listeners/events baru
- tests
- optional admin email UI menu if registry does not auto expose

## Merge Tags Suggested

```text
student_name
student_email
package_title
tier_name
invoice_number
payment_amount
currency_code
installment_count
installments_paid_count
balance_due
next_due_at
grace_deadline_at
payment_completed_at
```

## Definition of Done

- Default templates tersedia.
- Admin recipients configurable.
- Email logs tercatat.
- Trigger success/failed/overdue/completed berjalan.
- Payment completed student email terkirim saat lunas.

## Non-Goals

- Tidak redesign email UI.

---

# Module 12 — Frontend Public Checkout Update

## Objective

Mengubah checkout UI agar mendukung full payment dan installment subscription dari backend contract.

## Scope

Frontend public checkout only.

## Main Scope

Update checkout page agar:

- membaca `payment_options` dari backend
- tidak menghitung installment sendiri
- menampilkan:
  - first payment amount
  - recurring amount
  - number of installments
  - billing day tanggal 15
  - final billing date 15 Januari
- memakai PayPal subscription flow untuk installment
- tetap memakai PayPal order/capture untuk full payment

## Likely Touched Areas

- `resources/js/Pages/Public/Checkout.jsx`
- `resources/js/Components/public/PublicCheckoutPanel.jsx`
- checkout API interaction helpers

## Important Rule

Jangan sentuh upgrade checkout di phase pertama.

## Definition of Done

- Full payment tetap berjalan.
- Installment option tampil hanya jika backend mengirimnya.
- UI tidak lagi hardcode "Pay in 4".
- Amounts berasal dari backend.
- On approval, frontend mengikuti redirect URL dari backend.

## Non-Goals

- Tidak mengubah mobile Flutter.
- Tidak membuat admin subscription UI.

---

# Module 13 — Tests and Regression Pass

## Objective

Menjamin installment tidak merusak checkout existing.

## Scope

Backend + frontend smoke.

## Required Tests

### Unit Tests

- installment calculator
- first payment formula
- due date generation
- grace deadline generation
- currency rounding behavior

### Feature Tests

- package with installment disabled rejects installment
- package with installment enabled returns installment option
- start installment checkout creates invoice + subscription
- first payment webhook opens onboarding
- recurring payment success reduces balance
- duplicate webhook does not double reduce balance
- failed payment sets past_due
- overdue command deactivates user after grace
- later payment success reactivates user
- final payment marks invoice paid_full
- payment completed email sent
- full payment regression still passes

### Webhook Tests

- event log stores payload
- duplicate provider event ignored
- unknown event ignored safely
- order webhook existing still works

## Definition of Done

- Existing payment tests pass.
- New installment tests pass.
- Manual sandbox test checklist documented.

## Non-Goals

- No load testing required in phase one.

---

# Module 14 — Documentation and Admin Verification

## Objective

Menutup implementasi dengan dokumentasi operasional agar admin dan developer memahami cara kerja installment.

## Scope

Docs + verification checklist.

## Main Scope

Update atau buat docs:

```text
docs/installment-implementation.md
docs/installment-admin-operations.md
docs/installment-paypal-webhook-notes.md
```

Minimal isi:

- cara package mengaktifkan installment
- rule tanggal 15
- rule deadline 15 Januari
- rule first payment
- rule failed payment H+3
- cara akun inactive/reactive
- webhook yang harus didaftarkan di PayPal
- troubleshooting event duplicate
- sandbox test flow

## Definition of Done

- Dokumen tersedia.
- Admin dapat memahami setup package installment.
- Developer dapat memahami webhook dan scheduler.

## Non-Goals

- Tidak membuat analytics dashboard.

---

## 5. Recommended Implementation Boundaries

## Touch Allowed

- payment schema baru
- payment subscription models
- payment subscription services
- PayPal provider service for subscription
- checkout backend contract
- PayPal webhook handler
- scheduler/console command
- email notification types
- public checkout component
- tests

## Avoid in Phase One

- upgrade installment
- mobile Flutter
- learning module/progress domain
- certificate rules
- admin analytics dashboard
- major refactor of `PaymentFinalizerService`
- deleting existing PayPal order/capture flow

---

## 6. Risk Notes

### Risk 1 — PayPal Subscription Event Mapping

PayPal webhook payload for subscriptions may differ from one-time orders. Implement event log first so debugging is safe.

### Risk 2 — First Payment Recognition

First payment is business-critical because it opens onboarding. Do not activate onboarding only after all installments are complete.

### Risk 3 — Duplicate Webhooks

Every provider event and transaction id must be idempotent.

### Risk 4 — Rounding and Currency

The formula applies to all currencies. Keep money calculations centralized in calculator/service, not frontend.

### Risk 5 — Existing Payment Regression

Full payment order/capture is already live. Do not rewrite it while adding subscriptions.

### Risk 6 — Account Reactivation

Reactivation should happen only after successful recovery payment, not merely after subscription status changes to active.

---

## 7. Final Summary

Installment implementation should start only after package domain is stable.

Final target architecture:

```text
Package
↓
Checkout
↓
Invoice
↓
PaymentSubscription
↓
PayPal Subscription
↓
Webhook Events
↓
PaymentActivities
↓
Invoice Balance
↓
Onboarding / Account Status / Email
```

This phase should deliver:

- dynamic installment until 15 January
- first payment at checkout
- recurring charge on the 15th
- PayPal subscription lifecycle
- payment ledger per charge
- invoice balance sync
- onboarding after first payment
- overdue H+3 deactivation
- automatic reactivation after recovery payment
- admin/student notifications
- no regression to full payment