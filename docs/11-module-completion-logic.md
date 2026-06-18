# Module Completion Logic

# YogaFX LMS

## Purpose

Dokumen ini menjadi source of truth untuk logika completion modul di YogaFX LMS.

Dokumen ini dibuat karena modul tidak boleh lagi dianggap completed dengan satu rule global. Completion modul harus bergantung pada isi modul tersebut.

---

# 1. Core Principle

## Final Rule

Completion modul harus ditentukan berdasarkan isi utama modul.

Modul yang berbeda bisa memiliki rule completion yang berbeda.

Jangan terapkan satu rule global untuk semua modul.

---

# 2. Priority Order

Gunakan urutan prioritas berikut untuk menentukan rule completion modul:

1. Jika modul memiliki **lesson**
2. Jika modul tidak memiliki lesson, tetapi memiliki **assignment**
3. Jika modul tidak memiliki lesson, tetapi termasuk **certificate**
4. Jika modul tidak memiliki lesson, tetapi termasuk **ebook / video lecturer**

Rule ini dipakai untuk menentukan completion utama modul.

---

# 3. Rule A — Module Has Lesson

## Final Rule

Jika modul memiliki lesson, maka modul dianggap **completed** jika student telah menyelesaikan video lesson minimal **95%**.

## Meaning

- lesson/video progress menjadi penentu completion utama
- jika belum 95%, modul belum done
- rule ini memiliki prioritas tertinggi

---

# 4. Rule B — Module Has No Lesson but Has Assignment

## Final Rule

Jika modul tidak memiliki lesson, tetapi memiliki assignment, maka modul dianggap **completed** jika student sudah **mengumpulkan assignment** yang ada.

## Meaning

- hanya membuka modul tidak cukup
- completion baru terjadi setelah assignment submission

---

# 5. Rule C — Module Has No Lesson but Is Certificate Module

## Final Rule

Jika modul tidak memiliki lesson, tetapi termasuk certificate module, maka modul dianggap **completed** jika student sudah **download certificate** yang tersedia.

## Meaning

- generate certificate oleh admin tidak otomatis membuat modul completed
- completion terjadi saat student benar-benar download certificate
- event download certificate harus tercatat

---

# 6. Rule D — Module Has No Lesson but Includes Ebook / Video Lecturer

## Final Rule

Jika modul tidak memiliki lesson, tetapi termasuk:

- ebook
- video lecturer

maka modul dianggap **completed** jika student **membuka modul itu minimal satu kali**.

## Meaning

- cukup ada jejak akses/open event
- tidak perlu rule 95% watch seperti lesson
- tidak perlu assignment submission
- tidak perlu certificate download

---

# 7. Mutual Exclusivity

## Final Rule

Rule completion modul harus dipilih berdasarkan konteks utama modul, sesuai urutan prioritas.

Jangan campur semua rule sekaligus untuk satu modul kecuali nanti ada keputusan baru.

Contoh:

- jika modul punya lesson dan juga ebook, maka tetap gunakan rule lesson 95%
- jika modul tidak punya lesson tetapi punya assignment dan ebook, maka gunakan rule assignment submitted

---

# 8. Required Tracking Direction

Agar rule ini bisa berjalan, sistem harus mampu melacak minimal:

## Lesson Module

- watch progress video lesson
- completion threshold 95%

## Assignment Module

- assignment submission state

## Certificate Module

- certificate download event

## Ebook / Video Lecturer Module

- module open/access event minimal sekali

---

# 9. Completion Event Summary

## Jika modul punya lesson

Completed jika:

- lesson/video progress >= 95%

## Jika modul tidak punya lesson, tetapi punya assignment

Completed jika:

- assignment submitted

## Jika modul tidak punya lesson, tetapi certificate module

Completed jika:

- certificate downloaded

## Jika modul tidak punya lesson, tetapi ebook/video lecturer module

Completed jika:

- module opened at least once

---

# 10. Important Boundary

Dokumen ini hanya mengatur:

- logika completion modul

Dokumen ini tidak mengatur:

- scoring assessment
- certificate generation logic
- lesson media architecture
- student session time
- payment

---

# 11. Final Summary

Completion modul di YogaFX LMS sekarang harus mengikuti isi modul, bukan satu rule global.

Rule prioritas:

1. Lesson -> 95% watch progress
2. Assignment -> submitted
3. Certificate -> downloaded
4. Ebook / Video Lecturer -> opened once

Dokumen ini menjadi source of truth untuk logika completion modul.
