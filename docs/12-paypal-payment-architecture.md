# PayPal Payment Architecture

# YogaFX LMS

## Purpose

Dokumen ini menjadi source of truth untuk arsitektur payment nyata berbasis **PayPal REST API v2** pada YogaFX LMS.

Dokumen ini menjelaskan:

- struktur database final
- status final
- flow checkout
- flow cancel
- flow success capture
- webhook fallback
- anti-limbo integration
- idempotency guard
- mock payment compatibility untuk transisi dari simulated flow

Dokumen ini harus dipakai sebagai acuan utama ketika mulai implementasi payment nyata berbasis PayPal.

---

# 1. Scope

Domain ini mencakup:

1. invoice architecture
2. payment activity architecture
3. PayPal checkout integration
4. cancel behaviour
5. success capture flow
6. webhook fallback
7. anti-limbo compatibility
8. idempotency
9. mock mode compatibility
10. upgrade compatibility

Dokumen ini tidak membahas:

- UI detail bank transfer proof upload
- sistem cicilan bertahap dengan schedule table khusus
- integrasi payment gateway lain selain PayPal
- email template detail

---

# 2. Core Principle

## Final Rule

Sistem payment YogaFX sekarang bergerak ke arah **PayPal nyata**, tetapi tetap mempertahankan mode `mock` untuk kebutuhan transisi dan testing.

Arsitektur ini harus:

- siap untuk transaksi nyata
- siap untuk anti-limbo
- aman terhadap proses ganda
- tetap mendukung fallback bila browser user terputus
- tetap bisa digunakan dalam mode simulasi internal

---

# 3. Database Architecture

## 3.1 Invoices

`invoices` adalah tagihan induk.

### Required Fields

- `id`
- `invoice_number`
- `pending_registration_id` nullable
- `user_id` nullable
- `access_tier_id`
- `total_amount`
- `balance_due`
- `currency_code`
- `status`
- `type`
- timestamps

## 3.2 Payment Activities

`payment_activities` adalah event transaksi individual terhadap invoice.

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

---

# 4. Final Naming Lock

## 4.1 Invoice Status

Final enum:

- `unpaid`
- `installment`
- `paid_full`

## 4.2 Payment Activity Status

Final enum:

- `pending`
- `success`
- `failed`
- `cancelled`

## 4.3 Invoice Type

Final enum:

- `initial`
- `upgrade`

## 4.4 Payment Type

Final enum:

- `pay_full`
- `installment`

## 4.5 Payment Method

Final enum:

- `paypal`
- `bank_transfer`
- `mock`

---

# 5. Invoice Number Rule

## Final Rule

`invoice_number` harus dibuat **langsung saat row invoice diinsert**.

## Format

Harus:

- unik
- human-readable

Contoh:

- `INV-2026-0001`

## Important Rule

Invoice number tetap ada meskipun transaksi akhirnya dibatalkan atau abandoned.

---

# 6. Relation Rule Between Pending Registration and User

## Checkout Awal

Saat checkout dari flow scoreboard awal:

- `pending_registration_id` terisi
- `user_id` null

## After Anti-Limbo

Setelah akun user berhasil dibuat:

- `user_id` diisi
- `pending_registration_id` tetap dipertahankan

## Reason

Ini penting untuk audit trail konversi lead ke student.

---

# 7. Invoice Creation Timing

## Final Rule

Invoice dibuat saat user menekan:

- `Pay Now`

Bukan saat baru membuka checkout.

## Reason

Menghindari invoice sampah dari user yang hanya membuka checkout tanpa niat melanjutkan.

---

# 8. Payment Activity Creation Timing

## Final Rule

Payment activity juga dibuat saat user menekan:

- `Pay Now`

Flow:

1. create invoice
2. create payment activity
3. proses redirect / payment handling sesuai method

---

# 9. Checkout Strategy

## Final Rule

Untuk checkout public awal:

- tampilkan halaman checkout di sistem YogaFX sendiri
- gunakan **PayPal JavaScript SDK** sebagai layer resmi di frontend
- gunakan **PayPal Card Fields** untuk debit/credit card
- gunakan **PayPal Buttons** untuk flow akun PayPal secara in-context semaksimal capability resmi PayPal

Jangan membuat form kartu buatan sendiri yang menangani raw card number, expiry, atau CVV di backend YogaFX.

## Important Boundary

- data sensitif kartu harus langsung masuk ke komponen hosted milik PayPal
- login / wallet / account-specific interaction PayPal tetap dikelola PayPal
- jika PayPal membuka popup atau window in-context resmi, itu tetap dianggap valid
- jangan paksa fake embedded login atau workaround tidak aman

---

# 10. PayPal Integration Strategy

## Final Rule

Gunakan:

- **PayPal REST API v2**
- **Laravel HTTP Client**
- **Service Pattern**

## Must Use

Contoh arah implementasi:

- `PayPalService.php`
- `Http::withToken()`

## Must Not Use

Jangan bergantung ke package pihak ketiga seperti:

- `srmklive/paypal`

## Reason

1. menghindari dependency hell
2. memberi kontrol penuh pada logic payment
3. menjaga anti-limbo dan fallback logic tetap fleksibel
4. lebih future-proof

---

# 11. Environment Rule

## Final Rule

Untuk development sekarang:

- gunakan **PayPal Sandbox**

Credentials disimpan aman di:

- `.env`

Contoh kebutuhan:

- PayPal client id
- PayPal secret
- API base URL sandbox

---

# 12. Cancel Flow

## Final Rule

Jika user menekan **Cancel** di PayPal dan kembali ke situs LMS:

- `invoice.status` tetap `unpaid`
- `payment_activities.status` diubah menjadi `cancelled`

## Important Rule

Tidak boleh hard delete invoice/payment row.

## Reason

Data ini penting untuk:

- abandoned cart analysis
- reminder flow di masa depan
- histori transaksi yang lengkap

---

# 13. Success Capture Flow (Synchronous Layer)

## Final Rule

Saat user kembali ke **Success URL** dari PayPal, controller harus langsung menjalankan capture flow.

## Urutan wajib:

1. update `invoice` dan `payment_activities`
2. buat `User` jika belum ada
3. assign `access_tier_id` ke user
4. kirim continuation/welcome email
5. arahkan user ke halaman sukses pendaftaran

## UI Rule

User harus melihat halaman sukses dulu, dengan CTA:

- `Continue Registration / Enrollment`

Jangan langsung redirect diam-diam ke form berikutnya.

---

# 14. Webhook Fallback (Asynchronous Layer)

## Final Rule

Webhook PayPal adalah **full fallback processor**.

Jika user menutup browser sebelum success URL sempat diproses, webhook harus tetap bisa:

1. update `invoice`
2. update `payment_activities`
3. buat `User` jika belum ada
4. assign tier
5. kirim continuation email

Webhook tidak punya UI redirect, karena berjalan di background.

---

# 15. Idempotency Guard

## Final Rule

Gunakan dua guard berikut:

1. jika `invoice.status == paid_full`
    - abaikan proses berikutnya

2. jika `payment_reference` sudah ada di `payment_activities` dengan status `success`
    - abaikan proses berikutnya

## Reason

Cukup untuk fase awal tanpa perlu membuat tabel webhook log terpisah.

---

# 16. Anti-Limbo Compatibility

## Final Rule

Arsitektur payment ini harus sepenuhnya kompatibel dengan anti-limbo flow.

## Behaviour

Jika payment berhasil:

- akun dasar dibuat bila belum ada
- continuation flow disiapkan
- user tetap bisa lanjut onboarding walaupun sempat terputus

---

# 17. Upgrade Rule

## Final Rule

Upgrade selalu:

- membuat invoice baru
- membuat payment activity baru
- tidak mengubah invoice lama

## Upgrade Invoice

- `user_id` terisi
- `pending_registration_id` null
- `type = upgrade`

## Prorata Rule

Nominal upgrade dihitung dari:

- harga program target
- dikurangi nominal yang sudah pernah dibayar

---

# 18. Installment Rule

## Final Rule

Untuk fase sekarang, installment hanya berarti:

- masih ada `balance_due > 0`
- tetapi sudah ada payment activity `success`

Tidak perlu tabel schedule/termin cicilan khusus dulu.

---

# 19. Mock Mode Compatibility

## Final Rule

Walaupun backend diarahkan ke PayPal nyata, mode simulasi tetap harus hidup melalui:

- `payment_method = mock`

## Behaviour

Jika method = `mock`:

- controller tidak redirect ke PayPal
- sistem mensimulasikan jeda/loading
- lalu masuk ke alur success capture lokal

## Purpose

1. testing internal
2. transisi bertahap dari simulated flow ke PayPal nyata
3. non-production fallback

---

# 20. Currency Rule

## Final Rule

Invoice dan payment activity harus menyimpan:

- `currency_code`

Currency ini adalah snapshot dari access tier saat transaksi dibuat.

Jangan membaca currency live dari tier saat render transaksi lama.

---

# 21. Final Summary

Arsitektur payment final YogaFX LMS sekarang harus seperti ini:

- `invoices` = tagihan induk
- `payment_activities` = event log transaksi
- invoice dibuat saat klik `Pay Now`
- payment activity dibuat saat klik `Pay Now`
- checkout tetap berlangsung di halaman YogaFX
- PayPal dipakai aman di belakang layar melalui SDK resmi
- success capture diproses di success URL
- webhook menjadi fallback penuh
- idempotency wajib dijaga
- anti-limbo wajib kompatibel
- cancel tidak menghapus data
- mock mode tetap hidup
- upgrade memakai invoice baru
- struktur siap untuk payment nyata dan tetap stabil untuk transisi

Dokumen ini menjadi source of truth implementasi PayPal payment architecture untuk YogaFX LMS.
