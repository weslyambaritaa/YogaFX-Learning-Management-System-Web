# Scoreboard / Assessment Source of Truth
# YogaFX LMS

## 1. Purpose of This Document

Dokumen ini menjadi **source of truth utama** untuk domain **Scoreboard / Assessment** pada YogaFX LMS.

Dokumen ini dibuat untuk membantu AI Coding Assistant (Codex) melakukan implementasi secara konsisten dan tidak menyimpang dari keputusan yang sudah disepakati.

Dokumen ini mencakup:
- konsep produk scoreboard
- flow builder
- flow player
- struktur data final
- rules per question type
- scoring
- jump logic
- result logic
- attempt logic
- batasan implementasi

Dokumen ini **tidak berisi kode**, tetapi harus cukup lengkap agar implementasi bisa langsung dimulai.

---

# 2. Product Direction

## 2.1 Perubahan Konsep Utama

Domain assessment lama **dirombak total** menjadi model yang lebih mirip **ScoreApp**.

Perubahan utamanya:
- tidak ada lagi **page manual**
- satu scoreboard punya banyak question
- satu question otomatis menjadi satu screen
- builder bersifat visual
- ada list semua scoreboard
- klik satu scoreboard masuk ke builder/editor
- setiap question punya konfigurasi sendiri
- setiap answer/option juga bisa punya konfigurasi sendiri
- ada jump logic
- ada scoring
- ada result range
- ada page design / screen design

---

## 2.2 Terminologi

### Scoreboard
Nama produk/UI untuk entitas utama assessment.

### Question
Satu unit screen dalam scoreboard.

### Answer / Option
Pilihan jawaban pada sebuah question.

### Attempt
Satu sesi pengerjaan scoreboard oleh user.

### Result Range
Kategori hasil berdasarkan total score.

### Jump
Lompatan ke question lain di luar urutan normal.

---

# 3. High Level Product Flow

## 3.1 Admin Side
1. Admin membuka halaman **Scoreboards**
2. Sistem menampilkan semua scoreboard
3. Admin memilih salah satu scoreboard
4. Admin masuk ke **Scoreboard Builder**
5. Admin menambah question
6. Admin mengatur question type
7. Admin mengatur tab:
   - Question
   - Answers
8. Admin menyimpan scoreboard

## 3.2 Student Side
1. Student membuka scoreboard yang tersedia
2. Student mulai mengerjakan
3. Sistem menampilkan satu question per screen
4. Student menjawab
5. Sistem menyimpan answer
6. Sistem menjalankan jump logic bila ada
7. Sistem menghitung score
8. Sistem menentukan result
9. Sistem menampilkan hasil akhir

---

# 4. UI / Feature Structure

## 4.1 Scoreboards List
Halaman ini menampilkan semua scoreboard.

### Minimal information:
- title
- status
- thumbnail / preview image
- updated time / visited time jika diperlukan
- action
- create scoreboard

### Behaviour:
- klik satu scoreboard → masuk ke builder/editor
- create scoreboard → membuat scoreboard baru

---

## 4.2 Scoreboard Builder
Builder terdiri dari 3 area utama:

### Left Panel
Daftar semua question

### Center Panel
Preview / editable question screen

### Right Panel
Tab konfigurasi:
- Question
- Answers

---

# 5. Builder Behaviour

## 5.1 Question List
- menampilkan seluruh question dalam scoreboard
- urutan berdasarkan `sort_order`
- klik satu question → tampil di editor
- tombol **Add Question** akan menambahkan question baru
- question baru otomatis menjadi screen berikutnya

## 5.2 No Manual Pages
Sistem **tidak lagi menggunakan page manual**.

### Rule:
- satu question = satu screen
- urutan question menentukan urutan flow normal

---

# 6. Core Data Model Direction

## 6.1 Main Entities

Domain scoreboard final menggunakan entitas berikut:

- `assessments`  
  > secara UI disebut **scoreboards**
- `assessment_designs`
- `questions`
- `question_options`
- `assessment_result_ranges`
- `assessment_attempts`
- `assessment_answers`
- `assessment_progress`

## 6.2 Entity Removed
- `assessment_pages` → **hapus total**

---

# 7. Final Schema Overview

## 7.1 `assessments`
**UI Name:** Scoreboards

### Purpose
Menyimpan data utama scoreboard.

### Fields
- `id`
- `title`
- `slug`
- `description`
- `thumbnail`
- `status`
- `duration_minutes`
- `scoring_mode`
- `result_mode`
- `is_active`
- `show_progress_bar`
- `allow_back_navigation`
- `created_at`
- `updated_at`

### Notes
- halaman list scoreboard mengambil data dari tabel ini
- `status` bisa berupa draft/live/archived
- scoreboard adalah entitas utama yang dipilih sebelum masuk builder

---

## 7.2 `assessment_designs`

### Purpose
Menyimpan pengaturan desain global scoreboard.

### Fields
- `id`
- `assessment_id`
- `logo`
- `logo_max_width`
- `logo_alignment`
- `logo_link`
- `header_position`
- `section_background`
- `top_margin`
- `bottom_margin`
- `footer_content`
- `created_at`
- `updated_at`

### Notes
Digunakan untuk:
- logo
- logo max-width
- logo alignment
- logo link
- header position
- section background
- top margin
- bottom margin
- footer content

---

## 7.3 `questions`

### Purpose
Menyimpan semua question/screen dalam scoreboard.

### Core fields
- `id`
- `assessment_id`
- `title`
- `question_text`
- `question_type`
- `sort_order`

### General config
- `show_instruction`
- `instruction_text`
- `required`
- `randomize_answers_order`
- `jump_enabled`
- `jump_to_question_id`

### Selection config
- `show_maybe_answer`
- `allow_multi_select`
- `min_count`
- `max_count`
- `allow_other_option`
- `show_labels`

### Scale / numeric / text config
- `score_range_min`
- `score_range_max`
- `starting_score`
- `section_count`
- `allow_decimals`
- `input_type`
- `character_limit`
- `show_score_tooltip`
- `score_tooltip_format`

### Image button config
- `answer_image_fit`
- `answers_per_row`

### Scoring config
- `scoring_category`

### Audit
- `created_at`
- `updated_at`

### Notes
- satu row = satu screen
- `info_screen` tetap masuk tabel ini
- `info_screen` tidak menerima jawaban user
- `jump_to_question_id` pada level question adalah jump global dari question itu sendiri

---

## 7.4 `question_options`

### Purpose
Menyimpan opsi/jawaban untuk question.

### Fields
- `id`
- `question_id`
- `label`
- `internal_value`
- `image`
- `sort_order`

### Scoring config
- `scoring_enabled`
- `score_value`

### Jump config
- `jump_enabled`
- `jump_to_question_id`

### Flags
- `is_other_option`
- `is_fixed_option`

### Audit
- `created_at`
- `updated_at`

### Notes
- digunakan untuk semua type yang punya answer/options
- untuk `yes_no_maybe`, options bersifat fixed
- jika `scoring_enabled = true`, maka `score_value` harus tampil di UI dan harus bisa diisi
- jika `jump_enabled = true`, maka dropdown target question harus tampil di UI

---

## 7.5 `assessment_result_ranges`

### Purpose
Menyimpan kategori hasil berdasarkan range score.

### Fields
- `id`
- `assessment_id`
- `title`
- `description`
- `min_score`
- `max_score`
- `sort_order`
- `created_at`
- `updated_at`

### Notes
Contoh:
- 0–20 = Low
- 21–50 = Medium
- 51–100 = High

Ini direkomendasikan agar scoreboard punya hasil yang bermakna, bukan hanya angka mentah.

---

## 7.6 `assessment_attempts`

### Purpose
Menyimpan satu sesi pengerjaan scoreboard oleh user.

### Fields
- `id`
- `user_id`
- `assessment_id`
- `attempt_number`
- `status`
- `started_at`
- `expires_at`
- `submitted_at`
- `completed_at`

### Flow state
- `current_question_id`
- `last_answered_question_id`

### Result
- `total_score`
- `result_range_id`
- `result_label`

### Ending info
- `finished_reason`

### Audit
- `created_at`
- `updated_at`

### Notes
- dipakai untuk melacak proses pengerjaan
- mendukung timer
- mendukung jump flow
- mendukung result engine

---

## 7.7 `assessment_answers`

### Purpose
Menyimpan jawaban user.

### Fields
- `id`
- `assessment_attempt_id`
- `question_id`
- `question_option_id`

### Value fields
- `answer_text`
- `answer_number`
- `answer_boolean`

### Scoring snapshot
- `score_awarded`

### State
- `is_final`
- `answered_at`
- `created_at`
- `updated_at`

### Notes
- multi-select = banyak row
- `open_text` = `answer_text`
- `numeric` = `answer_number`
- `yes_no_maybe` dan option based = `question_option_id`
- `info_screen` tidak membuat answer row

---

## 7.8 `assessment_progress`

### Purpose
Ringkasan scoreboard per user.

### Fields
- `id`
- `user_id`
- `assessment_id`
- `latest_score`
- `highest_score`
- `total_attempts`
- `is_done`
- `completed_at`
- `created_at`
- `updated_at`

### Notes
- tetap dipakai
- hanya untuk ringkasan
- bukan engine utama flow

---

# 8. Question Types Final

Semua type di bawah ini wajib masuk sekarang:

- `yes_no_maybe`
- `multiple_choice_checkboxes`
- `multiple_choice_buttons`
- `radio_buttons`
- `sliding_scale`
- `linear_scale`
- `divided_scale`
- `numeric`
- `open_text`
- `image_button`
- `info_screen`

---

# 9. Type-specific Rules

## 9.1 Yes / No / Maybe

### Fixed Answers
- Yes
- No
- Maybe

### Question config
- Show instruction
- Show maybe answer
- Required
- Randomize answers order
- Jump to

### Answer config
- Scoring
- Jump to

### Rules
- label Yes/No/Maybe fixed
- tidak boleh diedit bebas
- jika `show_maybe_answer = false`, option Maybe tidak ditampilkan di player

---

## 9.2 Multiple Choice Buttons

### Question config
- Show instruction
- Allow multi-select
- Required
- Randomize answers order
- Jump to
- Other option

### Answer config
- Scoring
- Jump to

### Recommendation
Type ini dianggap sebagai **gaya UI tombol**, bukan struktur data berbeda.
Kalau `allow_multi_select = true`, maka perilakunya seperti checkbox tetapi tampil sebagai button choice.

---

## 9.3 Multiple Choice Checkboxes

### Question config
- Show instruction
- Min count
- Max count
- Required
- Randomize answers order
- Jump to

### Answer config
- Scoring

### Rules
- `min_count` dan `max_count` wajib disimpan
- validasi jumlah pilihan harus mengikuti min dan max

---

## 9.4 Radio Buttons

### Question config
- Show instruction
- Required
- Randomize answers order
- Jump to

### Answer config
- Scoring
- Jump to

### Rules
- hanya satu jawaban bisa dipilih

---

## 9.5 Sliding Scale

### Question config
- Show instruction
- Score range
- Starting score
- Required
- Jump to
- Left label
- Center label
- Right label
- Show score tooltip
- Score tooltip format

### Answer config
- Answer label / value jika dibutuhkan tampilan
- Scoring category = Overall only

### Rules
- jawaban user disimpan sebagai angka
- score biasanya mengikuti nilai yang dipilih

---

## 9.6 Linear Scale

### Question config
- Show instruction
- Score range
- Starting score
- Required
- Jump to
- Left label
- Center label
- Right label

### Answer config
- Scoring category = Overall only

### Rules
- jawaban disimpan sebagai angka linear

---

## 9.7 Divided Scale

### Question config
- Show instruction
- Score range
- Starting score
- Section count
- Required
- Jump to

### Answer config
- Scoring category = Overall only

### Rules
- jawaban tetap disimpan sebagai angka
- `section_count` harus disimpan

---

## 9.8 Numeric

### Question config
- Show instruction
- Range min
- Range max
- Allow decimals
- Required
- Jump to

### Answer config
- Scoring category = Overall only

### Rules
- jawaban disimpan di `answer_number`
- validasi mengikuti range dan decimal rule

---

## 9.9 Open Text

### Question config
- Show instruction
- Input type
- Character limit
- Required
- Jump to

### Answer config
- Scoring category = Overall only

### Rules
- jawaban disimpan di `answer_text`
- validasi mengikuti input_type dan character_limit

---

## 9.10 Image Button

### Question config
- Show instruction
- Allow multi-select
- Show labels
- Required
- Randomize answers order
- Jump to
- Answer image fit
- Answers per row

### Answer config
- Image
- Image is required
- Scoring
- Jump to

### Rules
- option bisa punya image
- tampilan mengikuti `answer_image_fit` dan `answers_per_row`

---

## 9.11 Info Screen

### Question config
- Jump to
- Scoring category = Overall only

### Rules
- tetap disimpan sebagai `question`
- tidak menerima jawaban user
- tidak membuat row di `assessment_answers`
- dipakai untuk layar informasi / konten

---

# 10. Instruction Logic

## Rule
Jika `show_instruction = true`:
- UI harus menampilkan area instruction
- field `instruction_text` harus tersedia

Jika `show_instruction = false`:
- instruction tidak tampil di player
- field instruction boleh kosong

---

# 11. Scoring Logic

## 11.1 Answer-level scoring
Untuk option-based question:
- setiap answer/option bisa punya toggle `scoring_enabled`
- saat toggle ini ON, UI harus langsung memunculkan field nilai score
- nilai score disimpan di `score_value`

## 11.2 Question-level scoring
Untuk scale / numeric / text based question:
- score dapat dihitung berdasarkan value user
- `scoring_category` saat ini cukup `overall_only`

## 11.3 Attempt total score
Setelah user submit:
- sistem menjumlahkan semua score yang relevan
- hasilnya disimpan di `assessment_attempts.total_score`

## 11.4 Result range
Setelah total score didapat:
- sistem mencocokkan ke `assessment_result_ranges`
- hasil range disimpan di:
  - `result_range_id`
  - `result_label`

---

# 12. Jump Logic

## 12.1 Jump Enabled
`jump_enabled` berarti fitur lompat aktif.

## 12.2 Jump Target Question
`jump_to_question_id` berarti tujuan lompat adalah question tertentu.

## 12.3 Question-level jump
Jika `questions.jump_enabled = true`:
- setelah question selesai, flow bisa lompat ke question target
- ini berlaku sebagai jump umum pada question itu

## 12.4 Answer-level jump
Jika `question_options.jump_enabled = true`:
- saat answer tertentu dipilih, flow lompat ke target question pada answer itu
- ini **mengoverride urutan normal**

## 12.5 Priority rule
Jika answer-level jump aktif, maka:
- answer-level jump lebih prioritas daripada question-level jump

## 12.6 Skip rule
Question yang dilewati dianggap:
- tidak ditampilkan
- tidak dijawab
- flow lanjut dari target question ke question setelahnya secara normal

---

# 13. Attempt and Answer Logic

## 13.1 Attempt Structure
Tetap gunakan:
- `assessment_attempts`
- `assessment_answers`
- `assessment_progress`

Ini adalah struktur yang paling profesional dan scalable.

## 13.2 Multiple selected answers
Untuk question type yang multi-select:
- 1 option dipilih = 1 row di `assessment_answers`

## 13.3 Single answer
Untuk question type single choice:
- cukup 1 row answer

## 13.4 Open text
- simpan di `answer_text`

## 13.5 Numeric
- simpan di `answer_number`

## 13.6 Info screen
- tidak membuat row answer

---

# 14. Builder UX Rules

## 14.1 Add Question
Saat admin klik **Add Question**:
- question baru dibuat
- otomatis menjadi screen berikutnya
- tidak ada page manual

## 14.2 Question Editing
Admin dapat:
- klik question dari list kiri
- langsung edit text di area tengah
- konfigurasi dilakukan di panel kanan

## 14.3 Right Panel Tabs
Harus ada:
- Question
- Answers

## 14.4 Answers Tab
Semua question type tetap punya tab **Answers**, tetapi perilaku/isi tab bisa berbeda sesuai type.

---

# 15. Design / Page Design Rules

## Global design tersimpan di `assessment_designs`

### Harus mendukung:
- logo
- logo max-width
- logo alignment
- logo link
- header position
- section background
- top margin
- bottom margin
- footer content

## Notes
- design ini berlaku sebagai global design scoreboard
- nanti bisa di-override jika memang dibutuhkan, tetapi versi awal cukup global dulu

---

# 16. Flow Summary

## Admin
1. Buka halaman semua scoreboard
2. Pilih scoreboard
3. Masuk builder
4. Tambah / edit question
5. Atur answer
6. Atur scoring
7. Atur jump
8. Simpan

## Student
1. Mulai scoreboard
2. Lihat satu question per screen
3. Jawab
4. Sistem simpan answer
5. Sistem jalankan jump jika ada
6. Submit / selesai
7. Sistem hitung total score
8. Sistem tentukan result range
9. Tampilkan hasil

---

# 17. What Must Be Removed from Old Assessment Domain

Hal-hal lama yang tidak boleh dipakai lagi:
- `assessment_pages`
- page builder manual
- relasi `questions -> assessment_pages`

---

# 18. Required Change Scope

Perubahan ini dianggap memengaruhi seluruh domain assessment, yaitu:
- database structure
- admin builder flow
- student player flow
- scoring engine
- result page / result logic

Ini **bukan revisi kecil**, tetapi **perombakan total domain assessment**.

---

# 19. Final Recommendations for Codex

## Harus dilakukan
- gunakan scoreboard list sebagai entry point
- gunakan question sebagai satu screen otomatis
- pertahankan attempt/answer/progress model
- gunakan result ranges
- implementasikan answer-level scoring
- implementasikan answer-level jump
- implementasikan question-level jump
- simpan open text dan numeric secara terpisah

## Jangan dilakukan
- jangan gunakan lagi assessment_pages
- jangan buat flow page manual
- jangan satukan semua jenis jawaban ke satu field saja
- jangan abaikan jump logic
- jangan abaikan result range

---

# 20. Final Summary

Domain assessment lama sekarang berubah menjadi **Scoreboard Engine** dengan ciri:

- ada halaman daftar semua scoreboard
- builder mirip ScoreApp
- 1 question = 1 screen
- semua question type langsung aktif
- ada instruction
- ada scoring
- ada jump
- ada result range
- ada design settings
- attempt dan answer tetap dipakai dengan model yang lebih kaya

Dokumen ini menjadi source of truth utama implementasi untuk Codex.