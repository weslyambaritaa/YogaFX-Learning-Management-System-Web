# Integrated User Journey

# Scoreboard to LMS Access

# YogaFX LMS

## Purpose

Dokumen ini menjadi source of truth untuk alur sistem terintegrasi dari:

- calon murid mengisi scoreboard
- masuk ke checkout
- melalui proses pembayaran
- lanjut ke onboarding
- membuat akun
- masuk ke LMS
- hingga skenario upgrade program

Dokumen ini menjelaskan:

- urutan proses bisnis
- status utama tiap fase
- relasi antarfase
- business rules
- dependency antar domain

Dokumen ini tidak membahas implementasi teknis detail seperti migration, endpoint, atau struktur folder.

---

# 1. High-Level Flow

Sistem YogaFX LMS memiliki alur besar sebagai berikut:

1. **Scoreboard / Lead Registration**
2. **Pending Registration**
3. **Signed Checkout Link**
4. **Checkout**
5. **Invoice & Payment Activity**
6. **Payment Success / Verification**
7. **Enrollment**
8. **Account Sign Up**
9. **LMS Access**
10. **Upgrade / Upsell**

---

# 2. Phase 1 — Scoreboard / Lead Registration

## Purpose

Menangkap calon murid baru dan menghubungkan mereka ke program/tier yang dipilih.

## Main Flow

1. Calon murid membuka halaman Scoreboard.
2. Calon murid mengisi data dasar:
    - name
    - email
    - phone
    - informasi dasar lain
3. Calon murid memilih program/tier yang ingin diikuti.
4. Sistem menyimpan data tersebut sebagai **pending registration**.
5. Sistem mengirim email otomatis berisi:
    - ucapan konfirmasi
    - **signed payment link** yang aman

## Business Rules

1. Scoreboard adalah titik masuk utama untuk lead acquisition.
2. Program/tier yang dipilih di sini menjadi dasar alur checkout berikutnya.
3. Data awal belum menjadi akun LMS final.
4. Link pembayaran harus aman dan tidak boleh mudah dimanipulasi dari URL.

---

# 3. Phase 2 — Pending Registration

## Purpose

Menyimpan data sementara calon murid sebelum proses pembayaran dan onboarding selesai.

## Main Flow

1. Setelah Scoreboard disubmit, sistem membuat record pending registration.
2. Record ini menyimpan:
    - identity dasar calon murid
    - program/tier yang dipilih
    - status registrasi awal
3. Record ini menjadi dasar untuk membuat signed checkout link.

## Business Rules

1. Pending registration bukan akun LMS final.
2. Pending registration harus bisa dipakai untuk meneruskan alur walaupun calon murid belum menyelesaikan pembayaran saat itu juga.
3. Pending registration tetap terhubung ke tier/program yang dipilih.

---

# 4. Phase 3 — Signed Checkout Link

## Purpose

Mengarahkan calon murid ke halaman checkout yang aman dan sesuai dengan tier/program yang dipilih.

## Main Flow

1. Sistem membuat signed URL berdasarkan:
    - ID pending registration
    - slug / identifier program
    - informasi aman lain yang diperlukan
2. Signed URL dikirim melalui email.
3. Calon murid membuka signed URL tersebut dan masuk ke checkout.

## Business Rules

1. Signed URL harus meminimalkan kemungkinan manipulasi nominal atau program.
2. Signed URL hanya berlaku untuk calon murid dan program yang sesuai.
3. Signed URL harus dianggap sebagai continuation link, bukan link publik bebas.

---

# 5. Phase 4 — Checkout

## Purpose

Menjadi halaman transaksi untuk melanjutkan pembelian program/tier.

## Main Flow

1. Calon murid membuka checkout dari signed URL.
2. Sistem menampilkan form checkout yang sudah autofill:
    - first name
    - last name
    - email
    - mobile phone
    - country
    - amount
3. Calon murid memilih tipe pembayaran:
    - Pay in Full
    - Pay in 4 Installments
4. Calon murid memilih metode pembayaran:
    - PayPal / Credit Card
    - Bank Transfer
5. Sistem menyiapkan proses billing.

## Business Rules

1. Amount harus sesuai dengan program yang dipilih.
2. Tipe pembayaran dan metode pembayaran adalah dua keputusan yang berbeda.
3. Checkout harus tetap terkait ke calon murid yang benar.
4. Nilai tier/program tidak boleh bebas diubah saat checkout.

---

# 6. Phase 5 — Invoice & Payment Activity

## Purpose

Membentuk struktur pencatatan tagihan dan aktivitas pembayaran.

## 6.1 Invoice

Invoice adalah dokumen penagihan induk.

### Main Role

Invoice menyimpan:

- calon user / user reference
- tier/program
- total amount
- balance
- invoice status

### Business Rules

1. Satu transaksi pembelian program harus memiliki invoice.
2. Invoice menyimpan status kewajiban total, bukan hanya satu event pembayaran.
3. Invoice lama tidak boleh ditimpa untuk skenario upgrade.

## 6.2 Payment Activity

Payment Activity adalah riwayat transaksi pembayaran individual.

### Main Role

Payment Activity menyimpan:

- invoice terkait
- amount yang sedang dibayarkan
- payment method
- payment status
- payment proof jika metode manual

### Business Rules

1. Payment Activity adalah event transaksi.
2. Payment Activity harus dipisahkan dari invoice utama.
3. Satu invoice bisa memiliki lebih dari satu payment activity, terutama pada cicilan.

---

# 7. Phase 6 — Payment Success / Verification

## Purpose

Menentukan apakah pembayaran dianggap berhasil dan bagaimana status invoice diperbarui.

## Main Flow

1. Sistem menerima hasil/verifikasi pembayaran.
2. Sistem memperbarui payment activity terkait.
3. Sistem menghitung sisa balance invoice.
4. Status invoice diperbarui:
    - `Paid Full` jika balance = 0
    - `Installment` jika balance masih ada

## Business Rules

1. Pembayaran penuh mengubah invoice ke status lunas.
2. Cicilan yang berhasil tetapi masih menyisakan balance tidak boleh dianggap lunas.
3. Payment success adalah trigger penting untuk onboarding berikutnya.

---

# 8. Phase 7 — Anti-Limbo Flow

## Purpose

Mencegah calon murid hilang di tengah proses setelah pembayaran berhasil.

## Main Flow

1. Begitu pembayaran dinyatakan berhasil, sistem membuat akun dasar tanpa password final.
2. Sistem mengirim email kedua berisi:
    - status pembayaran berhasil
    - link untuk melanjutkan pendaftaran
3. Jika calon murid terputus koneksi sebelum onboarding selesai, mereka tetap bisa lanjut melalui email itu.

## Business Rules

1. Sistem harus menghindari kondisi “sudah bayar tetapi akun belum selesai dibuat”.
2. Anti-limbo flow wajib ada untuk menjaga continuity.
3. Akun dasar ini belum berarti onboarding dan sign up selesai.

---

# 9. Phase 8 — Enrollment

## Purpose

Melengkapi biodata dan status pendaftaran resmi murid.

## Main Flow

1. Setelah pembayaran berhasil, murid membuka langkah berikutnya.
2. Murid mengisi formulir enrollment/biodata lengkap.
3. Sistem menyimpan data profil yang lebih lengkap.
4. Status enrollment berubah menjadi `Complete`.

## Business Rules

1. Enrollment adalah tahap setelah payment success.
2. Enrollment bukan sekadar identitas awal scoreboard.
3. Enrollment complete menandakan data onboarding utama sudah selesai.

---

# 10. Phase 9 — Sign Up / Password Creation

## Purpose

Membuat kredensial login final untuk akun murid.

## Main Flow

1. Setelah enrollment selesai, murid diarahkan ke form sign up.
2. Nama dan email sudah terisi otomatis.
3. Murid membuat password.
4. Sistem menyimpan password final.
5. Akun siap digunakan untuk login normal.

## Business Rules

1. Sign up adalah tahap pembuatan kredensial final.
2. Setelah tahap ini, user tidak perlu OTP lagi untuk login rutin.
3. Nama/email yang terisi boleh tetap bisa dikoreksi jika diperlukan.

---

# 11. Phase 10 — LMS Access

## Purpose

Memberi murid akses ke dashboard LMS sesuai hak akses program/tier.

## Main Flow

1. Setelah password berhasil dibuat, murid diarahkan ke dashboard LMS.
2. Pada kunjungan berikutnya, murid login normal dengan:
    - email
    - password
3. Konten LMS dibuka sesuai tier/program yang valid.

## Business Rules

1. Akses konten harus mengikuti tier/program yang dibeli.
2. Tidak semua murid melihat konten yang sama.
3. Hak akses harus berasal dari status purchase/tier yang sah.

---

# 12. Phase 11 — Upgrade / Upsell

## Purpose

Mendukung kenaikan program/tier tanpa merusak histori transaksi sebelumnya.

## Main Flow

1. Murid membuka dashboard.
2. Murid menekan tombol upgrade ke program lebih tinggi.
3. Sistem menghitung nilai prorata:
    - harga target program
    - dikurangi nominal yang sudah pernah dibayar
4. Sistem membuat **invoice baru** khusus untuk upgrade.
5. Murid membayar sisa tagihan upgrade.
6. Setelah berhasil, hak akses murid diperbarui ke tier baru.

## Business Rules

1. Upgrade tidak mengedit invoice lama.
2. Upgrade selalu membuat invoice baru.
3. Nominal yang sudah pernah dibayar harus diperhitungkan.
4. Hak akses baru hanya aktif setelah transaksi upgrade dianggap berhasil.

---

# 13. Main Business Entities

Domain besar yang tercakup dalam alur ini:

1. Scoreboard
2. Pending Registration
3. Signed Checkout Link
4. Checkout
5. Invoice
6. Payment Activity
7. Enrollment
8. User Account
9. Access Tier / Program
10. LMS Content Access
11. Upgrade Transaction

---

# 14. Core Business Rules Summary

1. Scoreboard adalah pintu masuk lead.
2. Pending registration menyimpan data calon murid sementara.
3. Checkout harus dibuka lewat signed URL yang aman.
4. Invoice dan payment activity adalah dua domain berbeda.
5. Payment success memicu anti-limbo flow.
6. Enrollment dan sign up adalah dua fase onboarding yang berbeda.
7. Login rutin setelah onboarding cukup email + password.
8. Konten LMS harus mengikuti tier/program yang valid.
9. Upgrade selalu membuat invoice baru.
10. Prorata upgrade mengurangi nominal yang sudah pernah dibayar.

---

# 15. Definition of Success

Seorang calon murid dianggap berhasil melewati alur jika:

1. mengisi scoreboard
2. masuk checkout
3. pembayaran berhasil / tervalidasi
4. enrollment selesai
5. password dibuat
6. berhasil masuk LMS
7. mendapatkan akses konten sesuai tier/program

---

# 16. Final Notes

Dokumen ini adalah gambaran utuh alur bisnis terintegrasi.

Dokumen ini harus dipakai sebagai acuan utama ketika nanti:

- membuat domain checkout
- membuat domain billing
- membuat onboarding
- menghubungkan tier ke LMS access
- mengembangkan upgrade flow

Dokumen ini tidak dimaksudkan untuk menjelaskan implementasi pembayaran real-time secara teknis detail.
