# PayPal Upgrade Final Lock
# YogaFX LMS

## Purpose

Dokumen ini mengunci aturan implementasi upgrade payment yang aktif saat ini di YogaFX LMS.

Dokumen ini membahas:
- hubungan invoice upgrade dengan invoice basis sebelumnya
- guard upgrade by tier level
- proration logic yang aktif
- final effect setelah upgrade success

---

## 1. Core Principle

Upgrade adalah flow payment terpisah dari initial checkout.

Upgrade saat ini:
- memakai invoice baru
- memakai payment activity baru
- memerlukan tier target dengan `level` lebih tinggi
- berakhir pada perpindahan `user.access_tier_id` ke tier target saat success

---

## 2. Current Entity Rule

### Upgrade Invoice

Invoice upgrade aktif saat ini dibuat dengan:
- `type = upgrade`
- `user_id` terisi
- `pending_registration_id = null`
- `access_tier_id = target tier`

### Upgrade Payment Activity

Payment activity upgrade:
- terhubung ke invoice upgrade
- menyimpan `payment_method`
- menyimpan `payment_type`
- menyimpan `amount_paid`
- memakai `payment_reference` PayPal order id bila jalur PayPal dipakai

---

## 3. Tier Level Rule

### Final Rule

`access_tiers.level` adalah guard utama upgrade.

User hanya boleh upgrade jika:
- tier target aktif
- tier user saat ini aktif
- `target.level > current.level`

Downgrade tidak diizinkan.

---

## 4. Current Proration Rule

### Final Rule

`amount_due` upgrade dihitung dari:
- harga tier target
- dikurangi total payment sukses dari **basis invoice relevan terakhir**

### Important Rule

Implementasi aktif saat ini **tidak** menjumlah semua invoice historis user lintas produk.

Basis invoice yang dipakai:
- invoice milik user
- status `paid_full` atau `installment`
- terkait tier user saat ini, atau tier yang level-nya masih di bawah target bila perlu
- dipilih yang paling relevan terbaru

---

## 5. Upgrade Success Rule

### Final Rule

Saat upgrade payment difinalisasi sukses:
1. payment activity upgrade menjadi `success`
2. invoice upgrade diperbarui sesuai `balance_due`
3. `user.access_tier_id` dipindah ke tier target
4. basis invoice lama yang relevan dapat ditandai `upgraded`
5. upgrade welcome email dapat di-queue

Efek bisnis ini dipusatkan di:
- `PaymentFinalizerService`

---

## 6. Basis Invoice Upgrade Rule

### Final Rule

Invoice lama tidak diubah sembarangan.

Hanya **basis invoice relevan** yang dipakai untuk perhitungan upgrade yang dapat ditandai:
- `status = upgraded`

Tujuannya:
- menjaga histori invoice lain tetap utuh
- menandai jalur invoice yang memang sudah dinaikkan levelnya

---

## 7. Mock Compatibility

### Final Rule

Upgrade tetap bisa dijalankan melalui mode `mock` pada non-production.

Dalam mode ini:
- invoice upgrade tetap dibuat
- payment activity upgrade tetap dibuat
- finalizer tetap dipakai
- tier user tetap berpindah bila success

Jadi mock tidak mengubah aturan upgrade; hanya mengganti interaksi provider.

---

## 8. PayPal Upgrade Flow

### Active Behaviour

Jika `payment_method = paypal`:
- backend membuat invoice dan payment activity upgrade
- backend membuat PayPal order
- `payment_reference` diisi dengan order id
- frontend / PayPal return flow melanjutkan capture
- finalizer memproses success

Jika payment dibatalkan:
- payment activity pending menjadi `cancelled`
- user diarahkan kembali ke halaman upgrade target tier dengan error message

---

## 9. Amount Due Guard

### Final Rule

Jika hasil perhitungan upgrade menunjukkan:
- `amount_due <= 0`

maka flow upgrade baru tidak perlu diproses dan backend harus menolak checkout upgrade baru.

---

## 10. Final Summary

Aturan upgrade payment yang aktif saat ini:
- upgrade hanya ke tier dengan level lebih tinggi
- selalu membuat invoice upgrade baru
- selalu membuat payment activity baru
- memakai proration dari basis invoice relevan terakhir
- mengubah tier user hanya setelah success final
- dapat menandai basis invoice lama sebagai `upgraded`
- diproses secara konsisten lewat `PaymentFinalizerService`
