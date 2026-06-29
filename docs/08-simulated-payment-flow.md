# Simulated Payment Flow
# YogaFX LMS

## Purpose

Dokumen ini menjelaskan **mock payment compatibility** yang masih ada di proyek saat ini.

Dokumen ini bukan lagi source of truth utama untuk jalur payment normal user, karena jalur utama sekarang sudah mengarah ke **PayPal nyata**. Namun mode simulasi / mock masih dipertahankan untuk local, staging, dan testing non-production.

---

## 1. Current Position of Mock Payment

Implementasi aktif saat ini:
- jalur utama user: `paypal`
- jalur testing non-production: `mock`

Artinya:
- payment flow **bukan simulasi-only** lagi
- mock tetap ada sebagai compatibility layer
- mock tidak boleh menjadi asumsi utama untuk dokumentasi payment keseluruhan

---

## 2. Scope

Dokumen ini hanya mengatur:
- availability mode `mock`
- perilaku invoice/payment saat `mock` dipakai
- hubungan mock dengan onboarding initial payment
- hubungan mock dengan upgrade payment

Dokumen ini tidak mengatur:
- arsitektur PayPal utama
- webhook PayPal
- frontend PayPal checkout normal

---

## 3. Core Rule

### Final Rule

Jika `payment_method = mock`:
- backend tidak mengirim user ke PayPal
- sistem tetap membuat `invoice`
- sistem tetap membuat `payment activity`
- `PaymentFinalizerService` tetap dipakai
- efek bisnis setelah success harus sama seperti jalur PayPal sukses

Jadi mock hanya mengganti provider interaction, bukan mengganti business flow.

---

## 4. Availability Rule

### Current Implementation

Mode `mock` saat ini:
- hanya tersedia bila **bukan production**
- hanya muncul di UI jika `config('app.enable_mock_payment_ui', false)` aktif

### Final Rule

Mock harus:
- bisa dipakai untuk local/dev/testing
- ditolak oleh backend di production
- dianggap sebagai tool internal, bukan metode payment publik normal

---

## 5. Initial Checkout Mock Flow

### Main Flow

1. User membuka checkout page YogaFX.
2. User mengisi data billing yang dibutuhkan.
3. User memilih `payment_type`.
4. User menjalankan mock payment.
5. Sistem membuat:
   - `invoice`
   - `payment activity`
6. Sistem langsung memanggil `PaymentFinalizerService::finalizeSuccessfulPayment(...)`.
7. Jika invoice adalah `initial`, sistem:
   - menandai `pending_registration` sebagai `payment_success`
   - membuat atau menghubungkan user
   - membuat `onboarding_state`
   - mengirim continuation email bila perlu
8. User diarahkan ke payment success continuation flow.

---

## 6. Upgrade Mock Flow

### Main Flow

1. Student membuka halaman upgrade tier.
2. Sistem menghitung `amount_due`.
3. Student menjalankan mock payment.
4. Sistem membuat:
   - invoice upgrade baru
   - payment activity baru
5. Sistem langsung memfinalisasi success lewat `PaymentFinalizerService`.
6. Tier student diperbarui ke tier target.
7. Basis invoice lama yang relevan dapat ditandai `upgraded`.
8. User diarahkan ke halaman sukses upgrade.

---

## 7. Data Expectations

Walaupun mock dipakai, data berikut tetap nyata:
- `pending_registrations`
- `invoices`
- `payment_activities`
- `onboarding_states`
- user assignment ke tier

Mock tidak boleh membuat “shortcut palsu” yang melewati domain-domain tersebut.

---

## 8. Status Expectations

Saat mock sukses:
- `payment_activity.status = success`
- `invoice.status = paid_full` atau `installment`, sesuai `balance_due`

Saat mock diblok atau gagal validasi:
- jangan ubah invoice/payment menjadi sukses

Catatan:
- pada implementasi sekarang mock dipakai sebagai success path lokal
- flow cancel/gagal utama lebih relevan di jalur PayPal nyata

---

## 9. Relationship to Current PayPal Architecture

Mock mode harus mengikuti arsitektur yang sama sebisa mungkin:
- invoice number tetap dibuat saat insert
- payment activity tetap dibuat saat user menekan aksi bayar
- final business effect tetap dipusatkan di finalizer

Perbedaan utamanya hanya:
- tidak ada create order ke PayPal
- tidak ada capture order ke PayPal
- tidak ada webhook provider

---

## 10. Final Summary

Mode `mock` masih aktif, tetapi sekarang statusnya adalah:
- tool testing internal
- non-production compatibility path
- bukan lagi source of truth utama alur payment user normal

Jika dokumentasi lain membahas payment umum, prioritaskan dokumen PayPal dan payment architecture yang sudah disinkronkan dengan implementasi saat ini.
