# Payment Backend Foundation
# YogaFX LMS

## Purpose

Dokumen ini menjadi source of truth untuk fondasi backend payment yang aktif saat ini pada YogaFX LMS.

Dokumen ini fokus pada:
- struktur data payment
- timing pembuatan invoice dan payment activity
- hubungan initial checkout, onboarding, dan upgrade
- finalizer-based business effects

---

## 1. Active Backend Payment Domains

Domain aktif saat ini:
1. `pending_registrations`
2. `invoices`
3. `payment_activities` sebagai tabel fisik untuk model `Payment`
4. `onboarding_states`
5. relasi ke `users`
6. relasi ke `access_tiers`
7. balance tracking
8. upgrade payment logic

---

## 2. Important Naming Rule

### Current Implementation Rule

Di level aplikasi:
- model domain memakai nama `Payment`
- relasi invoice memakai istilah `paymentActivities()`

Di level database:
- tabel fisik yang aktif saat ini adalah `payment_activities`

Dokumentasi backend harus menganggap ini sebagai keadaan implementasi yang benar saat ini.

---

## 3. Pending Registration

### Purpose

`pending_registrations` menyimpan calon murid sebelum payment initial dan onboarding selesai.

### Active Lifecycle

Status aktif:
- `created`
- `checkout_opened`
- `payment_success`
- `completed`

### Rule

Saat scoreboard submit:
- sistem membuat `pending_registration`
- sistem belum membuat akun LMS final

---

## 4. Invoice Entity

### Purpose

`invoices` adalah tagihan induk untuk:
- initial checkout
- student upgrade

### Key Active Fields

- `invoice_number`
- `pending_registration_id` nullable
- `user_id` nullable
- `access_tier_id`
- `type`
- `payment_type`
- `total_amount`
- `balance_due`
- `currency_code`
- `status`
- `issued_at`
- `paid_at`

### Active Invoice Type

- `initial`
- `upgrade`

### Active Invoice Status

- `unpaid`
- `installment`
- `paid_full`
- `upgraded`

---

## 5. Payment Activity Entity

### Purpose

Setiap aktivitas pembayaran individual dicatat sebagai row pada tabel `payment_activities` melalui model `Payment`.

### Key Active Fields

- `invoice_id`
- `payment_method`
- `payment_type`
- `amount_paid`
- `currency_code`
- `status`
- `payment_reference` nullable
- `notes` nullable

### Active Payment Method

- `paypal`
- `mock`

Catatan:
- `bank_transfer` ada sebagai enum domain yang dikenali, tetapi **ditolak** oleh arsitektur aktif saat ini

### Active Payment Status

- `pending`
- `success`
- `failed`
- `cancelled`

---

## 6. Creation Timing Rule

### Final Rule

Invoice dan payment activity dibuat saat user benar-benar memulai aksi bayar, bukan hanya saat membuka halaman checkout.

### Initial Checkout

Pada jalur public checkout:
- invoice dibuat ketika backend memproses create order / start checkout
- payment activity dibuat bersamaan dengan invoice

### Upgrade Checkout

Pada jalur upgrade:
- invoice upgrade baru dibuat saat student memulai payment
- payment activity baru dibuat bersamaan

---

## 7. Invoice Number Rule

### Final Rule

`invoice_number` harus dibuat saat row invoice diinsert.

### Current Format

Implementasi aktif saat ini memakai format:
- `INV-YYYY-####`

Generator aktif:
- `InvoiceNumberService`

---

## 8. Currency Snapshot Rule

### Final Rule

Saat invoice dan payment activity dibuat:
- `currency_code` diambil dari `AccessTier`
- nilainya menjadi snapshot transaksi

Perubahan currency pada tier di masa depan tidak boleh mengubah histori transaksi lama.

---

## 9. Initial Payment Backend Behaviour

### Before Success

Untuk payment initial:
- `invoice.pending_registration_id` terisi
- `invoice.user_id` masih nullable

### After Success

Setelah payment final berhasil:
- `invoice.user_id` diisi
- `pending_registration_id` tetap dipertahankan
- `pending_registration.status` menjadi `payment_success`
- `onboarding_state` dibuat atau dipakai ulang

---

## 10. Finalizer Rule

### Final Rule

Semua efek bisnis pasca-payment-success harus dipusatkan di:
- `PaymentFinalizerService`

Service ini dipakai oleh:
- mock success path
- PayPal success path
- PayPal webhook fallback

### Business Effects

Initial payment success:
- update payment activity
- update invoice
- create or attach user
- assign tier ke user
- create or reuse onboarding state
- queue continuation email bila perlu

Upgrade payment success:
- update payment activity
- update invoice
- ubah tier user ke target
- tandai basis invoice lama menjadi `upgraded` bila relevan
- queue upgrade welcome email

---

## 11. Balance and Installment Rule

### Final Rule

`balance_due` adalah dasar status invoice.

Jika:
- `balance_due <= 0` -> `paid_full`
- `balance_due > 0` setelah success payment -> `installment`

Implementasi aktif menghitung jumlah awal payment dari `payment_type`:
- `pay_full` -> seluruh amount
- `installment` -> seperempat amount awal

---

## 12. Idempotency Rule

### Final Rule

Finalizer harus aman terhadap proses ganda minimal dengan guard aktif:
- jika invoice sudah `paid_full`, lewati finalisasi ulang
- jika `payment_reference` yang sama sudah pernah sukses di row lain, lewati finalisasi ulang

---

## 13. Upgrade Basis Rule

### Final Rule

Upgrade tidak menghitung seluruh histori invoice user tanpa filter.

Implementasi aktif memakai basis invoice relevan terakhir:
- status `paid_full` atau `installment`
- terkait tier user saat ini, atau tier di bawah target bila diperlukan

`amount_due` = harga tier target - total payment sukses dari basis invoice relevan

---

## 14. Mock Compatibility

Mode `mock` masih aktif untuk non-production:
- tetap membuat invoice
- tetap membuat payment activity
- langsung diproses oleh finalizer

Artinya fondasi backend payment yang aktif sekarang mendukung dua jalur:
- PayPal normal
- mock internal

---

## 15. Final Summary

Fondasi backend payment aktif saat ini adalah:
- `pending_registrations` untuk lead sebelum akun final
- `invoices` sebagai tagihan induk
- `payment_activities` sebagai tabel payment event individual
- `onboarding_states` untuk continuation setelah initial payment success
- `PaymentFinalizerService` sebagai pusat efek bisnis payment success
- `InvoiceNumberService` untuk nomor invoice
- dukungan untuk initial checkout, upgrade, installment, dan mock compatibility
