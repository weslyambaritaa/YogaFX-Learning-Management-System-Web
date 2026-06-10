# Scoreboard Question & Answer Matrix
# YogaFX LMS

## Purpose

Dokumen ini menjadi source of truth untuk menentukan **isi tab `Question` dan tab `Answers`** pada builder Scoreboard / Assessment YogaFX LMS.

Dokumen ini dibuat karena tiap `question_type` memiliki struktur field yang berbeda, dan implementasi saat ini belum sesuai.

Dokumen ini harus dipakai Codex untuk:
- menampilkan field yang benar pada tab `Question`
- menampilkan field yang benar pada tab `Answers`
- menyesuaikan isi panel kanan berdasarkan `question_type`
- menghindari field yang salah muncul di type yang salah

---

# 1. General Rules

## 1.1 Two Main Tabs
Setiap question di builder memiliki dua tab utama:
- `Question`
- `Answers`

## 1.2 Conditional Content
Isi dari kedua tab ini **harus berubah sesuai `question_type` yang aktif**.

## 1.3 Tab Visibility
- Tab `Question` dan `Answers` tetap selalu terlihat
- Tetapi isi masing-masing tab harus menyesuaikan type
- Jangan tampilkan field yang tidak relevan

## 1.4 Progressive Disclosure
Jika ada fitur seperti:
- scoring
- jump
- maybe answer
- other option

maka UI boleh memakai toggle/switch agar field turunannya baru muncul saat aktif.

## 1.5 Media
Saat ini field **Image or video** tetap dianggap ada di matrix UI karena itu yang diinginkan pada builder, walaupun implementasi backend/storage detailnya bisa ditangani bertahap.

---

# 2. Question Types Final

Semua type di bawah ini wajib didukung:

- `yes_no_maybe`
- `multiple_choice_buttons`
- `multiple_choice_checkboxes`
- `radio_buttons`
- `sliding_scale`
- `linear_scale`
- `divided_scale`
- `numeric`
- `open_text`
- `image_button`
- `info_screen`

---

# 3. Matrix per Question Type

## 3.1 Yes / No / Maybe

### Question Tab
- Show instruction
- Show maybe answer
- Required
- Randomize answers order
- Jump to
- Image or video

### Answers Tab
#### Yes
- Scoring
- Jump to

#### No
- Scoring
- Jump to

#### Maybe
- Scoring
- Jump to

### Notes
- `Yes`, `No`, dan `Maybe` adalah fixed answers
- Jika `Show maybe answer = OFF`, maka option `Maybe` tidak ditampilkan pada player
- Namun secara builder, sistem tetap boleh memperlakukan `Maybe` sebagai fixed option yang bisa di-toggle visibilitasnya

---

## 3.2 Multiple Choice Buttons

### Question Tab
- Show instruction
- Allow multi-select
- Required
- Randomize answers order
- Jump to
- "Other" option
- Image or video

### Answers Tab
Untuk setiap answer row:
- Answer label
- Scoring
- Jump to

### Notes
- Jumlah row mengikuti jumlah answer yang dibuat admin
- `Allow multi-select` membedakan perilaku single vs multi-select
- `Other option` menambahkan option khusus “Other”

---

## 3.3 Multiple Choice Checkboxes

### Question Tab
- Show instruction
- Min count
- Max count
- Required
- Randomize answers order
- Jump to
- Image or video

### Answers Tab
Untuk setiap answer row:
- Answer label
- Scoring

### Notes
- Untuk type ini, answer row **tidak punya `Jump to`**
- Validasi jumlah pilihan harus mengikuti `Min count` dan `Max count`

---

## 3.4 Multiple Choice Radio Button

### Question Tab
- Show instruction
- Required
- Randomize answers order
- Jump to
- Image or video

### Answers Tab
Untuk setiap answer row:
- Answer label
- Scoring
- Jump to

### Notes
- Hanya satu jawaban bisa dipilih

---

## 3.5 Sliding Scale

### Question Tab
- Show instruction
- Score range (from / to)
- Starting score
- Required
- Jump to
- Left
- Center
- Right
- Show score tooltip
- Score tooltip format
- Image or video

### Answers Tab
- Scoring category

### Notes
- `Scoring category` berbentuk dropdown
- Dapat memilih kategori yang ada, dan sistem sebaiknya siap untuk model kategori baru di masa depan
- Nilai user pada scale menjadi dasar score mentah

---

## 3.6 Linear Scale

### Question Tab
- Show instruction
- Score range (from / to)
- Starting score
- Required
- Jump to
- Left
- Center
- Right
- Image or video

### Answers Tab
- Scoring category
- Default label/value awal dapat berupa `Overall only`

### Notes
- Nilai user pada linear scale menjadi dasar score mentah

---

## 3.7 Divided Scale

### Question Tab
- Show instruction
- Score range (from / to)
- Starting score
- Section count
- Required
- Jump to
- Image or video

### Answers Tab
- Scoring category
- Default label/value awal dapat berupa `Overall only`

### Notes
- `Section count` wajib ada
- Nilai user tetap diperlakukan sebagai numeric score mentah

---

## 3.8 Numeric

### Question Tab
- Show instruction
- Range (from / to)
- Allow decimals
- Required
- Jump to
- Image or video

### Answers Tab
- Scoring category
- Default label/value awal dapat berupa `Overall only`

### Notes
- Jawaban user berupa angka
- Score mentah berasal dari angka yang diinput user
- `Allow decimals` menentukan validasi input

---

## 3.9 Open Text

### Question Tab
- Show instruction
- Input type
- Character limit
- Required
- Jump to
- Image or video

### Answers Tab
- Scoring category
- Default label/value awal dapat berupa `Overall only`

### Notes
- Tidak ada answer options klasik
- Untuk sekarang, `Open Text` tidak punya score otomatis berbasis isi text
- `Scoring category` tetap ada sebagai bagian struktur builder

---

## 3.10 Image Button

### Question Tab
- Show instruction
- Allow multi-select
- Show labels
- Required
- Randomize answers order
- Jump to
- Answer image fit
- Answers per row
- Image or video

### Answers Tab
Untuk setiap answer row:
- Answer label
- Image
- Image is required
- Scoring
- Jump to

### Notes
- Setiap answer wajib bisa punya image
- Jika `Show labels = OFF`, label bisa disembunyikan di player, tetapi tetap ada di builder/admin

---

## 3.11 Info Screen

### Question Tab
- Jump to
- Image or video

### Answers Tab
- Scoring category
- Default label/value awal dapat berupa `Overall only`

### Notes
- `Info Screen` tidak menerima jawaban user
- `Info Screen` tetap dianggap question/screen dalam flow
- `Info Screen` tetap punya tab `Answers`, tetapi isinya hanya `Scoring category`

---

# 4. Important Field Behaviour

## 4.1 Scoring
Jika pada answer row terdapat toggle **Scoring**:
- saat OFF -> field score value tidak tampil
- saat ON -> field score value harus langsung muncul

## 4.2 Jump to
Jika pada answer row atau question level terdapat toggle/field **Jump to**:
- saat aktif -> tampil dropdown target question
- target selalu berupa question lain

## 4.3 Image or Video
Field `Image or video` ada pada Question tab untuk type yang disebut di matrix ini.

## 4.4 Scoring Category
Untuk type yang tidak memakai answer options klasik, tab `Answers` tetap ada dan minimal berisi:
- `Scoring category`

---

# 5. UI Rules for Codex

1. Jangan tampilkan field yang tidak relevan untuk type tertentu.
2. Jangan samakan isi tab `Question` dan `Answers` untuk semua type.
3. Selalu render tab `Question` dan `Answers`.
4. Isi tab harus berubah berdasarkan `question_type`.
5. Gunakan progressive disclosure untuk field turunan.
6. Untuk type option-based, `Answers` tab harus berisi answer rows.
7. Untuk type non-option-based, `Answers` tab harus berisi config/helper yang sesuai, bukan answer rows palsu.

---

# 6. Final Reminder

Dokumen ini mengatur **isi final tab Question dan Answers**.

Jika ada konflik dengan implementasi lama, maka:
- dokumen ini menang untuk domain scoreboard builder UI
- implementasi lama harus disesuaikan

Dokumen ini harus dipakai Codex sebagai source of truth sebelum merapikan panel kanan builder scoreboard.