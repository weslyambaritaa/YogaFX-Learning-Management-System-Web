# Home Student
# YogaFX LMS

## Purpose

Dokumen ini menjadi source of truth untuk halaman **Home Student** pada YogaFX LMS.

Dokumen ini tidak membahas kode, mockup, atau wireframe. Fokus dokumen ini adalah menjelaskan konteks produk, struktur halaman, prioritas informasi, pengalaman pengguna, dan alasan UX di balik Home Student agar developer, designer, dan AI coding assistant memahami halaman ini sebagai produk LMS yang utuh.

Halaman ini harus dibangun dengan pendekatan **Netflix-style learning platform** yang sudah disepakati:
- bukan dashboard admin
- bukan halaman statistik berat
- bukan portal akademik tradisional
- fokus pada **continue learning**
- fokus pada **discovery konten**
- fokus pada **engagement**
- student harus langsung terdorong untuk melanjutkan pembelajaran

Dokumen ini disusun berdasarkan konteks proyek YogaFX LMS dan PRD, yang menegaskan bahwa student side mencakup dashboard, akses modul dan lesson, assessment, assignment, certification, dan e-book access, dengan akses yang dibedakan berdasarkan tier pengguna. :contentReference[oaicite:0]{index=0}

---

# 1. Product Role of Home Student

## 1.1 What Home Student Is

Home Student adalah **learning hub utama** untuk student setelah login.

Home bukan halaman laporan.  
Home bukan halaman pengaturan akun.  
Home bukan sekadar daftar menu.

Home harus menjadi halaman yang:
- menyambut student
- menunjukkan posisi belajar mereka saat ini
- memberi arah langkah berikutnya
- menampilkan konten yang relevan dengan tier mereka
- menjaga motivasi untuk terus belajar

## 1.2 Why Home Student Matters

Dalam LMS YogaFX, student tidak hanya menonton video. Mereka juga:
- membuka module dan lesson
- mengikuti assessment
- mengerjakan assignment
- menunggu review
- mengejar certificate
- membuka e-book

Karena itu, Home Student berfungsi sebagai:
- titik masuk utama
- pusat navigasi pembelajaran
- alat motivasi progres
- penghubung antar milestone pembelajaran

---

# 2. Main Goal of Home Student

Tujuan utama Home Student adalah:

1. **Bring the student back into motion**
   - setelah login, student harus langsung tahu apa yang harus dilakukan berikutnya

2. **Surface the most relevant learning action**
   - sistem harus menampilkan “lanjutkan belajar” sebelum hal lain

3. **Show progress and momentum**
   - student harus melihat bahwa mereka sedang bergerak menuju hasil nyata

4. **Enable content discovery**
   - student harus bisa melihat module, lesson, e-book, dan resource yang tersedia untuk tier mereka

5. **Expose milestone status**
   - assignment dan certificate harus terlihat sebagai bagian dari perjalanan belajar, bukan tersembunyi di menu lain

---

# 3. Home Student in the Learning Journey

Home Student berada di tengah perjalanan belajar student.

Secara produk, journey student di YogaFX kira-kira seperti ini:

1. Login
2. Masuk ke Home Student
3. Melihat lesson yang sedang berjalan
4. Melanjutkan lesson atau modul aktif
5. Menyelesaikan lesson
6. Mengerjakan assessment jika tersedia
7. Membuka lesson berikutnya jika sudah unlock
8. Mengirim assignment jika tier mengizinkan
9. Menunggu review assignment
10. Menerima certificate jika syarat terpenuhi

Dengan demikian, Home Student harus selalu menjawab tiga pertanyaan ini:

1. **Saya sedang berada di mana sekarang?**
2. **Apa yang harus saya lanjutkan sekarang?**
3. **Apa lagi yang tersedia untuk saya?**

---

# 4. What Must Be Visible Immediately After Login

Ketika student pertama kali login, informasi yang harus langsung terlihat adalah:

1. **Welcome / greeting yang personal**
2. **Tier akses student**
3. **Continue Learning**
4. **Overall progress atau progress utama**
5. **Next recommended action**

Student tidak boleh dipaksa mencari sendiri ke mana mereka harus pergi.  
Home harus langsung memberi arah.

## 4.1 First Screen Priority

Area di atas fold harus berisi:
- personal greeting
- ringkasan posisi belajar
- CTA utama untuk lanjut belajar

Contoh makna produk:
- “Hai, Anda sedang berada di Module X”
- “Lesson terakhir Anda belum selesai”
- “Lanjutkan sekarang”

---

# 5. Most Important Information

Urutan prioritas informasi pada halaman Home Student harus seperti ini:

1. Continue Learning
2. Progress belajar
3. Next recommended step
4. Available modules
5. Assignment / certificate status
6. Ebook dan resource pendukung
7. Secondary discovery

Ini penting karena student datang ke LMS terutama untuk:
- melanjutkan apa yang sedang berjalan
- melihat apa yang belum selesai
- tahu apa langkah berikutnya

Mereka tidak datang untuk membaca angka atau statistik administratif.

---

# 6. Information Hierarchy

Hierarki informasi halaman harus jelas dan bertingkat.

## Tier 1 — Immediate Action
Informasi yang paling atas dan paling besar:
- greeting
- continue learning
- CTA utama

## Tier 2 — Progress and Direction
Informasi berikutnya:
- progress ringkas
- rekomendasi langkah berikutnya
- status belajar utama

## Tier 3 — Discovery
Informasi berikutnya:
- available modules
- lesson yang tersedia
- content lain sesuai tier

## Tier 4 — Supporting Milestones and Resources
Informasi berikutnya:
- assignment
- certificate
- e-books
- resources

---

# 7. Experience to Be Built

Pengalaman pengguna yang harus dibangun pada Home Student adalah:

- **premium**
- **clean**
- **calm**
- **modern**
- **streaming-style**
- **motivating**
- **not academic-admin-like**

Student harus merasa:
- sistem memahami progres mereka
- sistem tahu apa yang perlu mereka lakukan
- platform terasa seperti tempat belajar premium
- belajar terasa berlanjut, bukan terputus-putus
- ada arah yang jelas menuju hasil akhir

Home harus lebih dekat ke:
- Netflix
- MasterClass
- Skillshare discovery layer
- modern streaming learning platform

Dan jauh dari:
- portal kampus
- dashboard admin
- tabel data-heavy
- e-learning tradisional yang kaku

---

# 8. Relationship with Other Student Domains

## 8.1 Module
Home harus menampilkan module yang tersedia berdasarkan tier student. PRD menegaskan bahwa akses konten dibedakan oleh tier Online, MasterClass, dan Starter Kit. :contentReference[oaicite:1]{index=1}

## 8.2 Lesson
Home harus memahami:
- lesson aktif
- lesson yang sudah selesai
- lesson yang belum unlock
- lesson yang bisa dilanjutkan

## 8.3 Assessment
Jika lesson memiliki assessment, Home harus bisa memberi sinyal bahwa:
- assessment tersedia
- assessment siap dikerjakan
- assessment baru bisa dibuka jika syarat lesson terpenuhi

PRD menegaskan bahwa assessment dapat dikerjakan setelah video ditonton minimal 95%. :contentReference[oaicite:2]{index=2}

## 8.4 Assignment
Home harus menampilkan status assignment sesuai tier dan progres student.

## 8.5 Certificate
Home harus menampilkan status certificate:
- tersedia
- belum tersedia
- tidak tersedia untuk tier tersebut

## 8.6 Ebook
Home harus menampilkan resource/e-book yang relevan dengan tier dan konteks belajar student.

---

# 9. Progress Display Strategy

Progress harus ditampilkan dalam bentuk yang mudah dipahami, bukan data mentah.

## 9.1 Overall Progress
Tampilkan progress perjalanan belajar secara keseluruhan, misalnya:
- persentase progress
- jumlah modul yang sudah selesai
- jumlah lesson yang sudah selesai

## 9.2 Contextual Progress
Selain progress global, tampilkan juga progress yang terkait langsung dengan konteks aktif:
- progress module aktif
- progress lesson aktif
- progress menuju milestone berikutnya

## 9.3 Progress as Motivation
Progress bukan hanya informasi, tetapi juga alat motivasi.  
Student harus merasa:
- ada pencapaian
- ada perjalanan
- ada kemajuan yang terlihat

---

# 10. Access Tier Logic on Home

PRD membagi tier sebagai berikut:  
- Online: semua modul, assignment ya, certificate ya  
- MasterClass: semua modul, assignment tidak, certificate ya  
- Starter Kit: sebagian modul, assignment tidak, certificate tidak. :contentReference[oaicite:3]{index=3}

## 10.1 What Home Must Do with Tier
Home harus:
- hanya menampilkan konten yang tersedia untuk tier student
- boleh menampilkan konten terkunci sebagai teaser jika memang diperlukan
- menjelaskan alasan mengapa konten tidak tersedia

## 10.2 Locked Content
Jika module atau lesson terkunci, tampilan harus jelas:
- visually different
- ada label locked
- ada alasan singkat

Contoh alasan:
- unavailable for your tier
- complete previous lesson first

---

# 11. Continue Learning Strategy

Continue Learning adalah komponen terpenting di halaman Home Student.

## 11.1 Why It Must Be Primary
Karena tujuan utama student saat login biasanya adalah:
- melanjutkan lesson
- menyelesaikan modul
- kembali ke aktivitas terakhir

## 11.2 What Continue Learning Should Show
Section ini idealnya menampilkan:
- current module
- current lesson
- progress lesson
- CTA utama untuk lanjut

## 11.3 Continue Learning States

### If student already started something
Tampilkan lesson/modul aktif.

### If student has not started anything
Tampilkan:
- Start Your First Lesson
- atau Start Your Learning Journey

### If student is blocked by an assessment or unlock rule
Tampilkan step berikutnya yang relevan:
- complete video to unlock assessment
- take assessment
- complete previous lesson first

---

# 12. Next Recommended Step

Home harus memberikan **next recommended action** yang paling relevan.

## 12.1 Why This Matters
Student tidak boleh harus menebak langkah berikutnya.

## 12.2 Recommendation Logic
Contoh logika:
- jika ada lesson aktif yang belum selesai → rekomendasikan lanjut lesson
- jika assessment unlock dan belum dikerjakan → rekomendasikan take assessment
- jika semua lesson modul selesai dan assignment tersedia → rekomendasikan submit assignment
- jika certificate ready → rekomendasikan view/download certificate

## 12.3 UX Principle
Satu aksi utama harus lebih menonjol daripada pilihan lain.  
Home harus memberi arah, bukan hanya pilihan.

---

# 13. Available Modules and Locked Modules

## 13.1 Available Modules
Modules yang tersedia harus tampil seperti content catalog / learning catalog.

Yang ditampilkan:
- thumbnail
- title
- short description atau status
- progress singkat
- CTA

## 13.2 Locked Modules
Modules yang terkunci tetap bisa tampil, tetapi:
- visualnya lebih redup / disabled
- ada badge locked
- ada alasan singkat

## 13.3 Why This Matters
Student perlu melihat:
- apa yang tersedia sekarang
- apa yang akan terbuka nanti
- apa yang memang di luar tier mereka

Ini menjaga rasa platform tetap kaya, bukan sempit.

---

# 14. Assignment and Certificate Status

## 14.1 Assignment Status
Status yang harus bisa ditampilkan:
- Not available for your tier
- Not started
- Submitted
- Under review
- Approved
- Rejected

## 14.2 Certificate Status
Status yang harus bisa ditampilkan:
- Not available for your tier
- In progress
- Ready
- Download available

## 14.3 Why These Must Be Visible
Assignment dan certificate adalah milestone besar dalam journey YogaFX.  
Jika disembunyikan terlalu jauh, student kehilangan rasa tujuan.

---

# 15. Ebook and Supporting Resources

## 15.1 Role of Ebook Section
E-book bukan prioritas pertama, tetapi sangat penting sebagai resource pendukung.

## 15.2 What to Show
- title
- cover/thumbnail jika ada
- CTA open/download
- relevansi terhadap tier

## 15.3 UX Principle
Section ini harus mendukung pembelajaran, bukan mengalihkan fokus dari continue learning.

---

# 16. Full Page Structure from Top to Bottom

## Section 1 — Welcome Hero
### Purpose
- personal greeting
- orientation
- emotional entry point

### Content
- greeting with name
- tier badge
- short message
- quick summary of learning position

### Why Needed
Ini membantu student merasa “masuk ke ruang belajar pribadi”, bukan ke sistem dingin.

---

## Section 2 — Continue Learning
### Purpose
- memberi jalur tercepat untuk kembali belajar

### Content
- module aktif
- lesson aktif
- progress lesson
- CTA continue

### Why Needed
Ini adalah anchor utama halaman.

---

## Section 3 — Learning Progress Summary
### Purpose
- menunjukkan momentum dan pencapaian

### Content
- overall progress
- modules completed
- lessons completed
- optional study summary

### Why Needed
Progress visual meningkatkan rasa kemajuan.

---

## Section 4 — Recommended Next Step
### Purpose
- mengurangi friction
- memberi satu arahan jelas

### Content
- next best action
- status singkat
- CTA relevan

### Why Needed
Student tidak perlu bingung harus ke mana.

---

## Section 5 — Available Modules
### Purpose
- discovery konten
- orientasi perjalanan belajar yang lebih luas

### Content
- module cards
- locked / unlocked state
- progress singkat
- CTA

### Why Needed
Ini membangun rasa katalog premium seperti platform streaming learning.

---

## Section 6 — Assignment & Certificate Milestones
### Purpose
- menunjukkan milestone besar

### Content
- assignment status card
- certificate status card

### Why Needed
Milestone besar perlu terlihat jelas agar student termotivasi.

---

## Section 7 — Ebooks & Resources
### Purpose
- memberi support content

### Content
- e-book cards
- open/download action

### Why Needed
Resource pendukung penting, tapi sebaiknya datang setelah learning flow utama.

---

## Section 8 — Explore More / Secondary Discovery
### Purpose
- memperkaya pengalaman
- menjaga engagement

### Content
- additional modules
- related resources
- supplementary discovery

### Why Needed
Home tidak boleh terasa habis setelah satu CTA.

---

# 17. Ideal Component List

Komponen yang sebaiknya ada:

- Welcome banner / hero
- Tier badge
- Continue learning card
- Overall progress indicator
- Next step card
- Module row / module carousel
- Locked state module card
- Assignment status card
- Certificate status card
- Ebook/resource row
- Empty state cards
- CTA buttons yang jelas

---

# 18. Empty States

## 18.1 New Student, No Progress
Tampilkan:
- greeting
- explanation singkat
- CTA mulai modul pertama

## 18.2 No Continue Learning Item Yet
Tampilkan:
- Start your first lesson
- atau Explore your available modules

## 18.3 Tier Has No Assignment / Certificate
Tampilkan state yang sopan:
- not available in your current tier

## 18.4 No Ebook Available
Sembunyikan section atau tampilkan state ringan yang tidak mengganggu

## 18.5 Everything Completed
Tampilkan:
- celebration state
- certificate if available
- explore more resources

---

# 19. Responsive Behaviour

## 19.1 Mobile
- satu kolom
- continue learning tetap di atas
- cards ditumpuk vertikal
- module list bisa horizontal swipe
- CTA tetap jelas dan besar

## 19.2 Tablet
- mulai pakai layout 2 kolom ringan di beberapa section
- card tidak terlalu padat
- progress dan continue learning tetap prioritas

## 19.3 Desktop
- experience lebih immersive
- rows lebih terasa seperti platform streaming
- lebih banyak whitespace
- section lebih jelas terpisah

PRD juga menegaskan bahwa antarmuka harus responsif dan dapat diakses di desktop dan mobile. :contentReference[oaicite:4]{index=4}

---

# 20. UX Reasons for Each Section

## Welcome Hero
Memberi konteks dan personalisasi.

## Continue Learning
Mendorong aksi paling penting dan menjaga momentum.

## Progress Summary
Membuat kemajuan terasa nyata.

## Recommended Next Step
Mengurangi cognitive load.

## Available Modules
Mendukung discovery dan orientasi konten.

## Assignment / Certificate
Menunjukkan milestone besar yang penting.

## Ebooks / Resources
Mendukung pembelajaran lebih dalam.

## Explore More
Menjaga engagement jangka panjang.

---

# 21. Business Context

Secara bisnis, Home Student harus mendukung:
- retensi penggunaan platform
- penyelesaian lesson dan module
- transisi ke assessment
- transisi ke assignment
- penyelesaian perjalanan menuju certificate
- pemanfaatan resource tambahan
- pemahaman student terhadap manfaat tier mereka

Home yang baik akan mengurangi drop-off karena student selalu tahu:
- progres mereka
- langkah berikutnya
- konten yang tersedia
- milestone yang akan datang

---

# 22. Final Product Statement

Home Student YogaFX harus dirancang sebagai **premium streaming-style learning hub** yang langsung mendorong student untuk melanjutkan pembelajaran.

Home harus terasa:
- personal
- jelas
- menenangkan
- modern
- tidak administratif
- tidak seperti dashboard data
- tidak seperti portal kampus

Jika Home Student berhasil dirancang dengan benar, maka student akan selalu bisa menjawab dalam hitungan detik:
1. Saya sedang berada di mana?
2. Apa yang harus saya lakukan sekarang?
3. Konten apa yang tersedia untuk saya?

Itulah fungsi utama Home Student dalam YogaFX LMS.