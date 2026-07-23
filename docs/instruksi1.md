Ada dua hal yang perlu diperbaiki pada flow installment YogaFX LMS. Tolong pahami dulu konteksnya, lalu lakukan audit sebelum coding besar.

## Konteks sistem

Project YogaFX LMS menggunakan Laravel + Inertia React.

Saat ini sistem sudah punya:

* `Package` sebagai commerce/payment offer layer
* `AccessTier` sebagai entitlement/access layer
* full payment PayPal
* installment berbasis PayPal Subscriptions
* webhook PayPal untuk memproses first payment dan recurring payment

Namun ada dua masalah/requirement baru:

1. Setelah installment/PayPal approval selesai, user belum diarahkan ke halaman enrollment.
2. Billing day installment tidak boleh fixed dari package saja. Calon student harus bisa memilih tanggal pembayaran: tanggal 1 atau tanggal 15 setiap bulan.

---

# Masalah 1 — User belum redirect ke halaman enrollment setelah pembayaran

Sebelum implementasi installment, setelah pembayaran berhasil user diarahkan ke halaman enrollment/personal information.

Setelah installment dibuat, user melihat pesan seperti:

```text
PayPal approval received. YogaFX is now waiting for the first payment confirmation from the sandbox webhook.
PayPal approval is already attached to this checkout. YogaFX is waiting for the first payment confirmation webhook before opening onboarding.
```

## Pemahaman penting

Webhook PayPal tidak bisa langsung redirect browser user.

Webhook hanya komunikasi server-to-server:

```text
PayPal → backend YogaFX
```

Jadi redirect ke enrollment harus dilakukan oleh frontend setelah backend menyatakan onboarding sudah ready.

## Flow installment yang benar

```text
User approve PayPal subscription
→ frontend menerima onApprove
→ frontend masuk waiting/processing state
→ PayPal webhook first payment success masuk ke backend
→ backend membuat payment activity
→ invoice balance berkurang
→ pending registration menjadi payment_success
→ user/onboarding_state dibuat lewat PaymentFinalizerService
→ frontend polling checkout status
→ jika onboarding_ready
→ frontend redirect ke halaman enrollment
```

## Yang harus diaudit

Cek file berikut:

* `resources/js/Pages/Public/Checkout.jsx`
* `resources/js/Components/public/PublicCheckoutPanel.jsx`
* `app/Http/Controllers/CheckoutController.php`
* `app/Services/PaymentCheckoutService.php`
* `app/Services/PaymentFinalizerService.php`
* `app/Services/Payments/InstallmentWebhookHandler.php`
* `app/Services/Payments/PaymentSubscriptionService.php`
* `app/Http/Controllers/OnboardingController.php`
* `routes/web.php`
* endpoint checkout status jika sudah ada

## Target audit

Cari tahu:

1. Apakah full payment masih redirect ke enrollment seperti sebelum installment.
2. Apakah regression hanya terjadi di installment.
3. Apakah webhook first payment success sudah masuk ke backend.
4. Apakah `InstallmentWebhookHandler` memanggil `PaymentFinalizerService`.
5. Apakah `PaymentFinalizerService` membuat/mengupdate:

   * `pending_registrations.status = payment_success`
   * user
   * onboarding_state
6. Apakah checkout status endpoint mengembalikan status onboarding ready.
7. Apakah frontend melakukan polling setelah PayPal approval.
8. Apakah frontend punya logic redirect ke enrollment setelah status onboarding ready.
9. Apakah route enrollment yang dikembalikan masih benar.
10. Apakah direct installment approval hanya attach subscription tanpa membuka onboarding terlalu awal.

## Expected behavior

### Full payment

Full payment harus tetap seperti sebelum installment:

```text
payment success
→ PaymentFinalizerService
→ onboarding_state dibuat
→ user diarahkan ke enrollment/personal information
```

Jika full payment rusak, itu regression dan harus diperbaiki.

### Installment

Installment tidak boleh membuka onboarding hanya karena PayPal approval.

Installment baru boleh membuka onboarding setelah first payment success dari webhook atau sandbox simulation yang aman.

Setelah first payment success diproses:

```text
frontend polling status
→ status onboarding_ready
→ redirect ke enrollment page
```

## Jika endpoint status belum ada

Buat/perbaiki endpoint checkout status yang bisa mengembalikan response seperti:

```json
{
  "status": "waiting_for_first_payment",
  "onboarding_ready": false,
  "onboarding_url": null
}
```

dan setelah webhook sukses:

```json
{
  "status": "onboarding_ready",
  "onboarding_ready": true,
  "onboarding_url": "..."
}
```

Frontend harus polling endpoint ini setelah PayPal approval.

## Local sandbox limitation

Jika testing di localhost, PayPal webhook tidak akan masuk tanpa public tunnel.

Dokumentasikan bahwa local dev perlu:

```text
ngrok/cloudflared public URL
→ PayPal webhook URL diarahkan ke /webhooks/paypal
```

Namun UX tetap harus jelas:

* waiting state
* polling status
* redirect otomatis jika status sudah onboarding_ready

---

# Masalah 2 — Calon student harus bisa pilih billing day 1 atau 15

Saat ini sistem masih punya field di Package:

```text
Fixed Billing Day
```

Contoh konfigurasi saat ini:

```text
Installment Ready: Enabled
Billing Interval Unit: MONTH
Billing Interval Count: 3
Fixed Billing Day: 15
Installment Deadline Month: 5
Installment Deadline Day: ...
```

Ini tidak sesuai lagi.

## Requirement baru

Calon student harus bisa memilih tanggal pembayaran sendiri saat checkout.

Pilihan yang tersedia:

```text
1
15
```

Artinya:

* Jika student memilih tanggal 1, pembayaran recurring berikutnya selalu tanggal 1 setiap bulan.
* Jika student memilih tanggal 15, pembayaran recurring berikutnya selalu tanggal 15 setiap bulan.
* Pilihan itu harus tetap untuk subscription student tersebut.
* Package tidak boleh lagi memaksa hanya satu fixed billing day untuk semua student.

## Konsep domain baru

### Package

Package hanya menentukan bahwa installment tersedia dan tanggal apa saja yang diizinkan.

Saran field:

```text
installment_enabled
billing_interval_unit
billing_interval_count
allowed_billing_days
installment_deadline_month
```

Untuk saat ini:

```text
billing_interval_unit = MONTH
billing_interval_count = 1
allowed_billing_days = [1, 15]
installment_deadline_month = 1
```

`fixed_billing_day` tidak boleh lagi menjadi source of truth utama untuk subscription baru.

Jika kolom `fixed_billing_day` masih ada, jangan hapus secara agresif. Jadikan legacy/deprecated atau fallback saja.

### Checkout

Saat user memilih installment, frontend public checkout harus menampilkan pilihan:

```text
Monthly billing date:
- Every 1st of the month
- Every 15th of the month
```

Frontend harus mengirim pilihan ini ke backend, misalnya:

```json
{
  "payment_type": "installment",
  "billing_day": 1
}
```

atau:

```json
{
  "payment_type": "installment",
  "billing_day": 15
}
```

### PaymentSubscription

Pilihan user harus disimpan sebagai snapshot di `payment_subscriptions`, misalnya:

```text
billing_day = 1 atau 15
```

Setelah subscription dibuat, billing day tidak boleh berubah otomatis walaupun konfigurasi package berubah.

## Calculator

`InstallmentPlanCalculator` tidak boleh hardcode tanggal 15 lagi.

Calculator harus menerima billing day dari checkout:

```text
billing_day = 1 atau 15
```

Contoh:

Checkout September, pilih billing day 15:

```text
first payment = saat checkout
recurring due dates = 15 Oct, 15 Nov, 15 Dec, 15 Jan
```

Checkout September, pilih billing day 1:

```text
first payment = saat checkout
recurring due dates = 1 Oct, 1 Nov, 1 Dec, 1 Jan
```

## Admin Package UI

Ubah UI Package agar tidak lagi menampilkan `Fixed Billing Day` sebagai single input utama.

Ganti menjadi pilihan allowed billing days:

```text
Allowed Billing Days
[ ] 1st of the month
[ ] 15th of the month
```

Untuk package installment, minimal satu billing day harus dipilih.

Jika kolom database `fixed_billing_day` masih ada, jangan hapus dulu kalau berisiko. Tapi jangan jadikan itu source of truth untuk subscription baru.

## Backend validation

Validasi:

* `billing_day` wajib jika `payment_type = installment`
* `billing_day` hanya boleh `1` atau `15`
* `billing_day` harus termasuk allowed billing days package
* package harus `installment_enabled = true`
* package harus active dan assigned ke access tier

## PayPal Subscription

Saat membuat PayPal plan/subscription, gunakan selected billing day dari user sebagai dasar schedule.

Jika PayPal API tidak bisa menjamin exact recurring day 1/15 hanya dari plan biasa, jangan diam-diam mengabaikan requirement. Laporkan limitation dan strategi yang dipakai.

---

# File yang perlu diaudit/update

Cek dan update jika relevan:

* `database/migrations/*packages*`
* `database/migrations/*payment_subscriptions*`
* `app/Models/Package.php`
* `app/Models/PaymentSubscription.php`
* `app/Services/Installments/InstallmentPlanCalculator.php`
* `app/Services/Payments/PaymentSubscriptionService.php`
* `app/Services/Payments/InstallmentWebhookHandler.php`
* `app/Services/PaymentCheckoutService.php`
* `app/Services/PaymentFinalizerService.php`
* `app/Http/Controllers/CheckoutController.php`
* `app/Http/Controllers/OnboardingController.php`
* `resources/js/Pages/Admin/Packages/*`
* `resources/js/Components/PackageForm.jsx`
* `resources/js/Pages/Public/Checkout.jsx`
* `resources/js/Components/public/PublicCheckoutPanel.jsx`
* `routes/web.php`
* tests installment/package/payment terkait

---

# Tests yang perlu dibuat/update

Tambahkan atau update test untuk:

## Redirect enrollment/payment success

1. Full payment success masih menghasilkan onboarding URL.
2. Full payment masih redirect/siap redirect ke enrollment.
3. Installment approval saja belum membuka onboarding.
4. Installment first payment webhook membuat onboarding_state.
5. Setelah webhook first payment success, checkout status menjadi onboarding_ready.
6. Checkout status mengembalikan onboarding_url.
7. Duplicate webhook tidak membuat onboarding/payment ganda.
8. Existing installment ledger test tetap lolos.
9. Existing full payment test tetap lolos.

## Billing day 1/15

1. Package bisa punya allowed billing days `[1, 15]`.
2. Checkout installment wajib mengirim billing_day.
3. Billing day selain 1 atau 15 ditolak.
4. Billing day yang tidak termasuk allowed billing days package ditolak.
5. Calculator menghasilkan schedule tanggal 1 jika user memilih 1.
6. Calculator menghasilkan schedule tanggal 15 jika user memilih 15.
7. PaymentSubscription menyimpan billing_day sebagai snapshot.
8. Admin Package UI tidak lagi memakai Fixed Billing Day sebagai single source.
9. Existing full payment tetap tidak rusak.
10. Existing installment webhook/ledger test tetap lolos.

---

# Batasan

Jangan ubah mobile.
Jangan ubah upgrade installment.
Jangan ubah email/scheduler kecuali memang perlu untuk test status.
Jangan membuka onboarding hanya karena PayPal approval.
Jangan menghapus PayPal Subscription module.
Jangan refactor besar di luar flow status/enrollment dan billing day.
Jangan hapus field legacy secara agresif.
Jangan ubah flow full payment yang sebelumnya sudah benar.

---

# Urutan kerja yang aman

Lakukan bertahap:

1. Audit dulu root cause user tidak redirect ke enrollment.
2. Pastikan full payment tidak rusak.
3. Perbaiki checkout status endpoint dan frontend polling/redirect.
4. Pastikan installment baru redirect ke enrollment setelah first payment webhook success.
5. Baru ubah billing day dari fixed package menjadi pilihan calon student 1/15.
6. Update admin package UI.
7. Update calculator dan checkout payload.
8. Update tests.
9. Jalankan regression full payment dan installment.

---

# Output yang saya butuhkan

Sebelum coding besar, laporkan dulu:

1. Apakah full payment ikut rusak atau hanya installment.
2. Root cause user tidak redirect ke enrollment.
3. Apakah webhook first payment berhasil diproses.
4. Apakah status polling sudah ada atau belum.
5. Field mana yang sekarang masih hardcode `fixed_billing_day`.
6. Rekomendasi migration yang aman untuk allowed billing days dan billing day snapshot.
7. File yang perlu diubah.
8. Urutan implementasi paling aman.
9. Risiko terhadap PayPal subscription schedule.

Setelah implementasi, laporkan:

1. File yang diubah.
2. Migration yang dibuat.
3. Test yang dijalankan.
4. Hasil test.
5. Hasil manual test:

   * full payment
   * installment approval
   * installment after first payment webhook
   * billing day 1
   * billing day 15
