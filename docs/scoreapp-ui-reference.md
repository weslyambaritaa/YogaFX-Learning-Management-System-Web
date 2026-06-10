# ScoreApp UI Reference
# YogaFX LMS

## Purpose

Dokumen ini menjadi referensi UI/UX untuk domain **Scoreboard / Assessment** pada YogaFX LMS.

Tujuan dokumen ini bukan untuk menyalin ScoreApp 1:1, tetapi untuk menangkap pola UI, struktur pengalaman, dan perilaku builder yang terasa modern, rapi, dan strategis. Referensi ini diambil dari materi resmi ScoreApp tentang AI Builder, page builder, brand settings, help center, dan positioning produk mereka. :contentReference[oaicite:0]{index=0}

Dokumen ini dipakai sebagai acuan untuk:
- scoreboard list UI
- scoreboard builder UI
- question editing UI
- scoring UI
- results configuration UI
- design settings UI

---

## 1. Core Product Feel to Imitate

ScoreApp menampilkan produknya sebagai alat untuk membangun **interactive assessments / scorecards** yang menghasilkan **personalized results**, bukan sekadar form atau quiz biasa. UI dan messaging mereka selalu mengarah ke:
- cepat membuat scorecard
- mengedit hasil AI/manual draft
- menyusun pertanyaan
- menyusun scoring logic
- menyusun result pages
- mengatur branding dan page appearance
- memandu user dari builder ke hasil akhir yang siap dipakai. :contentReference[oaicite:1]{index=1}

### Adaptation for YogaFX LMS
Untuk YogaFX LMS, domain ini harus terasa seperti:
- **modern scoreboard builder**
- **product builder**, bukan menu admin CRUD lama
- **visual and structured**
- **configuration-first but still friendly**
- **premium and clean**

---

## 2. ScoreApp-Inspired UX Principles

### 2.1 Builder, Not Basic Form Admin
UI harus terasa seperti builder, bukan hanya halaman form panjang. ScoreApp menonjolkan proses “generate, refine, publish” dan builder experience yang aktif, bukan form backend klasik. :contentReference[oaicite:2]{index=2}

**Implikasi untuk YogaFX:**
- jangan gunakan halaman create/edit question yang terpisah-pisah secara kaku
- gunakan builder layout yang persistent
- fokus pada edit cepat dan visual context

### 2.2 One Main Canvas at a Time
ScoreApp builder cenderung membawa user fokus pada satu bagian utama dulu, lalu memperlihatkan kontrol pendukung di panel lain. Ini membuat editing lebih fokus. :contentReference[oaicite:3]{index=3}

**Implikasi untuk YogaFX:**
- satu question aktif harus menjadi fokus utama
- panel lain mendukung, bukan mendominasi

### 2.3 Strong Separation Between Structure and Configuration
ScoreApp memisahkan:
- daftar item / structure
- editing content
- settings / theme / branding

**Implikasi untuk YogaFX:**
builder scoreboard harus dibagi jelas antara:
- list question
- editor tengah
- settings / answers panel kanan

### 2.4 Scoring Is Core, Not Secondary
Materi resmi ScoreApp berkali-kali menegaskan bahwa AI builder mereka membuat **questions, scoring logic, dan results pages** sebagai satu sistem. Jadi scoring bukan tambahan belakang, melainkan bagian inti dari builder. :contentReference[oaicite:4]{index=4}

**Implikasi untuk YogaFX:**
- scoring UI harus first-class
- jump logic dan results config juga harus dianggap first-class

### 2.5 Results Matter as Much as Questions
ScoreApp punya dokumentasi dan builder untuk results page, dan itu menjadi bagian inti experience. :contentReference[oaicite:5]{index=5}

**Implikasi untuk YogaFX:**
- jangan berhenti di question builder
- scoreboard harus dianggap lengkap hanya jika hasil akhir / result logic juga jelas

---

## 3. Recommended Product Structure for YogaFX

## 3.1 Scoreboards List Page

### Tujuan
Menjadi halaman awal domain scoreboard.

### Referensi ScoreApp
ScoreApp menempatkan user pada titik masuk yang jelas untuk membuat atau membuka scorecard, lalu lanjut ke builder. :contentReference[oaicite:6]{index=6}

### YogaFX Structure
Halaman ini harus menampilkan:
- semua scoreboard
- title
- status
- updated time
- action utama
- tombol create scoreboard

### Primary actions
- Create Scoreboard
- Open Builder
- Edit Meta
- Delete

### Visual direction
- list atau card list yang bersih
- bukan tabel berat seperti CRUD tradisional
- status badge jelas
- action utama mudah ditemukan

---

## 3.2 Scoreboard Builder Layout

### Tujuan
Menjadi workspace utama untuk membangun scoreboard.

### Referensi ScoreApp
Page builder dan AI builder ScoreApp menunjukkan pola builder yang lebih visual, modular, dan terfokus. :contentReference[oaicite:7]{index=7}

### Recommended layout
Gunakan **3-column builder layout**:

#### Left Panel — Structure
Berisi:
- daftar semua question
- urutan question
- tombol Add Question
- indikator question aktif
- indikator type question

#### Center Panel — Editable Canvas
Berisi:
- preview screen aktif
- editable question text
- instruction preview
- answer preview
- info screen preview
- page design effect preview

#### Right Panel — Configuration
Berisi tab:
- Question
- Answers

### Behaviour
- klik question di kiri -> update canvas tengah + config kanan
- add question -> row question baru dibuat dan langsung aktif
- perubahan question harus terasa cepat dan fokus

---

## 4. Builder Interaction Model

## 4.1 Left Panel Behaviour
Daftar question harus:
- mudah discan
- jelas question mana yang aktif
- menampilkan type secara ringkas
- mendukung pertumbuhan jumlah question yang besar

### UX recommendation
- gunakan list vertikal
- tampilkan nomor urut
- tampilkan title atau label fallback
- gunakan active state yang kuat tapi tetap clean

## 4.2 Center Canvas Behaviour
Canvas tengah harus terasa seperti area preview/edit utama.

### Harus bisa:
- klik text dan edit langsung
- melihat bagaimana screen akan tampil
- melihat hasil setting seperti:
  - instruction
  - other option
  - maybe answer
  - labels
  - image option
  - logo/header/footer effect

### UX recommendation
- canvas diberi ruang napas besar
- width tidak terlalu sempit
- background berbeda tipis dari shell builder
- preview harus terlihat seperti screen nyata, bukan form mentah

## 4.3 Right Panel Behaviour
Panel kanan adalah tempat semua pengaturan detail.

### Harus dipisah ke 2 tab:
- Question
- Answers

### Question tab
Isi:
- type
- instruction
- required
- randomize
- jump to
- type-specific settings

### Answers tab
Isi:
- daftar options
- scoring
- jump per option
- image per option jika perlu
- add/remove/reorder option

---

## 5. Question Editing UX

## 5.1 One Question = One Screen
Scoreboard builder YogaFX harus mengikuti keputusan final:
- tidak ada page manual
- satu question = satu screen

### UX implication
- user tidak perlu berpikir tentang page structure
- user hanya mengelola urutan question

## 5.2 Inline Content Editing
Pertanyaan harus bisa diedit dengan cara ringan.

### Recommended behaviour
- question text editable langsung di canvas
- title optional editable dari panel atau canvas
- instruction juga bisa terlihat langsung di preview

## 5.3 Type Switching
Mengubah type question harus:
- jelas
- tidak membingungkan
- memperlihatkan field config yang relevan saja

### Recommended behaviour
- type selector jelas di Question tab
- saat type berubah, panel config menyesuaikan
- jangan tampilkan semua field untuk semua type sekaligus

---

## 6. Answers UI Patterns

## 6.1 Answer Rows Must Be Modular
Untuk type yang punya option, setiap answer row harus modular.

### Satu row answer idealnya punya:
- label
- optional internal value
- scoring toggle
- score field jika scoring aktif
- jump toggle
- jump target jika jump aktif
- image picker jika type membutuhkannya

## 6.2 Progressive Disclosure
Field lanjutan jangan selalu terlihat.

### Example
- jika `scoring_enabled = false` → score field disembunyikan
- jika `jump_enabled = false` → jump target disembunyikan

Ini penting agar builder tidak terasa berat.

## 6.3 Fixed Option Types
Untuk `yes_no_maybe`, pilihan default harus:
- Yes
- No
- Maybe

Tetapi UI tetap harus bisa memperlihatkan konfigurasi scoring dan jump pada masing-masing opsi.

---

## 7. Type-Specific UI Recommendations

## 7.1 Yes / No / Maybe
### Tampilan
- 2 atau 3 pilihan besar
- jika `show_maybe_answer = false`, hanya tampil Yes dan No

### Pengaturan penting
- show instruction
- required
- randomize answers order
- jump
- scoring per answer
- jump per answer

---

## 7.2 Multiple Choice Buttons
### Tampilan
- pilihan seperti button choices
- cocok untuk jawaban cepat dan modern

### Rekomendasi
Anggap ini sebagai **UI style**, bukan data model yang sepenuhnya berbeda dari checkbox/radio.  
Perbedaan utama ada di presentasi dan `allow_multi_select`.

---

## 7.3 Multiple Choice Checkboxes
### Tampilan
- daftar pilihan dengan checkbox
- cocok untuk pilihan lebih banyak

### Pengaturan penting
- min count
- max count
- required
- randomize
- scoring

---

## 7.4 Radio Buttons
### Tampilan
- pilihan tunggal
- lebih klasik, lebih form-like

### Cocok untuk
- pilihan tunggal dengan banyak opsi
- situasi yang tidak butuh button visual besar

---

## 7.5 Sliding Scale / Linear Scale / Divided Scale
### Tampilan
- area scale harus visual, bukan sekadar input angka
- tampilkan left / center / right labels bila diisi
- jika ada tooltip score, harus terasa ringan

### UX notes
- nilai default harus jelas
- pilihan yang sedang aktif harus jelas
- preview harus menunjukkan interaksi skala

---

## 7.6 Numeric
### Tampilan
- input numerik sederhana dan bersih
- jika ada range, tampilkan helper text

### UX notes
- validasi range penting
- desimal hanya muncul jika diizinkan

---

## 7.7 Open Text
### Tampilan
- textarea atau input tunggal tergantung `input_type`
- tampilkan character limit jika ada

### UX notes
- jangan terlalu penuh border
- fokus pada readability

---

## 7.8 Image Button
### Tampilan
- grid image cards
- support show/hide labels
- support image fit
- support answers per row

### UX notes
- image picker harus nyaman dipakai admin
- preview harus jelas menunjukkan hasil layout

---

## 7.9 Info Screen
### Tampilan
- terlihat seperti content/interstitial screen
- bukan screen input jawaban

### UX notes
- tetap harus terlihat sebagai bagian flow
- cocok untuk intro singkat, pesan transisi, atau penjelasan

---

## 8. Jump Logic UI

ScoreApp menekankan builder yang membangun logic, bukan hanya tampilan. Karena itu, jump logic di YogaFX harus terasa sebagai fitur builder inti, bukan setting tersembunyi. :contentReference[oaicite:8]{index=8}

## 8.1 Question-Level Jump
Jika jump di level question aktif:
- tampilkan toggle
- setelah aktif, tampilkan dropdown target question

## 8.2 Answer-Level Jump
Jika jump di level answer aktif:
- tampilkan toggle di answer row
- setelah aktif, tampilkan dropdown target question

## 8.3 UI rule
- gunakan progressive disclosure
- jangan tampilkan dropdown target jika toggle masih off
- beri label jelas: “Jump to Question”

---

## 9. Scoring UI

ScoreApp menempatkan scoring sebagai bagian penting dari builder dan result engine. :contentReference[oaicite:9]{index=9}

## 9.1 Answer-Level Scoring
Jika `Score Answer` dinyalakan:
- langsung tampil input score value

Ini harus menjadi behaviour resmi UI.

## 9.2 Score Categories
Untuk fase awal, cukup:
- `Overall only`

Tapi UI harus tetap terlihat siap untuk ekspansi kategori jika nanti dibutuhkan.

## 9.3 Score Visibility
Di builder:
- scoring harus mudah dilihat
- jangan tersembunyi terlalu dalam
- tetapi juga jangan membuat panel penuh field angka sejak awal

---

## 10. Results Configuration UI

ScoreApp menekankan pentingnya results page dan personalized outcome. :contentReference[oaicite:10]{index=10}

## 10.1 Result Ranges
YogaFX scoreboard sebaiknya punya UI untuk:
- add result range
- set min score
- set max score
- set title
- set description

## 10.2 UX notes
- result ranges lebih baik tampil seperti list of cards/rows
- overlap harus dicegah
- gap sebaiknya dicegah

## 10.3 If No Result Range
Scoreboard tetap boleh live tanpa result range.  
UI harus menjelaskan bahwa:
- jika tidak ada result range
- hasil akhir hanya score mentah

---

## 11. Global Design Settings UX

ScoreApp punya global branding/theme settings dan page builder yang mendukung branding yang konsisten. :contentReference[oaicite:11]{index=11}

## 11.1 YogaFX Design Panel Should Support
- logo upload
- logo size / max-width
- logo alignment
- logo link
- header position
- section background
- top margin
- bottom margin
- footer content

## 11.2 UX notes
- setting global design sebaiknya dipisahkan dari question editing biasa
- bisa sebagai panel/tab/settings drawer terpisah
- jangan campur semua settings desain ke panel question agar tidak terlalu penuh

---

## 12. Student-Facing Experience Direction

Walaupun admin builder mengikuti pola ScoreApp, student-facing flow di YogaFX tetap harus terasa:
- clean
- premium
- focused
- easy to answer

### Student side rules
- satu screen satu question
- navigasi jelas
- progress bar optional
- hasil akhir mudah dipahami
- tidak terasa seperti quiz sekolah lama

---

## 13. What to Imitate From ScoreApp

### Imitate
- builder mindset
- list -> open builder flow
- separation of structure / content / settings
- strong scoring emphasis
- clear branding/theme controls
- results as first-class feature
- modern product feel

### Do Not Blindly Copy
- marketing-heavy copy style
- lead generation specific wording
- public landing page patterns yang tidak relevan dengan LMS internal
- semua fitur page builder publik mereka jika tidak dibutuhkan untuk scoreboard builder YogaFX

---

## 14. Final Implementation Direction for Codex

Codex harus membangun UI scoreboard YogaFX dengan arah berikut:

1. **Scoreboards List**
   - modern list/page
   - create/open/edit meta/delete

2. **Scoreboard Builder**
   - left question list
   - center preview/editor
   - right config panel

3. **Question Tab**
   - type config
   - required/instruction/randomize/jump
   - type-specific settings

4. **Answers Tab**
   - answer rows
   - scoring
   - jump
   - image if needed
   - internal values if needed

5. **Results Config**
   - range-based outcomes
   - not just total score

6. **Design Settings**
   - global visual controls
   - branding-like setup

7. **Student Flow**
   - one question per screen
   - clean progression
   - meaningful result output

---

## 15. Final Summary

UI scoreboard YogaFX sebaiknya meniru ScoreApp dalam hal:
- builder structure
- scoring-first thinking
- result-driven setup
- clean configuration experience
- global branding controls

Namun UI tetap harus disesuaikan dengan identitas YogaFX:
- premium
- calm
- elegant
- educational
- wellness-focused

Jadi target akhirnya bukan “clone ScoreApp”, tetapi:
**YogaFX Scoreboard Builder with ScoreApp-level clarity and modernity.**