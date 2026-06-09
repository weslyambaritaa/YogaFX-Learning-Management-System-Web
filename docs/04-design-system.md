# 04-design-system.md
# YogaFX LMS Design System

## 1. Purpose of This Document

Dokumen ini menjadi source of truth visual dan UI/UX untuk proyek YogaFX LMS.

Dokumen ini digunakan sebagai acuan utama untuk:
- membangun halaman student side
- membangun halaman admin side
- menjaga konsistensi layout
- menjaga konsistensi visual component
- menjaga arah pengalaman pengguna
- membantu AI Coding Assistant membangun UI tanpa menebak gaya visual

Dokumen ini **tidak membahas implementasi teknis**, CSS, atau kode program. Fokusnya adalah:
- prinsip desain
- bahasa visual
- pengalaman pengguna
- standar komponen
- pedoman layout
- konsistensi produk

---

## 2. Product Design Context

YogaFX LMS **bukan** LMS akademik tradisional.

Sistem ini **tidak boleh** terasa seperti:
- Moodle
- Google Classroom
- Blackboard
- Canvas LMS
- portal e-learning kampus
- dashboard administrasi pendidikan yang kaku

### Arah pengalaman yang diinginkan

### Student Side
Student side harus terasa seperti:
- platform streaming premium
- content discovery platform
- learning library modern
- video-first experience
- premium educational wellness platform

Referensi rasa pengalaman:
- Netflix
- MasterClass
- Skillshare discovery layer
- premium streaming membership platform

### Admin Side
Admin side boleh lebih:
- operational
- management-oriented
- structured
- data-driven
- efficient
- dashboard-centric

Namun tetap harus mempertahankan identitas YogaFX:
- premium
- clean
- calm
- elegant
- professional
- wellness-focused
- educational

---

## 3. Design Principles

## 3.1 Premium Simplicity
Tampilan harus terasa premium tanpa terlihat berlebihan. Hindari visual ramai, warna berlebihan, dan elemen dekoratif yang tidak perlu.

## 3.2 Calm Clarity
Setiap halaman harus terasa tenang, mudah dipahami, dan tidak membuat user lelah secara visual.

## 3.3 Content First
Konten pembelajaran adalah pusat pengalaman. Desain harus mendukung konten, bukan menyaingi konten.

## 3.4 Guided Progress
User harus selalu tahu:
- posisinya sekarang
- progresnya sejauh mana
- apa langkah berikutnya

## 3.5 Streaming-Like Discovery
Untuk student side, modul dan course harus terasa seperti katalog konten premium, bukan daftar akademik kaku.

## 3.6 Elegant Utility
Admin side harus tetap efisien, tetapi jangan terasa dingin atau terlalu teknis. Tetap bersih, teratur, dan modern.

## 3.7 Consistency Over Decoration
Konsistensi spacing, radius, card style, button style, dan typography lebih penting daripada variasi visual yang berlebihan.

## 3.8 Soft Confidence
UI harus terasa yakin, rapi, dan profesional tanpa tampil agresif.

---

## 4. Brand Personality

## 4.1 Visual Character
YogaFX LMS harus terasa:
- refined
- breathable
- spacious
- balanced
- polished
- soft-premium
- mindful

## 4.2 Emotional Character
Perasaan yang ingin dibangun:
- aman
- tenang
- fokus
- termotivasi
- tertuntun
- eksklusif namun tetap ramah

## 4.3 Product Feeling
Jika seseorang membuka YogaFX LMS, kesan pertamanya seharusnya:
- ini platform premium
- ini bukan portal akademik kaku
- ini tempat belajar yang modern
- ini produk wellness yang profesional
- ini pengalaman digital yang dirancang dengan sengaja

---

## 5. Student Experience Vision

Student side harus terasa seperti platform belajar premium yang berfokus pada konten dan progress, bukan sistem administrasi.

### 5.1 Desired Experience
Student harus merasa:
- sedang membuka perpustakaan pembelajaran premium
- bisa melanjutkan belajar dengan mudah
- kontennya terorganisir dengan baik
- progress-nya jelas
- tidak tersesat
- tidak dibebani UI yang terlalu teknis

### 5.2 Home Page Experience
Home page student harus terasa seperti:
- streaming platform homepage
- personalized learning entry point
- clean content discovery surface

### 5.3 Core Behaviour Expectations
Student harus bisa dengan cepat:
- melihat continue learning
- melihat module atau course yang tersedia
- memahami mana yang locked dan mana yang available
- memahami progres belajar
- mengakses lesson berikutnya tanpa berpikir terlalu banyak

### 5.4 Progress Experience
Progress harus:
- jelas
- mudah dibaca
- tidak terasa seperti raport akademik
- lebih terasa seperti journey completion

### 5.5 Discovery Experience
Discovery harus:
- nyaman
- visual
- berbasis card/catalog
- mudah dipindai
- tidak terlalu banyak teks di level awal

### 5.6 Guidance Experience
Student harus selalu tahu:
- apa yang sedang dipelajari
- apa yang sudah selesai
- apa yang harus dilakukan berikutnya
- apa yang masih terkunci dan kenapa

---

## 6. Admin Experience Vision

Admin side harus terasa seperti dashboard operasional modern yang:
- rapi
- cepat dipakai
- mudah discan
- efisien
- profesional

### 6.1 Desired Experience
Admin harus merasa:
- semua hal penting mudah ditemukan
- data terstruktur
- tabel dan form nyaman digunakan
- review assignment cepat dilakukan
- pengelolaan konten tidak membingungkan
- sistem terlihat serius dan dapat dipercaya

### 6.2 Admin Product Feel
Admin side harus:
- lebih tegas daripada student side
- lebih data-centric
- lebih utilitarian
- tapi tetap bersih dan premium

### 6.3 Admin Visual Behaviour
- gunakan card dan section separation yang jelas
- gunakan table yang ringan, bukan tabel berat penuh border
- gunakan form yang tenang dan terstruktur
- gunakan hierarchy yang kuat agar admin cepat membaca konteks

---

## 7. Color System

Warna harus mencerminkan wellness + premium + professional.

## 7.1 Primary Color
**Deep Forest / Deep Sage**
- Fungsi: warna identitas utama
- Dipakai untuk:
  - primary button
  - active navigation
  - key highlights
  - major emphasis
- Karakter: grounded, calm, premium, wellness-oriented

Suggested direction:
- deep green with muted tone
- bukan hijau neon
- bukan hijau terlalu terang

## 7.2 Secondary Color
**Warm Stone / Soft Sand**
- Fungsi: warna pendukung untuk surface atau subtle section
- Karakter: hangat, natural, organik, tenang

## 7.3 Accent Color
**Muted Gold / Soft Amber**
- Fungsi: highlight premium, badge khusus, emphasis ringan
- Karakter: elegant, premium, refined
- Gunakan hemat, jangan dominan

## 7.4 Success Color
**Soft Emerald**
- Fungsi:
  - completed
  - approved
  - successful actions
- Harus terlihat positif tanpa terlalu mencolok

## 7.5 Warning Color
**Muted Orange / Clay**
- Fungsi:
  - pending
  - caution
  - in review
- Harus jelas namun tetap lembut

## 7.6 Error Color
**Soft Crimson / Muted Red**
- Fungsi:
  - rejected
  - destructive action
  - validation error
- Tetap terlihat modern dan tidak terlalu agresif

## 7.7 Neutral Colors
Gunakan sistem neutral yang kuat:
- background utama: very light neutral
- surface card: white / near-white
- text utama: near-black / deep neutral
- text sekunder: muted gray
- border: soft neutral border
- muted background: pale neutral

### Color Philosophy
- jangan gunakan saturasi tinggi
- hindari warna neon
- utamakan warna yang terasa premium, natural, dan tenang
- student side sedikit lebih lembut
- admin side sedikit lebih tegas

---

## 8. Typography

## 8.1 Font Family
Gunakan:
- **Geist** atau font sans modern yang setara
- fallback: Inter / system sans

Karakter yang diinginkan:
- modern
- clean
- highly readable
- professional
- digital premium

## 8.2 Heading Style
Heading harus:
- tegas
- clean
- sedikit padat
- tidak terlalu playful
- tidak terlalu dekoratif

### Heading hierarchy
- H1: page headline utama
- H2: section headline
- H3: card headline / subsection
- H4: label kategori kecil

## 8.3 Body Text Style
Body text harus:
- sangat readable
- line-height nyaman
- tidak terlalu kecil
- tone tenang
- tidak terlalu tipis

## 8.4 Hierarchy Rules
- satu halaman hanya punya satu H1 kuat
- gunakan H2 untuk section besar
- gunakan body muted untuk supporting explanation
- gunakan small muted text untuk helper text
- jangan terlalu banyak ukuran font berbeda di satu layar

---

## 9. Spacing System

Gunakan spacing yang terasa lapang dan premium.

### Principles
- beri ruang bernapas
- hindari layar yang terlalu padat
- gunakan vertical rhythm yang konsisten
- setiap section harus punya separation jelas

### Spacing rhythm
- small spacing untuk hubungan dekat
- medium spacing untuk antar komponen dalam satu section
- large spacing untuk antar section
- extra large spacing untuk area hero, dashboard intro, page separation

### Rules
- student side lebih lapang
- admin side tetap lapang, tapi sedikit lebih rapat daripada student side
- gunakan container width yang konsisten
- hindari komponen saling menempel terlalu rapat

---

## 10. Border Radius

Gunakan radius yang modern, halus, dan premium.

### Guidelines
- card: rounded medium to large
- button: rounded medium
- input: rounded medium
- modal/sheet: rounded large
- badge: rounded full or medium depending on style

### Feel
Jangan terlalu sharp. Jangan terlalu bubble/cartoonish. Cari titik tengah yang elegan.

---

## 11. Shadow System

Gunakan shadow yang halus.

### Principles
- shadow dipakai untuk depth ringan
- jangan terlalu gelap
- jangan terlalu blur besar
- lebih baik subtle daripada dramatis

### Usage
- card default: shadow sangat ringan
- hover card: sedikit naik
- modal/sheet: shadow lebih kuat tapi tetap lembut
- dropdown: shadow cukup jelas untuk separation

---

## 12. Iconography

Gunakan icon set modern dan ringan.

### Direction
- Lucide style
- line icon
- clean
- minimal
- konsisten weight-nya

### Usage Rules
- icon untuk mendukung, bukan menggantikan label
- icon tidak boleh terlalu ramai
- gunakan icon terutama untuk:
  - navigation
  - status
  - action
  - metadata ringan

---

## 13. Component Guidelines

## 13.1 Button

### Purpose
Menjalankan aksi utama dan sekunder.

### Visual Style
- solid primary untuk CTA utama
- outline / secondary untuk aksi sekunder
- ghost untuk aksi ringan
- destructive untuk delete/reject

### Interaction Behaviour
- clear hover state
- clear disabled state
- loading state wajib untuk aksi penting
- focus state harus jelas

### Usage Guidelines
- satu area cukup satu primary CTA dominan
- jangan terlalu banyak primary button dalam satu card
- destructive hanya untuk aksi berisiko

---

## 13.2 Input

### Purpose
Mengumpulkan data teks pendek.

### Visual Style
- clean
- medium height
- soft border
- comfortable padding
- background putih atau neutral sangat terang

### Interaction Behaviour
- focus ring jelas
- error state jelas
- placeholder muted

### Usage Guidelines
- label harus selalu jelas
- helper/error text tampil di bawah
- jangan bergantung pada placeholder sebagai label utama

---

## 13.3 Select

### Purpose
Memilih satu opsi dari daftar.

### Visual Style
- visual seragam dengan input
- icon dropdown ringan
- panel dropdown bersih

### Interaction Behaviour
- open/close halus
- selected state jelas
- hover state jelas

### Usage Guidelines
- gunakan untuk pilihan tetap
- hindari option yang terlalu panjang tanpa grouping jika datanya besar

---

## 13.4 Textarea

### Purpose
Mengisi teks panjang seperti feedback atau body email.

### Visual Style
- konsisten dengan input
- padding nyaman
- resize behaviour terkendali

### Interaction Behaviour
- focus state jelas
- error state jelas

### Usage Guidelines
- cocok untuk:
  - feedback reviewer
  - body email
  - deskripsi
  - notes admin

---

## 13.5 Checkbox

### Purpose
Memilih boolean atau multi-select ringan.

### Visual Style
- clean, tegas, minimal
- konsisten dengan sistem warna

### Interaction Behaviour
- checked state jelas
- keyboard accessible
- focus visible

### Usage Guidelines
- cocok untuk toggle kecil, persetujuan, dan daftar multi pilihan ringan

---

## 13.6 Radio Button

### Purpose
Memilih satu opsi dari beberapa pilihan yang saling eksklusif.

### Visual Style
- clean
- visible checked state
- tidak terlalu kecil

### Interaction Behaviour
- klik area label juga harus nyaman
- focus state terlihat

### Usage Guidelines
- gunakan ketika semua opsi penting terlihat bersamaan

---

## 13.7 Card

### Purpose
Menjadi container utama untuk hampir semua content block.

### Visual Style
- clean white or near-white surface
- soft border
- subtle shadow
- rounded medium-large

### Interaction Behaviour
- hover optional untuk card klikable
- non-clickable card tetap statis
- elevasi naik sedikit saat interactive

### Usage Guidelines
- student catalog memakai card sebagai elemen utama
- dashboard summary memakai card
- form section juga dapat dibungkus card

---

## 13.8 Modal

### Purpose
Menampilkan aksi fokus singkat atau konfirmasi.

### Visual Style
- centered
- rounded
- clean
- shadow premium
- overlay lembut

### Interaction Behaviour
- ESC close
- click overlay close jika aman
- destructive modal harus jelas

### Usage Guidelines
- gunakan untuk konfirmasi
- gunakan untuk quick edit
- jangan terlalu banyak field kompleks di modal jika bisa jadi page sendiri

---

## 13.9 Drawer / Sheet

### Purpose
Menampilkan detail tambahan atau panel kerja samping.

### Visual Style
- side panel clean
- width proporsional
- lebih terasa modern daripada modal untuk detail record

### Interaction Behaviour
- slide in halus
- close behavior jelas

### Usage Guidelines
- cocok untuk:
  - detail student
  - detail assignment
  - quick preview
  - filter panel

---

## 13.10 Badge

### Purpose
Menampilkan status kecil atau kategori.

### Visual Style
- compact
- rounded
- high contrast enough
- warna sesuai status

### Interaction Behaviour
- biasanya non-interactive
- bisa clickable jika memang filter/tag

### Usage Guidelines
Gunakan untuk:
- active
- pending
- approved
- rejected
- locked
- completed
- tier label

---

## 13.11 Alert

### Purpose
Menampilkan feedback sistem penting.

### Visual Style
- clean
- icon optional
- status color subtle
- border dan background lembut

### Interaction Behaviour
- closeable jika non-critical
- persistent jika critical

### Usage Guidelines
Gunakan untuk:
- success message
- warning
- error
- guidance penting

---

## 13.12 Table

### Purpose
Menampilkan data admin secara terstruktur.

### Visual Style
- ringan
- bersih
- row height nyaman
- border halus
- jangan terasa seperti spreadsheet lama

### Interaction Behaviour
- sortable jika perlu
- hover row ringan
- action column jelas

### Usage Guidelines
- admin side only as primary pattern
- student side sebisa mungkin lebih banyak pakai card/list, bukan table

---

## 13.13 Pagination

### Purpose
Navigasi data berhalaman.

### Visual Style
- clean
- compact
- jelas current page-nya

### Interaction Behaviour
- state disabled jelas
- mobile-friendly

### Usage Guidelines
- gunakan di halaman admin list/data-heavy

---

## 13.14 Tabs

### Purpose
Mengelompokkan section yang sejenis dalam satu layar.

### Visual Style
- minimal
- active tab jelas
- tidak terlalu dekoratif

### Interaction Behaviour
- active state kuat
- keyboard accessible

### Usage Guidelines
- cocok untuk:
  - detail student
  - email sub settings
  - assessment builder panel
  - profile sections

---

## 13.15 Dropdown

### Purpose
Menyimpan action sekunder agar UI tetap rapi.

### Visual Style
- compact
- shadow lembut
- spacing rapi

### Interaction Behaviour
- open/close cepat
- menu item hover jelas

### Usage Guidelines
- gunakan untuk row actions
- jangan sembunyikan aksi kritis utama ke dropdown jika harus cepat diakses

---

## 13.16 Tooltip

### Purpose
Menjelaskan icon atau elemen ringkas.

### Visual Style
- kecil
- clean
- high contrast
- rounded

### Interaction Behaviour
- muncul cepat tapi tidak mengganggu

### Usage Guidelines
- gunakan untuk icon-only actions
- jangan masukkan informasi penting panjang ke tooltip

---

## 13.17 Sidebar

### Purpose
Navigasi utama, terutama di admin area.

### Visual Style
- clean vertical navigation
- background slightly tinted or neutral
- active item jelas
- icon + label

### Interaction Behaviour
- collapse optional
- active state kuat
- nested menu jelas

### Usage Guidelines
- admin side: sidebar utama
- student side: bisa sidebar ringan atau top navigation tergantung halaman

---

## 13.18 Navigation

### Purpose
Membantu user berpindah antar area utama.

### Visual Style
- sederhana
- jelas
- konsisten

### Interaction Behaviour
- active state jelas
- hover state jelas

### Usage Guidelines
- student side lebih fokus pada discoverability
- admin side lebih fokus pada efficiency

---

## 13.19 Header

### Purpose
Menyediakan konteks halaman dan action level atas.

### Visual Style
- minimal
- clean
- sticky optional
- page title jelas

### Interaction Behaviour
- support breadcrumbs jika perlu
- action button dapat muncul di kanan

### Usage Guidelines
- gunakan untuk page title, contextual actions, search/filter ringan

---

## 13.20 Footer

### Purpose
Area sekunder untuk informasi tambahan.

### Visual Style
- sangat ringan
- tidak dominan

### Interaction Behaviour
- mostly static

### Usage Guidelines
- student side boleh memiliki footer tipis
- admin side footer bisa minimal sekali atau bahkan tidak perlu jika dashboard full-app style

---

## 14. Student Layout Guidelines

Student side harus menjadi area paling premium secara rasa produk.

## 14.1 Home Page

### Objective
Menjadi learning homepage yang terasa seperti streaming platform premium.

### Structure
- welcome / greeting area
- continue learning section
- featured module or featured course
- module catalog
- ebook/resource section
- progress summary ringan

### Design Direction
- hero area lembut, tidak terlalu bombastis
- card-based catalog
- horizontal scrolling section diperbolehkan untuk discovery
- visual hierarchy harus langsung mengarahkan user ke langkah berikutnya

### Feel
Harus terasa seperti:
- “lanjutkan belajar”
- “konten premium”
- “terkurasi”
- “modern”

---

## 14.2 Continue Learning Section

### Objective
Menjadi elemen paling penting di home page.

### Must Show
- current lesson/module
- progress bar
- next action button
- optional thumbnail / poster style visual

### Behaviour
- selalu berada di atas fold
- satu CTA utama: Continue Learning

### Visual Direction
- biggest emphasis on page
- premium highlight card
- strong but calm contrast

---

## 14.3 Module Catalog

### Objective
Menampilkan daftar module sebagai katalog konten premium.

### Layout Direction
- grid cards di desktop
- stacked cards di mobile
- thumbnail-first
- title + short metadata + progress/status

### Status Visibility
Harus jelas:
- available
- locked
- in progress
- completed

### Visual Note
Jangan terasa seperti daftar pelajaran sekolah.

---

## 14.4 Lesson Detail Page

### Objective
Menjadi halaman transisi sebelum benar-benar masuk ke pengalaman belajar.

### Must Show
- lesson title
- status
- workbook requirement
- video section
- content section
- assessment CTA / locked notice

### Visual Direction
- clean content reading layout
- tidak terlalu banyak gangguan
- hierarchy harus mendukung fokus belajar

---

## 14.5 Video Learning Page

### Objective
Menjadi pusat pengalaman belajar untuk lesson berbasis video.

### Must Show
- video player dominan
- judul lesson
- progress info
- workbook status
- content tambahan
- next step

### Design Direction
- player jadi hero utama
- supporting info di bawah / samping
- tetap premium seperti streaming lesson

---

## 14.6 Assessment Page

### Objective
Membuat assessment terasa fokus dan modern, bukan seperti ujian sekolah kuno.

### Must Show
- timer
- progress
- current question/page
- navigation
- submit action

### Design Direction
- clean
- focused
- distraction minimal
- progress terlihat jelas
- urgency ada, tapi tidak menegangkan secara berlebihan

---

## 14.7 Assignment Page

### Objective
Membantu student memahami status assignment dan langkah selanjutnya.

### Must Show
- assignment list
- status
- upload action
- feedback jika rejected

### Design Direction
- status jelas
- feedback mudah ditemukan
- CTA re-upload jelas

---

## 14.8 Certificate Page

### Objective
Menjadi halaman hasil / milestone yang terasa rewarding.

### Must Show
- certificate type
- availability
- download action
- status jika belum tersedia

### Design Direction
- sedikit lebih celebratory
- tetap elegant
- tidak berlebihan

---

## 15. Admin Layout Guidelines

## 15.1 Dashboard

### Objective
Menyediakan ringkasan operasional dan akses cepat.

### Structure
- summary cards
- recent activity / pending items
- quick access actions
- compact but breathable

### Feel
- efficient
- structured
- modern

---

## 15.2 CRUD Pages

### Objective
Memudahkan admin mengelola data dengan cepat dan jelas.

### Layout Rules
- header dengan title + primary action
- filter/search di atas list jika perlu
- content utama dalam table atau card list
- form dalam section yang jelas

---

## 15.3 Tables

### Objective
Menampilkan data management dengan efisien.

### Guidelines
- gunakan table untuk data-heavy page
- action column di kanan
- status pakai badge
- search/filter di area atas
- row detail bisa modal/sheet jika cocok

---

## 15.4 Forms

### Objective
Memastikan admin bisa mengisi dan mengubah data tanpa bingung.

### Guidelines
- group field berdasarkan konteks
- gunakan heading/subheading untuk section besar
- gunakan max width nyaman
- tombol action di area yang jelas
- helper text dipakai bila dibutuhkan

---

## 15.5 Reports

### Objective
Menyajikan ringkasan data penting untuk pengambilan keputusan cepat.

### Guidelines
- visual sederhana
- gunakan cards, simple charts jika memang perlu, dan summary table
- prioritaskan readability
- jangan terlalu dekoratif

---

## 16. Responsive Design Rules

## 16.1 Mobile

### Student Side
- harus tetap usable dengan nyaman
- home page boleh vertical-first
- continue learning harus tetap jadi elemen utama
- catalog berubah menjadi stack atau carousel sederhana
- action utama harus reachable

### Admin Side
- tetap usable, tetapi tidak semua halaman admin harus seoptimal student side di mobile
- table bisa berubah menjadi stacked card
- sidebar menjadi drawer

---

## 16.2 Tablet
- gunakan layout transisi
- mulai tampilkan grid lebih lebar
- sidebar bisa collapsible
- card catalog bisa 2–3 kolom

---

## 16.3 Desktop
- gunakan ruang dengan seimbang
- student side lebih immersive
- admin side lebih information-dense tapi tetap clean

---

## 17. Accessibility Guidelines

- Kontras warna harus cukup jelas.
- Focus state harus terlihat.
- Semua komponen interaktif harus keyboard accessible.
- Form input harus punya label.
- Error message harus jelas dan dekat dengan field terkait.
- Jangan gunakan warna sebagai satu-satunya indikator status.
- Badge/status perlu didukung teks yang jelas.
- Hover-only information tidak boleh menjadi satu-satunya sumber informasi penting.
- Ukuran klik/tap target harus nyaman, terutama di mobile.

---

## 18. Design Do's and Don'ts

## Do's
- buat student side terasa seperti platform learning premium
- prioritaskan continue learning
- gunakan card-based content catalog
- tampilkan progress dengan cara yang ringan dan jelas
- gunakan banyak whitespace
- gunakan visual hierarchy yang tenang
- gunakan shadcn/ui secara konsisten
- buat admin side efisien dan data-friendly
- gunakan badge untuk status
- gunakan modal/sheet untuk detail atau quick action
- pertahankan tone visual YogaFX yang calm, elegant, premium

## Don'ts
- jangan membuat student side terasa seperti portal kampus
- jangan gunakan layout yang terlalu padat
- jangan gunakan tabel sebagai pola utama di student side
- jangan gunakan warna terlalu cerah atau terlalu agresif
- jangan gunakan banyak border keras
- jangan buat halaman terlihat penuh elemen kecil yang saling berebut perhatian
- jangan terlalu banyak CTA primer dalam satu area
- jangan gunakan icon tanpa label jika konteks belum sangat jelas
- jangan meniru gaya Moodle atau Google Classroom
- jangan membangun visual yang terlalu “corporate dashboard” di student side

---

## 19. Final Design Direction Summary

### Student Side
Harus terasa seperti:
- Netflix for learning
- premium streaming wellness education platform
- modern, soft, curated learning catalog

### Admin Side
Harus terasa seperti:
- professional management console
- clear, efficient, structured operational dashboard

### Overall YogaFX Identity
Harus selalu terasa:
- premium
- calm
- elegant
- clean
- wellness-focused
- educational
- modern
- trustworthy