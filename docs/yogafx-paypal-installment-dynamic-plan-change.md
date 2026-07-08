# YogaFX PayPal Installment Dynamic Plan Change

## 1. Latar Belakang

Pada checkout **Masterclass Standard**, UI YogaFX sudah menghitung cicilan dengan benar. Contoh yang terlihat di halaman checkout:

- Course Price: **$2,000.00**
- Number of Installments: **12**
- First Installment Due Today: **$166.74**
- Monthly Installment: **$166.66**
- Next Installment: **Jul 15, 2026 - $166.66**
- Last Installment: **May 15, 2027 - $166.66**

Namun popup PayPal Sandbox menampilkan nilai yang salah:

- **$25.00 USD for each 3 months, for 11 installments**
- **One-time setup fee: $25.00 USD**
- Total today: **$50.00 USD**

Ini menunjukkan bahwa UI dan kalkulasi internal YogaFX sudah benar, tetapi PayPal menerima atau menggunakan **plan lama** yang tidak sesuai dengan pilihan cicilan terbaru.

## 2. Root Cause

Masalah utama bukan berada pada UI checkout, bukan pada PayPal Sandbox, dan bukan pada kalkulator cicilan utama.

Masalah utama adalah backend masih dapat melakukan reuse terhadap **PayPal plan lama** dari beberapa sumber:

1. `payment_subscriptions.provider_plan_id` dari draft subscription lama.
2. `packages.metadata.paypal_plan_ids["15_12x"]`.
3. Legacy key seperti `packages.metadata.paypal_plan_ids["15"]` atau `packages.metadata.paypal_plan_ids["default"]`.
4. Fallback global `packages.paypal_plan_id`.

Akibatnya, walaupun sistem YogaFX menghitung cicilan baru dengan benar, backend dapat mengembalikan `provider_plan_id` lama ke frontend. Frontend kemudian memanggil PayPal Subscription menggunakan plan ID lama tersebut, sehingga popup PayPal menampilkan nominal lama seperti **$25 / 3 months**.

## 3. Bukti dari Data Package

Data package `Masterclass Standard` dari Tinker menunjukkan:

```php
id: 1
title: "Masterclass Standard"
slug: "masterclass-standard"
price: "2000.00"
currency_code: "USD"
installment_enabled: true
billing_interval_unit: "DAY"
billing_interval_count: 1
fixed_billing_day: 1
installment_deadline_date: "2028-10-18"
allowed_billing_days: "[1,15]"
paypal_product_id: "PROD-2H829281KL834124X"
paypal_plan_id: "P-5JC839308P3870118NI7M42A"
metadata: {
  "paypal_plan_ids": {
    "15": "P-5JC839308P3870118NI7M42A",
    "default": "P-6YV517325S157840GNJBR7IA",
    "1_14x": "P-6YV517325S157840GNJBR7IA",
    "1_2x": "P-6YV517325S157840GNJBR7IA",
    "15_2x": "P-5JC839308P3870118NI7M42A",
    "15_24x": "P-5JC839308P3870118NI7M42A",
    "15_27x": "P-5JC839308P3870118NI7M42A",
    "15_12x": "P-5JC839308P3870118NI7M42A"
  }
}
```

Data ini menunjukkan bahwa beberapa konfigurasi cicilan berbeda, seperti `15_2x`, `15_12x`, `15_24x`, dan `15_27x`, semuanya menunjuk ke PayPal plan ID yang sama. Ini tidak aman karena setiap konfigurasi cicilan seharusnya memiliki plan yang sesuai dengan jumlah cicilan, nominal first payment, nominal recurring, dan interval pembayaran.

Perlu dicatat juga bahwa field legacy pada package seperti:

- `billing_interval_unit: "DAY"`
- `billing_interval_count: 1`
- `fixed_billing_day: 1`

bukan akar masalah utama pada flow installment baru. Di source code aktif, `InstallmentPlanCalculator` tetap menghasilkan plan checkout baru dengan `billing_interval_unit = MONTH` dan `billing_interval_count = 1`. Jadi masalah utama tetap berada pada reuse `provider_plan_id` lama, bukan pada nilai field legacy package itu sendiri.

## 4. Kenapa Key Lama Tidak Aman

Key lama seperti:

```txt
15_12x
1_2x
15_24x
```

hanya mempertimbangkan:

- billing day
- jumlah installment

Padahal PayPal plan juga dipengaruhi oleh:

- package
- context checkout, misalnya initial checkout atau upgrade checkout
- total amount
- currency
- first payment amount
- recurring payment amount
- billing interval unit
- billing interval count

Contoh masalah:

Jika hari ini `15_12x` dibuat untuk package seharga `$2,000`, lalu suatu saat harga berubah menjadi `$2,500`, key `15_12x` tetap sama. Jika backend masih memakai key lama tersebut, PayPal tetap akan memakai plan lama dengan nominal lama.

Ini juga berbahaya untuk upgrade checkout, karena upgrade dapat memiliki amount yang berbeda dari harga penuh package.

## 5. Prinsip Perubahan yang Diusulkan

Perubahan yang diusulkan adalah membuat sistem PayPal installment plan menjadi **dynamic by exact plan fingerprint**.

Artinya, sistem tidak lagi menggunakan satu plan global per package atau key sederhana seperti `15_12x`. Sebaliknya, sistem harus membuat atau memakai PayPal plan berdasarkan fingerprint lengkap dari konfigurasi cicilan.

Fingerprint harus mempertimbangkan minimal:

- context: `initial` atau `upgrade`
- package id
- billing day
- installment count
- currency code
- total amount
- first payment amount
- recurring payment amount
- billing interval unit
- billing interval count

Contoh fingerprint:

```txt
v2_initial_pkg1_day15_12x_USD_total200000_first16674_rec16666_month1
```

Dengan pendekatan ini, jika salah satu komponen berubah, fingerprint berubah, sehingga sistem tidak akan memakai PayPal plan lama yang tidak sesuai.

## 6. Flow Baru yang Diinginkan

Flow baru untuk PayPal installment checkout:

```txt
User memilih Pay in installment
-> User memilih billing day dan jumlah cicilan
-> Backend menghitung installmentPlan terbaru
-> Backend membuat fingerprint v2 dari installmentPlan
-> Backend mengecek metadata paypal_plan_ids_v2[fingerprint]
-> Jika ada dan cocok, reuse PayPal plan tersebut
-> Jika tidak ada, buat PayPal plan baru via PayPalSubscriptionService::createPlan()
-> Simpan plan ID ke metadata paypal_plan_ids_v2[fingerprint]
-> Simpan fingerprint ke payment_subscriptions.metadata
-> Return provider_plan_id ke frontend
-> Frontend membuat PayPal subscription dengan provider_plan_id tersebut
```

## 7. Hal yang Tidak Boleh Lagi Dilakukan untuk Checkout Baru

Untuk checkout installment baru, sistem sebaiknya tidak lagi memakai fallback berikut:

```txt
packages.paypal_plan_id
packages.metadata.paypal_plan_ids["15"]
packages.metadata.paypal_plan_ids["default"]
packages.metadata.paypal_plan_ids["15_12x"] versi lama
```

Sistem juga tidak boleh reuse draft subscription lama hanya karena `billing_day` dan `installment_count` sama. Reuse hanya aman jika fingerprint v2 juga sama persis.

## 8. Draft Subscription Lama

Selain metadata package, sumber masalah lain adalah draft subscription lama.

Saat ini, jika ada `payment_subscriptions` draft/approval yang cocok berdasarkan billing day dan installment count, sistem bisa reuse `provider_plan_id` dari row tersebut. Ini berbahaya jika draft itu dibuat saat plan lama masih salah.

Untuk flow baru, existing prepared subscription hanya boleh direuse jika:

1. `provider_plan_id` ada.
2. `metadata.provider_plan_fingerprint` sama dengan fingerprint terbaru.
3. `metadata.provider_plan_cache_version` adalah `v2`.
4. Amount dan konfigurasi penting masih cocok:
   - total amount
   - first payment amount
   - next billing amount
   - currency
   - billing day
   - installment count

Jika fingerprint tidak cocok dan subscription masih draft tanpa approval PayPal, sistem boleh rebuild/update subscription draft tersebut.

Jika approval PayPal sudah dimulai, sistem tetap harus menjaga rule lama agar konfigurasi tidak berubah setelah approval started.

## 9. Data Cleanup untuk Local/Sandbox

Untuk local/sandbox, setelah perubahan code nanti, data lama perlu dibersihkan agar testing tidak tetap memakai plan lama.

Contoh cleanup package:

```php
$package = \App\Models\Package::where('slug', 'masterclass-standard')->firstOrFail();

$metadata = is_array($package->metadata) ? $package->metadata : [];

unset($metadata['paypal_plan_ids']);

$package->forceFill([
    'paypal_plan_id' => null,
    'metadata' => $metadata,
])->save();
```

Namun cleanup package saja belum tentu cukup. Jika masih ada `payment_subscriptions` draft lama dengan `provider_plan_id` lama, service tetap bisa reuse draft tersebut jika guard belum diperketat.

Untuk retest local/sandbox, perlu juga membersihkan draft subscription yang belum approved/active untuk pending registration yang sedang dites, atau minimal memastikan code baru tidak akan reuse draft yang fingerprint-nya tidak cocok.

## 10. Dampak ke Existing Active Subscription

Perubahan ini tidak perlu mengubah active subscription lama.

Existing active subscriptions di PayPal tetap berjalan dengan plan ID yang sudah mereka gunakan. Webhook dan lifecycle subscription seharusnya tetap memakai data dari `payment_subscriptions`, bukan membaca ulang `packages.paypal_plan_id`.

Jadi prinsipnya:

```txt
Active subscription lama: jangan disentuh
Checkout baru: wajib pakai fingerprint v2
Legacy metadata lama: boleh tetap tersimpan, tapi jangan dipakai untuk checkout baru
```

## 11. Scope Implementasi yang Direkomendasikan

Implementasi sebaiknya fokus pada:

```txt
app/Services/Payments/PaymentSubscriptionService.php
tests terkait installment checkout initial dan upgrade
```

Perubahan di `PayPalSubscriptionService.php` kemungkinan tidak wajib, karena method `createPlan()` sudah membaca data dari `installmentPlan`. Jika ingin memperjelas nama/description plan, file tersebut boleh disentuh, tetapi bukan inti masalah.

Frontend checkout tidak perlu disentuh untuk masalah nominal PayPal plan ini.

## 12. Acceptance Criteria

Setelah perubahan diterapkan:

1. Untuk Masterclass Standard `$2,000`, billing day `15`, dan `12 installments`, sistem membuat atau memakai PayPal plan yang sesuai fingerprint v2.
2. PayPal popup menampilkan first payment sekitar `$166.74`.
3. PayPal popup menampilkan recurring payment sekitar `$166.66`.
4. PayPal billing interval adalah monthly, bukan every 3 months.
5. Plan memiliki `11` recurring cycles untuk total `12` pembayaran.
6. Sistem tidak memakai legacy cached plan mana pun jika fingerprint v2 tidak cocok dengan konfigurasi checkout terbaru.
7. Sistem tidak memakai `package.paypal_plan_id` untuk checkout installment baru.
8. Sistem tidak memakai legacy metadata key seperti `15`, `default`, atau `15_12x` versi lama.
9. Jika amount berubah, fingerprint berubah dan plan baru dibuat.
10. Jika fingerprint sama persis, plan boleh direuse.
11. Pay in full tidak berubah.
12. UI checkout tidak berubah.
13. Active subscription lama tidak dimodifikasi.

## 13. Test yang Perlu Ditambahkan

Tambahkan atau update test untuk memastikan:

1. Checkout installment baru tidak memakai `package.paypal_plan_id` lama.
2. Checkout installment baru tidak memakai `metadata.paypal_plan_ids["15_12x"]` lama.
3. Checkout installment baru memakai `metadata.paypal_plan_ids_v2[fingerprint]` jika fingerprint cocok.
4. Jika total amount berubah, fingerprint berubah dan plan baru dibuat.
5. Upgrade checkout dengan amount berbeda tidak reuse plan initial yang nominalnya berbeda.
6. Existing draft subscription hanya direuse jika fingerprint cocok.
7. Existing draft subscription dengan fingerprint lama/salah tidak direuse untuk checkout baru.

## 14. Catatan Penting

Perubahan ini sebaiknya tidak langsung dilakukan tanpa test, karena berkaitan dengan pembayaran.

Sebelum implementasi, pastikan developer memahami bahwa tujuan perubahan bukan hanya menghapus metadata lama, tetapi memperbaiki strategi caching PayPal plan agar aman untuk kombinasi cicilan yang dinamis.
