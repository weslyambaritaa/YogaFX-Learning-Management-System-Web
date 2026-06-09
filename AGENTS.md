# AGENTS.md

## Project Overview

YogaFX LMS adalah platform pembelajaran web premium untuk YogaFX. Produk ini bukan LMS akademik tradisional dan saat ini memiliki dua role aktif:
- **Admin**
- **Student**

Produk dipisahkan menjadi dua pengalaman:
- **Student Side**: premium, calm, content-first
- **Admin Side**: structured, professional, management-oriented

Stack dan arah arsitektur sudah ditetapkan. Jangan mendesain ulang stack atau mental model produk saat implementasi.

---

## Source of Truth Documents

Sebelum mengerjakan fitur apa pun, baca dokumentasi dalam urutan berikut:

1. `docs/00-current-project-status.md`
2. `docs/01-prd.md`
3. `docs/02-user-flow.md`
4. `docs/03-erd.md`
5. `docs/04-design-system.md`
6. `docs/05-information-architecture.md`
7. `docs/06-modular-implementation.md`

Jika domain yang dikerjakan punya dokumen khusus, baca juga setelah dokumen inti:
- `docs/student-progress-flow.md`
- `docs/email-notification-flow.md`

Dokumen dalam `docs/history/` adalah arsip implementasi fase sebelumnya dan tidak menjadi source of truth aktif.

### Reading Order Rules
- `00-current-project-status.md` menjelaskan **apa yang sudah benar-benar terimplementasi**
- `01-prd.md` menjelaskan **scope produk aktif**
- `02-user-flow.md` menjelaskan **alur user yang sudah hidup atau sudah disetujui**
- `03-erd.md` menjelaskan **entity dan relasi data aktif**
- `04-design-system.md` menjelaskan **arah visual dan guardrail UI**
- `05-information-architecture.md` menjelaskan **penempatan halaman, menu, dan grouping fitur**
- `06-modular-implementation.md` menjelaskan **status fase dan urutan pengembangan**

Jangan melewati urutan baca ini.

---

## Diagram Location

Seluruh diagram Mermaid berada di:
- `docs/mermaid/`

Diagram digunakan sebagai visual support. Jika diagram bertentangan dengan dokumen tertulis, prioritaskan dokumen tertulis.

---

## Core Development Rules

1. **Follow approved scope only.**
   Jangan menambah fitur di luar dokumentasi aktif.

2. **Build incrementally.**
   Kerjakan satu domain atau subdomain dalam satu waktu.

3. **Respect dependency order.**
   Jangan mengerjakan domain hilir sebelum fondasi domain hulunya stabil.

4. **Do not redesign architecture.**
   Stack, role model, dan arah produk sudah diputuskan.

5. **Respect the product model.**
   Student side harus terasa seperti premium learning platform, bukan portal sekolah.

6. **Admin and Student are distinct experiences.**
   Jangan campur mental model student dan admin.

7. **Current documentation beats stale assumptions.**
   Jika dokumentasi lama atau ingatan lama bertentangan dengan `docs/00-current-project-status.md`, ikuti dokumen terbaru.

8. **ERD is an implementation document, not a wishlist.**
   Jangan tambahkan entity atau relasi baru tanpa dukungan requirement yang jelas.

9. **IA defines placement.**
   Jangan membuat halaman atau navigasi baru di luar penempatan yang sudah didokumentasikan.

10. **Do not overbuild unfinished domains.**
    Jika domain future belum aktif, jangan diam-diam mengimplementasikan seluruh domain tersebut.

---

## Coding Principles

1. **Clarity over cleverness**
2. **Maintainability first**
3. **Reuse only when it helps**
4. **Business rules before UI polish**
5. **Keep domains isolated**
6. **Avoid speculative abstractions**
7. **Respect role boundaries**
8. **Respect product tone**
9. **Keep important rules explicit**
10. **Do not shortcut around requirements**

---

## Implementation Workflow

### Step 1 - Read First
Sebelum menyentuh kode, baca bagian yang relevan dari:
- Current Project Status
- PRD
- User Flow
- ERD
- Design System
- Information Architecture
- Modular Implementation Guide
- dokumen domain khusus jika ada

### Step 2 - Confirm Domain Scope
Identifikasi:
- domain yang sedang dikerjakan
- dependency yang harus sudah ada
- apa yang masih out of scope

### Step 3 - Implement in Layer Order
Urutan default:
1. data layer
2. backend/domain logic
3. frontend/page flow
4. integration points

### Step 4 - Validate Against Rules
Periksa:
- alur user
- role restriction
- tier restriction
- konsistensi data
- penempatan fitur di IA

### Step 5 - Stop at Domain Boundary
Jangan meluas ke domain lain tanpa permintaan eksplisit.

### Step 6 - Report What Changed
Saat selesai, laporkan:
- apa yang diimplementasikan
- dokumen apa yang diikuti
- apa yang tetap di luar scope

---

## Definition of Done

Sebuah domain atau fitur baru dianggap selesai jika:

1. **Requirement alignment**
   - sesuai `docs/00-current-project-status.md`
   - sesuai PRD
   - sesuai User Flow
   - sesuai ERD
   - sesuai Design System
   - sesuai Information Architecture

2. **Functional completeness**
   - use case utama berjalan end-to-end
   - validasi penting sudah ada
   - state penting tersimpan dan terlihat

3. **Boundary correctness**
   - role restriction benar
   - tier restriction benar
   - ownership restriction benar jika relevan

4. **UI correctness**
   - fitur muncul di tempat yang benar
   - flow cocok dengan perjalanan user yang didokumentasikan
   - tone student/admin tetap terjaga

5. **No unauthorized scope expansion**
   - tidak ada fitur tambahan yang tidak diminta

6. **Code quality**
   - readable
   - maintainable
   - konsisten dengan arah proyek

Fitur belum dianggap selesai hanya karena halaman sudah tampil.

---

## Conflict Resolution Rules

Jika ada konflik antar dokumen:

### Rule 1 - Latest agreed revision wins
Keputusan terbaru yang sudah disetujui mengalahkan asumsi lama.

### Rule 2 - Written docs beat diagrams
Jika Mermaid berbeda dengan dokumen tertulis, ikuti dokumen tertulis.

### Rule 3 - Current implementation docs beat stale planning docs
Jika dokumen lama masih menggambarkan future scope sebagai fitur aktif, ikuti dokumen yang sudah disinkronkan dengan implementasi aktual.

### Rule 4 - PRD defines active scope
Jika sebuah fitur tidak didukung PRD aktif atau status implementasi aktif, jangan menambahkannya.

### Rule 5 - IA defines placement
Jika lokasi fitur tidak jelas, ikuti Information Architecture.

### Rule 6 - ERD defines boundaries
Jika ide implementasi butuh perubahan entity/relationship, jangan lakukan tanpa kebutuhan yang jelas.

### Rule 7 - Ask before inventing
Jika konflik tidak bisa diselesaikan dari dokumentasi, berhenti dan klarifikasi.

---

## Rules Against Building Outside Requirement

Jangan:
- membuat fitur baru di luar dokumentasi aktif
- menambah role baru
- menambah entity baru tanpa dasar requirement
- mengubah produk menjadi LMS kampus
- membuat shortcut yang melemahkan tier/role restriction
- menganggap fitur planned sebagai fitur aktif

Jika sesuatu terasa berguna tetapi belum jelas diminta, anggap **out of scope**.

---

## Product Experience Guardrails

### Student Side
Jaga:
- premium feel
- calm visual flow
- content-first discovery
- clear next step guidance

Jangan membuat student side terasa seperti:
- Moodle
- Google Classroom
- Blackboard
- Canvas

### Admin Side
Jaga:
- clarity
- efficiency
- structured workflows
- strong status visibility

---

## Final Instruction to AI Coding Assistant

Sebelum mengimplementasikan apa pun:
1. baca dokumentasi sesuai urutan
2. pastikan domain yang sedang dikerjakan
3. konfirmasi dependency
4. implementasikan hanya dalam scope
5. validasi terhadap dokumen
6. berhenti saat domain yang diminta selesai

Do not guess.
Do not improvise requirements.
Do not silently expand scope.
Build YogaFX LMS sesuai dokumentasi aktif.
