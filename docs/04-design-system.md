# 04-design-system.md
# YogaFX LMS Design System

## 1. Purpose

Dokumen ini menjadi acuan visual dan UX untuk implementasi YogaFX LMS saat ini.

Dokumen ini harus dipakai bersama `docs/00-current-project-status.md` karena beberapa area student masih bersifat foundation dan belum mencapai visual final.

---

## 2. Product Design Direction

### 2.1 Student Side
Student side harus terasa:
- premium
- calm
- content-first
- ringan
- tidak seperti portal kampus

### 2.2 Admin Side
Admin side harus terasa:
- structured
- efficient
- professional
- operasional
- modern

---

## 3. Current Implementation Guardrails

### 3.1 Admin Layout
Admin layout aktif saat ini memakai:
- left sidebar sebagai navigasi utama
- sidebar bisa collapse/expand
- topbar hanya untuk page title, toggle sidebar, dan user menu
- tidak lagi memakai top navigation utama

### 3.2 Student Layout
Student layout aktif saat ini masih sederhana:
- top navigation ringan
- halaman dashboard foundation
- page header standar

Student side belum dianggap final visual target. Saat mengubah student side, tetap jaga arah premium dan tenang.

### 3.3 shadcn/ui First
Komponen interaktif utama harus mengikuti pola shadcn/ui yang sudah dipakai di project:
- button
- dialog
- dropdown-menu
- input
- textarea
- badge
- sheet
- separator

### 3.4 Consistent Safety Patterns
Pola UI yang sudah aktif dan harus dipertahankan:
- dialog konfirmasi sebelum delete
- redirect ke list page setelah create/update berhasil
- helper text pada field upload
- error message dekat dengan field terkait

---

## 4. Visual Principles

### 4.1 Calm Clarity
Hindari layout yang terlalu padat atau penuh ornamen.

### 4.2 Premium Simplicity
Gunakan hierarchy yang kuat, spacing yang lega, dan tampilan yang bersih.

### 4.3 Content First
Untuk student, konten belajar harus tetap menjadi fokus utama.

### 4.4 Operational Readability
Untuk admin, table, form, dan status harus cepat dipindai.

---

## 5. Color and Tone

Warna harus tetap:
- netral dan tenang
- tidak neon
- tidak agresif
- cukup kontras untuk admin productivity

Status harus jelas untuk:
- success
- warning
- error
- active / inactive

---

## 6. Typography

Gunakan tipografi sans modern yang:
- mudah dibaca
- rapi
- tidak dekoratif
- cocok untuk layout aplikasi

Hierarchy minimum:
- satu H1 atau page title yang jelas
- section heading ringkas
- helper text kecil dan muted

---

## 7. Component Guidelines

### 7.1 Buttons
- satu CTA utama per area
- destructive action harus jelas berbeda
- loading/disabled state harus terlihat

### 7.2 Inputs and Textareas
- label wajib jelas
- error message tampil di bawah field
- helper text dipakai untuk batas upload atau format input

### 7.3 Tables
- dipakai terutama di admin side
- action column harus jelas
- status sebaiknya memakai badge

### 7.4 Dropdown Menus
- dipakai untuk action sekunder, misalnya row actions atau user menu
- item harus langsung bisa dipahami tanpa tebakan

### 7.5 Dialogs
- dipakai untuk konfirmasi aksi destruktif
- gunakan pola yang konsisten di semua CRUD

### 7.6 File Preview
- ebook preview harus lebih dulu menampilkan isi file jika didukung
- download tetap menjadi aksi terpisah

---

## 8. Layout Guidelines

### 8.1 Admin
- sidebar kiri adalah anchor utama
- topbar tetap ringan
- content area menjadi workspace utama
- dashboard admin saat ini sengaja sederhana, jangan memaksa menambah card statistik tanpa requirement

### 8.2 Student
- navigasi tetap ringan
- halaman konten harus fokus pada keterbacaan
- jangan memindahkan student side ke pola table-heavy

---

## 9. Responsive Rules

### 9.1 Admin
- sidebar berubah menjadi sheet/drawer di mobile
- table harus tetap usable

### 9.2 Student
- mobile-first usability tetap penting
- link dan CTA harus nyaman disentuh

---

## 10. Accessibility

- focus state harus terlihat
- semua field harus punya label
- dialog dan dropdown harus keyboard accessible
- status tidak boleh hanya dibedakan lewat warna
- target klik harus nyaman di desktop maupun mobile

---

## 11. Design Do and Don't

### Do
- pertahankan UI yang bersih dan terstruktur
- gunakan shadcn/ui secara konsisten
- buat admin cepat bekerja
- buat student tetap merasa masuk ke produk learning yang premium

### Don't
- jangan ubah student side menjadi portal sekolah
- jangan penuhi dashboard admin dengan elemen dekoratif yang tidak perlu
- jangan campur pola navigasi admin dan student
- jangan tambahkan visual complexity tanpa alasan produk
