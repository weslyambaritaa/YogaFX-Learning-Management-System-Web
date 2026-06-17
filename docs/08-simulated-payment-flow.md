# Simulated Payment Flow

# YogaFX LMS

## Purpose

Dokumen ini menjadi source of truth untuk implementasi **simulasi flow pembayaran** pada tahap awal pengembangan.

Tujuannya adalah:

- menampilkan seluruh pengalaman checkout dan pembayaran seperti sistem nyata
- mempertahankan UI/UX dan alur bisnis utama
- tetapi **belum terhubung ke payment gateway nyata**
- belum memproses uang sungguhan
- belum melakukan integrasi live payment provider

Dengan kata lain:
**payment flow tetap ada, tetapi hanya untuk simulasi.**

---

# 1. Scope

Dokumen ini mengatur simulasi untuk:

1. checkout page
2. pilihan tipe pembayaran
3. pilihan metode pembayaran
4. invoice creation
5. payment activity creation
6. payment success/failure simulation
7. anti-limbo continuation
8. onboarding continuation

Dokumen ini tidak mengatur:

- integrasi PayPal live
- integrasi credit card live
- integrasi bank transfer live
- webhook payment provider nyata
- settlement nyata
- pembayaran sungguhan

---

# 2. Core Principle

## Final Rule

Sistem harus terlihat seperti memiliki alur pembayaran penuh, tetapi untuk fase implementasi saat ini:

- pembayaran **hanya simulasi**
- tidak ada uang sungguhan berpindah
- tidak ada provider payment live yang aktif
- status payment success/failure ditentukan oleh flow simulasi internal sistem

---

# 3. What Must Still Exist in Simulation

Walaupun pembayaran hanya simulasi, sistem tetap harus memiliki semua layer berikut:

1. pending registration
2. checkout page
3. payment type selection
4. payment method selection
5. invoice
6. payment activity
7. payment result
8. onboarding continuation

Artinya:

- UI tetap ada
- model bisnis tetap ada
- state bisnis tetap ada
- hanya gateway real-nya yang belum aktif

---

# 4. Checkout Simulation

## Main Flow

1. User datang dari signed checkout link.
2. Sistem menampilkan checkout page.
3. Field autofill tetap tampil:
    - first name
    - last name
    - email
    - mobile phone
    - country
    - amount
4. User memilih:
    - Pay in Full
    - Pay in 4 Installments
5. User memilih metode:
    - PayPal / Credit Card
    - Bank Transfer

## Final Rule

Walaupun UI menampilkan metode pembayaran nyata, untuk fase ini semua hasil tetap simulasi.

---

# 5. Simulated Payment Method Behaviour

## 5.1 PayPal / Credit Card

Untuk saat ini:

- tidak memanggil provider live
- tidak redirect ke gateway nyata
- sistem hanya mensimulasikan hasil transaksi

Sistem boleh memakai tombol/aksi seperti:

- `Simulate Payment Success`
- atau otomatis menganggap sukses setelah submit flow tertentu

## 5.2 Bank Transfer

Untuk saat ini:

- boleh tetap ada tampilan metode bank transfer
- upload proof boleh disimulasikan / disiapkan UI-nya
- tetapi verifikasi juga belum ke proses bank nyata

---

# 6. Invoice Behaviour in Simulation

## Final Rule

Walaupun pembayaran simulasi, invoice tetap harus dibuat.

Invoice tetap menyimpan:

- target user / pending user
- tier/program
- total amount
- balance
- status

## Status Rule

- jika simulasi dianggap lunas, invoice = `Paid Full`
- jika simulasi cicilan dan masih ada balance, invoice = `Installment`

---

# 7. Payment Activity Behaviour in Simulation

## Final Rule

Payment activity tetap harus dibuat walaupun pembayaran tidak nyata.

Payment activity tetap menyimpan:

- invoice reference
- amount
- selected payment method
- payment type
- status

## Purpose

Ini penting agar:

- struktur bisnis tidak perlu diubah total saat nanti payment live diaktifkan
- simulasi tetap meniru sistem nyata

---

# 8. Simulated Payment Result

## 8.1 Success Simulation

Jika flow simulasi menganggap pembayaran berhasil:

1. payment activity status menjadi success/paid
2. invoice diperbarui
3. anti-limbo flow dijalankan
4. user lanjut ke enrollment/sign up

## 8.2 Failure Simulation

Jika flow simulasi menganggap pembayaran gagal:

1. payment activity status menjadi failed
2. invoice tidak berubah menjadi lunas
3. user tidak lanjut onboarding
4. user tetap bisa diarahkan untuk mencoba lagi

---

# 9. Anti-Limbo in Simulation

## Final Rule

Walaupun pembayaran hanya simulasi, anti-limbo flow tetap harus berjalan.

Jika pembayaran simulasi dinyatakan berhasil:

- sistem membuat akun dasar
- sistem kirim / siapkan continuation flow
- user tetap bisa melanjutkan onboarding jika sempat terputus

---

# 10. Onboarding Continuation

## Final Rule

Setelah simulasi payment success:

- user tetap diarahkan ke enrollment
- lalu sign up
- lalu LMS access

Artinya:
**simulasi payment harus membuka seluruh flow berikutnya seperti sistem nyata.**

---

# 11. Upgrade Flow in Simulation

## Final Rule

Upgrade juga tetap harus bisa disimulasikan.

### Main Rule

1. user klik upgrade
2. sistem hitung nominal prorata
3. sistem buat invoice baru
4. payment upgrade tetap simulasi
5. jika dianggap berhasil, tier user diperbarui

## Important Rule

Walaupun simulasi, upgrade harus tetap:

- membuat invoice baru
- tidak mengedit invoice lama

---

# 12. UI/UX Expectations

Walaupun payment hanya simulasi, UI tetap harus terasa seperti sistem nyata.

## Must Feel Real

- checkout page harus rapi dan lengkap
- payment type dan method tetap tampil
- status success/failure tetap jelas
- alur onboarding setelahnya tetap natural

## Must Be Honest Internally

Di level implementasi, sistem harus diperlakukan sebagai:

- payment simulation
- bukan gateway live

---

# 13. Data and Status Expectations

## What Must Be Real

Walaupun payment-nya simulasi, data berikut tetap harus nyata di database/domain:

- pending registration
- invoice
- payment activity
- onboarding state
- account continuation state

## What Is Not Real Yet

- payment provider external
- transaksi uang sungguhan
- webhook live
- settlement bank/card/paypal

---

# 14. Recommended Behaviour for Initial Implementation

Untuk implementasi tahap awal:

1. pertahankan seluruh screen/payment choice
2. buat invoice dan payment activity seperti normal
3. tentukan hasil payment lewat simulasi internal
4. jika success, lanjut onboarding
5. jika failure, tampilkan flow gagal yang masuk akal

Ini membuat pengembangan:

- domain bisnis bisa dibangun sekarang
- gateway real bisa ditambahkan belakangan

---

# 15. Business Rules Summary

1. Payment flow harus tetap ada.
2. Checkout flow harus tetap lengkap.
3. Invoice tetap harus dibuat.
4. Payment activity tetap harus dibuat.
5. Payment success/failure masih simulasi.
6. Anti-limbo tetap harus berjalan.
7. Onboarding tetap harus berjalan setelah simulated success.
8. Upgrade tetap harus bisa disimulasikan.
9. Invoice lama tidak boleh diedit saat upgrade.
10. Saat nanti gateway live ditambahkan, struktur bisnis utama tidak perlu dirombak besar.

---

# 16. Final Summary

Untuk fase implementasi saat ini:

- sistem tetap memiliki alur checkout dan pembayaran lengkap
- tetapi pembayaran masih **simulasi**
- UI tetap tampil
- invoice dan payment activity tetap dibuat
- success/failure tetap ada
- onboarding tetap lanjut
- upgrade tetap bisa disimulasikan

Dokumen ini menjadi source of truth agar tim implementasi membangun seluruh alur bisnis pembayaran dengan tampilan dan state yang lengkap, sambil menunda integrasi gateway live ke fase berikutnya.
