Simpan sebagai **`docs/mobile-architecture.md`**:

````md
# YogaFX Mobile Student App — Architecture

## Purpose

Dokumen ini menjelaskan arsitektur sistem untuk YogaFX Mobile Student App.

Tujuan dokumen ini adalah memastikan bahwa pengembangan mobile dilakukan dengan boundary yang jelas:

- backend tetap memakai sistem existing
- API mobile dibuat di repo backend existing
- frontend mobile dibuat di repo Flutter terpisah
- business logic tetap canonical di backend
- mobile app menjadi client student baru, bukan sistem baru yang berdiri sendiri secara backend

Dokumen ini bukan daftar endpoint detail.  
Dokumen ini adalah peta arsitektur sistem.

---

## 1. Architecture Principle

Arsitektur mobile harus mengikuti prinsip berikut:

```text
Existing YogaFX Backend
↓
Mobile API Layer
↓
Flutter Mobile Student App
↓
Android + iOS
````

Mobile app tidak boleh membuat business rules baru yang seharusnya hidup di backend.

Backend tetap menjadi source of truth untuk:

* user/student identity
* modules
* lessons
* media references
* assessment logic
* assignment logic
* certificate access
* progress/completion state
* permissions and business rules

---

## 2. Repo Topology

Sistem dibagi menjadi dua repo utama:

## Repo A — Existing Web Backend

Repo ini tetap memuat:

* Laravel backend
* database
* existing admin
* existing web student
* mobile API layer baru

## Repo B — Flutter Mobile Frontend

Repo ini memuat:

* Flutter app
* student UI
* API client
* token auth client
* media playback client
* local app state
* navigation and screens

### Important Rule

Backend logic tidak dipindah ke Flutter.

Flutter hanya:

* mengonsumsi API
* merender UI
* mengelola local app state
* mengelola session/token di device

---

## 3. System Boundary

## In Backend Existing

Tetap hidup di repo lama:

* database tables
* models
* services
* mail
* media integrations
* admin CRUD
* web student pages
* mobile API routes/controllers/resources

## In Flutter Repo

Hanya hidup di repo mobile:

* UI mobile
* navigation
* state management
* API consumption
* token storage
* media playback implementation

## Not Allowed

Mobile app tidak boleh:

* membuat copy business rule yang berbeda dari backend
* menghitung logic completion sendiri jika backend sudah punya rule
* membuat truth baru tentang content structure
* menjadi admin app

---

## 4. Layered Architecture

## 4.1 Backend Layers

### Web/Admin Layer

Tetap melayani:

* admin dashboard
* web student experience
* existing web UI

### Mobile API Layer

Layer baru untuk mobile, misalnya:

* `/api/mobile/v1/...`

Tugasnya:

* expose contract mobile yang bersih
* transform data backend menjadi payload mobile-friendly
* enforce auth dan ownership
* menjaga agar frontend mobile tidak bergantung pada Inertia/web views

### Domain / Business Logic Layer

Tetap reuse existing services/rules sebanyak mungkin:

* progress
* lesson access
* assessments
* assignments
* certificates
* profile
* media URL logic

### Data Layer

Tetap existing database sebagai single source of truth.

---

## 4.2 Flutter Layers

### Presentation Layer

* screens
* widgets
* Netflix-like layout
* visual components
* navigation shell

### Application Layer

* use cases
* orchestration UI flow
* session state
* screen state
* loading/error state

### Data Layer

* repositories
* API client
* DTO/model mapping
* token persistence
* local temporary state

### Infrastructure Layer

* secure token storage
* media player adapter
* file open/download
* upload adapter
* network configuration

---

## 5. Authentication Architecture

## Final Direction

Mobile memakai **token-based auth**.

### Login Method

* email
* password

### Session Behaviour

* satu device bisa tetap login lama
* student tidak perlu login ulang terus-menerus
* logout tetap tersedia

### Backend Requirement

Backend harus menyediakan endpoint auth mobile, bukan reuse session web tradisional.

### Recommendation

Jika belum dikunci secara teknis, rekomendasi awal adalah **Laravel Sanctum** untuk token-based auth mobile, selama tidak ada kebutuhan OAuth/provider yang lebih kompleks.

### Scope for Now

* login
* logout
* current authenticated student
* protected student endpoints

Forgot/reset password akan disiapkan kemudian sebagai domain terpisah.

---

## 6. API Versioning Architecture

Semua API mobile harus dipisahkan jelas dari web flow.

### Prefix

`/api/mobile/v1`

### Reason

* menjaga kontrak mobile tetap stabil
* memisahkan mobile dari web implementation
* memudahkan perubahan di masa depan
* memudahkan version bump tanpa merusak app yang sudah terpasang

### API Contract Rule

API mobile harus:

* JSON-first
* predictable
* versioned
* student-safe
* independent dari structure HTML/Inertia web

---

## 7. Data Ownership Principle

Backend existing tetap menjadi owner data utama.

## Canonical Data Lives In Backend

* student profile
* modules
* lessons
* lesson media
* assessment data
* assignment data
* certificate data
* progress
* permissions

## Mobile Only Consumes

Flutter hanya:

* membaca data canonical
* menyimpan token/session lokal
* menyimpan state UI lokal
* menyimpan temporary form state bila perlu

## Important Rule

Jika terjadi konflik antara mobile state dan backend state, backend menang.

---

## 8. Feature Domain Architecture

## 8.1 Dashboard Domain

Backend menyediakan:

* student dashboard summary
* continue learning
* progress summary
* content highlights

Flutter menampilkan:

* Netflix-like dashboard
* banners / rails / cards
* continue learning section

---

## 8.2 Module Domain

Backend menyediakan:

* module list
* module detail
* lesson ordering
* assignment ordering/status
* progress per module

Flutter menampilkan:

* module rails/grids
* module detail page
* lesson/assignment entries

---

## 8.3 Lesson Domain

Backend menyediakan:

* lesson detail
* media references
* workbook/file URLs
* assessment relation
* progress/completion status

Flutter menampilkan:

* lesson screen
* video section
* audio section
* workbook access
* lesson content
* related assessment CTA

---

## 8.4 Assessment Domain

Backend tetap memegang:

* question flow
* scoring
* result
* progress
* submission state
* all logic rules

Flutter menampilkan:

* assessment UI
* question rendering
* answer submission
* results

### Important Rule

Assessment mobile harus sedekat mungkin 100% dengan web.

---

## 8.5 Assignment Domain

Backend menyediakan:

* assignment list/detail
* submission status
* feedback
* upload endpoint

Flutter menyediakan:

* assignment screens
* upload file video flow
* status display
* feedback display

### Phase 1 Rule

Student cukup upload file video.
Direct camera recording belum wajib.

---

## 8.6 Certificate Domain

Backend menyediakan:

* certificate visibility
* certificate download URL

Flutter menyediakan:

* certificate screen
* open/download action

Untuk sekarang:

* view
* download

Sudah cukup.

---

## 8.7 Profile Domain

Backend menyediakan:

* profile data
* update profile
* change password

Flutter menyediakan:

* profile form
* change password screen

---

## 9. Media Architecture

## 9.1 Lesson Video

Video tetap berasal dari:

* Bunny Stream
* HLS playback

### Backend Responsibility

Backend mengirim data video yang diperlukan untuk mobile dengan aman.

### Mobile Responsibility

Flutter harus memakai player yang mendukung HLS playback dengan stabil.

### Important Rule

Mobile app tidak membangun media rule baru.
Ia hanya consume media contract dari backend.

---

## 9.2 Audio

Audio tetap berasal dari backend existing, dengan URL final yang sudah dibentuk backend.

Flutter cukup:

* render audio player
* play/pause audio

---

## 9.3 Workbook / Files

Workbook dan file lain:

* URL final dibentuk backend
* mobile membuka / mengunduh file tersebut

---

## 9.4 Assignment Video Upload

Upload video assignment dari mobile harus melewati API backend existing, lalu backend mengelola storage flow yang relevan.

---

## 10. Online-Only Architecture

Fase awal mobile adalah **online-only**.

### Meaning

* tidak ada offline sync
* tidak ada download offline lesson state
* tidak ada local-first content model
* semua data diambil dari backend saat dibutuhkan

### Implication

Arsitektur mobile tidak perlu offline repository complexity di fase awal.

---

## 11. UI Architecture Direction

## Student Experience

UI mobile harus terasa seperti:

* Netflix app
* streaming app
* premium dark-first content experience

### Characteristics

* strong visual hierarchy
* cinematic module/lesson browsing
* rails/carousels
* hero-style dashboard areas
* content-first navigation

## Important Rule

Jangan membuat mobile app terasa seperti:

* admin dashboard
* web form dipindahkan ke mobile
* LMS kampus
* dashboard SaaS generik

---

## 12. Compatibility With Existing Web System

Mobile app harus kompatibel dengan:

* student accounts existing
* content existing
* progress existing
* lessons existing
* assessments existing
* assignments existing
* certificates existing

### Important Rule

Backend changes untuk mobile tidak boleh merusak:

* admin web
* web student
* existing business rules

---

## 13. Recommended Architectural Decisions

Beberapa keputusan sudah final:

* student-only mobile app
* Flutter
* Android + iOS
* token-based auth
* `/api/mobile/v1`
* full parity direction
* online-only
* Netflix-like public student experience
* backend existing as single source of truth

Beberapa keputusan masih bisa ditetapkan nanti saat implementasi detail:

* final auth package choice jika belum dikunci
* final Flutter state management
* push notification phase
* local caching strategy minimal

---

## 14. State Management Decision Status

Belum ada keputusan final untuk Flutter state management.

### Architecture Note

Dokumen ini belum mengunci:

* Riverpod
* Bloc
* Provider
* GetX

Keputusan ini sebaiknya dibuat saat masuk ke repo Flutter setup, tetapi arsitektur overall tetap harus mendukung pola modular dan testable.

---

## 15. Media and API Safety Rules

### Backend Must

* validate ownership
* validate student access
* build safe URLs
* hide implementation details yang tidak perlu

### Mobile Must

* never assume access by itself
* always trust API contract
* fail gracefully on missing/invalid media

---

## 16. Future Expansion Boundary

Arsitektur ini harus tetap membuka ruang untuk:

* forgot/reset password mobile
* push notifications
* richer media experience
* analytics hooks
* performance optimization
* offline-lite features
* app store production hardening

Namun itu bukan prioritas fase awal.

---

## 17. Architectural Success Criteria

Arsitektur dianggap benar jika:

1. backend existing tetap utuh
2. mobile API layer bersih dan versioned
3. Flutter app bisa berkembang tanpa menyentuh business logic canonical
4. media-heavy student features bisa berjalan
5. UI mobile bisa dikejar sampai parity dengan web
6. perubahan mobile tidak merusak admin/web existing

---

## 18. Final Summary

YogaFX Mobile Student App menggunakan arsitektur berikut:

* existing Laravel backend tetap menjadi source of truth
* mobile API hidup di repo backend yang sama
* Flutter app hidup di repo terpisah
* auth bersifat token-based
* student-only
* Android + iOS
* full parity dengan web student sedekat mungkin
* Netflix-like immersive UI
* online-only
* media dan business rules tetap mengikuti backend existing

Dokumen ini menjadi dasar untuk:

* mobile API requirements
* mobile modular implementation
* repo setup Flutter
* future Codex implementation prompts

