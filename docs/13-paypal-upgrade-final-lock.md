# PayPal Upgrade Final Lock

# YogaFX LMS

## Purpose

Dokumen ini menjadi source of truth final yang paling mutakhir untuk implementasi domain:

- PayPal payment architecture
- invoice and payment activity flow
- anti-limbo compatibility
- mock mode compatibility
- upgrade payment flow
- upgrade pricing and invoice behaviour

Dokumen ini dibuat untuk mengunci keputusan final yang sebelumnya tersebar di diskusi klarifikasi, sehingga implementasi berikutnya tidak perlu menebak-nebak ulang.

Dokumen ini mengalahkan keputusan lama yang bertentangan.

---

# 1. Core Principle

## Final Rule

Sistem payment YogaFX LMS sekarang diarahkan ke **PayPal nyata** dengan pola:

- PayPal REST API v2
- full redirect
- Laravel HTTP Client
- Service Pattern
- tanpa package pihak ketiga

Tetapi:

- mode `mock` tetap hidup untuk local/staging/testing
- anti-limbo tetap wajib didukung
- webhook verification wajib ada sejak awal
- semua efek bisnis payment success harus dipusatkan di satu finalizer service

---

# 2. Final Entities

## 2.1 Invoices

Invoices adalah tagihan induk.

### Required Fields

- `id`
- `invoice_number`
- `pending_registration_id` nullable
- `user_id` nullable
- `access_tier_id`
- `type`
- `total_amount`
- `balance_due`
- `currency_code`
- `status`
- timestamps

## 2.2 Payment Activities

Payment activities adalah log event transaksi individual terhadap invoice.

### Required Fields

- `id`
- `invoice_id`
- `payment_method`
- `payment_type`
- `amount_paid`
- `currency_code`
- `status`
- `payment_reference` nullable
- `notes` nullable
- timestamps

## 2.3 Access Tiers

Access tiers tetap menjadi sumber utama tier access user.

### Additional Required Field

- `level` integer

Field ini dipakai untuk menentukan hierarchy upgrade.

---

# 3. Final Enum Lock

## 3.1 Invoice Type

Final enum:

- `initial`
- `upgrade`

## 3.2 Invoice Status

Final enum:

- `unpaid`
- `installment`
- `paid_full`
- `upgraded`

## 3.3 Payment Activity Status

Final enum:

- `pending`
- `success`
- `failed`
- `cancelled`

## 3.4 Payment Type

Final enum:

- `pay_full`
- `installment`

## 3.5 Payment Method

Final enum:

- `paypal`
- `bank_transfer`
- `mock`

---

# 4. Invoice Number Rule

## Final Rule

`invoice_number` harus dibuat saat row invoice diinsert.

## Format

Gunakan format:

- `INV-YYYY-####`

Contoh:

- `INV-2026-0001`

## Rule

- unik
- human-readable
- tetap tersimpan meskipun invoice kemudian abandoned/cancelled

## Implementation Direction

`invoice_number` harus digenerate lewat service/helper khusus, bukan observer model.

Contoh arah:

- `InvoiceNumberService`

---

# 5. Pending Registration and User Relation

## Initial Checkout

Saat checkout awal:

- `pending_registration_id` terisi
- `user_id` null

## After Anti-Limbo

Setelah payment success dan user berhasil dibuat:

- `user_id` diisi
- `pending_registration_id` tetap dipertahankan

## Reason

Ini penting untuk:

- audit trail conversion
- relasi lead ke akun final

---

# 6. Full Redirect PayPal Rule

## Final Rule

Untuk payment method `paypal`, flow harus memakai:

- **full redirect ke halaman resmi PayPal**

Jangan gunakan embedded checkout sebagai jalur utama.

## Reason

- lebih aman
- lebih stabil
- lebih cocok untuk mobile/WebView integration di masa depan

---

# 7. PayPal Integration Strategy

## Final Rule

Gunakan:

- PayPal REST API v2
- Laravel HTTP Client
- Service Pattern

## Must Use

Contoh arah implementasi:

- `PayPalService`
- `Http::withToken()`

## Must Not Use

Jangan pakai package pihak ketiga seperti:

- `srmklive/paypal`

---

# 8. PayPal Service Responsibility

`PayPalService` hanya bertugas untuk komunikasi HTTP dengan PayPal API.

## Responsibility

- auth token
- create order
- capture order
- verify webhook signature

## Important Rule

Jangan letakkan efek bisnis LMS di service ini.

Efek bisnis harus dipusatkan ke finalizer terpisah.

---

# 9. Payment Finalizer Service

## Final Rule

Semua efek bisnis pasca-payment-success harus dipusatkan di satu service:

- `PaymentFinalizerService`

## This Service Must Be Used By

- success capture flow
- webhook fallback
- mock success path

## Responsibility

1. idempotency guard
2. update `payment_activity`
3. update `invoice`
4. create `user` jika belum ada
5. isi `invoice.user_id`
6. assign tier ke user
7. update continuation / anti-limbo state
8. trigger email via queue
9. final success behaviour untuk initial
10. final success behaviour untuk upgrade
11. trigger continuation / welcome email untuk initial payment success
12. trigger welcome upgrade email untuk upgrade payment success

---

# 10. Idempotency Guard

## Final Rule

Gunakan minimal dua guard:

1. jika `invoice.status == paid_full`
    - jangan proses lagi

2. jika `payment_reference` yang sama sudah pernah punya activity `success`
    - jangan proses lagi

## Reason

Cukup untuk fase ini tanpa perlu webhook log table tambahan.

---

# 11. Success Capture Flow (Sync)

## Final Rule

Success URL PayPal harus memicu flow sinkron yang melakukan capture ke PayPal.

## Required Order

1. capture order ke PayPal
2. panggil `PaymentFinalizerService`
3. tampilkan success page
4. tampilkan CTA:
    - `Continue Registration / Enrollment` untuk initial
    - atau CTA yang sesuai untuk upgrade

## UX Rule

Jangan langsung redirect diam-diam ke onboarding.
User harus melihat halaman sukses dulu.

---

# 12. Webhook Fallback Flow (Async)

## Final Rule

Webhook PayPal adalah full fallback processor.

## Required Behaviour

Jika success URL tidak sempat menyelesaikan proses:

- webhook tetap harus dapat memproses seluruh efek bisnis yang sama
- dengan memanggil `PaymentFinalizerService`

## Webhook Route

Gunakan satu endpoint khusus PayPal, misalnya:

- `/webhooks/paypal`

## Security Rule

Webhook signature verification harus diimplementasikan penuh sejak awal.

Jangan menunda verification.

---

# 13. Cancel Flow

## Final Rule

Jika user menekan Cancel di halaman PayPal lalu kembali ke LMS:

- `invoice.status` tetap `unpaid`
- activity yang sebelumnya dibuat `pending` diubah menjadi `cancelled`

## Important Rule

Jangan buat row baru khusus event cancel.
Jangan hard delete data.

## Reason

Data ini penting untuk:

- abandoned cart analytics
- reminder flow di masa depan
- histori transaksi

---

# 14. Mock Mode

## Final Rule

`mock` tetap valid sebagai mode testing/transisi.

## Behaviour

Jika `payment_method = mock`:

- jangan redirect ke PayPal
- jalankan flow success lokal
- tetap gunakan arsitektur yang sama sebisa mungkin

## Frontend Rule

`mock` tidak boleh tampil di UI user biasa.

Boleh disembunyikan dengan environment flag, misalnya:

- `VITE_ENABLE_MOCK_PAYMENT=true`

## Backend Rule

Backend wajib menolak `mock` di production.

---

# 15. Bank Transfer

## Final Rule

Untuk fase ini, `bank_transfer` hanya disiapkan sebagai:

- enum
- struktur data

## Not Yet In Scope

- upload bukti transfer
- admin approval
- capture logic bank transfer

Fokus tetap 100% pada PayPal full redirect.

---

# 16. Upgrade Rule

## Final Rule

Upgrade adalah flow terpisah dari payment initial.

### Upgrade Invoice Rule

Saat upgrade:

- selalu buat invoice baru
- `type = upgrade`
- `user_id` terisi
- `pending_registration_id = null`

### Upgrade Activity Rule

Upgrade juga membuat payment activity baru.

### Important Rule

Invoice lama tidak diubah sembarangan.
Hanya satu invoice aktif utama yang menjadi basis upgrade saat itu yang diubah menjadi:

- `upgraded`

Jangan ubah seluruh invoice historis user.

---

# 17. Upgrade Price Proration

## Final Rule

Harga upgrade dihitung dari:

- harga tier target
- dikurangi total uang riil sukses dari invoice aktif terakhir yang relevan pada jalur tier itu

## Important Rule

Jangan menghitung dari semua invoice sukses user lintas produk tanpa filter.

Hanya invoice aktif terakhir yang relevan yang menjadi basis perhitungan.

---

# 18. Tier Assignment Rule

## Initial Success

Saat payment `initial` sukses:

- user di-assign ke tier target tersebut
- jika ada tier lama karena data kotor, tier aktif user harus diganti

## Upgrade Success

Saat payment `upgrade` sukses:

- user dipindah ke tier target upgrade

## Assumption

Satu user idealnya hanya punya:

- satu `access_tier_id` aktif

---

# 19. Access Tier Level Rule

## Final Rule

Tambahkan `level` integer pada `access_tiers`.

## Purpose

Dipakai untuk:

- menentukan apakah target benar-benar upgrade
- mencegah downgrade
- menjaga logic hierarchy

## Example

- Starter Kit = 1
- Online = 2
- Master Class = 3

---

# 20. Upgrade Guard Rule

## Final Rule

User hanya boleh melihat / memproses upgrade jika:

- tier target punya `level` lebih tinggi dari tier user saat ini

Jangan izinkan downgrade.

---

# 21. Installment Rule

## Final Rule

Untuk fase sekarang, installment berarti:

- invoice belum lunas (`balance_due > 0`)
- tetapi sudah ada payment activity `success`

## Important Rule

Satu invoice boleh punya lebih dari satu payment activity sukses dengan:

- `payment_type = installment`

Tidak perlu schedule table cicilan khusus dulu.

---

# 22. Email Trigger Rule

## Final Rule

Email harus dipicu melalui queue/job/event, bukan diblok di request sinkron utama.

## Initial Payment Success

Jika payment `initial` berhasil:

- kirim continuation / welcome email
- email ini wajib terkirim
- trigger dilakukan dari `PaymentFinalizerService`
- pengiriman harus melalui queue/job/event

## Upgrade Payment Success

Jika payment `upgrade` berhasil:

- kirim welcome upgrade email berbahasa Inggris
- trigger dilakukan dari `PaymentFinalizerService`
- pengiriman harus melalui queue/job/event

## Important Rule

Jadi:

- payment initial sukses -> email terkirim
- payment upgrade sukses -> email upgrade terkirim

# 23. Recoverability Rule

## Final Rule

Jika payment sukses tetapi proses create user / continuation gagal di tengah:

- flow harus tetap recoverable

Recovery bisa melalui:

- webhook fallback
- idempotent re-entry
- retry mechanism aman

Sistem tidak boleh buntu permanen.

---

# 24. Implementation Direction

Urutan implementasi yang harus diikuti:

1. audit schema existing vs schema final
2. migration alignment
3. model alignment
4. service layer
5. controller flow
6. webhook handler
7. queue/email trigger
8. tests

## Important Rule

Jangan langsung coding liar tanpa migration alignment dulu.

---

# 25. Final Summary

Arsitektur final payment YogaFX LMS sekarang adalah:

- PayPal nyata sebagai jalur utama
- full redirect
- invoices + payment_activities
- anti-limbo compatibility
- sync success capture
- async webhook fallback
- one finalizer service
- full webhook verification
- mock tetap hidup untuk transisi
- bank transfer belum diimplementasikan penuh
- upgrade adalah first-class flow
- invoice lama basis upgrade menjadi `upgraded`
- tier hierarchy dikunci lewat `access_tiers.level`

Dokumen ini adalah source of truth final dan terbaru untuk implementasi domain PayPal + Upgrade di YogaFX LMS.
