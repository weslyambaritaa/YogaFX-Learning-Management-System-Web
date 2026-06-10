# YogaFX LMS - Student Module & Lesson Design Specification

> Dokumen ini merupakan acuan desain resmi untuk halaman Module Student dan Lesson Detail pada YogaFX LMS.
>
> Seluruh implementasi harus mengikuti filosofi desain YogaFX yang mengutamakan pengalaman belajar premium dengan pendekatan visual seperti Netflix, MasterClass, dan modern streaming platform.

---

# MODULE STUDENT PAGE

## Tujuan Halaman

Module Student Page berfungsi sebagai pusat navigasi seluruh modul pembelajaran yang tersedia dalam kursus YogaFX.

Halaman ini muncul ketika student memilih menu **Modules** pada navigation bar.

Student harus dapat langsung memahami:

* jumlah modul yang tersedia
* modul yang sudah selesai
* modul yang sedang aktif
* modul yang masih terkunci
* urutan pembelajaran yang harus diikuti

Halaman ini bukan dashboard akademik dan bukan halaman administrasi. Fokus utamanya adalah pengalaman eksplorasi konten pembelajaran.

---

## Design Philosophy

Inspirasi utama:

* Netflix
* MasterClass
* Premium Learning Platform

Karakteristik:

* clean
* premium
* minimalis
* fokus pada konten
* dark theme

User harus merasa sedang menjelajahi katalog pembelajaran premium, bukan sistem LMS tradisional.

---

## Layout Structure

Layout halaman:

Navbar
↓
Page Title
↓
Module Grid
↓
More Modules

Halaman menggunakan vertical scrolling.

Background menggunakan warna hitam dominan agar konsisten dengan Home Page.

---

## Navigation Bar

Navigation harus identik dengan halaman Home.

Komponen:

### Left Section

* YogaFX Logo

### Center Section

* Home
* Modules
* Instant Access

Pada halaman ini menu Modules berada dalam status aktif.

### Quick Access Buttons

* Full Standing Dialog
* Full Floor Dialog

Menggunakan tombol berbentuk pill dengan border putih.

### Right Section

* Search Icon
* User Avatar
* User Name
* Profile Dropdown

---

## Module Grid

Modul ditampilkan dalam bentuk grid.

Desktop:

2 Columns

Contoh:

Module 1 | Module 2

Module 3 | Module 4

Module 5 | Module 6

Module 7 | Module 8

Tablet:

1-2 Columns

Mobile:

1 Column

---

## Module Card Structure

Setiap modul terdiri dari:

1. Thumbnail Area
2. Module Information
3. Status Indicator

### Thumbnail Area

Merupakan area visual terbesar.

Pada implementasi final dapat berupa:

* module cover
* yoga image
* lesson preview
* course thumbnail

Card harus memiliki:

* rounded corners
* modern appearance
* hover animation
* premium visual quality

---

## Module Information

Di samping thumbnail terdapat informasi modul.

Struktur:

Module Number

Module Title

Contoh:

Module 1

Premier Online Introduction to Yoga

Module 4

Premier Online Anatomy & Physiology Lecture Series

---

## Module Status

### Completed

Ditandai dengan:

Green Check Icon

Menunjukkan student telah menyelesaikan modul.

### Available

Ditandai dengan:

Play Icon atau Active Indicator

Menunjukkan modul siap dipelajari.

### Locked

Ditandai dengan:

Lock Icon

Menunjukkan modul belum dapat diakses.

---

## Interaction Behaviour

### Hover

Card sedikit membesar.

Tujuan:

* meningkatkan feedback visual
* memperjelas bahwa card dapat diklik

### Click

Jika modul tersedia:

membuka halaman Module Detail atau Lesson Detail.

Jika modul terkunci:

menampilkan pesan bahwa modul sebelumnya harus diselesaikan terlebih dahulu.

---

## Color Palette

Background:

#000000

Primary Accent:

YogaFX Red

Text:

#FFFFFF

Secondary Text:

#C8C8C8

Success:

#3DDC84

Locked:

#B0B0B0

---

## UX Goals

Student harus langsung mengetahui:

* progres kursus
* urutan pembelajaran
* modul yang tersedia
* modul yang terkunci
* topik pembelajaran setiap modul

Halaman harus terasa seperti katalog kursus premium bergaya Netflix.

---

# LESSON DETAIL PAGE

## Tujuan Halaman

Lesson Detail Page merupakan halaman yang muncul setelah student memilih sebuah lesson atau module.

Halaman ini menjadi pusat aktivitas belajar sebelum student mengakses:

* video
* audio
* workbook
* assignment
* resource lainnya

Student harus dapat langsung memahami:

* lesson yang sedang dipelajari
* tujuan lesson
* progres pembelajaran
* lesson lain dalam module yang sama

---

## Design Philosophy

Inspirasi:

* Netflix Episode Detail Page
* MasterClass Lesson View
* Premium Learning Experience

Berbeda dengan halaman Module yang berfokus pada eksplorasi konten, halaman Lesson berfokus pada pengalaman belajar yang lebih mendalam.

Fokus utama:

* lesson content
* learning progress
* lesson navigation

---

## Layout Structure

Desktop Layout:

Left Content Area | Right Sidebar

Perbandingan lebar:

70% | 30%

Halaman menggunakan vertical scrolling.

---

## Navigation Bar

Navbar harus sama dengan halaman Home dan Module.

Tujuan:

menjaga konsistensi pengalaman pengguna.

Menu Modules tetap dianggap aktif karena lesson merupakan bagian dari module.

---

## Lesson Header

Menampilkan judul lesson.

Contoh:

Premier Online Introduction to Yoga

Karakteristik:

* besar
* bold
* mudah terlihat

Header menjadi identitas utama lesson yang sedang dibuka.

---

## Main Content Area

Area terbesar pada halaman.

Pada implementasi final area ini dapat digunakan untuk:

* video preview
* lesson banner
* lesson cover
* featured content

Area ini menjadi fokus visual utama halaman.

Harus menggunakan ukuran besar dan proporsi dominan.

---

## Lesson Description

Terletak di bawah area konten utama.

Berisi:

* pengenalan lesson
* tujuan pembelajaran
* manfaat lesson
* hasil yang akan diperoleh student

Deskripsi ditulis dalam bahasa yang mudah dipahami dan memotivasi student untuk melanjutkan pembelajaran.

---

## Sidebar

Sidebar berada di sisi kanan halaman.

Fungsi:

* progress tracking
* lesson navigation
* quick access information

---

## Progress Indicator

Menampilkan progres pembelajaran.

Contoh:

You've completed 14 of 14 modules

Disertai indikator visual berwarna hijau.

Tujuan:

* meningkatkan motivasi
* memperlihatkan pencapaian
* mendukung gamification

---

## Lesson Navigation Card

Menampilkan daftar lesson yang tersedia.

Contoh:

Lesson 1

Introduction to Yoga

Status lesson dapat berupa:

* Completed
* Available
* Locked

---

## Running Total Widget

Terletak di bagian bawah sidebar.

Contoh:

Running Total

Login Time

132:65:06

Fungsi:

* menampilkan total waktu belajar student
* meningkatkan engagement
* memberikan rasa pencapaian

Widget menggunakan gaya glassmorphism atau semi-transparent card.

---

## Responsive Behaviour

### Desktop

Content Area + Sidebar

### Tablet

Content Area
↓
Sidebar

### Mobile

Single Column Layout

Semua komponen ditampilkan secara vertikal.

---

## Color Palette

Background:

#000000

Primary Accent:

YogaFX Red

Text:

#FFFFFF

Secondary Text:

#C8C8C8

Success:

#3DDC84

Card Border:

rgba(255,255,255,0.1)

---

## UX Goals

Ketika membuka halaman ini, student harus langsung mengetahui:

* lesson yang sedang dipelajari
* tujuan lesson
* progres pembelajaran
* lesson berikutnya
* total waktu belajar

Secara keseluruhan halaman harus terasa seperti halaman detail episode pada Netflix yang diadaptasi menjadi pengalaman belajar yoga premium.
