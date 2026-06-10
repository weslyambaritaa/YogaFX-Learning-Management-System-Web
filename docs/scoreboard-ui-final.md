# Scoreboard UI Final Decisions
# YogaFX LMS

## Purpose

Dokumen ini menjadi source of truth UI final untuk domain **Scoreboard / Assessment Builder** pada YogaFX LMS.

Dokumen ini khusus membahas:
- layout builder
- perilaku panel
- tab Question dan Answers
- perilaku center canvas
- tingkat kemiripan terhadap ScoreApp
- prioritas implementasi UI

Dokumen ini digunakan sebagai acuan Codex sebelum merapikan UI scoreboard builder.

---

# 1. Main UI Direction

UI scoreboard builder harus dibuat **semirip mungkin dengan ScoreApp**, terutama pada:
- struktur layout builder
- perilaku panel
- pola tab Question / Answers
- feel visual secara keseluruhan

Namun tetap mempertahankan identitas YogaFX pada:
- warna / palette
- typography
- tone visual produk
- nuansa premium dan clean

---

# 2. Desktop Layout Structure

Builder desktop harus memakai **3 panel persisten** dengan proporsi:

- **Left panel:** 18%
- **Center panel:** 64%
- **Right panel:** 18%

## Notes
- layout ini harus menjadi struktur utama desktop
- panel kanan boleh berubah menjadi **drawer / sheet** pada layar sempit
- desktop tetap 3-column persisten

---

# 3. Left Panel Rules

## Purpose
Left panel berfungsi sebagai **navigator question screens**.

## Content
Left panel hanya berisi:
- daftar question vertikal
- nomor urut
- indicator type
- label internal / title fallback
- active state yang kuat

## Rules
- left panel harus terasa seperti builder navigator
- bukan hanya list polos biasa
- tetap hanya berisi question screens
- belum perlu item khusus seperti:
  - Intro
  - Results
  - Design

## Width
Left panel mengikuti proporsi 18% dan tidak perlu terlalu lebar.

---

# 4. Center Panel Rules

## Purpose
Center panel adalah area utama builder.

## Direction
Center panel harus menjadi **editor-preview hybrid**.

## Rules
- preview student screen harus terasa hidup
- semua elemen di tengah harus bisa diedit langsung **inline**
- pengecualian:
  - tombol **Next** harus tampil, tetapi **tidak editable**
- design settings global harus terlihat live di center canvas jika setting-nya sudah ada

## Editing Behaviour
- `question_text` adalah elemen dominan
- edit dilakukan secara **inline**
- `title` tetap ada, tetapi sifatnya sekunder / internal

---

# 5. Right Panel Rules

## Purpose
Right panel adalah config panel utama.

## Main Tabs
Right panel harus hanya punya 2 tab utama:
- **Question**
- **Answers**

## Tab Behaviour
- tab harus **bersebelahan secara horizontal**
- user klik salah satu tab
- hanya isi tab aktif yang ditampilkan
- tab tidak boleh ditumpuk ke bawah

## Visual Direction
- panel kanan harus dibuat semirip mungkin dengan ScoreApp
- padat
- rapi
- settings-oriented
- tidak terasa seperti form admin panjang biasa

---

# 6. Question Tab Rules

## Direction
Question tab harus mengikuti pola ScoreApp semirip mungkin.

## Structure
Question tab harus menampilkan field sesuai kebutuhan question type, dengan struktur yang mengikuti ScoreApp.

## Advanced Controls
- advanced field harus muncul dengan **progressive disclosure**
- gunakan toggle/switch inline
- jika toggle aktif, field turunannya langsung muncul di bawahnya

## Important Note
Grouping section seperti:
- General
- Behaviour
- Jump
- Type-specific settings

harus mengikuti pola ScoreApp aslinya semirip mungkin, bukan pola custom yang terlalu jauh.

---

# 7. Answers Tab Rules

## Direction
Answers tab harus mengikuti ScoreApp semirip mungkin.

## Structure
Answers tab harus menampilkan:
- row answer yang rapi
- compact
- tidak terlalu berat

## Behaviour
- detail tambahan seperti scoring, jump, image, dan lain-lain muncul inline di bawah row saat toggle aktif
- style answers harus lebih dekat ke ScoreApp, bukan semi-card besar yang berat

---

# 8. Buttons and Screen Template

## Next Button
- tombol **Next** wajib tampil di center preview
- tombol ini bagian dari template bawaan screen
- tombol ini **tidak editable**

## Editable Area
Yang editable inline di center:
- question text
- answer labels yang tampil di preview jika relevan
- konten screen lain yang memang bagian dari body screen

---

# 9. Visual Similarity Requirement

UI builder harus dimiripkan ke ScoreApp semaksimal mungkin pada detail berikut:
- border
- radius
- shadow
- spacing
- active state tab
- jarak antar panel
- tinggi panel
- keseimbangan whitespace
- feel builder secara keseluruhan

---

# 10. Priority Order for UI Cleanup

Urutan prioritas implementasi UI adalah:

1. **builder shell 3 panel**
2. **Question / Answers tab interaction**
3. **left panel navigator feel**
4. **center canvas inline editing feel**
5. **detail visual agar makin mirip ScoreApp**

---

# 11. Responsive Rule

## Desktop
- 3 panel persisten

## Smaller screens
- panel kanan boleh turun menjadi drawer / sheet
- fokus utama tetap fungsional dulu

---

# 12. What Must Not Be Changed

Saat merapikan UI scoreboard builder:
- jangan mengubah domain lain
- jangan merusak fitur yang sudah berjalan
- jangan refactor besar di luar domain scoreboard/assessment
- jangan mengubah logic bisnis tanpa konfirmasi
- fokus pada UI scoreboard builder

---

# 13. Final Summary

Builder scoreboard YogaFX harus:
- sangat dekat secara struktur dan feel dengan ScoreApp
- memakai 3 panel builder
- memakai tab horizontal Question / Answers
- memakai center canvas inline editing
- memakai left navigator yang builder-like
- mempertahankan identitas YogaFX di warna, font, dan nuansa visual

Dokumen ini menjadi source of truth UI final untuk Codex sebelum implementasi scoreboard builder.