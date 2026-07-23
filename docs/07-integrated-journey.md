# Integrated User Journey
# Scoreboard to LMS Access
# YogaFX LMS

## Purpose

Dokumen ini menjadi source of truth untuk alur terintegrasi dari:
- calon murid masuk lewat scoreboard / product entry
- checkout dan payment
- onboarding enrollment
- signup completion
- login ke LMS
- hingga upgrade tier

Dokumen ini fokus pada urutan bisnis end-to-end, bukan detail teknis per controller.

---

## 1. High-Level Flow

Alur besar aktif saat ini:
1. Scoreboard / Lead Registration
2. Pending Registration
3. Signed Checkout Link
4. Checkout on YogaFX
5. Invoice & Payment Activity
6. Payment Success / Verification
7. Onboarding Enrollment
8. Signup Completion
9. LMS Access
10. Upgrade

---

## 2. Scoreboard / Lead Registration

### Main Flow

1. Calon murid membuka scoreboard atau public product entry.
2. Calon murid mengisi data dasar.
3. Calon murid memilih tier/program.
4. Sistem membuat `pending_registration`.
5. Sistem menyiapkan signed continuation ke checkout.

### Important Rule

Calon murid belum menjadi akun LMS final pada tahap ini.

---

## 3. Signed Checkout Continuation

### Main Flow

1. Sistem membuat signed checkout URL untuk kombinasi pending registration dan tier.
2. User membuka signed URL tersebut.
3. Sistem menampilkan checkout yang sesuai dengan tier terkait.

### Important Rule

Signed URL dipakai untuk menjaga continuation flow tetap aman dan tidak bebas dimanipulasi.

---

## 4. Checkout

### Main Flow

1. User membuka checkout YogaFX.
2. Sistem menampilkan billing identity, address, amount, dan payment type.
3. User memilih:
   - `pay_full`
   - `installment`
4. User memproses payment lewat metode yang aktif.

### Current Payment Method

Metode aktif saat ini:
- `paypal`
- `mock` non-production bila diaktifkan

Bank transfer belum menjadi flow aktif saat ini.

---

## 5. Invoice & Payment Activity

### Main Flow

1. Saat aksi bayar benar-benar dimulai, backend membuat invoice.
2. Backend juga membuat payment activity.
3. Payment activity terhubung ke invoice yang sama.

### Important Rule

Invoice dan payment activity tidak dibuat hanya karena user membuka checkout.

---

## 6. Payment Success / Verification

### Main Flow

1. Sistem menerima approval / capture result payment.
2. Payment activity diperbarui.
3. Balance invoice dihitung ulang.
4. Invoice menjadi:
   - `paid_full` bila lunas
   - `installment` bila masih ada sisa
5. Final business effect diproses lewat finalizer.

### Important Rule

Baik jalur success normal, success URL fallback, maupun webhook fallback harus berakhir pada efek bisnis yang konsisten.

---

## 7. Onboarding Continuation

### Main Flow

1. Setelah initial payment sukses, sistem membuat atau menghubungkan akun dasar student.
2. Sistem membuat `onboarding_state`.
3. User diarahkan ke payment success continuation.
4. User melanjutkan ke enrollment.

### Important Rule

Initial payment success tidak langsung berarti onboarding selesai.

---

## 8. Enrollment

### Main Flow

1. User membuka enrollment form.
2. User melengkapi biodata lebih lengkap.
3. Sistem menyimpan profile user.
4. `onboarding_state` berubah ke tahap signup completion.

---

## 9. Signup Completion

### Main Flow

1. User membuka halaman signup onboarding.
2. User membuat password.
3. Sistem mengaktifkan akun student.
4. Pending registration ditandai completed.
5. User diarahkan ke login.

---

## 10. LMS Access

### Main Flow

1. User login ke LMS.
2. User melewati email OTP verification.
3. Jika student belum lengkap profile-nya, user dipaksa ke profile edit.
4. Jika student aktif dan profile lengkap, user masuk ke Home.

---

## 11. Upgrade

### Main Flow

1. Student membuka profile dan melihat opsi upgrade.
2. Student memilih tier target yang level-nya lebih tinggi.
3. Sistem menghitung amount due berdasarkan basis invoice relevan terakhir.
4. Sistem membuat invoice upgrade baru.
5. Student menyelesaikan payment upgrade.
6. Jika sukses, tier student diperbarui.

### Important Rule

Upgrade tidak menimpa invoice lama; ia membuat invoice baru yang berdiri sendiri.

---

## 12. Core Business Rules Summary

1. Scoreboard adalah pintu masuk lead.
2. Pending registration menyimpan calon murid sebelum akun final siap.
3. Checkout dibuka lewat signed continuation yang aman.
4. Invoice dan payment activity adalah dua domain berbeda.
5. Payment success memicu onboarding continuation untuk initial payment.
6. Enrollment dan signup completion adalah dua tahap berbeda.
7. Login rutin web saat ini memakai email + password + OTP verification.
8. Upgrade selalu membuat invoice baru.
9. Tier hierarchy dikontrol oleh `access_tiers.level`.
10. Mock hanya alat non-production, bukan jalur payment utama user normal.

---

## 13. Final Summary

Journey aktif YogaFX saat ini adalah:
- lead masuk
- checkout di YogaFX
- payment berhasil
- onboarding enrollment
- signup completion
- login ke LMS
- lalu upgrade bila user naik tier

Dokumen ini harus dibaca bersama dokumen payment dan PayPal yang lebih teknis saat mengerjakan domain commerce atau onboarding.
