# PayPal Payment Architecture
# YogaFX LMS

## Purpose

Dokumen ini menjadi source of truth untuk arsitektur PayPal yang aktif saat ini di YogaFX LMS.

Dokumen ini mencakup:
- struktur invoice dan payment activity
- checkout flow berbasis halaman YogaFX sendiri
- PayPal order creation and capture
- cancel flow
- success flow
- webhook fallback
- mock compatibility

---

## 1. Core Principle

Implementasi aktif saat ini memakai:
- halaman checkout YogaFX sendiri
- PayPal REST API v2 di backend
- PayPal JavaScript SDK di frontend checkout
- service pattern di Laravel
- webhook verification untuk fallback

Ini berarti checkout saat ini **bukan full redirect-only architecture**.

---

## 2. Active Domain Structure

### Invoice

Invoice tetap menjadi tagihan induk dengan field aktif:
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

### Payment Activity

Payment event individual disimpan di tabel `payment_activities` melalui model `Payment`, dengan field aktif:
- `invoice_id`
- `payment_method`
- `payment_type`
- `amount_paid`
- `currency_code`
- `status`
- `payment_reference`
- `notes`

---

## 3. Final Enum Lock

### Invoice Type
- `initial`
- `upgrade`

### Invoice Status
- `unpaid`
- `installment`
- `paid_full`
- `upgraded`

### Payment Status
- `pending`
- `success`
- `failed`
- `cancelled`

### Payment Method
- `paypal`
- `mock`

Catatan:
- `bank_transfer` tidak menjadi metode aktif saat ini

### Payment Type
- `pay_full`
- `installment`

---

## 4. Current Checkout Strategy

### Final Rule

Checkout tetap berlangsung di halaman YogaFX sendiri.

Frontend checkout aktif saat ini:
- memuat PayPal JS SDK
- merender PayPal Buttons
- membuat order lewat backend YogaFX
- menangkap approved order lewat backend YogaFX

### Important Note

Implementasi checkout aktif saat ini **belum memakai card fields aktif** di frontend public checkout yang ada sekarang. Jalur utama yang hidup adalah PayPal Buttons dengan order create/capture flow.

---

## 5. Backend Create Order Flow

### Main Flow

1. User menekan aksi bayar di checkout.
2. Backend membuat:
   - invoice
   - payment activity
3. Backend memanggil `PayPalService::createOrder(...)`.
4. Backend menyimpan `payment_reference` dengan PayPal `order_id`.
5. Backend mengembalikan:
   - `order_id`
   - `capture_url`
   - `cancel_url`

### Important Rule

Invoice dan payment activity dibuat saat aksi bayar dimulai, bukan hanya ketika checkout dibuka.

---

## 6. On-Page Capture Flow

### Final Rule

Flow utama saat ini terjadi di halaman checkout YogaFX:
- PayPal approves order
- frontend memanggil `capture_url`
- backend menangkap order via `PayPalService::captureOrder(...)`
- backend memfinalisasi payment melalui `PaymentFinalizerService`
- backend mengembalikan `redirect_url`
- frontend mengarahkan user ke langkah berikutnya

Ini adalah flow utama yang aktif sekarang.

---

## 7. Success URL Flow

### Final Rule

Success URL PayPal tetap ada dan aktif sebagai jalur lanjutan / fallback ketika user kembali lewat PayPal route:
- `/paypal/checkout/{invoice}/success`

Controller aktif:
- mencocokkan token order dengan payment activity
- memanggil capture jika payment belum sukses
- memanggil finalizer
- mengarahkan:
  - ke onboarding payment success untuk invoice `initial`
  - ke upgrade success untuk invoice `upgrade`

---

## 8. Cancel Flow

### Final Rule

Cancel URL PayPal tetap aktif:
- `/paypal/checkout/{invoice}/cancel`

Saat cancel:
- payment activity pending diubah menjadi `cancelled`
- invoice tetap tidak dianggap lunas
- user diarahkan kembali:
  - ke checkout initial jika invoice `initial`
  - ke halaman upgrade jika invoice `upgrade`

---

## 9. Webhook Fallback

### Final Rule

Webhook PayPal tetap aktif sebagai fallback processor:
- route: `/webhooks/paypal`
- signature verification wajib aktif

Webhook saat ini:
- memverifikasi signature lewat `PayPalService::verifyWebhookSignature(...)`
- mengekstrak `order_id`
- memuat payment activity berdasarkan `payment_reference`
- capture order jika event `CHECKOUT.ORDER.APPROVED`
- memanggil finalizer

Jadi webhook bukan jalur utama UI, tetapi jalur pemulihan dan sinkronisasi penting.

---

## 10. PayPal Service Boundary

`PayPalService` saat ini bertanggung jawab untuk:
- generate client token
- create order
- capture order
- verify webhook signature
- extract order reference dari webhook

`PayPalService` tidak memegang efek bisnis LMS seperti:
- create user
- assign tier
- create onboarding state
- mark invoice upgraded

Efek tersebut tetap berada di `PaymentFinalizerService`.

---

## 11. Mock Compatibility

### Final Rule

Walaupun PayPal adalah jalur utama aktif, mode `mock` tetap hidup untuk non-production.

Jika method = `mock`:
- tidak ada create order PayPal
- backend langsung memfinalisasi success
- struktur invoice/payment/onboarding tetap dipakai

---

## 12. Bank Transfer Status

### Current State

`bank_transfer` bukan metode aktif pada arsitektur saat ini.

Backend secara eksplisit menolak bank transfer pada phase payment aktif sekarang.

Dokumen lama yang menggambarkan bank transfer sebagai opsi jalan aktif tidak lagi sesuai dengan implementasi saat ini.

---

## 13. Final Summary

Arsitektur PayPal aktif saat ini adalah:
- checkout YogaFX sendiri
- PayPal JS SDK di frontend
- order create/capture lewat backend
- success URL dan cancel URL tetap aktif
- webhook verification aktif sebagai fallback
- final business effects dipusatkan di `PaymentFinalizerService`
- mock tetap tersedia untuk non-production

Ini adalah kondisi implementasi yang benar saat ini.
