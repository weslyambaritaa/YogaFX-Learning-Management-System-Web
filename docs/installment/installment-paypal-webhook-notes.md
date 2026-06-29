# Installment PayPal Webhook Notes
# YogaFX LMS

## Purpose

Dokumen ini menjelaskan bagaimana webhook PayPal installment diproses di YogaFX LMS dan apa yang perlu dicek saat troubleshooting.

---

## 1. Route and Verification

Webhook route aktif:

```text
POST /webhooks/paypal
```

Proteksi:
- route tidak memakai CSRF
- signature PayPal diverifikasi lebih dulu
- request dengan signature invalid ditolak `401`

---

## 2. Subscription Event Types

Event subscription yang dikenali backend:
- `BILLING.SUBSCRIPTION.CREATED`
- `BILLING.SUBSCRIPTION.ACTIVATED`
- `BILLING.SUBSCRIPTION.CANCELLED`
- `BILLING.SUBSCRIPTION.SUSPENDED`
- `BILLING.SUBSCRIPTION.EXPIRED`
- `PAYMENT.SALE.COMPLETED`
- `PAYMENT.SALE.DENIED`
- `BILLING.SUBSCRIPTION.PAYMENT.FAILED`

Event order one-time lama tetap diproses oleh controller yang sama, tetapi lewat jalur order/capture existing.

---

## 3. What Must Be Registered in PayPal

Webhook PayPal sandbox atau live harus mengirim event subscription di atas ke route:

```text
/webhooks/paypal
```

Minimal yang wajib untuk installment phase pertama:
- subscription created
- subscription activated
- payment sale completed
- payment failed
- subscription cancelled
- subscription suspended
- subscription expired

Jika event payment success tidak dikirim, onboarding first payment dan pengurangan invoice balance tidak akan sinkron.

---

## 4. Event Log Behavior

Semua subscription webhook masuk dulu ke:
- `payment_subscription_events`

Field penting:
- `provider_event_id`
- `provider_event_type`
- `provider_subscription_id`
- `provider_order_id`
- `provider_capture_id`
- `status`
- `payload`

Lifecycle event log:
- `received`
- `processed`
- `ignored`
- `failed`

Tujuan event log:
- menyimpan payload mentah
- memberi idempotency
- memudahkan audit sandbox/live

---

## 5. Duplicate Event Rule

Duplicate dicek dari `provider_event_id`.

Jika PayPal mengirim event yang sama dua kali:
- sistem tidak membuat row event kedua
- handler business tidak dijalankan dua kali
- response webhook mengembalikan status `duplicate`

Tambahan safety:
- recurring ledger juga memakai `payment_reference` unik
- duplicate capture/sale id tidak boleh mengurangi `invoice.balance_due` dua kali

---

## 6. Unknown Event Rule

Jika event bukan subscription event dan juga tidak punya order reference yang bisa diproses:
- sistem mengembalikan `202 ignored`
- flow existing tidak rusak

Jika event subscription unsupported tetapi lolos log:
- payload tetap tersimpan
- event dapat ditandai `ignored`

---

## 7. Business Mapping

Ringkasan mapping aktif:
- `BILLING.SUBSCRIPTION.CREATED` -> local status `approval_pending`
- `BILLING.SUBSCRIPTION.ACTIVATED` -> local status `active`
- `PAYMENT.SALE.COMPLETED` -> create/update ledger, reduce invoice balance, finalize onboarding bila first payment
- `BILLING.SUBSCRIPTION.PAYMENT.FAILED` -> local status `past_due`, isi `grace_deadline_at`
- `PAYMENT.SALE.DENIED` -> diperlakukan sebagai failure
- `BILLING.SUBSCRIPTION.CANCELLED` -> local status `cancelled`
- `BILLING.SUBSCRIPTION.SUSPENDED` -> local status `suspended`
- `BILLING.SUBSCRIPTION.EXPIRED` -> local status `completed`

---

## 8. Troubleshooting Guide

### Duplicate Status Returned

Artinya event dengan `provider_event_id` yang sama sudah pernah tercatat.

Yang perlu dicek:
- apakah event pertama sudah `processed`
- apakah invoice balance sudah benar
- apakah payment activity dengan reference yang sama sudah ada

### Student Belum Onboarding Setelah Approval

Cek:
- apakah PayPal mengirim `PAYMENT.SALE.COMPLETED`
- apakah `payment_subscription_events` tercatat
- apakah event diproses atau ignored
- apakah `invoice` terkait masih `unpaid`

### Student Tetap Inactive Setelah Bayar Recovery

Cek:
- apakah success event terbaru masuk
- apakah subscription status final `cancelled` atau `suspended`
- apakah metadata subscription mencatat `reactivation_blocked_reason`

### Invoice Balance Salah

Cek:
- duplicate webhook
- duplicate `payment_reference`
- nominal amount dari payload PayPal
- `payments` ledger untuk invoice terkait

---

## 9. Sandbox Test Flow

Alur verifikasi manual yang direkomendasikan:
1. aktifkan package installment
2. jalankan initial checkout installment
3. verifikasi first payment webhook membuka onboarding
4. simulasikan recurring success
5. simulasikan failed payment
6. jalankan command overdue setelah grace lewat
7. simulasikan recovery payment
8. simulasikan final payment
9. kirim duplicate event yang sama
10. jalankan full payment regression

Checklist rinci:
- `docs/installment-sandbox-test-checklist.md`
