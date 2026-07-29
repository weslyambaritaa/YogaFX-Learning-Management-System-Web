# accommodation-booking.md
# Accommodation Booking Domain
# YogaFX LMS

## Status Dokumen

Dokumen ini adalah **spesifikasi aktif** untuk domain baru: Accommodation Booking.
Domain ini disetujui masuk scope produk dan menjadi source of truth implementasi.

Baca dokumen ini setelah dokumen inti (`docs/00` sampai `docs/06`) sesuai aturan `AGENTS.md`.

---

## 1. Ringkasan Produk

YogaFX ingin menjual akomodasi (hotel) kepada student dan publik umum.

Konsep:
- satu **Accommodation** = satu hotel (contoh: title `Djabu`, slug `djabu`)
- setiap accommodation punya beberapa **Room Type** (contoh: Deluxe, Suite) dengan harga per malam dan jumlah total kamar
- pengunjung membuka public link `/stay/{slug}`, mengisi form booking (nama, email, phone, room type, check-in, check-out), lalu membayar via PayPal **di halaman yang sama**
- booking sah hanya setelah pembayaran PayPal berhasil
- ketersediaan kamar dihitung **per rentang tanggal**, bukan counter stok manual

Aksesibilitas link:
- link public, bisa diakses **tanpa akun LMS**
- jika pengunjung sedang **login sebagai student**, form nama/email/phone **auto-fill** dari profil student (`first_name` + `last_name`, `email`, `whatsapp`)
- auto-fill adalah prefill server-side lewat props Inertia (`auth()->user()`), **bukan webhook**

---

## 2. Prinsip Implementasi Wajib

1. **Tiru pola domain Package, jangan menciptakan pola baru.**
   Struktur controller, form request, halaman Inertia admin, dan alur PayPal mengikuti pola yang sudah hidup di domain Package/Checkout.
2. **Pakai ulang `PayPalService` yang sudah ada** untuk create order dan capture order. Jangan menulis integrasi PayPal baru dari nol.
3. **Domain terpisah dari commerce LMS.**
   Booking TIDAK memakai tabel `invoices`, `payments`, `pending_registrations`, `onboarding_states`, dan TIDAK menyentuh access tier / enrollment / signup. Tidak ada pembuatan akun dari booking.
4. **Email notification mengikuti registry & pola child menu Email yang sudah ada.**
   Baca `EmailNotificationController`, registry notification types, dan `EmailNotificationService` sebagai referensi sebelum implementasi.
5. Ikuti seluruh aturan `AGENTS.md`: baca dokumentasi dulu, implementasi per layer (data → backend → frontend → integrasi), berhenti di batas domain.

---

## 3. Data Layer (ERD)

### 3.1 `accommodations`

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| title | string | contoh: `Djabu` |
| slug | string, unique | contoh: `djabu`; dipakai di `/stay/{slug}` |
| description | text, nullable | |
| image | string, nullable | path thumbnail; upload maks 10 MB mengikuti constraint umum |
| currency_code | string(3) | satu mata uang per hotel; semua room type mengikuti |
| is_active | boolean, default true | hotel non-aktif tidak bisa diakses public |
| created_at / updated_at | timestamps | |

### 3.2 `accommodation_room_types`

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| accommodation_id | FK → accommodations, cascade on delete dilarang jika ada booking aktif (lihat 6.6) | |
| title | string | contoh: `Deluxe Room` |
| price | decimal(12,2) | harga **per malam**, mata uang mengikuti accommodation |
| total_rooms | integer, min 1 | jumlah fisik kamar; ketersediaan DIHITUNG, bukan disimpan |
| is_active | boolean, default true | |
| sort_order | integer | otomatis, mengikuti pola sort_order konten lain |
| created_at / updated_at | timestamps | |

### 3.3 `accommodation_bookings`

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| booking_number | string, unique | format readable, contoh `BOOK-2026-000001`; tiru pola `InvoiceNumberService` dengan service sendiri |
| accommodation_id | FK → accommodations | |
| accommodation_room_type_id | FK → accommodation_room_types | |
| user_id | FK → users, nullable | terisi hanya jika pemesan sedang login sebagai student saat booking |
| guest_name | string | |
| guest_email | string | |
| guest_phone | string | |
| check_in_date | date | |
| check_out_date | date | harus > check_in_date |
| nights | integer | dihitung server-side: selisih hari |
| price_per_night | decimal(12,2) | **snapshot** harga saat booking; perubahan harga room type tidak mengubah booking lama |
| total_amount | decimal(12,2) | `price_per_night × nights`, dihitung server-side |
| currency_code | string(3) | snapshot dari accommodation |
| status | string | `pending_payment` \| `confirmed` \| `cancelled` \| `expired` |
| paypal_order_id | string, nullable, index | |
| hold_expires_at | timestamp, nullable | batas hold 30 menit untuk status `pending_payment` |
| paid_at | timestamp, nullable | |
| cancelled_at | timestamp, nullable | |
| created_at / updated_at | timestamps | |

Index yang wajib ada untuk query ketersediaan:
`(accommodation_room_type_id, status, check_in_date, check_out_date)`.

Model Eloquent: `Accommodation`, `AccommodationRoomType`, `AccommodationBooking` dengan konstanta status di model (tiru gaya konstanta di `Invoice.php`).

---

## 4. Aturan Ketersediaan Kamar (Core Business Rule)

Ketersediaan **tidak pernah disimpan sebagai angka stok**. Selalu dihitung:

```
terpakai(room_type, in, out) =
    COUNT booking WHERE accommodation_room_type_id = room_type
        AND status IN ('confirmed', 'pending_payment' yang hold_expires_at > now())
        AND check_in_date < out
        AND check_out_date > in

tersedia = total_rooms − terpakai
```

Catatan aturan overlap:
- pakai konvensi hotel standar **[check_in, check_out)** — tanggal check-out TIDAK dihitung menginap. Booking A checkout 5 Agustus dan booking B check-in 5 Agustus **tidak overlap**.
- booking `cancelled` dan `expired` tidak pernah dihitung.
- booking `pending_payment` yang `hold_expires_at` sudah lewat diperlakukan tidak dihitung dalam query (filter di query, jangan bergantung pada scheduler untuk kebenaran hitungan).

Logika ini dipusatkan di satu service, contoh: `App\Services\Accommodations\RoomAvailabilityService`, dan dipakai oleh public page, proses create order, dan proses capture. Jangan menduplikasi query overlap di banyak tempat.

### 4.1 Hold 30 Menit (Anti Race Condition)

1. Saat pengunjung menekan bayar, backend memvalidasi ketersediaan lalu membuat booking `pending_payment` dengan `hold_expires_at = now + 30 menit`, dalam **satu transaction database dengan lock** (`SELECT ... FOR UPDATE` pada baris room type, atau `lockForUpdate()` di Eloquent) supaya dua request bersamaan tidak lolos berdua di kamar terakhir.
2. Booking `pending_payment` aktif ikut dihitung sebagai kamar terpakai.
3. Capture PayPal sukses → status `confirmed`, isi `paid_at`.
4. Hold kadaluarsa tanpa pembayaran → booking dianggap hangus. Scheduler ringan boleh menandai `expired` untuk kebersihan data, tapi kebenaran perhitungan TIDAK boleh bergantung pada scheduler (lihat filter query di atas).
5. Validasi ketersediaan **diulang saat capture** sebagai lapisan kedua. Jika ternyata tidak tersedia (kasus ekstrem), jangan konfirmasi booking; kembalikan error yang jelas ke frontend dan catat di log.

---

## 5. Public Flow — `/stay/{slug}`

### 5.1 Routes Public

```
GET  /stay/{accommodation:slug}                     → halaman booking public
POST /stay/{accommodation:slug}/availability        → cek ketersediaan (room type + rentang tanggal) untuk UI
POST /stay/{accommodation:slug}/orders              → validasi + buat booking pending_payment + create PayPal order
POST /stay/{accommodation:slug}/orders/{booking}/capture → capture PayPal + konfirmasi booking
POST /stay/{accommodation:slug}/orders/{booking}/cancel  → batalkan attempt (booking → expired/cancelled attempt)
GET  /stay/{accommodation:slug}/bookings/{booking}/success → halaman konfirmasi sukses
```

Catatan routes:
- prefix `/stay` WAJIB, karena route catch-all `/{packageSlug}` di bagian bawah `routes/web.php` akan menelan path satu-segmen. Daftarkan routes `/stay/...` SEBELUM catch-all package.
- endpoint order/capture menyimpan referensi `paypal_order_id` di booking; halaman success dilindungi agar hanya menampilkan data booking terkait (signed URL atau token acak di URL, tiru pola proteksi yang dipakai flow checkout yang ada).

### 5.2 Halaman Booking (Inertia Page Public)

Satu halaman berisi:
- info hotel (title, description, image)
- pilihan room type (title, harga per malam, indikasi ketersediaan untuk tanggal terpilih)
- date range picker check-in / check-out
- ringkasan harga: `harga per malam × jumlah malam = total` (hitungan final tetap server-side; angka frontend hanya display)
- form data pemesan: nama, email, phone
- tombol PayPal (pay full saja — **tidak ada installment** untuk booking)

Auto-fill:
- controller mengirim prop `prefill` berisi `{name, email, phone}` jika `auth()->user()` ada dan berrole student; selain itu `null`
- field tetap bisa diedit user meski ter-prefill

Gunakan `PublicFlowLayout` yang sudah ada agar konsisten dengan halaman public lain.

### 5.3 Flow Pembayaran

```
User pilih room type + tanggal → isi data → klik PayPal
→ POST /orders: validasi ketersediaan (dengan lock) → buat booking pending_payment (hold 30 menit)
  → create PayPal order via PayPalService → return order id ke frontend
→ User approve di PayPal popup
→ POST /capture: capture via PayPalService → re-validasi ketersediaan
  → sukses: booking confirmed, paid_at diisi, kirim email konfirmasi
→ redirect ke halaman success
```

Gagal/cancel di tengah: booking dibiarkan `pending_payment` sampai hold habis, atau langsung ditandai `expired` bila user menekan cancel.

---

## 6. Admin Side

### 6.1 Penempatan IA

Menu sidebar baru: **`Accommodations`** (satu parent), berisi:
- daftar accommodation (index)
- halaman room types per accommodation
- halaman **Bookings** (semua booking lintas hotel)

Ikuti pola layout admin, tabel, search, filter, dan pagination yang dipakai domain Packages/Students.

### 6.2 Routes Admin (prefix `admin`, middleware `role:admin,super_admin`)

```
GET    /admin/accommodations                        index
GET    /admin/accommodations/create                 create
POST   /admin/accommodations                        store
GET    /admin/accommodations/{accommodation}/edit   edit
PATCH  /admin/accommodations/{accommodation}        update
DELETE /admin/accommodations/{accommodation}        destroy

GET    /admin/accommodations/{accommodation}/room-types            index
GET    /admin/accommodations/{accommodation}/room-types/create     create
POST   /admin/accommodations/{accommodation}/room-types            store
GET    /admin/accommodations/{accommodation}/room-types/{roomType}/edit  edit
PATCH  /admin/accommodations/{accommodation}/room-types/{roomType} update
DELETE /admin/accommodations/{accommodation}/room-types/{roomType} destroy

GET    /admin/accommodation-bookings                index (filter: accommodation, status, rentang tanggal; search: nama/email/booking number)
GET    /admin/accommodation-bookings/{booking}      detail
POST   /admin/accommodation-bookings/{booking}/cancel   cancel booking
```

### 6.3 Accommodation CRUD
- field sesuai ERD; slug divalidasi unique dan hanya `[a-z0-9-]`
- upload image mengikuti pola upload thumbnail package (maks 10 MB)
- copy-link button di index untuk menyalin URL public `/stay/{slug}`

### 6.4 Room Type CRUD
- field sesuai ERD
- `total_rooms` boleh diubah admin kapan saja; jika diturunkan di bawah jumlah kamar terpakai pada suatu periode, booking lama TIDAK dibatalkan otomatis — ketersediaan periode itu saja yang menjadi 0/negatif dan ditampilkan penuh
- perubahan `price` hanya berlaku untuk booking baru (booking lama pakai snapshot)

### 6.5 Bookings Admin
- index menampilkan: booking number, hotel, room type, nama tamu, tanggal in/out, nights, total, status, paid_at
- detail menampilkan seluruh data booking
- **Cancel**: mengubah status `confirmed` → `cancelled`, mengisi `cancelled_at`. Kamar otomatis tersedia lagi karena booking cancelled tidak dihitung. Refund PayPal berada DI LUAR scope — diproses manual oleh admin di PayPal dashboard; tampilkan catatan ini di dialog konfirmasi cancel.

### 6.6 Aturan Delete
- delete room type ditolak jika masih punya booking `confirmed`/`pending_payment` aktif (tiru pola penolakan delete access tier yang masih dipakai)
- delete accommodation ditolak jika masih punya room type dengan booking aktif

---

## 7. Email Notification

Tambahkan notification type baru ke registry email yang sudah ada:

- `accommodation_booking_confirmed` — dikirim ke `guest_email` saat booking berubah menjadi `confirmed`

Wajib mengikuti pola child menu Email yang sudah hidup (referensi: `EmailNotificationController`, registry types, template management, upload media, send test, email logs):
- template dapat diedit admin dari child menu Email baru: **Accommodation**
- placeholder minimal yang tersedia untuk template: nama tamu, nama hotel, room type, check-in, check-out, nights, total + currency, booking number
- pengiriman dicatat ke email logs seperti tipe lain
- send test berperilaku sama dengan tipe lain

Email cancel oleh admin: **out of scope fase ini** (boleh dicatat sebagai future work).

---

## 8. Yang Secara Eksplisit OUT OF SCOPE

Jangan diimplementasikan diam-diam:
- installment/subscription untuk booking (pay full saja)
- refund PayPal otomatis
- pembuatan akun LMS dari booking
- keterkaitan booking dengan access tier, invoice LMS, payments LMS, onboarding
- kalender ketersediaan visual yang kompleks (cukup indikasi tersedia/tidak untuk tanggal terpilih)
- multi-kamar dalam satu booking (satu booking = satu kamar; jika butuh 2 kamar, booking dua kali)
- halaman list booking untuk student di sisi student LMS
- diskon, kupon, pajak, biaya tambahan

---

## 9. Urutan Implementasi (Modular)

Kerjakan bertahap, satu fase selesai dan tervalidasi sebelum lanjut:

**Fase 1 — Data Layer**
migrations + models + konstanta status + `RoomAvailabilityService` + unit test logika overlap & hold.

**Fase 2 — Admin CRUD**
Accommodations CRUD → Room Types CRUD → menu sidebar. Belum menyentuh booking.

**Fase 3 — Public Booking Page (tanpa pembayaran dulu)**
halaman `/stay/{slug}`, availability endpoint, prefill student, kalkulasi harga display.

**Fase 4 — PayPal Integration**
create order + hold + capture + re-validasi + halaman success + scheduler expired ringan.

**Fase 5 — Admin Bookings + Email**
bookings index/detail/cancel + notification type `accommodation_booking_confirmed` + child menu Email.

Setiap fase diakhiri laporan: apa yang diimplementasikan, dokumen yang diikuti, apa yang tetap out of scope (sesuai `AGENTS.md` Step 6).

---

## 10. Definition of Done Domain Ini

1. Public bisa booking dan membayar dari `/stay/{slug}` tanpa akun.
2. Student login mendapat auto-fill nama/email/phone.
3. Ketersediaan per-tanggal benar: overlap [in, out), hold dihitung, cancelled/expired tidak dihitung, checkout membebaskan tanggal.
4. Dua request bersamaan tidak bisa mengambil kamar terakhir yang sama (lock teruji).
5. Total harga selalu dihitung server-side: `price_per_night × nights`.
6. Booking confirmed memicu email konfirmasi yang templatenya bisa dikelola admin.
7. Admin bisa CRUD hotel & room type, melihat & cancel booking, dan delete-protection berlaku.
8. Tidak ada perubahan apa pun pada domain invoice/payment/onboarding/access-tier LMS.