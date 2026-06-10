# Home Student Implementation Plan
# YogaFX LMS

## Purpose

Dokumen ini menjadi panduan implementasi bertahap untuk membangun **Home Student** pada YogaFX LMS.

Dokumen ini disusun agar Codex dapat mengerjakan Home Student secara terstruktur, aman, dan bertahap, tanpa langsung membuat semuanya sekaligus.

Tujuan utamanya:
- membagi pekerjaan menjadi 12 tahap implementasi
- memastikan dependency dikerjakan dalam urutan yang benar
- memberi checkpoint validasi di setiap tahap
- menjaga agar implementasi tetap konsisten dengan konteks produk YogaFX LMS

Dokumen ini mengacu pada definisi Home Student sebagai:
- pusat orientasi belajar student
- halaman continue learning
- halaman discovery konten
- halaman milestone assignment dan certificate
- halaman resource dan ebook  
serta tetap mengikuti aturan tier, lesson progression, assessment unlock, assignment, dan certificate yang dijelaskan di PRD. :contentReference[oaicite:0]{index=0}

---

# 1. Implementation Philosophy

Home Student tidak boleh dibangun sekaligus sebagai halaman besar yang penuh section tanpa prioritas.

Home Student harus dibangun dengan pendekatan berikut:
- bangun struktur utama dulu
- pastikan data yang paling penting tersedia dulu
- tampilkan pengalaman yang benar-benar membantu student melanjutkan belajar
- baru tambahkan layer discovery, milestone, dan resource
- selalu prioritaskan continue learning di atas statistik
- tetap jaga agar UI terasa seperti platform belajar premium, bukan dashboard admin

---

# 2. Dependencies Before Starting

Sebelum implementasi dimulai, domain berikut diasumsikan sudah tersedia atau minimal sudah punya fondasi:

- authentication student
- user profile / student identity
- access tier
- modules
- lessons
- lesson progress
- assessments
- assignments
- certificates
- ebooks

Kalau salah satu domain di atas belum stabil, implementasi Home Student harus memakai fallback sementara dan tidak membuat asumsi yang terlalu jauh.

---

# 3. 12 Coding Stages

## Stage 1 — Build Home Student Route and Base Page Shell

### Objective
Membuat fondasi halaman Home Student yang bisa dibuka student setelah login.

### Scope
- route Home Student
- controller / page entry
- basic layout student
- page shell kosong
- auth protection untuk student

### Deliverables
- student bisa membuka Home page
- layout dasar student siap
- halaman belum penuh, tapi struktur dasarnya sudah benar

### Validation
- route bisa diakses student
- role selain student tidak memakai halaman ini
- layout konsisten dengan student side, bukan admin side

---

## Stage 2 — Load Core Student Context

### Objective
Menampilkan konteks utama student yang diperlukan oleh seluruh halaman.

### Scope
- greeting dengan nama student
- access tier student
- status profil minimum jika diperlukan
- ringkasan konteks user yang sedang login

### Deliverables
- Home bisa menampilkan siapa student yang sedang login
- tier student terlihat
- data inti student tersedia untuk section-section berikutnya

### Validation
- nama tampil benar
- tier tampil benar
- jika tier kosong / belum di-assign, ada fallback yang aman

---

## Stage 3 — Implement Continue Learning Engine

### Objective
Membangun bagian paling penting dari Home Student: **Continue Learning**.

### Scope
- cari module aktif / lesson aktif terakhir
- tentukan lesson yang perlu dilanjutkan
- tentukan CTA utama
- fallback jika belum ada progress

### Deliverables
- card / section Continue Learning tampil
- jika student punya progress, sistem menampilkan item yang tepat
- jika student baru, sistem menampilkan start state

### Validation
- student dengan progress melihat lesson terakhir yang benar
- student tanpa progress melihat CTA mulai belajar
- CTA mengarah ke halaman yang tepat

---

## Stage 4 — Implement Overall Progress Summary

### Objective
Menampilkan progress belajar secara ringkas dan memotivasi.

### Scope
- overall progress
- jumlah module selesai
- jumlah lesson selesai
- progress summary yang mudah dipahami

### Deliverables
- section progress summary tampil
- data progress tidak mentah dan tetap mudah dibaca

### Validation
- angka progress sesuai data lesson_progress / module progress
- empty state aman jika progress masih nol
- tampilan tetap ringan dan tidak terasa seperti admin report

---

## Stage 5 — Implement Next Recommended Step Logic

### Objective
Mengarahkan student ke langkah berikutnya tanpa membuat mereka bingung.

### Scope
- rule rekomendasi langkah berikutnya
- prioritization logic
- CTA recommendation card

### Suggested recommendation order
1. continue current lesson
2. take assessment if unlocked
3. continue next lesson if available
4. submit assignment if due and available
5. check certificate if ready
6. explore modules if nothing active

### Deliverables
- satu section “Next Step” yang benar-benar kontekstual
- CTA utama sesuai kondisi student

### Validation
- rekomendasi berubah sesuai kondisi user
- tidak ada rekomendasi yang bertabrakan
- jika tidak ada step aktif, sistem fallback ke discovery

---

## Stage 6 — Implement Available Modules Section

### Objective
Menampilkan module yang tersedia untuk student sesuai tier.

### Scope
- ambil module berdasarkan access tier
- tampilkan unlocked modules
- tampilkan locked modules bila diperlukan
- tampilkan state available vs locked

### Deliverables
- section daftar module tersedia
- module cards / rows sesuai tier
- state locked/unlocked jelas

### Validation
- Online dan MasterClass melihat semua module sesuai aturan PRD
- Starter Kit hanya melihat sebagian module sesuai tier logic
- module yang tidak tersedia tidak tampil salah
- state locked jelas jika dipakai

---

## Stage 7 — Implement Sequential Lesson Awareness

### Objective
Membuat Home Student sadar terhadap aturan progression lesson.

### Scope
- cek lesson sebelumnya selesai atau belum
- cek lesson berikutnya unlock atau belum
- pastikan CTA dan module card tidak menyesatkan
- sinkron dengan aturan workbook / video / assessment unlock

### PRD rules to respect
- workbook bisa menjadi syarat sebelum akses lesson penuh
- assessment unlock setelah video 95%
- lesson berikutnya tidak dapat diakses jika lesson sebelumnya belum selesai. :contentReference[oaicite:1]{index=1}

### Deliverables
- Home tidak hanya menampilkan konten, tetapi juga state aksesnya
- student tahu kenapa sesuatu belum bisa dilanjutkan

### Validation
- next lesson tidak direkomendasikan jika belum unlock
- assessment tidak direkomendasikan jika syarat 95% belum tercapai
- pesan status tidak membingungkan

---

## Stage 8 — Implement Assignment Milestone Section

### Objective
Menampilkan status assignment sebagai milestone besar bagi tier yang relevan.

### Scope
- cek apakah tier student berhak assignment
- tampilkan assignment state
- tampilkan CTA yang sesuai status

### Suggested states
- not available for your tier
- not started
- submitted
- under review
- approved
- rejected

### Deliverables
- section assignment milestone tampil
- hanya muncul secara bermakna
- tidak mengganggu student yang tier-nya tidak punya assignment

### Validation
- Online tier melihat assignment section dengan benar
- MasterClass / Starter Kit melihat status unavailable sesuai rule PRD
- student dengan assignment submission melihat status yang benar

---

## Stage 9 — Implement Certificate Milestone Section

### Objective
Menampilkan perjalanan menuju certificate dan status certificate yang tersedia.

### Scope
- cek hak certificate berdasarkan tier
- cek apakah certificate sudah ready
- tampilkan CTA yang tepat

### Suggested states
- not available for your tier
- in progress
- ready
- download available

### Deliverables
- section certificate milestone tampil
- student tahu status certificate mereka

### Validation
- Online dan MasterClass bisa melihat jalur certificate sesuai aturan PRD
- Starter Kit mendapat state unavailable
- jika certificate tersedia, CTA download / view tampil benar

---

## Stage 10 — Implement Ebook and Supporting Resources Section

### Objective
Menampilkan e-book dan resource pendukung yang relevan.

### Scope
- ambil ebooks berdasarkan tier
- tampilkan resource cards
- tampilkan open/download action

### Deliverables
- section ebook/resource tampil
- resource tidak mengalahkan prioritas continue learning
- resource tetap mudah ditemukan

### Validation
- ebook hanya tampil untuk tier yang sesuai
- jika tidak ada ebook, section memakai empty state ringan atau disembunyikan
- CTA resource berfungsi

---

## Stage 11 — Implement Empty States, Fallback States, and Edge Cases

### Objective
Merapikan semua kondisi yang tidak ideal agar Home tetap terasa matang.

### Scope
- student baru tanpa progress
- tier tanpa assignment/certificate
- tidak ada continue learning
- semua progress selesai
- tidak ada ebook
- tidak ada module yang tersedia
- missing thumbnail / incomplete supporting data

### Deliverables
- semua section punya empty state yang manusiawi
- tidak ada area kosong yang membingungkan
- Home tetap terasa “jadi” walau data belum lengkap

### Validation
- halaman tetap bagus untuk first-time student
- halaman tetap jelas untuk student aktif
- halaman tetap stabil untuk data edge case

---

## Stage 12 — Final Polish, Responsive, and Integration Review

### Objective
Menyatukan semua section menjadi pengalaman Home Student yang utuh.

### Scope
- susun urutan section final
- rapikan responsive mobile / tablet / desktop
- rapikan hierarchy visual
- cek performa query
- cek consistency antar section
- final QA seluruh learning logic

### Final section order
1. Welcome Hero
2. Continue Learning
3. Progress Summary
4. Next Recommended Step
5. Available Modules
6. Assignment & Certificate
7. Ebooks / Resources
8. Secondary Discovery (jika ada)

### Deliverables
- halaman Home Student terasa utuh
- experience terasa seperti premium learning platform
- mobile dan desktop sama-sama nyaman
- data logic konsisten

### Validation
- section order mengikuti prioritas produk
- mobile tidak terasa padat
- desktop terasa seperti streaming-style learning hub
- student langsung terdorong untuk melanjutkan pembelajaran

---

# 4. Suggested Build Order Summary

Urutan implementasi yang paling aman:

1. Route + shell
2. Student context
3. Continue Learning
4. Progress summary
5. Next step logic
6. Modules available
7. Sequential access awareness
8. Assignment
9. Certificate
10. Ebook/resources
11. Empty states
12. Polish + responsive

---

# 5. Rules That Must Not Be Violated

Saat mengerjakan Home Student, Codex tidak boleh melanggar aturan berikut:

1. Home Student bukan dashboard admin
2. Continue Learning harus lebih penting daripada statistik
3. Konten harus mengikuti access tier
4. Lesson progression harus mengikuti dependency lesson sebelumnya
5. Assessment recommendation harus mengikuti unlock logic 95% watch progress
6. Assignment dan certificate harus mengikuti entitlement tier
7. Empty state harus tetap terasa premium, bukan halaman rusak
8. Home harus terasa seperti learning platform modern, bukan portal data

---

# 6. UX Priorities Across All Stages

Urutan prioritas UX yang harus dijaga selama implementasi:

1. Clarity
2. Momentum
3. Relevance
4. Progress visibility
5. Discovery
6. Milestone awareness
7. Calm and premium feeling

---

# 7. Definition of Done

Home Student dianggap selesai jika:

- student langsung tahu harus lanjut ke mana
- continue learning tampil benar
- progress terasa jelas dan memotivasi
- module list sesuai tier
- next recommended step akurat
- assignment/certificate status terlihat jelas
- ebook/resource relevan tampil dengan baik
- empty state tidak membingungkan
- mobile, tablet, dan desktop terasa rapi
- halaman terasa seperti produk LMS premium yang sudah jadi

---

# 8. Final Note for Codex

Kerjakan Home Student **bertahap**, bukan sekaligus.

Setiap tahap harus:
- selesai dengan jelas
- diverifikasi
- baru lanjut ke tahap berikutnya

Jangan lompat ke polish visual sebelum:
- continue learning
- progress
- recommendation logic
- tier filtering
sudah benar.

Home Student adalah halaman yang paling menentukan apakah student merasa terdorong untuk terus belajar. Karena itu, prioritasnya bukan sekadar menampilkan data, tetapi membangun **learning momentum**.