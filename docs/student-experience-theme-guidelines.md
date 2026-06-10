# Student Experience Theme Guidelines
# YogaFX LMS

## Purpose

Dokumen ini menjadi acuan tema dan bahasa desain utama untuk seluruh **Student Experience** di YogaFX LMS.

Dokumen ini dibuat agar pengembangan student side berikutnya tidak perlu menebak ulang arah visual produk.

Jika ada halaman baru atau refactor baru di sisi student, baca dokumen ini terlebih dahulu sebelum implementasi.

---

## 1. Product Identity

YogaFX pada sisi student **bukan**:
- dashboard admin
- portal akademik tradisional
- aplikasi SaaS enterprise
- sistem CRUD

YogaFX pada sisi student adalah:
- premium online yoga learning platform
- modern wellness learning product
- streaming-style learning experience
- content-first learning environment

Kalimat kerja yang harus diingat:

> Student side harus terasa seperti Netflix untuk pembelajaran yoga.

---

## 2. Primary Inspirations

Inspirasi utama untuk Student Experience:
- Netflix
- MasterClass
- premium streaming platform
- modern wellness website

Karakter yang harus selalu terasa:
- dark
- immersive
- cinematic
- premium
- calm
- modern
- content-led

---

## 3. Visual Direction

### 3.1 Theme

Tema utama student side adalah:
- dark elegant interface
- warm cinematic gradients
- high-contrast white typography
- restrained accent color

Jangan kembali ke:
- white dashboard cards
- pale admin surfaces
- generic SaaS shells

### 3.2 Backgrounds

Gunakan:
- black atau near-black base
- dark brown / warm black variation
- subtle radial highlights
- dark overlays di area visual

Hindari:
- flat putih sebagai background utama
- layout yang terasa seperti panel administrasi

### 3.3 Accent Color

Aksen utama:
- YogaFX red / warm red-orange untuk CTA utama

Fungsi:
- tombol utama
- progress fill utama
- highlight learning action

### 3.4 Supporting Colors

Gunakan:
- putih untuk teks utama
- abu terang / putih redup untuk teks sekunder
- hijau untuk status complete
- abu netral untuk locked / muted state

---

## 4. Layout Principles

### 4.1 Content First

Halaman student harus dibangun dengan fokus pada:
- discovery konten
- learning journey
- progression
- engagement

Bukan fokus pada:
- management
- reporting
- statistics-first UI
- administration workflow

### 4.2 Large Visual Hierarchy

Gunakan hierarki visual besar:
- hero besar
- thumbnails dominan
- section title yang jelas
- CTA utama yang menonjol

Jangan gunakan:
- box-box kecil seragam seperti dashboard widget
- grid statistik ala admin

### 4.3 Vertical Scrolling Experience

Student pages harus terasa seperti content browsing:
- section tersusun vertikal
- ritme scroll nyaman
- setiap section jelas fungsinya
- spacing lega

---

## 5. Navigation Pattern

Navigation student harus konsisten di semua halaman student.

Struktur yang diinginkan:
- kiri: logo / identitas YogaFX
- tengah: menu utama seperti `Home`, `Modules`, `Instant Access`
- kanan: search, quick access buttons, avatar, user menu

Karakter navigation:
- ringan
- premium
- minimalis
- tidak ramai
- tidak menyerupai navbar admin

Quick access pills seperti:
- `Full Standing Dialog`
- `Full Floor Dialog`

diperbolehkan sebagai shortcut penting jika relevan.

---

## 6. Component Language

### 6.1 Prioritized Components

Komponen yang diprioritaskan:
- hero sections
- featured learning banners
- continue learning cards
- module cards
- lesson cards
- course cards
- ebook covers
- progress indicators
- completion states
- glassmorphism utility widgets ringan

### 6.2 Components to Minimize

Kurangi atau hindari:
- generic dashboard cards
- admin widgets
- data-table style layouts
- enterprise SaaS panels
- CRUD-style action surfaces
- statistik berat yang tidak mendorong learning flow

---

## 7. Card Patterns

### 7.1 Module Cards

Module card harus:
- visual-first
- punya thumbnail kuat
- punya status yang cepat dipahami
- terasa seperti premium course catalog

### 7.2 Lesson Cards

Lesson card harus:
- terasa seperti episode selection
- jelas urutannya
- jelas statusnya
- mudah dipilih untuk lanjut belajar

### 7.3 Course Cards

Course card harus:
- besar
- thumbnail-led
- punya hierarchy judul/deskripsi yang kuat
- memberi rasa premium lecture content

### 7.4 Ebook Cards

Ebook card harus:
- terasa seperti digital library shelf
- memakai pendekatan cover-driven
- tidak terlihat seperti asset manager

---

## 8. Typography

Tipografi student side harus:
- modern
- clean
- highly readable
- cocok untuk background gelap

Hierarchy minimum:
- hero title sangat besar dan kuat
- section title medium dan jelas
- card title tegas
- supporting text lebih kecil dan lebih redup

Hindari:
- tipografi kecil seragam di seluruh halaman
- struktur teks yang terasa seperti panel utilitarian

---

## 9. Spacing and Atmosphere

Student side harus punya:
- generous spacing
- breathing room
- section separation yang jelas
- rhythm yang tenang

Jangan membuat halaman student terasa:
- padat
- sempit
- penuh box
- seperti workspace admin

---

## 10. Status and Progress Language

Status penting harus terbaca cepat:
- completed
- active / current
- available
- locked

Progress harus:
- memotivasi
- mudah dipindai
- tampil sebagai learning momentum

Progress jangan dipresentasikan seperti:
- laporan admin
- KPI dashboard
- data operasional

---

## 11. UX Rules

Saat membuka halaman student, user harus cepat memahami:
- saya sedang di mana
- apa yang bisa saya buka
- apa yang sedang aktif
- apa yang sudah selesai
- apa langkah berikutnya

Pengalaman harus:
- sederhana
- cepat
- tenang
- memotivasi

---

## 12. Anti-Patterns

Jangan gunakan pola berikut pada student side:
- admin dashboard cards
- statistic widgets admin style
- table-heavy layouts
- generic shadcn dashboard grids
- CRUD-style UI
- enterprise SaaS appearance
- white-background admin shells sebagai baseline visual

Jika sebuah halaman student terlihat seperti halaman admin, berarti arah desainnya salah.

---

## 13. Consistency Rule

Home Student adalah referensi visual utama untuk student side.

Halaman berikut harus mengikuti bahasa desain yang sama:
- Home
- Modules
- Module Detail
- Lesson
- Courses
- Ebooks
- halaman student lain yang akan ditambahkan nanti

Saat ada konflik visual, prioritaskan:
1. `yogafx-home-page-design.md`
2. `student-module-and-lesson-design.md`
3. dokumen ini sebagai guardrail tema lintas halaman

---

## 14. Final Reminder

Setiap pengembangan student side harus menjaga kesan:
- premium learning platform
- Netflix-style browsing
- MasterClass-style presentation
- modern wellness experience

Jika hasil implementasi terasa seperti sistem administrasi, lakukan refactor sampai kembali ke identitas YogaFX Student Experience.
