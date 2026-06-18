# Access Tier Currency

# YogaFX LMS

## Purpose

Dokumen ini menjadi source of truth untuk currency pada domain Access Tier dan alur turunannya ke checkout, invoice, dan payment.

Dokumen ini dibuat karena harga tier tidak boleh statis USD/dollar saja. Admin harus bisa memilih currency pada setiap tier.

---

# 1. Core Principle

## Final Rule

Harga pada Access Tier tidak boleh dianggap selalu USD.

Setiap Access Tier harus punya:

- harga
- currency

Currency dipilih oleh admin pada saat mengelola Access Tier.

---

# 2. Access Tier Requirements

## Final Rule

Setiap Access Tier minimal harus memiliki field:

- `price`
- `currency_code`

## Minimum Supported Currency

Untuk tahap awal, minimal support:

- `USD`
- `IDR`

Currency lain dapat ditambahkan nanti bila diperlukan.

---

# 3. Why Currency Must Be Stored on Access Tier

## Reason

Agar:

- setiap tier bisa punya nominal dengan mata uang yang sesuai
- checkout tidak statis dollar
- invoice dan payment bisa mewarisi currency yang benar
- sistem siap untuk tier internasional dan lokal

---

# 4. Checkout Currency Flow

## Final Rule

Saat user masuk checkout:

- amount harus mengikuti `price` dari tier
- currency harus mengikuti `currency_code` dari tier

Artinya:

- checkout tidak boleh hardcoded USD
- checkout harus menampilkan nilai sesuai currency tier

---

# 5. Invoice Currency

## Final Rule

Saat invoice dibuat:

- invoice harus menyimpan `currency_code`

## Why

Agar invoice menjadi snapshot transaksi pada saat itu.

Jika nanti:

- harga tier berubah
- currency tier berubah

invoice lama tetap konsisten dengan kondisi saat transaksi dibuat.

---

# 6. Payment Currency

## Final Rule

Saat payment dibuat:

- payment juga harus menyimpan `currency_code`

## Why

Agar setiap payment activity memiliki konteks currency yang jelas dan konsisten dengan invoice.

---

# 7. Recommended Fields

## Access Tier

Minimal:

- `price`
- `currency_code`

## Invoice

Minimal tambahan:

- `currency_code`

## Payment

Minimal tambahan:

- `currency_code`

---

# 8. Snapshot Rule

## Final Rule

Currency di invoice dan payment harus dibekukan/snapshot dari Access Tier saat transaksi dibuat.

Jangan membuat invoice/payment selalu membaca currency live dari tier setiap kali dirender.

---

# 9. Display Rule

## Admin Side

Admin saat mengelola Access Tier harus bisa:

- memilih currency
- melihat harga bersama currency yang dipilih

## Checkout

Checkout harus menampilkan:

- amount
- currency

## Invoice / Payment

UI boleh menampilkan currency yang tersimpan pada snapshot invoice/payment.

---

# 10. Future Compatibility

Struktur ini harus kompatibel dengan:

- simulated payment flow
- future live payment gateway
- installment
- upgrade
- currency-sensitive billing display

---

# 11. Final Summary

Access Tier sekarang harus punya currency yang dipilih admin.

Minimal:

- USD
- IDR

Currency itu harus mengalir ke:

- checkout
- invoice
- payment

Dan invoice/payment harus menyimpan snapshot currency agar data transaksi tetap konsisten di masa depan.

Dokumen ini menjadi source of truth untuk domain Access Tier Currency.
