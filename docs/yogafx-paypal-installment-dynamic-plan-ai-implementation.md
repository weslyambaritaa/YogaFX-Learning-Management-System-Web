# YogaFX PayPal Installment Dynamic Plan AI Implementation Guide

## 1. Tujuan Dokumen

Dokumen ini menjabarkan implementasi bertahap untuk perubahan **dynamic PayPal installment plan** di YogaFX LMS.

Dokumen ini ditujukan untuk AI coding assistant atau developer yang akan mengeksekusi perubahan secara aman, bertahap, dan tetap berada dalam scope yang sudah disetujui.

Dokumen ini **bukan** source of truth produk baru. Dokumen ini adalah panduan implementasi teknis dari keputusan yang sudah dijelaskan di:

- [yogafx-paypal-installment-dynamic-plan-change.md](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/docs/yogafx-paypal-installment-dynamic-plan-change.md)

## 2. Target Perubahan

Target implementasi adalah:

1. Checkout installment baru tidak lagi reuse PayPal plan lama hanya berdasarkan key sederhana atau fallback legacy.
2. Plan cache baru menggunakan **fingerprint v2** yang merepresentasikan konfigurasi cicilan secara exact.
3. Existing active subscriptions tetap aman dan tidak dimodifikasi.
4. Checkout **initial** dan **upgrade** sama-sama mengikuti aturan cache baru.

## 3. Out of Scope

Jangan mengerjakan hal berikut dalam perubahan ini:

- mengubah UI/UX checkout student
- mendesain ulang flow onboarding
- mengubah webhook lifecycle subscription lama
- menghapus atau memigrasi active subscription lama
- merombak schema payment domain tanpa kebutuhan jelas
- menambah role, halaman, atau menu baru

## 4. Prinsip Implementasi

AI harus mengikuti prinsip berikut:

1. Jangan langsung menghapus semua legacy data hanya untuk membuat bug hilang.
2. Perbaikan utama harus terjadi pada **strategi lookup dan reuse plan**.
3. Active subscription lama harus dianggap immutable.
4. Checkout baru hanya boleh reuse plan jika fingerprint exact match.
5. Setiap perubahan harus dibarengi test yang membuktikan behavior baru.

## 5. File Utama yang Akan Disentuh

Prioritas utama:

- `app/Services/Payments/PaymentSubscriptionService.php`
- `tests/Feature/InstallmentCheckoutContractTest.php`
- `tests/Feature/InstallmentCheckoutOrchestrationTest.php`

Kemungkinan tambahan bila diperlukan:

- `app/Services/Payments/PayPalSubscriptionService.php`
- test lain yang menyentuh upgrade checkout atau reuse subscription draft

## 6. Gambaran Strategi Baru

Strategi target:

1. Backend menghitung `installmentPlan` terbaru.
2. Backend membuat **fingerprint v2** dari konfigurasi cicilan.
3. Backend mencari cache di `package.metadata.paypal_plan_ids_v2[fingerprint]`.
4. Jika cocok, reuse plan tersebut.
5. Jika tidak ada, backend membuat plan baru lewat `PayPalSubscriptionService::createPlan()`.
6. Backend menyimpan `provider_plan_id`, `provider_plan_fingerprint`, dan `provider_plan_cache_version = v2`.
7. Existing draft subscription hanya boleh direuse jika fingerprint exact match.

## 7. Tahapan Implementasi

### Tahap 1 - Tambah Fingerprint Builder

Tujuan:
- menambah satu cara konsisten untuk membentuk fingerprint v2

Kerja:
- tambahkan helper/private method di `PaymentSubscriptionService`
- method menerima:
  - context
  - package
  - billing day
  - installment count
  - currency
  - total amount
  - first payment amount
  - recurring payment amount
  - billing interval unit
  - billing interval count
- hasilkan string fingerprint yang stabil dan deterministic

Rule:
- gunakan nilai yang sudah dinormalisasi
- nominal sebaiknya disimpan dalam cents agar stabil
- jangan bergantung pada formatting UI

Output tahap ini:
- helper fingerprint v2 tersedia dan bisa dipakai initial + upgrade flow

### Tahap 2 - Tambah Cache Key v2 di Package Metadata

Tujuan:
- memisahkan cache plan baru dari cache legacy

Kerja:
- buat namespace metadata baru:
  - `paypal_plan_ids_v2`
- jangan overwrite atau menghapus `paypal_plan_ids` legacy dalam logic utama
- lookup checkout baru harus mengarah ke `paypal_plan_ids_v2`

Rule:
- cache legacy boleh tetap tersimpan
- checkout installment baru tidak boleh lagi bergantung pada `paypal_plan_ids` lama

Output tahap ini:
- package bisa menyimpan mapping fingerprint v2 ke `plan_id`

### Tahap 3 - Ubah Lookup Provider Plan untuk Checkout Baru

Tujuan:
- menghentikan reuse plan lama yang tidak exact match

Kerja:
- ubah `providerPlanIdForInstallmentConfig()` atau pecah ke method baru
- lookup baru hanya mengecek:
  - `package.metadata.paypal_plan_ids_v2[fingerprint]`
- jangan lagi fallback ke:
  - `package.metadata.paypal_plan_ids["15"]`
  - `package.metadata.paypal_plan_ids["default"]`
  - `package.metadata.paypal_plan_ids["15_12x"]`
  - `package.paypal_plan_id`

Rule:
- legacy fallback tidak dipakai untuk checkout installment baru
- bila tidak ada exact match, buat plan baru

Output tahap ini:
- checkout baru hanya memakai cache v2 atau create plan baru

### Tahap 4 - Perketat Reuse Existing Draft Subscription

Tujuan:
- mencegah draft subscription lama mengembalikan `provider_plan_id` yang salah

Kerja:
- saat ada `existingPreparedSubscription`, jangan hanya cek:
  - `billing_day`
  - `installment_count`
- tambahkan verifikasi:
  - `metadata.provider_plan_fingerprint`
  - `metadata.provider_plan_cache_version`
  - amount penting dan currency bila perlu

Reuse hanya jika:
- `provider_plan_id` ada
- fingerprint cocok
- cache version `v2`

Jika tidak cocok:
- jika status masih `draft` dan approval belum dimulai, update/rebuild draft lokal
- jika approval PayPal sudah dimulai, pertahankan guard `409`

Output tahap ini:
- draft lama tidak otomatis direuse hanya karena billing day dan installment count sama

### Tahap 5 - Simpan Metadata Baru Saat Plan Siap

Tujuan:
- memastikan plan baru yang dibuat bisa direuse dengan aman

Kerja:
- saat plan baru ditemukan atau dibuat:
  - simpan `paypal_plan_ids_v2[fingerprint] = planId` pada package
  - simpan metadata pada payment subscription:
    - `provider_plan_fingerprint`
    - `provider_plan_cache_version`
    - `provider_plan_key` bila masih dibutuhkan untuk debug

Rule:
- metadata baru harus konsisten di initial dan upgrade flow

Output tahap ini:
- cache v2 siap dipakai di request berikutnya

### Tahap 6 - Pastikan Initial dan Upgrade Flow Konsisten

Tujuan:
- menghindari fix hanya berjalan pada public checkout

Kerja:
- cek `startInitialCheckout()`
- cek `startUpgradeCheckout()`
- pastikan keduanya memakai:
  - fingerprint builder yang sama
  - lookup cache v2 yang sama
  - rule reuse draft yang sama

Output tahap ini:
- tidak ada collision plan antara initial checkout dan upgrade checkout dengan nominal berbeda

### Tahap 7 - Test Coverage

Tujuan:
- memastikan perubahan aman dan tahan regresi

Tambahkan atau update test untuk:

1. checkout installment baru tidak memakai `package.paypal_plan_id` lama
2. checkout installment baru tidak memakai `metadata.paypal_plan_ids["15_12x"]` lama
3. checkout installment baru memakai `paypal_plan_ids_v2[fingerprint]` jika fingerprint cocok
4. perubahan total amount menghasilkan fingerprint berbeda dan create/reuse plan yang berbeda
5. upgrade checkout dengan nominal berbeda tidak reuse plan initial
6. existing draft subscription hanya direuse jika fingerprint cocok
7. draft subscription lama dengan metadata non-v2 tidak direuse

Output tahap ini:
- perilaku baru terkunci oleh test

### Tahap 8 - Optional Local/Sandbox Cleanup

Tujuan:
- memudahkan retest manual setelah code berubah

Kerja opsional:
- null-kan `package.paypal_plan_id`
- hapus `metadata.paypal_plan_ids`
- bersihkan draft subscription lokal yang sudah terlanjur salah

Rule:
- ini bukan inti fix
- jangan jadikan cleanup data sebagai solusi utama

Output tahap ini:
- retest sandbox lebih bersih dan tidak bias oleh cache lama

## 8. Urutan Eksekusi yang Direkomendasikan untuk AI

AI sebaiknya bekerja dengan urutan berikut:

1. Tambah helper fingerprint v2.
2. Tambah lookup cache v2.
3. Ubah rule reuse existing draft subscription.
4. Simpan metadata v2 pada package dan payment subscription.
5. Sinkronkan initial + upgrade flow.
6. Tambah/update test.
7. Jalankan test yang relevan.
8. Hanya setelah code aman, pertimbangkan cleanup local sandbox.

## 9. Checklist Review Sebelum Menyatakan Selesai

Sebelum perubahan dianggap selesai, pastikan:

- checkout initial installment tidak membaca fallback legacy
- checkout upgrade installment tidak membaca fallback legacy
- existing active subscription tidak terpengaruh
- webhook flow tidak membutuhkan perubahan
- test baru mencakup reuse benar dan reuse yang harus ditolak
- tidak ada perubahan UI yang tidak diperlukan
- tidak ada scope creep ke domain lain

## 10. Kriteria Selesai

Implementasi dianggap selesai jika:

1. `provider_plan_id` untuk checkout baru berasal dari cache v2 exact match atau plan baru
2. legacy fallback tidak lagi dipakai untuk installment checkout baru
3. existing draft subscription tidak direuse jika fingerprint tidak cocok
4. initial dan upgrade flow konsisten
5. test coverage untuk skenario utama sudah ada dan lulus

## 11. Catatan untuk AI Coding Assistant

Jika AI mengerjakan dokumen ini:

1. Jangan improvisasi requirement di luar perubahan dynamic plan.
2. Jangan menghapus data legacy production sebagai bagian dari fix inti.
3. Jangan menyentuh webhook handler kecuali terbukti perlu.
4. Jangan mengubah frontend checkout hanya untuk menutupi bug backend.
5. Jika ada konflik antara implementasi lama dan strategi v2, prioritaskan checkout baru yang aman, sambil menjaga active subscription lama tetap stabil.
