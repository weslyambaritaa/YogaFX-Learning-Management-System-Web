# Payment Backend Foundation

# YogaFX LMS

## Purpose

Dokumen ini menjadi source of truth untuk fondasi backend domain payment pada YogaFX LMS.

Tujuan dokumen ini adalah:

- menyiapkan struktur backend payment sejak awal
- memisahkan invoice sebagai tagihan induk dari payment sebagai aktivitas transaksi
- mendukung simulated payment flow sekarang
- tetap siap untuk integrasi payment gateway nyata di masa depan
- tetap kompatibel dengan:
    - pending registration
    - anti-limbo flow
    - onboarding
    - installment
    - upgrade

Dokumen ini fokus pada fondasi backend, bukan integrasi gateway live.

---

# 1. Scope

Domain ini mencakup:

1. `pending_registrations`
2. `invoices`
3. `payments`
4. relasi ke `users`
5. relasi ke `access_tiers`
6. balance tracking
7. payment type
8. payment method
9. payment status
10. compatibility untuk upgrade
11. compatibility untuk anti-limbo

---

# 2. Core Principle

## Final Rule

Walaupun payment yang aktif saat ini masih simulasi, backend payment harus dibangun **nyata** dan **production-ready**.

Artinya:

- invoice nyata
- payment nyata
- relasi nyata
- status nyata
- balance nyata

Yang belum nyata hanya:

- provider payment live
- webhook
- perpindahan uang sungguhan

---

# 3. Pending Registration

## Final Rule

Gunakan satu entitas/tabel:

- `pending_registrations`

Tidak perlu tabel status lifecycle terpisah.

## Required Lifecycle Field

Tabel ini harus punya field `status`, misalnya:

- `created`
- `checkout_opened`
- `payment_success`
- `completed`

## Role

Pending registration menjadi sumber data calon murid sebelum mereka menjadi user final.

Saat scoreboard submit:

- data hanya masuk ke `pending_registrations`
- belum membuat record di `users`

---

# 4. Invoice Entity

## Definition

Invoice adalah tagihan induk.

Invoice menyimpan kewajiban total pembelian sebuah tier/program.

## Required Fields

Tabel `invoices` minimal harus punya:

- `id`
- `pending_registration_id` nullable
- `user_id` nullable
- `access_tier_id`
- `total_amount`
- `balance_due`
- `status`
- `type` nullable
- timestamps

## Field Meaning

### `pending_registration_id`

Dipakai saat checkout masih terhubung ke calon murid yang belum menjadi user final.

### `user_id`

Dipakai setelah akun user sudah dibuat.
Penting juga untuk flow upgrade.

### `access_tier_id`

Tier/program yang ditagihkan.

### `total_amount`

Total harga tier/program.

### `balance_due`

Sisa tagihan yang belum dibayar.

### `status`

Status invoice.

### `type`

Optional, misalnya:

- `initial`
- `upgrade`

---

# 5. Invoice Status

Invoice minimal harus mendukung:

- `pending`
- `installment`
- `paid_full`

## Meaning

### `pending`

Tagihan sudah dibuat tetapi belum selesai.

### `installment`

Sudah ada pembayaran sukses, tetapi masih ada balance due.

### `paid_full`

Tagihan sudah lunas, balance due = 0.

---

# 6. Payment Entity

## Definition

Payment adalah aktivitas transaksi individual yang terkait ke sebuah invoice.

Satu invoice dapat memiliki lebih dari satu payment.

## Required Fields

Tabel `payments` minimal harus punya:

- `id`
- `invoice_id`
- `payment_method`
- `payment_type`
- `amount_paid`
- `status`
- `payment_reference` nullable
- `notes` nullable
- timestamps

## Field Meaning

### `invoice_id`

Relasi ke invoice induk.

### `payment_method`

Contoh:

- `paypal`
- `bank_transfer`

### `payment_type`

Contoh:

- `pay_full`
- `installment`

### `amount_paid`

Nominal pembayaran pada aktivitas ini.

### `status`

Contoh:

- `pending`
- `success`
- `failed`

### `payment_reference`

Dipakai untuk masa depan, misalnya:

- transaction id provider
- bank reference
- nomor bukti transaksi
- external payment id

### `notes`

Field opsional untuk kebutuhan catatan internal.

---

# 7. Payment Status

Payment minimal harus mendukung:

- `pending`
- `success`
- `failed`

---

# 8. Why Payments Must Not Store User ID and Tier ID Directly

## Final Rule

Tabel `payments` tidak perlu menyimpan:

- `user_id`
- `access_tier_id`

## Reason

Data tersebut sudah tersedia lewat invoice.

Jika ingin tahu:

- siapa yang ditagih → lihat invoice
- tier apa yang dibeli → lihat invoice

Ini menjaga normalisasi data tetap bersih.

---

# 9. When Invoice and Payment Are Created

## Final Rule

Invoice dan payment dibuat saat user menekan tombol:

- `Pay Now`

Bukan saat baru membuka checkout page.

## Final Flow

1. user membuka checkout
2. user memilih payment type
3. user memilih payment method
4. user menekan `Pay Now`
5. backend membuat invoice
6. backend membuat payment
7. backend melanjutkan logic simulasi / payment result

---

# 10. Simulated Payment Compatibility

## Final Rule

Untuk fase sekarang, payment masih simulasi.

### Simulated Flow

1. user memilih payment type
2. user memilih payment method
3. user klik `Pay Now`
4. UI menampilkan loading sekitar 2–3 detik
5. sistem otomatis menganggap payment sukses

## Consequence

Setelah simulated success:

- payment status = success
- invoice diperbarui
- anti-limbo flow dijalankan
- user lanjut onboarding

---

# 11. Installment Logic

## Final Rule

Jika user memilih installment:

- invoice awal tetap `pending`
- setelah payment pertama sukses, jika balance due masih ada:
    - invoice status menjadi `installment`

## Example

- total = 500
- payment pertama = 125
- balance due = 375
- invoice status = `installment`

---

# 12. Paid Full Logic

## Final Rule

Jika setelah payment sukses:

- balance due = 0

maka:

- invoice status = `paid_full`

---

# 13. Anti-Limbo Compatibility

## Final Rule

Struktur ini harus kompatibel dengan anti-limbo flow.

### Behaviour

Setelah payment sukses:

1. payment diperbarui
2. invoice diperbarui
3. jika user belum ada, buat akun dasar
4. lanjutkan continuation flow
5. user masuk ke enrollment lalu sign up

---

# 14. User Creation Timing

## Final Rule

Saat scoreboard submit:

- belum ada record di `users`

Data hanya berada di:

- `pending_registrations`

Record `users` baru dibuat saat:

- payment success / anti-limbo stage

---

# 15. Upgrade Compatibility

## Final Rule

Struktur invoices & payments harus mendukung upgrade.

## Upgrade Rules

1. upgrade selalu membuat invoice baru
2. invoice lama tidak diubah
3. payment upgrade dibuat sebagai payment baru
4. `type` invoice boleh dipakai untuk:
    - `initial`
    - `upgrade`

## Prorata Rule

Upgrade dihitung dari:

- harga program target
- dikurangi total nominal yang sudah pernah dibayar

---

# 16. What Must Stay Real in Database

Walaupun payment masih simulasi, data berikut harus nyata:

- pending_registrations
- invoices
- payments
- user creation state
- enrollment state
- continuation state
- tier assignment pada user

Yang belum nyata hanya:

- gateway live
- webhook provider
- transaksi uang sungguhan

---

# 17. Final Summary

Fondasi backend payment YogaFX LMS harus dibangun seperti ini:

- `pending_registrations` untuk lead sebelum jadi user
- `invoices` sebagai tagihan induk
- `payments` sebagai aktivitas transaksi individual
- invoice & payment dibuat saat klik `Pay Now`
- payment saat ini masih simulasi
- data bisnis tetap nyata
- struktur siap untuk:
    - anti-limbo
    - onboarding
    - installment
    - upgrade
    - future live payment integration

Dokumen ini menjadi source of truth untuk persiapan backend payment.
