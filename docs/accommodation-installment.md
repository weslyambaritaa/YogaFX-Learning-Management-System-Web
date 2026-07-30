# accommodation-installment.md
# Accommodation Booking — Installment Payment
# YogaFX LMS

## Status Dokumen

Spesifikasi aktif untuk penambahan fitur **cicilan (installment)** pada domain Accommodation Booking yang sudah live (lihat `docs/accomodation.md` untuk domain intinya — dokumen ini adalah **tambahan**, bukan pengganti).

Baca urutan ini sebelum implementasi: `AGENTS.md` → dokumen inti `docs/00`–`06` → `docs/accomodation.md` → dokumen ini.

---

## 1. Ringkasan

Booking akomodasi bisa dibayar dengan dua cara: **pay-full** (sudah ada) atau **installment/cicilan** (fitur baru ini). Cicilan akomodasi **meniru konsep cicilan Package yang sudah ada**, dengan penyesuaian karena booking akomodasi tidak punya "akses berkelanjutan" yang bisa dicabut seperti akses LMS.

Tersedia untuk **siapa saja** (student maupun guest tanpa akun), sesuai keputusan bisnis.

---

## 2. Prinsip Implementasi Wajib

1. **Tiru struktur `InstallmentPlanCalculator`, `PayPalSubscriptionService`, dan `InstallmentWebhookHandler`** yang sudah ada untuk Package. Jangan menulis ulang logika kalkulasi jadwal cicilan atau integrasi PayPal Subscriptions dari nol.
2. **Jangan reuse tabel `PaymentSubscription`/`PaymentSubscriptionEvent` milik LMS.** Model itu terikat erat ke `Invoice`, `PendingRegistration`, `AccessTier` — sama seperti masalah `PayPalService::createOrder()` di awal proyek Accommodation Booking. Buat tabel/model baru yang setara, terikat ke `AccommodationBooking`.
3. **Domain tetap terpisah dari commerce LMS** — sama seperti prinsip di `docs/accomodation.md`.
4. Ikuti seluruh aturan kerja di `AGENTS.md`: satu fase, tervalidasi, baru lanjut.

---

## 3. Keputusan Bisnis (Sudah Final — Jangan Diubah Tanpa Konfirmasi)

1. **Tidak ada penalti otomatis terhadap booking.** Booking tetap berstatus `confirmed` begitu cicilan pertama berhasil dibayar. Kegagalan cicilan berikutnya **tidak pernah** membatalkan booking secara otomatis, berapa pun kali gagalnya.
2. **`payment_failure_threshold = 5`** pada konfigurasi PayPal Billing Plan — PayPal akan mencoba menagih hingga 5 kali gagal berturut-turut sebelum otomatis men-suspend subscription.
3. **Setiap kali satu attempt penagihan gagal** (bukan cuma sekali di akhir), sistem mengirim email ke **guest/student DAN admin** — meniru persis pola `InstallmentWebhookHandler::handlePaymentFailed()` milik Package, yang trigger langsung dari webhook `PAYMENT.SALE.DENIED` / `BILLING.SUBSCRIPTION.PAYMENT.FAILED`, bukan dari job terjadwal.
4. **Toggle installment ada di level Accommodation (hotel)**, bukan Room Type. Satu pengaturan `installment_enabled` + mode (fixed/flexible) berlaku untuk seluruh room type di hotel itu.
5. **Eligibility per-booking**: minimal harus ada **2 cicilan** yang muat (cicilan pertama + minimal 1 cicilan bulanan berikutnya) berdasarkan jarak dari **tanggal booking dibuat** ke **tanggal check-in**. Kalau tidak cukup (hotel eligible tapi jarak kurang dari 1 bulan), opsi installment **otomatis disembunyikan** dari tampilan booking — hanya pay-full yang muncul, walau `installment_enabled = true` di level hotel.
6. **Kalau admin cancel booking** yang masih dalam masa cicilan aktif (subscription belum selesai/di-cancel), subscription PayPal-nya **harus ikut di-cancel** — supaya guest tidak terus tertagih untuk booking yang sudah dibatalkan.

---

## 4. Data Layer (ERD Tambahan)

### 4.1 Kolom baru di tabel `accommodations`

| Kolom | Tipe | Keterangan |
|---|---|---|
| installment_enabled | boolean, default false | Toggle di level hotel |
| installment_count_mode | string, nullable | `fixed` atau `flexible`, meniru `Package::installment_count_mode` |
| installment_fixed_count | integer, nullable | Dipakai kalau mode `fixed`; jumlah cicilan tetap |

Tidak ada kolom `installment_deadline_date` — deadline selalu dinamis = `check_in_date` booking yang bersangkutan.

### 4.2 Tabel baru `accommodation_payment_subscriptions`

Setara `PaymentSubscription`, tapi terikat ke `AccommodationBooking`:

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| accommodation_booking_id | FK → accommodation_bookings | |
| paypal_plan_id | string | ID Billing Plan di PayPal |
| paypal_subscription_id | string, index | ID Subscription di PayPal |
| status | string | meniru konstanta `PaymentSubscription` (`active`, `past_due`, `suspended`, `cancelled`, `completed`) |
| total_installment_count | integer | |
| billing_day | integer | |
| monthly_amount | decimal(12,2) | |
| next_due_at | timestamp, nullable | |
| grace_deadline_at | timestamp, nullable | |
| last_payment_failed_at | timestamp, nullable | |
| last_synced_at | timestamp, nullable | |
| created_at / updated_at | timestamps | |

### 4.3 Tabel baru `accommodation_payment_subscription_events`

Setara `PaymentSubscriptionEvent` — log mentah tiap webhook event untuk idempotency & audit, sama persis strukturnya (`event_id` unik dari PayPal untuk mencegah pemrosesan dobel, `processed_at`, payload mentah).

---

## 5. Kalkulasi Jadwal Cicilan

**Tiru `InstallmentPlanCalculator` hampir verbatim**, dengan substitusi:

| Di Package | Di Accommodation |
|---|---|
| `isEligible()`: `$package->installment_enabled && access_tier_id !== null` | `$accommodation->installment_enabled` |
| `resolveDeadlineDate()`: dari `$package->installment_deadline_date` | dari `$booking->check_in_date` (per booking, bukan per konfigurasi) |
| Checkout date | Tanggal booking dibuat (hari ini) |
| `Package::MIN_INSTALLMENT_COUNT = 2` | **Sama, pakai ulang konstanta 2** |
| `Package::MAX_PROVIDER_INSTALLMENT_COUNT = 15` | Sama |
| `usesNumberBasedInstallment()` vs date-based | Accommodation **hanya pakai jalur date-based** (deadline = check-in), tidak perlu jalur number-based Package karena tidak relevan di sini — deadline selalu ada (check-in date pasti terisi) |

Alur yang sama seperti `calculateDateBasedPlan()`:
1. Hitung `buildMonthlyRecurringDueDatesUntilDeadline()` dari hari ini sampai `check_in_date`.
2. `maximumInstallmentCount = min(1 + jumlah tanggal recurring, MAX_PROVIDER_INSTALLMENT_COUNT)`.
3. Kalau `maximumInstallmentCount < MIN_INSTALLMENT_COUNT (2)` → **installment tidak eligible untuk booking ini**, sembunyikan opsi di frontend, backend juga menolak kalau tetap dipaksa lewat API langsung.
4. Kalau eligible dan mode `fixed` → pakai `installment_fixed_count` dari accommodation (clamp ke maximum kalau ternyata jumlah tetap itu melebihi yang muat).
5. Kalau mode `flexible` → user boleh pilih jumlah cicilan antara `MIN_INSTALLMENT_COUNT` dan `maximumInstallmentCount`.

Buat service baru `App\Services\Accommodations\AccommodationInstallmentPlanCalculator` yang strukturnya menyalin method-method privat `InstallmentPlanCalculator` (`buildMonthlyRecurringDueDatesUntilDeadline`, `resolveFlexibleInstallmentCount`, `resolveFixedInstallmentCount`, `safeMonthlyBillingDate`, dll) — boleh disalin karena logikanya generik (tidak menyentuh field spesifik Package), hanya titik masuk (`isEligible`, `resolveDeadlineDate`) yang beda sumber datanya.

---

## 6. Integrasi PayPal Subscriptions

Tiru `PayPalSubscriptionService` — buat method setara untuk domain akomodasi (boleh generalisasi method yang sudah ada seperti pola refactor `createOrderFromReference` di `PayPalService` sebelumnya: extract logic jadi lebih generik, entry point lama untuk Package tidak berubah perilaku).

**Konfigurasi Billing Plan untuk akomodasi:**
```php
'payment_preferences' => [
    'auto_bill_outstanding' => true,
    'payment_failure_threshold' => 5,   // sesuai keputusan §3.2
    ...
],
```

**Koreksi setelah verifikasi kode (Fase 1)**: cicilan pertama **bukan** diproses via order/capture terpisah. Package yang sudah ada memakai mekanisme `payment_preferences.setup_fee` pada Billing Plan itu sendiri (`setup_fee.value = first_payment_amount`, `setup_fee_failure_action: CANCEL`) — PayPal otomatis menagih cicilan pertama saat guest approve subscription, lalu hasilnya dikonfirmasi lewat webhook `BILLING.SUBSCRIPTION.ACTIVATED` (`handleActivated()` + `extractActivationLastPayment()` membaca `resource.billing_info.last_payment`). Akomodasi harus meniru mekanisme ini persis — **tidak** reuse `AccommodationCheckoutService::createOrder()`/`captureOrder()` untuk cicilan pertama installment.

**Perlu didiskusikan sebelum Fase 3 dimulai**: mekanisme *hold* 30 menit di `RoomAvailabilityService::reserveWithLock()` dirancang mengasumsikan capture terjadi cepat setelah order dibuat (flow pay-full). Untuk installment, booking dibuat lalu guest approve *subscription* (bukan order), dan konfirmasi baru datang lewat webhook `BILLING.SUBSCRIPTION.ACTIVATED` yang waktunya bisa berbeda. Perlu diverifikasi apakah hold 30 menit masih cukup, atau perlu penyesuaian durasi/mekanisme khusus untuk booking installment.

---

## 7. Webhook Handler

Buat `App\Services\Accommodations\AccommodationInstallmentWebhookHandler`, struktur menyalin `InstallmentWebhookHandler` persis, event yang ditangani sama:

| Event PayPal | Aksi |
|---|---|
| `BILLING.SUBSCRIPTION.PAYMENT.FAILED`, `PAYMENT.SALE.DENIED` | Set status `past_due`, set `grace_deadline_at`, **kirim email `ACCOMMODATION_INSTALLMENT_PAYMENT_FAILED`** (ke guest + admin) — **booking TIDAK disentuh**, tetap `confirmed` |
| `PAYMENT.SALE.COMPLETED` (per cycle) | Update `next_due_at`, kirim email cycle sukses (opsional, lihat §9) |
| Subscription selesai semua cycle | Set status `completed` |
| `BILLING.SUBSCRIPTION.CANCELLED` | Set status `cancelled` |

**Tidak ada job terjadwal seperti `OverdueInstallmentService`/`SyncOverdueInstallmentsCommand` untuk akomodasi** — karena tidak ada akun untuk dinonaktifkan. Cukup notifikasi per-event dari webhook (§3.3).

---

## 8. Cancel Booking oleh Admin — Perilaku Baru

Perluas `AccommodationCheckoutService::cancelOrder()` / admin cancel yang sudah ada di Fase 5: kalau booking punya `accommodation_payment_subscriptions` dengan status aktif (`active`/`past_due`), panggil `PayPalSubscriptionService`-equivalent `cancelSubscription()` **sebelum** atau **bersamaan** dengan mengubah status booking jadi `cancelled`. Ini mencegah guest terus tertagih untuk booking yang sudah dibatalkan admin.

---

## 9. Email Notification — Type Baru

Tambah ke `EmailNotificationTypeRegistry`:
- `ACCOMMODATION_INSTALLMENT_PAYMENT_FAILED` — varian user + admin, wajib (§3.3).

Placeholder minimal: nama tamu, nama hotel, room type, nomor cicilan ke berapa dari berapa, jumlah yang gagal ditagih, tanggal jatuh tempo berikutnya (kalau PayPal masih akan coba lagi), booking number.

**Opsional, perlu konfirmasi Anda sebelum dikerjakan** (di luar yang eksplisit diminta): apakah perlu juga `ACCOMMODATION_INSTALLMENT_PAYMENT_SUCCESS` (tiap cicilan berhasil) dan `ACCOMMODATION_INSTALLMENT_PAYMENT_COMPLETED` (semua cicilan lunas), untuk paritas dengan Package? Kalau tidak diminta, **jangan dibangun** — cukup `PAYMENT_FAILED` saja sesuai yang diminta.

---

## 10. Admin & Public UI

**Admin — form Accommodation** (edit existing form dari Fase 2): tambah field `installment_enabled` (toggle), `installment_count_mode` (select fixed/flexible), `installment_fixed_count` (muncul kondisional kalau mode fixed) — tiru pola field installment di form Package.

**Public — halaman `/stay/{slug}`**: setelah tanggal check-in & check-out dipilih dan room type dipilih, kalau `accommodation.installment_enabled` DAN hasil kalkulasi eligibility (§5) menunjukkan `maximumInstallmentCount >= 2`, tampilkan opsi "Bayar Penuh" vs "Cicilan" dengan preview jadwal cicilan (tanggal + jumlah tiap cicilan). Kalau tidak eligible, opsi cicilan **tidak muncul sama sekali** — tidak ditampilkan disabled, langsung tidak ada, supaya tidak membingungkan.

---

## 11. Yang Eksplisit OUT OF SCOPE

- Tidak ada pembatalan booking otomatis karena gagal bayar cicilan (§3.1).
- Tidak ada mekanisme deaktivasi akun (tidak relevan, booking bisa dari guest).
- Tidak ada job terjadwal grace-period seperti Package (§7).
- Email `PAYMENT_SUCCESS`/`PAYMENT_COMPLETED` per-cycle — hanya dikerjakan kalau dikonfirmasi diperlukan (§9).
- `payment_failure_threshold` per-hotel yang bisa diatur admin — pakai konstanta global `5`, tidak dibuat configurable per Accommodation kecuali diminta kemudian.

---

## 12. Urutan Implementasi

**Fase 1 — Data Layer**: migration kolom baru di `accommodations`, tabel `accommodation_payment_subscriptions` + `accommodation_payment_subscription_events`, model + relasi.

**Fase 2 — Kalkulasi Eligibility**: `AccommodationInstallmentPlanCalculator`, unit test (termasuk kasus tepat di batas 2 cicilan, kasus kurang dari 1 bulan, kasus mode fixed melebihi maksimum yang muat).

**Fase 3 — Integrasi PayPal Subscription**: adaptasi `PayPalSubscriptionService`, buat Plan (dengan `setup_fee` = cicilan pertama) + Subscription untuk booking installment. Cicilan pertama dikonfirmasi lewat webhook `BILLING.SUBSCRIPTION.ACTIVATED` (lihat koreksi §6), bukan capture biasa. Selesaikan dulu pertanyaan soal durasi hold sebelum menulis kode.

**Fase 4 — Webhook Handler + Email**: `AccommodationInstallmentWebhookHandler`, entry `ACCOMMODATION_INSTALLMENT_PAYMENT_FAILED`, wiring cancel-booking-cancels-subscription (§8).

**Fase 5 — Admin & Public UI**: field baru di form Accommodation, opsi cicilan di halaman `/stay/{slug}`.

Setiap fase: selesai → STOP → laporkan → verifikasi kode baris demi baris → baru lanjut. Sama persis disiplin yang dipakai di seluruh domain Accommodation Booking sebelumnya.