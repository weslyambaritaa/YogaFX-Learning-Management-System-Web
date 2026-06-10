# ScoreApp Official Reference
# Reference Notes for YogaFX LMS Scoreboard Builder

## Purpose

Dokumen ini merangkum referensi resmi ScoreApp yang relevan untuk membantu mengarahkan desain produk, builder flow, dan pengalaman UI domain **Scoreboard / Assessment** pada YogaFX LMS.

Dokumen ini **bukan** hasil menyalin ScoreApp 1:1.  
Dokumen ini dipakai untuk:
- memahami pola produk ScoreApp
- memahami flow builder yang modern
- mengambil prinsip UI/UX yang relevan
- membantu Codex membangun scoreboard builder YogaFX dengan arah yang lebih tepat

Jika ada konflik antara referensi ini dan keputusan produk YogaFX, maka:
1. keputusan final YogaFX tetap menang
2. dokumen ini hanya referensi pendukung

---

# 1. ScoreApp Product Pattern

ScoreApp secara resmi memosisikan produknya sebagai platform untuk membuat **quizzes, scorecards, dan assessments** yang menghasilkan:
- personalized results
- lead data
- next step recommendations
- conversion-oriented outcome

Artinya, ScoreApp bukan sekadar “form builder”, tetapi **assessment engine + result engine**.

### Relevance for YogaFX
Ini sangat relevan dengan perubahan domain assessment YogaFX, karena scoreboard YogaFX juga tidak lagi dianggap sebagai kuis sederhana, melainkan:
- builder pertanyaan
- builder scoring
- builder result
- builder flow / jump logic

---

# 2. Entry Flow Reference

Menurut dokumentasi resmi ScoreApp, user bisa mulai dari:
- Dashboard
- tombol **Create Scorecard**
- AI Builder flow

Setelah itu, user masuk ke flow pembuatan scorecard yang menghasilkan:
- concept
- questions
- scoring
- results pages

### Relevance for YogaFX
Ini mendukung flow produk YogaFX berikut:
1. tampilkan semua scoreboard
2. pilih scoreboard
3. masuk ke builder/editor

Jadi YogaFX sebaiknya juga memandang entitas assessment sebagai **scoreboard entry point**, bukan langsung “halaman soal”.

---

# 3. Builder Mental Model

Dokumentasi ScoreApp AI Builder menjelaskan bahwa mereka membangun scorecard sebagai satu sistem yang terdiri dari:
- questions
- scoring logic
- results pages
- branding/page settings

ScoreApp tidak memisahkan assessment menjadi sekadar soal-soal terisolasi. Builder mereka bersifat holistic:
- pertanyaan
- skor
- hasil
- tampilan

### Relevance for YogaFX
Builder scoreboard YogaFX sebaiknya dipahami sebagai:
- **question builder**
- **answer/scoring builder**
- **jump logic builder**
- **result builder**
- **design settings builder**

Bukan sekadar CRUD question.

---

# 4. Results as a Core Feature

Di dokumentasi resmi ScoreApp, **results page** adalah bagian inti, bukan tambahan belakang.

Results page dapat berisi:
- overall score
- category score
- personalized explanation
- business information
- CTA / next step

### Relevance for YogaFX
Ini mendukung keputusan bahwa YogaFX sebaiknya:
- tidak berhenti di total score mentah
- menyiapkan `assessment_result_ranges`
- menyiapkan result engine
- memikirkan hasil akhir assessment sebagai pengalaman penting

---

# 5. Branding / Theme / Design Pattern

ScoreApp juga menunjukkan pola builder yang mendukung branding dan visual consistency:
- logo
- colors
- fonts
- theme settings
- page builder / design layer

Dalam AI Builder, mereka bahkan bisa otomatis menarik:
- brand colors
- fonts
- logos
dari website user.

### Relevance for YogaFX
Ini mendukung keputusan YogaFX untuk memiliki layer design settings seperti:
- logo
- logo max-width
- logo alignment
- logo link
- header position
- section background
- top margin
- bottom margin
- footer content

Artinya, design settings memang wajar sebagai bagian dari builder yang modern.

---

# 6. Page Builder Direction

Update resmi ScoreApp tentang page builder menunjukkan arah UI mereka yang:
- builder-oriented
- visual
- clean
- panel-based
- mudah dikonfigurasi

Ini bukan tampilan admin CRUD tradisional.

### Relevance for YogaFX
Builder scoreboard YogaFX harus terasa seperti:
- product builder
- screen builder
- modern editor
- bukan sekadar form backend panjang

Karena itu layout 3 panel sangat masuk akal:
- left = structure
- center = preview/editor
- right = config

---

# 7. Lead / Result Data Pattern

ScoreApp juga memiliki pola bahwa setiap scorecard menghasilkan data hasil pengerjaan user yang bisa dilihat kemudian, misalnya:
- nama
- waktu submit
- total score
- individual category scores

### Relevance for YogaFX
Ini menguatkan bahwa struktur data YogaFX tetap sebaiknya punya:
- attempts
- answers
- progress
- total score
- result range

Jadi model:
- `assessment_attempts`
- `assessment_answers`
- `assessment_progress`

adalah arah yang tepat dan profesional.

---

# 8. What YogaFX Should Imitate

YogaFX sebaiknya meniru ScoreApp pada area berikut:

## 8.1 Product Structure
- Scoreboards list sebagai entry point
- builder sebagai workspace utama
- pertanyaan + scoring + result dianggap satu sistem

## 8.2 Builder UX
- shell 3 panel
- focus pada satu screen aktif
- panel config yang jelas
- editor/preview hybrid
- progressive disclosure

## 8.3 Result Thinking
- hasil assessment bermakna
- bukan hanya angka mentah
- gunakan result ranges atau outcome

## 8.4 Design Layer
- logo
- background
- layout control
- builder terasa punya layer design

---

# 9. What YogaFX Should Not Copy Blindly

YogaFX tidak perlu meniru ScoreApp mentah-mentah pada area berikut:

- copywriting marketing mereka
- lead generation specific language
- semua fitur page builder publik mereka
- semua flow commercial tools mereka
- semua istilah yang terlalu sales/marketing-oriented

YogaFX harus tetap menjaga identitas sendiri sebagai:
- premium
- calm
- educational
- clean
- wellness-oriented

---

# 10. Official Reference Summary for Codex

Codex harus memahami bahwa referensi resmi ScoreApp menunjukkan pola berikut:

1. Assessment/scorecard adalah **builder product**, bukan form biasa
2. Questions, scoring, dan results harus dipandang sebagai satu sistem
3. Entry flow dimulai dari daftar scorecards / scoreboards
4. Builder harus terasa visual dan panel-based
5. Results adalah fitur utama
6. Branding / design settings adalah bagian wajar dari builder
7. Data hasil user harus kuat dan terstruktur

---

# 11. Final Takeaway

Untuk YogaFX LMS, referensi resmi ScoreApp mendukung arah bahwa domain assessment baru memang sebaiknya berubah menjadi:

- **Scoreboard List**
- **Scoreboard Builder**
- **Question + Answer + Scoring + Jump + Result**
- **Design Settings**
- **Attempt / Result Tracking**

Jadi, saat Codex membangun domain ini, dia harus menghindari pendekatan:
- CRUD assessment biasa
- page-based old quiz
- form admin linear yang kaku

Dan bergerak ke arah:
- builder modern
- visual editor
- scoring-driven experience
- result-aware engine

---

# 12. Source Reminder

Dokumen ini adalah referensi pendukung berdasarkan materi resmi ScoreApp.

Tetapi untuk implementasi final YogaFX:
- source of truth tertinggi tetap dokumen final YogaFX
- terutama:
  - `docs/assessment.md`
  - `docs/scoreboard-ui-final.md`

Dokumen ini hanya berfungsi untuk memperkuat arah UI/UX dan product pattern.