## PayPal Installment Debugging History and Recovery

Dokumen ini merangkum masalah PayPal installment/subscription YogaFX LMS dari awal sampai akhir, termasuk gejala yang muncul, akar masalah, cara investigasi, perubahan kode yang dilakukan, dan prosedur recovery aman untuk local/dev.

Dokumen ini fokus pada flow initial checkout installment PayPal untuk package YogaFX, terutama kasus ketika subscription PayPal sudah aktif tetapi onboarding student masih stuck di halaman approval.

---

## 1. Ringkasan Masalah Besar

Selama debugging, ada beberapa masalah berbeda yang saling menumpuk:

1. Database schema live tidak sinkron dengan migration/code.
2. Webhook PayPal sempat diblokir CSRF.
3. Verifikasi signature PayPal sempat gagal karena `PAYPAL_WEBHOOK_ID` tidak cocok.
4. Event `BILLING.SUBSCRIPTION.ACTIVATED` berhasil masuk, tetapi logic lama hanya mengaktifkan subscription dan belum menganggap first payment sukses.
5. Event `PAYMENT.SALE.COMPLETED` untuk pembayaran pertama sempat gagal lebih dulu, sehingga onboarding tetap stuck walaupun subscription di PayPal sudah `ACTIVE`.
6. Karena event payment tidak selalu mudah di-resend dari dashboard client, dibutuhkan recovery internal yang aman untuk local/dev.

---

## 2. Gejala yang Muncul

### 2.1 Error Database Awal

Log Laravel sempat menunjukkan error:

```text
SQLSTATE[42703]: Undefined column: 7 ERROR:
column "installment_billing_day" of relation "pending_registrations" does not exist
```

Efeknya:

- checkout installment gagal saat backend mencoba menyimpan `pending_registrations.installment_billing_day`
- flow belum sampai ke tahap webhook dengan benar

### 2.2 Checkout Stuck Setelah Approve PayPal

Halaman package tetap menampilkan pesan seperti:

```text
Waiting for the first PayPal payment webhook.
```

Padahal di sisi PayPal:

- subscription sudah terlihat `ACTIVE`
- delivery webhook terlihat berjalan

Efeknya:

- `payment_subscriptions.status` bisa berubah menjadi `active`
- tetapi `pending_registrations.status` belum berubah ke `payment_success`
- onboarding belum terbuka

### 2.3 Webhook Response Tidak Konsisten

Dalam proses debugging, `POST /webhooks/paypal` sempat menghasilkan beberapa status:

1. `419` karena CSRF
2. `401 Unauthorized` karena verifikasi signature gagal
3. `200 OK` setelah konfigurasi diperbaiki

---

## 3. Kronologi Akar Masalah

### 3.1 Mismatch Migration History vs Live Database

Masalah pertama adalah mismatch antara history migration dan schema database yang benar-benar aktif.

Code checkout/installment sudah mengisi:

- `pending_registrations.installment_billing_day`

Tetapi kolom tersebut belum ada di live database yang sedang dipakai untuk testing.

Root cause:

- migration/patch schema yang dibutuhkan code belum benar-benar diterapkan ke database aktif

Solusi:

- sinkronkan schema database dengan migration/code
- pastikan kolom installment yang dipakai backend benar-benar ada

Setelah schema sinkron, checkout installment bisa lanjut ke tahap approval PayPal.

### 3.2 Webhook PayPal Tidak Bisa Masuk Karena CSRF

Masalah berikutnya muncul saat PayPal mencoba memanggil:

```text
POST /webhooks/paypal
```

Route webhook sempat terkena proteksi CSRF, sehingga request eksternal dari PayPal dibalas:

```text
419 Page Expired
```

Root cause:

- route webhook PayPal belum dikecualikan dari validasi CSRF

Solusi:

- exclude `/webhooks/paypal` dari CSRF middleware
- jangan menonaktifkan middleware lain yang tidak perlu

Hasil:

- webhook tidak lagi diblokir oleh CSRF

### 3.3 Signature Verification Gagal Karena Webhook ID Salah

Setelah CSRF selesai, webhook berubah dari `419` menjadi:

```text
401 Unauthorized
```

Artinya request sudah sampai ke Laravel, tetapi verifikasi signature PayPal gagal.

Root cause yang paling penting:

- `PAYPAL_WEBHOOK_ID` di `.env` belum cocok dengan webhook listener yang didaftarkan di akun PayPal client

Konfigurasi yang akhirnya benar:

```env
PAYPAL_WEBHOOK_ID=13J10434JY870804E
```

Webhook listener URL yang dipakai saat debugging:

```text
https://nikola-exorcismal-insuppressibly.ngrok-free.dev/webhooks/paypal
```

Catatan:

- webhook ID terkait langsung dengan webhook listener yang didaftarkan pada akun PayPal yang aktif
- jika URL tunnel berubah atau client mendaftarkan listener lain, webhook ID bisa berbeda

Solusi:

1. pastikan ngrok URL benar dan aktif
2. client mendaftarkan URL itu di PayPal webhook settings
3. ambil webhook ID yang benar dari listener tersebut
4. set ke `.env`

Hasil:

- event `BILLING.SUBSCRIPTION.ACTIVATED` mulai lolos verifikasi signature
- Laravel membalas `200 OK`

### 3.4 Subscription Activated Masuk, Tapi Onboarding Masih Stuck

Setelah `PAYPAL_WEBHOOK_ID` benar, event berikut berhasil:

```json
{
  "event_type": "BILLING.SUBSCRIPTION.ACTIVATED",
  "resource": {
    "id": "I-4P3S5GCM47FT",
    "status": "ACTIVE",
    "billing_info": {
      "last_payment": {
        "amount": {
          "currency_code": "USD",
          "value": "25.0"
        },
        "time": "2026-06-29T06:59:05Z"
      }
    }
  }
}
```

Laravel sudah membalas:

```text
200 OK
```

Tetapi database masih menunjukkan kondisi seperti:

- `payment_subscriptions.status = active`
- `first_payment_paid_at = null`
- `installments_paid_count = 0`
- invoice masih `unpaid`
- belum ada `payment_activities` sukses untuk first payment
- `pending_registrations.status` masih `checkout_opened`
- `onboarding_state` belum terbentuk

Root cause:

- logic lama untuk `BILLING.SUBSCRIPTION.ACTIVATED` hanya menandai subscription sebagai `active`
- field `billing_info.last_payment` di payload belum dipakai untuk memproses pembayaran pertama

Akibatnya backend masih menganggap:

- subscription aktif, tetapi first payment belum finalized
- onboarding belum boleh dibuka

### 3.5 Event PAYMENT.SALE.COMPLETED Sempat Gagal dan Tidak Otomatis Memulihkan State

Untuk subscription yang sama, event payment pertama:

```text
PAYMENT.SALE.COMPLETED
```

pernah masuk, tetapi saat itu masih dibalas:

```text
401 Unauthorized
```

Itu berarti event payment pertama tidak berhasil diproses ketika pertama kali datang.

Walaupun kemudian event activation berhasil `200`, state business tetap belum pulih sendiri karena:

1. event payment yang gagal sebelumnya belum tentu di-resend lagi oleh PayPal
2. logic activation lama belum memakai `last_payment`

Jadi sistem berada di state setengah benar:

- provider subscription aktif
- tetapi first payment di backend belum diselesaikan

---

## 4. Prinsip Flow yang Benar

Untuk initial installment YogaFX, flow business yang benar adalah:

1. User approve subscription di PayPal.
2. Backend perlu memastikan pembayaran pertama dianggap sukses.
3. Pembayaran pertama harus membuat atau memperbarui ledger `payment_activities`.
4. `PaymentFinalizerService` harus dijalankan agar:
   - invoice bergerak dari `unpaid`
   - `pending_registrations.status` menjadi `payment_success`
   - `onboarding_state` terbentuk
   - onboarding bisa dilanjutkan

Artinya:

- `subscription active` saja belum cukup
- yang membuka onboarding adalah `first payment success`, bukan sekadar status subscription di provider

---

## 5. Perubahan Kode yang Dilakukan

### 5.1 Perbaikan Route Webhook

Webhook PayPal dikecualikan dari CSRF sehingga request dari PayPal tidak lagi diblokir.

Tujuan:

- membiarkan webhook eksternal masuk
- tetap menjaga verifikasi signature PayPal sebagai lapisan keamanan utama

### 5.2 Verifikasi Signature Tetap Dipertahankan

Selama debugging, signature verification tidak pernah dibypass.

Yang diperbaiki adalah:

- konfigurasi `PAYPAL_WEBHOOK_ID`
- observability/logging ketika verifikasi gagal

Ini penting karena solusi yang dipilih harus aman, bukan sekadar memaksa webhook menjadi sukses.

### 5.3 Handler BILLING.SUBSCRIPTION.ACTIVATED Diperbaiki

File utama:

- `app/Services/Payments/InstallmentWebhookHandler.php`

Perubahan utama:

1. event `BILLING.SUBSCRIPTION.ACTIVATED` tetap mengubah subscription menjadi `active`
2. jika payload punya `billing_info.last_payment`, handler sekarang juga memproses pembayaran pertama
3. logic pembayaran sukses dipusatkan ke method bersama agar:
   - activation event
   - sale completed event
   memakai flow finalisasi yang sama

Hasil:

- activation event yang valid kini bisa sekaligus memulihkan first payment
- invoice dan onboarding bisa bergerak tanpa menunggu logic terpisah yang berbeda

### 5.4 Deduplication untuk First Payment

Masih di `InstallmentWebhookHandler`, ditambahkan proteksi agar:

- jika first payment sudah sukses diproses dari activation payload
- lalu `PAYMENT.SALE.COMPLETED` datang belakangan

maka sistem tidak:

- membuat payment activity ganda
- menaikkan `installments_paid_count` dua kali

Ini penting karena PayPal bisa mengirim lebih dari satu event yang berhubungan dengan pembayaran pertama.

### 5.5 Recovery Command untuk Local/Dev

Karena tidak nyaman meminta client resend webhook berulang-ulang, dibuat command recovery:

```bash
php artisan yogafx:installment-recover-first-payment {provider_subscription_id}
```

Contoh:

```bash
php artisan yogafx:installment-recover-first-payment I-4P3S5GCM47FT
```

File utama:

- `app/Console/Commands/RecoverInstallmentFirstPaymentCommand.php`

Tujuan command:

1. hanya aman untuk `local`, `testing`, atau `staging`
2. mencari `payment_subscriptions` berdasarkan `provider_subscription_id`
3. memastikan status subscription sudah `active`
4. memastikan first payment belum pernah berhasil diproses
5. memakai payload `BILLING.SUBSCRIPTION.ACTIVATED` yang sudah tersimpan bila ada
6. fallback ke fetch detail subscription dari PayPal API jika payload tidak cukup
7. mengambil `billing_info.last_payment.amount` dan `billing_info.last_payment.time`
8. memproses first payment memakai logic finalisasi yang sama dengan webhook handler
9. memanggil `PaymentFinalizerService`
10. menjaga idempotency agar command tidak menggandakan efek jika dijalankan dua kali

Hasil yang diharapkan setelah command sukses:

- `first_payment_paid_at` terisi
- `installments_paid_count >= 1`
- ada row sukses di `payment_activities`
- invoice tidak lagi `unpaid`
- `pending_registrations.status = payment_success`
- `onboarding_state` terbentuk

---

## 6. State Database yang Menjadi Penanda Sukses

Sesudah first payment benar-benar selesai diproses, state yang benar adalah:

### payment_subscriptions

- `status = active` atau `completed` bila memang sudah lunas
- `first_payment_paid_at` terisi
- `installments_paid_count` minimal `1`

### payment_subscription_events

- event `BILLING.SUBSCRIPTION.ACTIVATED` tersimpan
- status event `processed`
- jika recovery command dipakai, event recovery atau event existing tetap tertaut dengan payment activity yang benar

### payment_activities

Harus ada ledger sukses untuk pembayaran pertama dengan referensi stabil seperti:

```text
subscription_activation:{subscription_id}:{timestamp}
```

### invoices

- status tidak lagi `unpaid`
- untuk installment awal normalnya bergerak ke `installment`
- `balance_due` berkurang sesuai first payment

### pending_registrations

- `status = payment_success`

### onboarding_states

- state onboarding sudah terbentuk sehingga user bisa lanjut enrollment/signup

---

## 7. Kenapa Halaman Bisa Tetap Stuck Walaupun Subscription PayPal Sudah ACTIVE

Ini adalah poin terpenting dari debugging ini.

Jawabannya:

Karena sistem YogaFX tidak membuka onboarding hanya berdasarkan status subscription provider.

Sistem baru menganggap checkout installment berhasil jika first payment sudah difinalisasi ke domain internal, yaitu:

1. ledger pembayaran sukses tercatat
2. invoice ter-update
3. pending registration berubah ke `payment_success`
4. onboarding state dibuat

Jadi kondisi berikut masih bisa terjadi:

- PayPal subscription `ACTIVE`
- webhook `BILLING.SUBSCRIPTION.ACTIVATED` `200 OK`
- tetapi onboarding tetap belum terbuka

Jika backend belum memproses first payment menjadi sukses.

---

## 8. Keputusan Teknis yang Sengaja Tidak Diambil

Selama perbaikan, ada beberapa shortcut yang sengaja tidak dipakai:

1. Tidak menonaktifkan verifikasi signature PayPal.
2. Tidak mem-bypass webhook menjadi selalu sukses.
3. Tidak meng-hardcode invoice menjadi paid.
4. Tidak mengubah PayPal plan untuk menutupi bug backend.
5. Tidak melakukan refactor arsitektur besar yang tidak perlu.

Alasannya:

- solusi harus aman
- flow existing tetap harus bisa dipakai untuk sandbox dan production
- bug perlu diperbaiki di titik penyebabnya, bukan ditambal dengan bypass

---

## 9. File Penting yang Terkait

Berikut file yang paling relevan dalam debugging dan penyelesaian akhir:

### Backend Flow

- `app/Services/Payments/PaymentSubscriptionService.php`
- `app/Services/PaymentCheckoutService.php`
- `app/Services/Payments/InstallmentWebhookHandler.php`
- `app/Services/Payments/PayPalSubscriptionService.php`
- `app/Services/PaymentFinalizerService.php`
- `app/Http/Controllers/PayPalWebhookController.php`

### Recovery Command

- `app/Console/Commands/RecoverInstallmentFirstPaymentCommand.php`

### Routing / Middleware

- `routes/web.php`
- `bootstrap/app.php`

### Tests

- `tests/Feature/InstallmentWebhookHandlerTest.php`
- `tests/Feature/PayPalWebhookControllerTest.php`
- `tests/Feature/RecoverInstallmentFirstPaymentCommandTest.php`

---

## 10. Cara Menjalankan Recovery di Local/Dev

Jika ada subscription stuck seperti:

```text
provider_subscription_id = I-4P3S5GCM47FT
```

jalankan:

```bash
php artisan yogafx:installment-recover-first-payment I-4P3S5GCM47FT
```

Lalu verifikasi:

1. `payment_subscriptions.first_payment_paid_at` terisi
2. `payment_subscriptions.installments_paid_count` naik ke `1`
3. ada row sukses di `payment_activities`
4. `invoices.status` berubah dari `unpaid`
5. `pending_registrations.status` menjadi `payment_success`
6. `onboarding_states` sudah ada

Kalau semua benar, halaman checkout/installment yang tadinya stuck seharusnya bisa lanjut ke onboarding.

---

## 11. Checklist Test Ulang

### 11.1 Sebelum Test

Pastikan:

- tunnel publik aktif, misalnya `ngrok`
- URL webhook PayPal mengarah ke `/webhooks/paypal`
- `.env` memakai `PAYPAL_WEBHOOK_ID` yang benar
- environment sandbox/credentials cocok dengan akun PayPal yang dipakai

### 11.2 Test Normal Flow

1. buka halaman package public
2. pilih installment
3. approve subscription di PayPal sandbox
4. cek apakah event `BILLING.SUBSCRIPTION.ACTIVATED` masuk
5. cek apakah `PAYMENT.SALE.COMPLETED` juga masuk
6. cek apakah onboarding langsung terbuka

### 11.3 Jika Stuck Lagi

Lakukan audit berurutan:

1. cek response webhook di ngrok inspector
2. cek `storage/logs/laravel.log`
3. cek row `payment_subscription_events`
4. cek `payment_subscriptions`
5. cek `payment_activities`
6. cek `invoices`
7. cek `pending_registrations`
8. cek `onboarding_states`

Jika `subscription active` tetapi first payment belum sukses, gunakan recovery command di local/dev.

---

## 12. Command Test yang Sudah Dipakai

Untuk memastikan perubahan aman, test berikut dijalankan:

```bash
php artisan test tests/Feature/RecoverInstallmentFirstPaymentCommandTest.php
php artisan test tests/Feature/RecoverInstallmentFirstPaymentCommandTest.php tests/Feature/InstallmentWebhookHandlerTest.php tests/Feature/PayPalWebhookControllerTest.php
```

Area yang diverifikasi oleh test:

1. recovery berhasil membuka onboarding
2. command idempotent
3. command menolak subscription non-active
4. command menolak jika first payment sudah diproses
5. command diblok di production tanpa guard
6. webhook activation bisa memproses `last_payment`
7. sale completed yang datang sesudah activation tidak menggandakan first payment

---

## 13. Pelajaran Penting dari Insiden Ini

Ada beberapa pelajaran utama:

### 13.1 Subscription Active Tidak Sama dengan Payment Finalized

Provider status `ACTIVE` belum otomatis berarti LMS boleh membuka onboarding.

Business state internal tetap harus lengkap:

- ledger sukses
- invoice update
- pending registration update
- onboarding state tersedia

### 13.2 Webhook Debugging Harus Dilihat Sebagai Rantai

Masalah webhook bukan satu bug tunggal. Pada kasus ini urutannya adalah:

1. schema mismatch
2. CSRF
3. webhook ID / signature verification
4. business logic event activation
5. recovery state untuk event yang sudah terlewat

Kalau hanya melihat satu lapisan, kita mudah salah menyimpulkan bahwa webhook sudah benar padahal state business belum beres.

### 13.3 Payload Activation Bisa Menjadi Safety Net Penting

Jika PayPal mengirim:

```text
resource.billing_info.last_payment
```

maka payload itu bisa dipakai sebagai jalur pemulihan yang aman untuk first payment, selama:

- signature sudah valid
- data payment tervalidasi
- finalisasi tetap lewat service existing

### 13.4 Recovery Internal untuk Dev Sangat Penting

Dalam local/dev, terlalu bergantung pada resend manual dari dashboard PayPal/client membuat debugging lambat dan tidak nyaman.

Command recovery memberi jalur yang:

- aman
- terkontrol
- idempotent
- tetap memakai business logic resmi

---

## 14. Status Akhir Solusi

Setelah semua perbaikan:

1. schema installment yang dibutuhkan code sudah sinkron
2. webhook PayPal tidak lagi diblokir CSRF
3. `PAYPAL_WEBHOOK_ID` sudah benar untuk listener yang dipakai saat debugging
4. `BILLING.SUBSCRIPTION.ACTIVATED` bisa diproses sebagai aktivasi subscription sekaligus first payment jika `last_payment` tersedia
5. duplicate first payment dari event sale completed bisa dicegah
6. tersedia command recovery aman untuk local/dev bila transaction sudah terlanjur stuck

Dengan kondisi ini, kasus stuck onboarding installment yang sebelumnya membutuhkan resend dari PayPal Dashboard sekarang bisa dipulihkan secara internal di environment development tanpa bypass keamanan.

---

## 15. Rekomendasi Operasional ke Depan

1. Setiap kali ngrok URL berubah, anggap konfigurasi webhook PayPal perlu dicek ulang.
2. Simpan catatan pasangan antara:
   - PayPal app / account
   - webhook listener URL
   - webhook ID
3. Pertahankan logging detail untuk kegagalan verifikasi signature.
4. Gunakan recovery command hanya untuk local/dev/staging, bukan sebagai flow normal production.
5. Jika ada kejadian serupa di production, prioritaskan investigasi event asli dari PayPal sebelum melakukan tindakan manual.

---

## 16. Penutup

Masalah installment ini bukan satu bug tunggal, tetapi rangkaian mismatch teknis dari schema, middleware, konfigurasi provider, sampai business logic event handling.

Solusi akhirnya juga bukan satu patch tunggal, melainkan kombinasi dari:

- sinkronisasi database
- perbaikan route webhook
- koreksi konfigurasi `PAYPAL_WEBHOOK_ID`
- penyempurnaan handler `BILLING.SUBSCRIPTION.ACTIVATED`
- proteksi deduplication
- command recovery internal yang aman

Dokumen ini bisa dipakai sebagai referensi utama jika nanti ada kasus serupa: subscription PayPal terlihat aktif, tetapi onboarding YogaFX belum terbuka.
