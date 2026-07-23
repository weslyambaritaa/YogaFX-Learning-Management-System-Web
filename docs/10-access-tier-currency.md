# Access Tier Currency
# YogaFX LMS

## Purpose

Dokumen ini menjadi source of truth untuk currency pada domain `AccessTier` dan alur turunan ke checkout, invoice, dan payment.

Dokumen ini mengikuti implementasi aktif saat ini.

---

## 1. Core Rule

Harga tier tidak boleh dianggap selalu USD.

Setiap `AccessTier` aktif saat ini harus punya:
- `price`
- `currency_code`

Currency dipilih saat admin mengelola tier.

---

## 2. Current Supported Currency

Implementasi aktif saat ini mendukung:
- `IDR`
- `USD`
- `GBP`
- `EUR`

Dokumen lama yang menyebut hanya `USD` dan `IDR` tidak lagi cukup menggambarkan kondisi repository saat ini.

---

## 3. Why Currency Lives on Access Tier

Currency disimpan pada `AccessTier` supaya:
- setiap tier bisa membawa nominal dan currency sendiri
- checkout menampilkan amount yang benar
- invoice menyimpan snapshot transaksi yang benar
- payment activity juga menyimpan snapshot yang konsisten
- upgrade flow tetap mengikuti currency tier target

---

## 4. Checkout Currency Flow

### Initial Checkout

Saat calon student masuk checkout:
- amount mengikuti `access_tier.price`
- currency mengikuti `access_tier.currency_code`

### Upgrade Checkout

Saat student membuka upgrade checkout:
- amount due dihitung terhadap tier target
- currency mengikuti `target_tier.currency_code`

---

## 5. Invoice Currency

### Final Rule

Saat invoice dibuat:
- `invoice.currency_code` harus diisi dari tier yang menjadi basis transaksi saat itu

Invoice adalah snapshot transaksi, bukan pembacaan live dari tier di kemudian hari.

---

## 6. Payment Activity Currency

### Final Rule

Saat payment activity dibuat:
- `payment.currency_code` juga harus diisi
- nilainya harus konsisten dengan invoice yang sedang dibayar

---

## 7. Snapshot Rule

Currency pada:
- checkout payload
- invoice
- payment activity

harus mengikuti snapshot transaksi ketika row dibuat.

Jangan merender histori transaksi lama dengan membaca currency live dari tier terbaru.

---

## 8. Admin-Side Rule

Admin saat mengelola access tier harus bisa melihat:
- price
- currency_code
- level
- payment_link

Karena currency sekarang sudah menjadi bagian dari commerce setup tier, bukan sekadar informasi tambahan.

---

## 9. Final Summary

Implementasi aktif sekarang memakai `currency_code` sebagai bagian penting dari flow:
- Access Tier
- Checkout
- Invoice
- Payment Activity
- Upgrade

Supported currency aktif saat ini:
- IDR
- USD
- GBP
- EUR
