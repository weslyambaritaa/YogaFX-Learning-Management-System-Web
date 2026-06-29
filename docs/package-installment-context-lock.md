# YogaFX Package + Installment Context Lock

## Purpose

Dokumen ini merangkum hasil diskusi terbaru tentang perubahan domain payment YogaFX.

Dokumen ini dibuat agar Codex dapat memahami keputusan terbaru sebelum melakukan coding di repo:

`YogaFX-Learning-Management-System-Web`

Scope utama dokumen ini:

1. memisahkan `AccessTier` dari payment/pricing
2. memperkenalkan domain baru bernama `packages`
3. menjaga public link lama tetap hidup
4. menyiapkan dasar implementasi installment PayPal Subscriptions
5. mengunci urutan implementasi yang aman

Dokumen ini bukan instruksi coding langsung untuk semua fitur sekaligus. Gunakan ini sebagai context lock sebelum membuat implementation plan atau patch bertahap.

---

## 1. Background Singkat

Sebelumnya, `AccessTier` memegang dua tanggung jawab sekaligus:

1. menentukan akses belajar student
2. menyimpan metadata commerce/payment seperti price, currency, dan payment link

Struktur lama `ACCESS_TIERS` kira-kira:

```text
ACCESS_TIERS {
    bigint id PK
    string name
    string slug
    string payment_link
    decimal price
    string currency_code
    int level
    boolean is_active
}
```

Perubahan terbaru dari client: `AccessTier` tidak boleh lagi menjadi pusat harga/payment.

Mulai sekarang, konsep domain harus dipisah:

```text
AccessTier = entitlement / hak akses content
Package    = commercial offer / produk pembayaran / harga / promo / installment
```

Dengan kata lain:

- `AccessTier` menjawab: student boleh membuka module/lesson/course/ebook yang mana?
- `Package` menjawab: produk apa yang dijual, harganya berapa, currency apa, dan installment-nya bagaimana?

---

## 2. Keputusan Domain Utama

### 2.1 AccessTier Fokus ke Akses Konten

`AccessTier` harus tetap dipakai untuk:

- menentukan hak akses module
- menentukan hak akses lesson
- menentukan hak akses ebook
- menentukan hak akses course/video lecture
- hierarchy tier/level
- assignment ke student setelah payment sukses

`AccessTier` tidak lagi menjadi source of truth untuk:

- harga
- currency
- image public payment product
- description produk checkout
- installment setting
- PayPal product/plan mapping
- public payment offer

Field lama seperti `price`, `currency_code`, dan `payment_link` sebaiknya dipindahkan secara bertahap ke `packages`.

Rekomendasi migration strategy:

1. jangan langsung hapus field lama dari `access_tiers`
2. buat `packages` dan pindahkan logic checkout untuk membaca dari `packages`
3. setelah stabil, field payment lama pada `access_tiers` bisa dianggap deprecated
4. penghapusan fisik field lama dilakukan nanti bila aman

---

## 3. Package Domain

### 3.1 Nama Tabel Final

Nama tabel final yang dipilih:

```text
packages
```

Bukan `payment_packages`.

### 3.2 Fungsi Package

`Package` adalah produk komersial yang dijual ke public checkout.

Package menyimpan:

- title produk
- slug public/campaign
- harga
- currency
- image
- description
- installment enabled/disabled
- billing config untuk installment
- relasi ke access tier yang akan diberikan setelah payment sukses

### 3.3 Relasi Package ke AccessTier

Rule final:

```text
Package boleh assign ke 1 AccessTier atau none.
AccessTier hanya boleh di-assign oleh 1 Package aktif pada satu waktu.
```

Artinya:

- `packages.access_tier_id` boleh nullable
- jika `access_tier_id = null`, package sedang tidak memberi akses ke tier mana pun
- package dengan `access_tier_id = null` tidak boleh tersedia untuk public checkout
- admin tetap bisa melihat dan mengelola package tersebut di CRUD admin

### 3.4 Default Package Awal

Walaupun package bersifat dinamis dan bisa dibuat manual oleh admin, kondisi awal minimal harus memiliki tiga package standard:

```text
Masterclass Standard  -> Masterclass
Online Standard       -> Online
Starter-kit Standard  -> Starter Kit
```

Ini bukan hardcoded selamanya. Admin bisa membuat package lain seperti:

```text
Masterclass Easter
Online New Year
Starter-kit Promo
Black Friday Masterclass
```

### 3.5 Promo/Event Assignment Rule

Jika ada event/promo, admin membuat package baru dan meng-assign package tersebut ke tier yang relevan.

Contoh kondisi normal:

```text
Masterclass Standard -> Masterclass
Online Standard      -> Online
Starter-kit Standard -> Starter Kit
Masterclass Easter   -> none
```

Saat event Easter:

```text
Masterclass Standard -> none
Masterclass Easter   -> Masterclass
Online Standard      -> Online
Starter-kit Standard -> Starter Kit
```

Jadi tier Masterclass berpindah assignment dari package standard ke package promo.

### 3.6 Auto-unassign Behaviour

Keputusan final:

Saat admin meng-assign sebuah package ke suatu tier, sistem boleh otomatis melepas package lama dari tier itu.

Contoh:

Admin set:

```text
Masterclass Easter -> Masterclass
```

Sistem otomatis menjalankan dalam DB transaction:

```text
Masterclass Standard -> none
Masterclass Easter   -> Masterclass
```

Ini lebih aman daripada meminta admin melakukan dua aksi manual.

---

## 4. Rekomendasi Schema Package

Schema awal yang direkomendasikan:

```text
packages
- id
- access_tier_id nullable foreign key to access_tiers.id
- title
- slug unique
- description nullable/text
- image nullable
- price decimal
- currency_code string(3)
- is_active boolean
- installment_enabled boolean
- billing_interval_unit nullable/string
- billing_interval_count nullable/integer
- fixed_billing_day nullable/integer
- installment_deadline_month nullable/integer
- installment_deadline_day nullable/integer
- paypal_product_id nullable/string
- paypal_plan_id nullable/string
- metadata nullable/json
- created_at
- updated_at
```

Penjelasan field:

- `access_tier_id`: tier yang akan diberikan setelah payment sukses; nullable untuk mode `none`
- `title`: nama package, contoh `Masterclass Standard`
- `slug`: slug public/direct package link, contoh `masterclass-standard`
- `description`: copywriting package
- `image`: image public checkout/package card
- `price`: harga package
- `currency_code`: currency package
- `is_active`: package aktif secara admin
- `installment_enabled`: apakah package boleh dibeli via installment
- `billing_interval_unit`: contoh `MONTH`
- `billing_interval_count`: contoh `1`
- `fixed_billing_day`: untuk YogaFX = `15`
- `installment_deadline_month`: untuk Januari = `1`
- `installment_deadline_day`: untuk tanggal 15 = `15`
- `paypal_product_id`: id product PayPal jika sudah dibuat
- `paypal_plan_id`: id plan PayPal jika sudah dibuat/cache
- `metadata`: ruang fleksibel untuk config provider/campaign tambahan

Catatan nama field:

Field installment di atas boleh disesuaikan saat coding jika ditemukan nama yang lebih cocok dengan implementasi PayPal, tetapi konsepnya harus tetap sama.

---

## 5. Constraint dan Validasi Package

### 5.1 Satu Tier Maksimal Satu Package Aktif

Business rule penting:

```text
Satu AccessTier hanya boleh sedang di-assign oleh satu Package aktif pada satu waktu.
```

Rekomendasi implementasi:

- lakukan validasi pada service/admin action
- gunakan DB transaction saat reassignment
- saat package A di-assign ke tier X, package lain yang sebelumnya mengarah ke tier X harus otomatis di-set `access_tier_id = null`

### 5.2 Package None Tidak Bisa Checkout

Package dengan kondisi berikut tidak boleh dibeli public:

```text
access_tier_id = null
```

Walaupun `is_active = true`, jika package tidak memberi tier, maka checkout harus ditolak.

Pesan yang disarankan:

```text
This package is currently unavailable for checkout.
```

atau dalam Bahasa Indonesia admin/dev:

```text
Package ini belum dihubungkan ke access tier sehingga tidak tersedia untuk checkout.
```

### 5.3 Direct Link Package

Direct package link hanya valid jika:

```text
package.is_active = true
package.access_tier_id is not null
```

---

## 6. Public Link Strategy

### 6.1 Link Public Lama Harus Dijaga

Link public lama tetap harus dipertahankan:

```text
/masterclass
/online
/starter-kit
/starterkit
```

Alasan:

- kemungkinan sudah dipakai di marketing/social media/client
- menjaga backward compatibility
- menghindari broken link public

### 6.2 Perubahan Behaviour Link Lama

Link lama tidak lagi langsung berarti checkout berdasarkan `AccessTier.price`.

Mulai sekarang, link lama harus menjadi tier intent route.

Contoh:

```text
/masterclass
```

Flow baru:

```text
resolve tier Masterclass
find package where package.access_tier_id = masterclass.id and package.is_active = true
show public lead/checkout for that package
```

Contoh normal:

```text
/masterclass  -> Masterclass Standard
/online       -> Online Standard
/starter-kit  -> Starter-kit Standard
```

Saat event Easter:

```text
/masterclass  -> Masterclass Easter
/online       -> Online Standard
/starter-kit  -> Starter-kit Standard
```

### 6.3 Direct Package Link Baru

Selain link lama, sistem juga harus punya direct package link:

```text
/p/{package_slug}
```

Contoh:

```text
/p/masterclass-standard
/p/masterclass-easter
/p/online-standard
/p/starter-kit-standard
```

Fungsi direct package link:

- campaign khusus
- promo event
- link iklan tertentu
- testing package tertentu tanpa mengubah link tier utama

Rule direct package link:

```text
Package harus aktif dan harus memiliki access_tier_id.
Jika access_tier_id = null, package tidak bisa checkout.
```

---

## 7. Dampak ke Existing Checkout Domain

### 7.1 PendingRegistration

Saat ini checkout flow existing membuat `pending_registrations` berdasarkan access tier.

Setelah package domain masuk, pending registration harus menyimpan package snapshot.

Rekomendasi tambahan field:

```text
pending_registrations
- package_id nullable/foreign key
- access_tier_id tetap ada
- amount_snapshot
- currency_code_snapshot atau currency_code
```

Catatan:

- `access_tier_id` tetap diperlukan karena ini adalah entitlement yang akan diberikan
- `package_id` diperlukan karena harga dan checkout berasal dari package
- `amount_snapshot` harus berasal dari `packages.price`
- currency snapshot harus berasal dari `packages.currency_code`

### 7.2 Invoice

Invoice juga harus tahu package apa yang dibeli.

Rekomendasi tambahan field:

```text
invoices
- package_id nullable/foreign key
- access_tier_id tetap ada
- total_amount dari package.price
- currency_code dari package.currency_code
```

Kenapa invoice tetap menyimpan `access_tier_id`?

Karena invoice harus menjadi snapshot transaksi: package apa yang dibeli dan tier apa yang diberikan pada saat transaksi terjadi.

### 7.3 Payment / payment_activities

`payment_activities` tetap menjadi ledger pembayaran individual.

Tidak perlu menjadikan package sebagai ledger. Payment activity cukup tetap mengarah ke invoice.

Relasi flow:

```text
Package
  -> PendingRegistration
  -> Invoice
  -> Payment/payment_activities
  -> User receives AccessTier after payment success
```

---

## 8. Dampak ke Installment

Perubahan package harus dilakukan sebelum PayPal Subscriptions installment final.

Alasan:

Sebelumnya installment dihitung dari:

```text
AccessTier.price
AccessTier.currency_code
```

Setelah perubahan, installment harus dihitung dari:

```text
Package.price
Package.currency_code
Package.installment_enabled
Package.billing configuration
```

Dengan ini, promo/event bisa punya harga dan installment config sendiri.

Contoh:

```text
Masterclass Standard
- price = 300
- currency_code = USD
- installment_enabled = true

Masterclass Easter
- price = 250
- currency_code = USD
- installment_enabled = true
```

Jika Masterclass Easter sedang assign ke Masterclass, maka:

```text
/masterclass -> checkout dengan harga 250 USD
```

Tetapi setelah payment sukses, student tetap mendapat:

```text
users.access_tier_id = Masterclass
```

---

## 9. Installment Rule Lock

Rule installment yang sudah dikunci dalam diskusi:

1. Fase pertama installment hanya untuk initial checkout, bukan upgrade.
2. Deadline cicilan adalah tanggal 15 Januari.
3. Penarikan bulanan selalu tanggal 15 setiap bulan.
4. Pembayaran pertama terjadi langsung saat checkout/setup berhasil.
5. Setelah pembayaran pertama sukses, student boleh lanjut enrollment, create password, dan memakai sistem.
6. Jumlah cicilan dihitung dinamis dari bulan checkout sampai billing tanggal 15 Januari.
7. Rumus pembulatan:

```text
monthly_base = floor(total_amount / installment_count)
first_payment = total_amount - (monthly_base * (installment_count - 1))
```

8. Payment pertama menampung selisih pembulatan.
9. Recurring payment berikutnya memakai `monthly_base`.
10. Rumus berlaku untuk semua currency.
11. Jika pembayaran gagal, grace period adalah H+3 setelah tanggal 15.
12. Jika masih gagal setelah H+3, akun student otomatis menjadi inactive.
13. Jika payment berhasil setelah inactive, akun otomatis active lagi.
14. Admin perlu notifikasi untuk:
    - installment payment success
    - installment payment failed
    - overdue H+3 dan akun dinonaktifkan
    - semua cicilan lunas
15. Saat semua cicilan lunas, student mendapat email payment completed.

---

## 10. Payment Subscription Domain yang Tetap Dibutuhkan

Walaupun package domain ditambahkan, installment tetap membutuhkan lifecycle subscription baru.

Rekomendasi tabel tetap:

```text
payment_subscriptions
payment_subscription_events
```

Namun `payment_subscriptions` nantinya harus mengarah ke package juga.

Rekomendasi field tambahan:

```text
payment_subscriptions
- package_id
- access_tier_id
- invoice_id
- pending_registration_id nullable
- user_id nullable
- provider
- provider_subscription_id
- status
- installment_count
- installments_paid_count
- total_amount
- monthly_base_amount
- first_payment_amount
- currency_code
- next_due_at
- final_due_at
- grace_deadline_at
- completed_at
- cancelled_at
- metadata
```

Dengan ini source of truth tetap:

```text
backend database + PayPal webhook events
```

Frontend tidak boleh menghitung sendiri status cicilan.

---

## 11. Email Notification Requirement

Package/payment perubahan tetap harus mengikuti sistem email existing.

Notification type baru yang kemungkinan dibutuhkan:

```text
installment_payment_success
installment_payment_failed
installment_overdue_deactivated
installment_payment_completed
```

Trigger:

- `installment_payment_success`: setiap recurring payment berhasil
- `installment_payment_failed`: PayPal memberi event gagal bayar
- `installment_overdue_deactivated`: scheduler H+3 menonaktifkan akun
- `installment_payment_completed`: semua cicilan lunas

Recipient:

- Admin notification untuk success/failed/overdue/completed
- Student notification untuk payment completed
- Student notification untuk failed/overdue bisa dipertimbangkan, tetapi requirement eksplisit baru menyebut admin dan payment completed untuk student

---

## 12. Admin CRUD Package

Harus ada fitur CRUD package di admin.

Minimal capability:

1. list packages
2. create package
3. edit package
4. delete/deactivate package
5. upload/change image
6. set price
7. set currency
8. set installment enabled
9. set billing config
10. assign package to access tier or none
11. auto-unassign package lama saat package baru di-assign ke tier yang sama

Admin form assignment harus menyediakan pilihan:

```text
Assign to tier:
- None
- Starter Kit
- Online
- Masterclass
```

Rule:

- memilih `None` berarti package tidak tersedia untuk checkout
- memilih tier berarti package itu menjadi package aktif untuk tier tersebut
- package lain yang sebelumnya assign ke tier itu otomatis menjadi none

---

## 13. Recommended Implementation Order

Keputusan final urutan implementasi:

```text
Buat Package domain + migrasi checkout dari AccessTier.price ke Package.price dulu.
Baru setelah itu masuk dynamic installment / PayPal Subscriptions.
```

Urutan detail yang direkomendasikan:

### Phase 1 - Package Foundation

1. Buat migration `packages`.
2. Buat model `Package`.
3. Tambahkan relasi:
   - `Package belongsTo AccessTier`
   - `AccessTier hasOne/hasMany Package` sesuai kebutuhan query, tetapi business rule-nya satu active assigned package per tier
4. Seed default package:
   - Masterclass Standard
   - Online Standard
   - Starter-kit Standard
5. Isi package dari data lama `access_tiers.price`, `currency_code`, `payment_link` bila tersedia.
6. Tambahkan admin CRUD package.
7. Tambahkan service assignment agar satu tier hanya punya satu package assigned.
8. Jangan hapus field lama access tier dulu.

### Phase 2 - Public Link Resolution

1. Pertahankan route public lama:
   - `/masterclass`
   - `/online`
   - `/starter-kit`
   - `/starterkit`
2. Ubah resolver agar route lama mencari package aktif berdasarkan tier intent.
3. Tambahkan direct package route:
   - `/p/{package_slug}`
4. Pastikan package `none` tidak bisa checkout.
5. Pastikan error state jelas jika tidak ada package aktif untuk tier.

### Phase 3 - Checkout Migrates to Package

1. Ubah lead registration agar membuat pending registration berdasarkan package.
2. Simpan `package_id`, `access_tier_id`, `amount_snapshot`, dan currency snapshot.
3. Ubah checkout payload agar membaca:
   - title dari package
   - price dari package
   - currency dari package
   - image/description dari package
4. Ubah invoice creation agar menyimpan `package_id`.
5. Pastikan setelah payment sukses, user tetap mendapat `access_tier_id` dari package.
6. Regression test one-time PayPal existing.

### Phase 4 - Installment Calculator Based on Package

1. Tambahkan `InstallmentPlanCalculator`.
2. Hitung installment dari `Package.price` dan package billing config.
3. Hitung dynamic cycles sampai 15 Januari.
4. Hitung first payment dan monthly base.
5. Expose summary ke checkout payload.

### Phase 5 - PayPal Subscriptions

1. Tambah `payment_subscriptions` dan `payment_subscription_events`.
2. Tambah provider service untuk PayPal Subscriptions.
3. Buat subscription dari package installment config.
4. Handle first payment success.
5. Handle recurring payment success/failure via webhook.
6. Update invoice balance dan payment activities.
7. Scheduler overdue H+3.
8. Auto deactivate/reactivate user.
9. Email notifications.

---

## 14. Important Non-Goals for Early Phases

Jangan dilakukan terlalu awal:

- jangan langsung ubah Flutter/mobile
- jangan langsung implement PayPal Subscriptions sebelum package checkout stabil
- jangan refactor learning/content access
- jangan hapus field payment lama dari `access_tiers` sebelum checkout baru stabil
- jangan sentuh upgrade installment pada fase pertama
- jangan ubah one-time PayPal order/capture flow tanpa regression test

---

## 15. Impact on Existing Audit

Sebelum perubahan package, audit payment menyimpulkan bahwa:

- payment flow existing masih PayPal one-time order flow
- `installment` existing masih hardcoded Pay in 4
- belum ada subscription lifecycle canonical
- perlu `payment_subscriptions` dan `payment_subscription_events`

Perubahan package tidak membatalkan audit tersebut.

Perubahan package justru menambahkan satu prasyarat baru:

```text
Sebelum dynamic installment/subscription dibuat, checkout harus dipindahkan dari AccessTier pricing ke Package pricing.
```

Jadi PayPal installment implementation harus membaca package, bukan access tier.

---

## 16. Final Architecture Summary

Final target architecture:

```text
AccessTier
  = access entitlement
  = controls module/lesson/course/ebook visibility

Package
  = commercial product
  = controls price/currency/image/description/installment config
  = assigns buyer to one AccessTier after payment success

PendingRegistration
  = pre-student lead for a selected Package
  = snapshots package and target AccessTier

Invoice
  = billing document for a Package purchase
  = snapshots Package, AccessTier, amount, currency

Payment / payment_activities
  = ledger of each payment activity
  = one-time capture or recurring subscription payment

PaymentSubscription
  = canonical lifecycle for installment subscription
  = references Package, Invoice, AccessTier, User/PendingRegistration

PaymentSubscriptionEvent
  = provider webhook event log and idempotency layer
```

---

## 17. Final Decisions Lock

These decisions are locked from the latest discussion:

1. New table name: `packages`.
2. Payment attributes move from `access_tiers` to `packages`.
3. Package can assign to one tier or none.
4. One tier can only be assigned by one active package at a time.
5. When assigning a new package to a tier, system may auto-unassign old package from that tier.
6. Keep old public links for now: `/masterclass`, `/online`, `/starter-kit`, `/starterkit`.
7. Add direct package link: `/p/{package_slug}`.
8. Package with `access_tier_id = null` is not available for checkout.
9. Installment fields proposed are acceptable for now and may be adjusted during implementation.
10. Implementation order: Package + checkout migration first, then dynamic installment / PayPal Subscription.

---

## 18. Suggested Codex Instruction

When Codex starts implementation, do not implement everything in one pass.

Start with audit and phase 1 only:

```text
Implement Package foundation first.
Do not implement PayPal Subscriptions yet.
Do not change mobile.
Do not remove AccessTier old payment fields yet.
Keep one-time payment working.
```

Recommended first implementation prompt:

```text
Read this document and the existing payment audit.
Implement Phase 1 only: Package Foundation.
Create packages table/model, seed standard packages from existing access tiers, add admin CRUD package, and add package assignment service with auto-unassign behavior.
Do not migrate checkout yet unless Phase 1 is complete and tested.
Do not implement installment subscription yet.
```
