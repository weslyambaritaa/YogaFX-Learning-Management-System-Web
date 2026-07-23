# 04-design-system.md
# YogaFX LMS Design System

## 1. Purpose

Dokumen ini menjadi acuan visual dan UX untuk implementasi YogaFX LMS saat ini.

Dokumen ini mengikuti tampilan yang benar-benar sedang hidup di aplikasi, terutama perbedaan tegas antara student side yang premium-immersive dan admin side yang operasional.

---

## 2. Product Design Direction

### 2.1 Student Side
Student side harus terasa:
- premium
- calm
- cinematic
- guided
- content-first

Student side tidak boleh terasa seperti:
- portal sekolah
- dashboard statistik admin
- LMS kampus tradisional

### 2.2 Admin Side
Admin side harus terasa:
- structured
- efficient
- clean
- professional
- fast to scan

---

## 3. Current Implementation Guardrails

### 3.1 Student Experience
Pola student aktif saat ini:
- top navigation ringan
- Home memakai immersive dark atmosphere
- module browsing lebih visual daripada tabular
- CTA utama menonjol untuk melanjutkan belajar
- progress dan lock state tampil sebagai guidance, bukan sebagai tabel kompleks

### 3.2 Admin Experience
Pola admin aktif saat ini:
- left sidebar sebagai anchor utama
- topbar sederhana untuk page title, sidebar toggle, dan user menu
- workspace dominan putih/slate dengan card, form, dan table
- area CRUD dan review fokus pada kejelasan status

### 3.3 Component Direction
Komponen interaktif utama mengikuti pola shadcn/ui yang sudah dipakai:
- button
- badge
- dialog
- dropdown-menu
- sheet
- input
- textarea
- table
- select
- separator

### 3.4 Safety Pattern
Pola UX yang harus dipertahankan:
- destructive action memakai konfirmasi
- status penting tampil jelas
- error message dekat field
- upload helper text tersedia
- protected content tidak diakses sebagai public asset mentah

---

## 4. Visual Principles

### 4.1 Student: Premium Guidance
Student harus selalu merasa diarahkan ke langkah berikutnya tanpa kebisingan UI yang berlebihan.

### 4.2 Student: Dark, Warm, Focused
Area student aktif saat ini cenderung memakai:
- latar gelap
- gradien hangat
- highlight merah / oranye brand
- white text dengan hierarchy lembut

### 4.3 Admin: Operational Clarity
Admin harus memudahkan scanning table, form, badge, dan action row tanpa dekorasi berlebih.

### 4.4 Separation of Experiences
Student dan admin harus tetap terasa sebagai dua experience berbeda meskipun memakai stack komponen yang sama.

---

## 5. Color and Tone

### 5.1 Student
Warna student side aktif saat ini cenderung:
- dark charcoal / near-black
- warm accent merah brand
- sedikit warm orange glow
- text putih / off-white

Tone visual:
- tenang
- yakin
- tidak playful berlebihan

### 5.2 Admin
Warna admin side aktif saat ini cenderung:
- putih
- slate
- gray borders
- badge status yang jelas

Tone visual:
- netral
- produktif
- mudah dibaca

---

## 6. Typography

### 6.1 Student
Student experience aktif saat ini memakai tipografi yang lebih expressive dan premium. Home secara implementasi menggunakan keluarga font seperti `Montserrat` untuk menjaga rasa modern dan tegas.

### 6.2 Admin
Admin memakai tipografi sans yang bersih dan utilitarian.

### 6.3 Hierarchy
Minimum hierarchy:
- page title yang jelas
- section heading ringkas
- helper / support text muted
- status label yang mudah discan

---

## 7. Layout Guidelines

### 7.1 Student
- navigasi utama tetap ringan
- hero / continue-learning area boleh dominan secara visual
- cards module boleh lebih visual dan atmospheric
- gunakan status lock / complete / available sebagai guidance
- jangan ubah student area menjadi table-heavy workspace

### 7.2 Admin
- sidebar kiri tetap menjadi navigasi utama
- table tetap dipakai untuk list data
- form tetap direct dan eksplisit
- action menu titik tiga dipakai untuk row-level operations

---

## 8. Component Guidelines

### 8.1 Buttons
- student CTA utama harus mudah dikenali
- destructive button harus jelas berbeda
- loading dan disabled state harus terlihat

### 8.2 Badges
- badge dipakai untuk status seperti:
  - active / inactive
  - submitted / approved / rejected
  - available / locked / completed

### 8.3 Tables
- utamakan di admin side
- header harus jelas
- row action jangan ambigu

### 8.4 Dialogs and Sheets
- dialog dipakai untuk konfirmasi dan locked-state explanation
- sheet dipakai untuk mobile navigation

### 8.5 Cards
- student cards boleh lebih visual dan content-led
- admin cards tetap simple dan information-led

---

## 9. Responsive Rules

### 9.1 Student
- mobile navigation memakai sheet
- CTA dan target sentuh harus nyaman
- visual richness tidak boleh mengorbankan keterbacaan

### 9.2 Admin
- desktop tetap sidebar-based
- mobile admin sidebar berubah menjadi sheet
- table harus tetap usable dengan overflow horizontal yang aman

---

## 10. Accessibility

- focus state harus terlihat
- semua field harus punya label
- status tidak boleh hanya dibedakan lewat warna
- dialog, dropdown, dan sheet harus keyboard accessible
- teks di area student gelap harus tetap punya kontras cukup

---

## 11. Design Do and Don't

### Do
- pertahankan student side yang terasa premium dan guided
- pertahankan admin side yang terstruktur dan cepat dibaca
- gunakan shadcn/ui secara konsisten
- jaga perbedaan mental model student dan admin

### Don't
- jangan ubah student side menjadi portal sekolah
- jangan samakan style student Home dengan admin dashboard
- jangan tambahkan dekorasi admin yang tidak membantu pekerjaan
- jangan mencampur navigasi dan tone admin ke halaman student
