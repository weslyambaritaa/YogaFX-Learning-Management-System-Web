Simpan sebagai **`docs/mobile-system-overview.md`**:

````md
# YogaFX Mobile Student App — System Overview

## Purpose

Dokumen ini menjadi source of truth tingkat tinggi untuk produk **YogaFX Mobile Student App**.

Aplikasi ini adalah **mobile frontend baru** yang dibangun terpisah dari web frontend, tetapi tetap memakai **backend, database, business logic, dan media infrastructure** dari sistem YogaFX yang sudah ada.

Tujuan dokumen ini adalah memastikan bahwa seluruh pihak yang terlibat memahami dengan jelas:

- apa itu mobile app ini
- siapa penggunanya
- apa cakupan fiturnya
- bagaimana relasinya dengan sistem web yang sudah ada
- apa batasan produknya
- apa arah experience yang diinginkan

---

## Product Summary

YogaFX Mobile Student App adalah aplikasi mobile khusus **student** untuk platform YogaFX.

Aplikasi ini bukan sistem baru yang berdiri sendiri secara backend.  
Aplikasi ini adalah **client mobile** yang mengonsumsi API dari backend YogaFX yang sudah ada.

Arsitektur produk:

```text
Existing YogaFX Backend
↓
Mobile API Layer
↓
Flutter Mobile App
↓
Android + iOS Student Experience
````

---

## Main Product Goal

Target utama mobile app ini adalah:

* menghadirkan **seluruh pengalaman student** dari web ke mobile
* mempertahankan **full parity sedekat mungkin** dengan web student
* memberikan experience yang terasa seperti **Netflix-style app**
* tetap memakai business rules, content, progress, dan media yang sama dari backend existing

---

## Users

### Primary User

* Student

### Not Included

Aplikasi mobile ini **tidak** ditujukan untuk:

* Admin
* Public lead
* Scoreboard public participant
* Marketing team
* Teacher dashboard
* Internal operator

Jadi role mobile untuk fase ini adalah **student only**.

---

## Product Boundary

Mobile app ini hanya mencakup domain student dari sistem YogaFX yang sudah ada.

### Included

* login student
* dashboard student
* modules
* lessons
* lesson video
* lesson audio
* workbook / file lesson
* assessment
* assignment
* certificate
* profile
* change password
* progress / completion state yang sama dengan web

### Not Included

* admin dashboard
* LMS admin CRUD
* public scoreboard system
* lead funnel system
* marketing landing pages
* public webhook participant experience
* CMS / backoffice non-student area

---

## Core Principle

Mobile app ini bukan “versi ringan” dari web.
Arah produknya adalah **full parity sedekat mungkin** dengan web student, sejak awal.

Artinya, mobile app harus berusaha mendukung fitur web student berikut secara lengkap:

* content browsing
* learning flow
* media consumption
* assessment flow
* assignment submission
* certificate access
* profile management

---

## Technology Direction

### Backend

Tetap memakai backend YogaFX yang sudah ada:

* Laravel backend
* database existing
* business logic existing
* media integrations existing

### Mobile Frontend

Aplikasi mobile dibuat dengan:

* **Flutter**

### Platforms

Target platform:

* Android
* iOS

### API Model

Backend harus menyediakan **API khusus mobile**.

Arah versioning:

* `/api/mobile/v1/...`

### Authentication

Mobile app memakai:

* **token-based auth**
* student login dengan:

  * email
  * password

---

## Authentication Model

Mobile app tidak memakai session web tradisional.

Student login dengan:

* email
* password

Backend mengembalikan token untuk mobile.

### Auth Behaviour

* satu device bisa tetap login lama
* token-based auth menjadi dasar akses API mobile

### Future Note

Forgot / reset password bisa dikembangkan kemudian, tetapi fase awal fokus pada login + authenticated student flow.

---

## Design Direction

Client menginginkan tampilan mobile app yang **sangat dekat dengan Netflix**.

Bukan sekadar “terinspirasi”, tetapi:

* dark-first
* immersive
* cinematic
* horizontal browsing patterns
* bold visual hierarchy
* content-first

### Product UX Direction

Mobile app harus terasa seperti:

* app streaming premium
* bukan portal sekolah
* bukan LMS kampus
* bukan dashboard admin
* bukan SaaS form-heavy app

### Continuity with YogaFX

Walaupun visualnya Netflix-like, experience tetap harus terasa sebagai produk YogaFX.

---

## Admin vs Mobile

Admin tetap hidup di sistem web existing.

Mobile app ini tidak perlu membawa admin interface ke mobile.

Artinya:

* semua content dikelola dari admin web
* mobile hanya menjadi consumption + interaction layer untuk student

---

## Content Model

Mobile app harus mengikuti content model yang sudah ada di backend:

* modules
* lessons
* assessments
* assignments
* certificates

Aplikasi mobile **tidak** membuat content model baru.
Semua data berasal dari sistem existing.

---

## Media Model

### Video

Lesson video tetap memakai:

* Bunny Stream
* HLS playback

Mobile app harus bisa memutar video lesson dari backend existing dengan source Bunny Stream yang sama.

### Audio

Lesson audio tetap memakai:

* asset/url dari backend existing
* storage/backend pattern yang sama dengan web

### Workbook / File

Workbook dan file lesson lain tetap berasal dari backend existing.

Mobile app harus bisa:

* membuka
* mengunduh
* atau mengakses file tersebut sesuai behaviour yang sudah disepakati

---

## Lesson Experience

Lesson experience di mobile harus mendukung:

* opening lesson
* video playback
* audio playback
* workbook access
* lesson content display
* assessment relation
* progress/completion state

Jika lesson memiliki:

* video
* audio
* workbook

maka mobile app harus menampilkannya sebagai bagian dari lesson experience.

---

## Assessment Experience

Assessment di mobile harus mendukung **100% sama dengan web** sedekat mungkin.

Artinya:

* question flow
* answer flow
* scoring
* result
* completion state
* semua logic utama assessment builder/player harus bisa berjalan di mobile

Assessment bukan fitur sekunder.
Assessment adalah fitur inti yang harus hadir sejak fase awal mobile.

---

## Assignment Experience

Student juga harus bisa melakukan assignment dari mobile.

### Phase 1 requirement

* upload file video assignment dari mobile
* cukup upload file video terlebih dahulu
* belum perlu direct camera recording sebagai requirement awal

---

## Certificate Experience

Student mobile harus bisa:

* melihat certificate
* membuka / mengunduh certificate

Untuk sekarang:

* lihat dan download cukup
* share certificate belum menjadi requirement utama fase awal

---

## Online-Only Model

Fase awal mobile app bersifat:

* **online-only**

Artinya:

* tidak perlu offline mode
* tidak perlu caching offline kompleks
* tidak perlu sync offline

Semua data diambil langsung dari backend/API saat dibutuhkan.

---

## Account Model

Student yang sudah ada di sistem web existing harus bisa login ke mobile dengan akun yang sama.

Artinya:

* tidak ada sistem akun student terpisah
* tidak ada database identity terpisah
* mobile hanya memakai student identity existing

---

## API Ownership Principle

Business logic harus tetap hidup di backend.

Mobile frontend tidak boleh menciptakan rule bisnis baru sendiri jika rule tersebut seharusnya hidup di backend.

Contoh:

* lock/unlock content
* progress state
* assessment logic
* result logic
* assignment status
* certificate visibility

Semua itu harus tetap ditentukan oleh backend.

---

## API Versioning Principle

Karena mobile akan memakai repo frontend terpisah, API harus diperlakukan sebagai kontrak yang jelas.

Arah yang diinginkan:

* `/api/mobile/v1/...`

Tujuannya:

* memisahkan kontrak mobile dari web implementation
* memudahkan perubahan di masa depan
* menjaga kestabilan app mobile

---

## Repo Strategy

### Backend API

Tetap berada di repo backend existing.

### Mobile Frontend

Dibuat di repo terpisah sebagai aplikasi Flutter.

Artinya:

* satu repo untuk backend + web existing
* satu repo khusus Flutter mobile student app

---

## Delivery Philosophy

Urutan besar pengerjaan mobile nanti harus mengikuti:

```text
Backend Existing
↓
Mobile API Contract
↓
Flutter App Foundation
↓
Student Feature Parity
↓
UX Refinement
```

Bukan mulai dari UI tanpa memastikan kontrak API dan flow student terlebih dahulu.

---

## Success Criteria

Mobile app dianggap menuju arah yang benar jika:

1. student bisa login dengan akun existing
2. dashboard student tampil
3. student bisa melihat modules dan lessons
4. video/audio/workbook lesson berjalan
5. assessment berjalan
6. assignment berjalan
7. certificate terlihat
8. profile bisa diakses
9. experience terasa seperti mobile streaming app, bukan LMS tradisional

---

## Non-Goals for This Product

Untuk fase ini, mobile app bukan untuk:

* admin
* scoreboard public users
* CRM users
* landing page visitors
* marketing ops
* offline-first learning
* public content builder

---

## Final Summary

YogaFX Mobile Student App adalah:

* student-only mobile client
* built with Flutter
* connected to existing YogaFX backend
* powered by token-based auth
* targeted for Android + iOS
* expected to mirror web student features as closely as possible
* designed with a Netflix-like immersive interface
* fully online
* dependent on mobile-specific API layer inside the existing backend repo

Dokumen ini menjadi dasar untuk:

* mobile architecture
* mobile API requirements
* mobile modular implementation
* mobile Codex implementation prompts
