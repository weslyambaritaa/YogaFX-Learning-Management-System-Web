# Payment, Package, dan Installment Explainer
# YogaFX LMS

## Tujuan

Dokumen ini menjelaskan implementasi payment YogaFX LMS yang aktif saat ini, terutama hubungan antara:

- `AccessTier`
- `Package`
- full payment PayPal
- installment PayPal Subscription
- onboarding enrollment setelah payment

Dokumen ini juga merangkum masalah yang sedang kita alami saat ini berdasarkan implementasi kode yang ada di repository.

---

## 1. Konsep Domain Yang Aktif

### 1.1 `AccessTier`

`AccessTier` adalah entitlement/access layer.

Fungsinya:

- menentukan tier student
- menentukan akses konten
- menentukan hierarchy upgrade melalui `level`
- menjadi dasar filtering learning access

`AccessTier` bukan lagi source of truth utama untuk:

- harga
- offer payment
- public checkout link utama
- konfigurasi installment baru

### 1.2 `Package`

`Package` adalah commerce/payment offer layer.

`Package` saat ini menyimpan:

- title
- slug
- description
- image
- price
- currency code
- active status
- installment configuration
- PayPal product/plan cache
- assignment ke satu `AccessTier` atau `null`

Artinya:

- checkout public harus berangkat dari `Package`
- direct public link utama adalah `/p/{package_slug}`
- package menentukan setelah payment sukses user akan mendapatkan tier mana

Contoh:

- `/p/masterclass-standard`

### 1.3 Relasi `Package` ke `AccessTier`

Aturan aktif:

- satu package boleh assign ke satu tier atau `null`
- package tanpa `access_tier_id` tidak boleh checkout public
- legacy link seperti `/masterclass` tetap boleh hidup
- legacy link harus resolve ke package aktif yang assign ke tier terkait

---

## 2. Flow Payment Yang Aktif

### 2.1 Public Entry

Flow public saat ini secara umum:

1. visitor membuka public page
2. visitor submit lead registration
3. sistem membuat `pending_registrations`
4. visitor masuk ke signed checkout
5. visitor memilih `Pay in full` atau `Installment` jika eligible

### 2.2 Full Payment

Flow full payment:

1. checkout membuat `invoice` dan `payment activity`
2. backend membuat PayPal order
3. user approve payment di PayPal
4. backend capture order
5. `PaymentFinalizerService` dijalankan
6. `pending_registrations.status` menjadi `payment_success`
7. `user` dan `onboarding_state` disiapkan
8. browser diarahkan ke flow onboarding

Untuk full payment, redirect ke onboarding terjadi langsung setelah capture sukses.

### 2.3 Installment

Flow installment berbeda:

1. checkout membuat `invoice` installment
2. backend menyiapkan `payment_subscriptions`
3. frontend membuka PayPal subscription approval
4. user approve subscription
5. approval hanya menempelkan `provider_subscription_id`
6. browser belum boleh langsung membuka onboarding
7. sistem menunggu webhook first payment dari PayPal
8. setelah webhook payment pertama sukses diproses:
   - payment ledger dibuat
   - invoice balance dikurangi
   - `PaymentFinalizerService` dijalankan
   - `pending_registration` menjadi `payment_success`
   - `onboarding_state` dibuat
9. frontend polling status checkout
10. jika backend menjawab `onboarding_ready`, browser redirect ke enrollment

Poin penting:

- approval PayPal subscription bukan final signal untuk enrollment
- signal final untuk membuka enrollment adalah first payment success yang diproses backend

---

## 3. Kenapa Installment Tidak Bisa Langsung Redirect Setelah Approval

PayPal webhook adalah komunikasi server-to-server:

```text
PayPal -> backend YogaFX
```

Webhook tidak bisa melakukan redirect browser user secara langsung.

Karena itu arsitektur yang benar adalah:

1. frontend masuk waiting state setelah approval
2. frontend polling endpoint status
3. backend menunggu webhook payment pertama
4. setelah onboarding siap, frontend redirect user ke enrollment

Di kode saat ini, endpoint status installment memang sudah ada, dan frontend checkout juga sudah punya logic polling.

Jadi jika user masih berhenti di pesan:

```text
Waiting for the first PayPal payment webhook...
```

itu berarti browser belum menerima sinyal bahwa onboarding sudah ready.

---

## 4. Billing Day Installment

### 4.1 Requirement Baru Yang Sudah Dipasang di Fondasi

Installment tidak lagi dimaksudkan memakai satu fixed billing day untuk semua student.

Calon student seharusnya bisa memilih:

- tanggal 1
- tanggal 15

### 4.2 Peran `Package`

Package tidak lagi semestinya memaksa satu tanggal tunggal.

Package hanya menentukan tanggal mana yang diizinkan melalui:

- `allowed_billing_days`

Contoh:

- `[1, 15]`
- `[15]`
- `[1]`

### 4.3 Peran Checkout

Public checkout yang memilih installment sekarang menjadi tempat user memilih billing day.

Artinya:

- pilihan tanggal bayar ada di halaman checkout installment
- bukan di `AccessTier`
- bukan di package direct link sebelum masuk checkout

Jika package mengizinkan dua pilihan, checkout harus menampilkan:

- Every 1st of the month
- Every 15th of the month

Jika package hanya mengizinkan satu pilihan, maka user hanya akan melihat satu opsi.

---

## 5. Kondisi Implementasi Saat Ini di Kode

Berdasarkan audit singkat terhadap kode aktif:

### 5.1 Yang Sudah Ada

- `Package` sudah menjadi commerce layer
- public direct package route `/p/{package_slug}` sudah aktif
- checkout installment sudah punya endpoint status polling
- frontend checkout installment sudah punya waiting state
- frontend checkout installment sudah punya logic redirect jika `onboarding_ready`
- `PaymentFinalizerService` memang membuat `pending_registration` payment success dan `onboarding_state`
- admin package form sudah punya `Allowed Billing Days`
- public checkout panel sudah punya radio pilihan billing day 1 atau 15

### 5.2 Yang Masih Menjadi Masalah Nyata

- user masih bisa tertahan di waiting state setelah approval installment
- enrollment belum terbuka jika webhook first payment tidak sampai atau belum diproses sukses
- pengalaman ini terasa seperti “payment berhasil tapi tidak lanjut”, padahal approval saja belum cukup
- pilihan tanggal bayar memang ada di checkout, tetapi tidak muncul jika package tidak eligible atau allowed billing days tidak terset dengan benar

---

## 6. Masalah Yang Sedang Kita Alami

### 6.1 Masalah A: Sudah approve PayPal, tetapi belum redirect ke enrollment

Gejala:

- user selesai approve PayPal subscription
- UI menampilkan pesan menunggu webhook
- user tidak otomatis lanjut ke enrollment

Makna teknisnya:

- approval subscription sudah diterima frontend
- tetapi backend belum menyatakan `onboarding_ready`

Kemungkinan penyebab utama:

1. webhook PayPal first payment belum pernah masuk ke backend
2. webhook masuk tetapi event yang dibutuhkan bukan event yang diproses
3. webhook masuk tetapi gagal diproses
4. webhook sukses, tetapi environment local/public tunnel belum stabil
5. frontend polling tetap jalan, tetapi backend masih mengembalikan `waiting_for_first_payment`

### 6.2 Masalah B: Calon student belum bisa memilih tanggal pembayaran

Gejala:

- user merasa belum ada pilihan tanggal bayar

Penjelasan kondisi implementasi saat ini:

1. pilihan billing day ditempatkan di halaman checkout installment, bukan di halaman package awal
2. pilihan hanya muncul jika package memang installment-eligible
3. pilihan hanya muncul sesuai `allowed_billing_days` package

Jadi problem ini bisa berarti salah satu dari berikut:

1. package belum dikonfigurasi dengan `installment_enabled = true`
2. package belum assign ke `access_tier_id`
3. `allowed_billing_days` hanya berisi satu nilai
4. user belum sampai ke state checkout installment
5. implementasi UI sudah ada, tetapi flow yang diuji belum memakai package/config yang benar

---

## 7. Kenapa Cloudflare Tunnel Saja Belum Menjamin Redirect Berhasil

Cloudflare tunnel atau ngrok hanya membuka akses publik ke local backend.

Itu belum otomatis berarti flow selesai. Admin masih harus memastikan:

1. URL tunnel aktif dan benar
2. webhook PayPal Sandbox diarahkan ke URL tunnel yang benar
3. route webhook YogaFX yang dipakai benar
4. event PayPal yang relevan aktif
5. `PAYPAL_WEBHOOK_ID` sesuai
6. first payment event benar-benar terkirim oleh PayPal Sandbox
7. event tersebut diproses menjadi ledger payment sukses

Jika salah satu titik itu gagal, frontend akan terus polling tetapi tetap menerima status:

- `waiting_for_first_payment`

---

## 8. Cara Membaca “Belum Terimplementasi” Dengan Tepat

Dari kondisi kode saat ini, ada perbedaan antara:

### 8.1 Fondasi Sudah Ada

Yang sudah ada:

- package sebagai commerce layer
- direct package route
- installment approval flow
- checkout status polling
- onboarding finalization service
- billing day choices 1/15 di checkout

### 8.2 End-to-End Belum Konsisten di Pengujian Nyata

Yang masih bermasalah:

- redirect enrollment setelah first payment installment belum terbukti stabil di local sandbox
- dependency pada webhook membuat UX terlihat “macet” jika webhook tidak sampai
- pemilihan billing day bisa terasa belum ada jika package/config yang diuji tidak expose dua pilihan

Jadi masalah kita saat ini bukan sekadar “fiturnya belum dibuat”, tetapi:

- sebagian fondasi sudah dibuat
- namun hasil end-to-end yang user inginkan belum konsisten tercapai

---

## 9. Checklist Investigasi Yang Perlu Dilanjutkan

Untuk memastikan masalah redirect enrollment:

1. cek apakah webhook `PAYMENT.SALE.COMPLETED` benar-benar masuk
2. cek apakah event tersebut tercatat di `payment_subscription_events`
3. cek apakah `InstallmentWebhookHandler` memproses event menjadi payment sukses
4. cek apakah `pending_registrations.status` berubah menjadi `payment_success`
5. cek apakah `onboarding_states` dibuat
6. cek apakah endpoint status mulai mengembalikan `onboarding_ready = true`

Untuk memastikan masalah billing day:

1. cek package admin yang sedang diuji
2. cek `installment_enabled`
3. cek `access_tier_id`
4. cek `allowed_billing_days`
5. cek apakah user benar-benar sampai ke checkout installment page
6. cek apakah package yang dipakai memang mengizinkan dua opsi

---

## 10. File Implementasi Penting

Beberapa file utama yang relevan:

- `app/Models/Package.php`
- `app/Services/PaymentCheckoutService.php`
- `app/Services/PaymentFinalizerService.php`
- `app/Services/Payments/InstallmentWebhookHandler.php`
- `app/Http/Controllers/CheckoutController.php`
- `resources/js/Components/PackageForm.jsx`
- `resources/js/Components/public/PublicCheckoutPanel.jsx`

---

## 11. Kesimpulan

Arsitektur payment YogaFX saat ini sudah memakai model yang benar:

- `AccessTier` untuk entitlement
- `Package` untuk commerce
- full payment memakai PayPal order/capture
- installment memakai PayPal subscription + webhook

Namun ada gap implementasi end-to-end yang masih terasa di pengujian:

1. redirect ke enrollment untuk installment masih bergantung penuh pada webhook first payment yang harus benar-benar masuk dan diproses
2. pilihan billing day 1/15 sudah dipasang di fondasi checkout, tetapi hasil yang terlihat user tetap bergantung pada konfigurasi package yang sedang diuji

Karena itu masalah kita saat ini lebih tepat diringkas sebagai:

- fondasi payment/package/installment sudah banyak terpasang
- tetapi flow nyata di local sandbox masih belum sepenuhnya memenuhi ekspektasi user

---

## 12. Dokumen Terkait

Untuk bacaan lanjutan:

- `docs/package-domain-implementation-summary.md`
- `docs/package-domain-modular-implementation.md`
- `docs/package-installment-context-lock.md`
- `docs/installment/installment-modular-implementation.md`
- `docs/installment/installment-sandbox-test-checklist.md`
- `docs/12-paypal-payment-architecture.md`
