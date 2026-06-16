# YogaFX Mobile Student App — Modular Implementation

## Purpose

Dokumen ini menjadi roadmap implementasi bertahap untuk YogaFX Mobile Student App.

Dokumen ini dibuat agar:
- pekerjaan bisa dipecah per modul
- backend dan mobile frontend bisa dikerjakan dengan urutan yang aman
- AI Coding Assistant bisa bekerja modular, tidak melebar, dan bisa melewati modul yang sudah selesai
- progress implementasi mudah diverifikasi

Dokumen ini mengikuti prinsip:
- backend existing tetap menjadi source of truth
- mobile API dibangun di repo backend existing
- Flutter app dibangun di repo terpisah
- student mobile harus menuju full parity sedekat mungkin dengan web student
- setiap modul harus ditutup dengan verifikasi

---

## 1. How to Use This Document

Gunakan dokumen ini seperti playbook.

### Rule 1
Kerjakan **satu modul aktif pada satu waktu**.

### Rule 2
Jika suatu modul sudah benar-benar selesai dan lolos verifikasi, **lewati**.

### Rule 3
Jika suatu modul belum selesai atau belum stabil, fokus perbaiki modul itu dulu sebelum lanjut.

### Rule 4
Jangan mengerjakan Flutter UI besar-besaran sebelum kontrak API modul terkait stabil.

### Rule 5
Jangan refactor domain lain di backend jika tidak relevan dengan modul aktif.

### Rule 6
Setiap modul harus punya:
- objective
- scope
- dependencies
- expected artifacts
- definition of done
- verification
- non-goals

---

## 2. AI Collaboration Rules

Aturan kerja dengan AI untuk mobile project ini:

1. Selalu baca dulu:
   - `docs/mobile-system-overview.md`
   - `docs/mobile-architecture.md`
   - `docs/mobile-api-requirements.md`
   - `docs/mobile-modular-implementation.md`

2. Jangan lompat ke modul berikutnya sebelum modul aktif diverifikasi.

3. Jika modul sudah ada sebagian, AI harus:
   - audit dulu
   - catat apa yang sudah benar
   - hanya kerjakan gap yang belum selesai

4. Backend contract harus dijaga stabil.
   Flutter mengikuti API, bukan sebaliknya.

5. Jangan menyentuh:
   - admin domain
   - public scoreboard domain
   - marketing/public flow
   jika tidak dibutuhkan untuk modul aktif.

6. Selalu tutup modul dengan test/verifikasi.

7. Jika ada ambiguity, tanyakan dulu sebelum coding.

---

## 3. Default Execution Order

Urutan implementasi default yang direkomendasikan:

1. Mobile API Foundation
2. Mobile Auth (token-based)
3. Flutter App Foundation
4. Mobile Design Shell
5. Dashboard
6. Modules & Module Detail
7. Lesson Detail & Media
8. Progress Sync
9. Assessment Full Flow
10. Assignment Flow
11. Certificate Flow
12. Profile & Change Password
13. Hardening & Full Parity Pass

---

## 4. Module 1 — Mobile API Foundation

### Objective
Menyiapkan fondasi API mobile yang versioned, aman, dan terpisah dari web flow.

### Scope
Backend only.

### Main Scope
- route group mobile API
- versioned prefix: `/api/mobile/v1`
- response format convention
- error format convention
- student auth middleware readiness
- namespace/controller/resource strategy

### Dependencies
- existing Laravel backend
- existing student domain models

### Likely Touched Areas
- `routes/api.php` atau file route mobile khusus
- `app/Http/Controllers/Mobile/*`
- `app/Http/Resources/Mobile/*`
- `app/Http/Middleware/*` jika perlu
- `tests/Feature/Mobile/*`

### Expected Artifacts
- route namespace mobile
- minimal smoke endpoint / me-check endpoint
- standard JSON response pattern

### Definition of Done
- `/api/mobile/v1` sudah tersedia
- minimal ada endpoint protected sederhana yang bisa dites
- response JSON konsisten
- web existing tidak rusak

### Verification
- route list menunjukkan mobile routes
- protected endpoint bisa diakses dengan auth valid
- unauthorized response konsisten

### Non-Goals
- belum membangun semua domain endpoint
- belum membuat Flutter app

### AI Guidance
Kalau fondasi route/versioning sudah benar, jangan refactor ulang tanpa alasan kuat.

---

## 5. Module 2 — Token Authentication

### Objective
Menyediakan autentikasi mobile student berbasis token.

### Scope
Backend first, kemudian konsumsi awal di Flutter foundation nanti.

### Main Scope
- login endpoint
- logout endpoint
- current authenticated user endpoint
- token issuance
- token revocation/logout
- student-only guard behaviour

### Dependencies
- Module 1 selesai
- student auth existing tersedia

### Likely Touched Areas
- auth controllers for mobile
- auth service/guard/token config
- user model/token traits
- request validation
- feature tests

### Expected Artifacts
- login API
- logout API
- me/current student API
- token auth contract

### Definition of Done
- student bisa login via API dengan email+password
- token valid bisa dipakai akses endpoint protected
- logout mencabut akses token
- auth hanya untuk student flow

### Verification
- test login sukses
- test me endpoint
- test logout
- test unauthorized token

### Non-Goals
- forgot/reset password
- social login
- multi-device management lanjutan

### AI Guidance
Jangan campur session web tradisional ke contract mobile.

---

## 6. Module 3 — Flutter App Foundation

### Objective
Menyiapkan repo Flutter mobile student app yang production-oriented.

### Scope
Flutter repo only.

### Main Scope
- app bootstrap
- environment config
- API client foundation
- secure token storage
- routing/navigation foundation
- base theme
- state management decision dan setup
- error/loading state pattern

### Dependencies
- Module 2 login endpoints harus stabil minimal
- repo Flutter sudah dibuat

### Likely Touched Areas
- `lib/main.dart`
- `lib/app/*`
- `lib/core/network/*`
- `lib/core/storage/*`
- `lib/core/theme/*`
- `lib/features/auth/*`

### Expected Artifacts
- runnable Flutter app
- environment handling
- token persistence
- auth gate
- chosen state management baseline

### Definition of Done
- app boot normal
- login API bisa dipanggil
- token tersimpan aman
- app bisa menjaga state logged-in

### Verification
- run Android emulator/device
- run iOS simulator/device jika tersedia
- login dan restore session

### Non-Goals
- full student feature screens
- polished Netflix-like UI penuh

### AI Guidance
Kalau state management belum dipilih, pilih satu yang stabil dan scalable, lalu kunci keputusan itu sebelum fitur banyak bertambah.

---

## 7. Module 4 — Mobile Design Shell

### Objective
Membangun shell UI utama mobile yang Netflix-like dan reusable.

### Scope
Flutter frontend only.

### Main Scope
- dark-first theme
- top-level app shell
- navigation structure
- typography and spacing baseline
- content cards / rails / banners / hero areas
- loading skeleton patterns
- empty/error states baseline

### Dependencies
- Module 3 selesai

### Likely Touched Areas
- theme system
- core widgets
- navigation shell
- reusable card/list/banner components

### Expected Artifacts
- app shell
- reusable UI primitives for student-facing experience
- Netflix-like browsing foundation

### Definition of Done
- shell sudah terasa seperti streaming app
- reusable widget foundation tersedia
- dashboard/module screens bisa dibangun konsisten di atas shell ini

### Verification
- visual QA di emulator/device
- responsive checks phone/tablet
- compare against design direction

### Non-Goals
- full data integration semua feature
- final polishing semua screen

### AI Guidance
Jangan buat UI terasa seperti form admin atau LMS kampus.

---

## 8. Module 5 — Dashboard

### Objective
Membawa dashboard student ke mobile.

### Scope
Backend API + Flutter screen.

### Main Scope
Backend:
- dashboard endpoint mobile
- transform continue learning, progress summary, and highlights

Flutter:
- dashboard screen
- continue learning section
- content rails/banner sections
- progress summary

### Dependencies
- Module 2
- Module 3
- Module 4

### Likely Touched Areas
Backend:
- mobile dashboard controller/resource
- dashboard service reuse if exists

Flutter:
- `features/dashboard/*`

### Expected Artifacts
- dashboard API
- dashboard mobile screen

### Definition of Done
- student login -> dashboard loads
- continue learning works
- progress summary visible
- content sections render with Netflix-like UI

### Verification
- API payload check
- screen render check
- logged in student sees real data

### Non-Goals
- full module/lesson detail
- assessment flow

### AI Guidance
Keep dashboard data-focused but cinematic.

---

## 9. Module 6 — Modules & Module Detail

### Objective
Membawa browsing modules ke mobile.

### Scope
Backend API + Flutter screens.

### Main Scope
- module list endpoint
- module detail endpoint
- module cards/rails
- module detail screen
- ordered lesson list
- ordered assignment list if applicable
- progress and access state

### Dependencies
- Dashboard foundation available
- API auth working

### Likely Touched Areas
Backend:
- mobile module controller/resources
- services/repositories for module data

Flutter:
- `features/modules/*`

### Expected Artifacts
- module list screen
- module detail screen
- module payload contract

### Definition of Done
- student can browse modules
- open module detail
- see ordered items and progress/access info

### Verification
- compare module order against web
- check access state correctness

### Non-Goals
- lesson media playback
- assignment submission details

### AI Guidance
Preserve content-first browsing experience.

---

## 10. Module 7 — Lesson Detail & Media

### Objective
Membawa full lesson consumption experience ke mobile.

### Scope
Backend API + Flutter screens + media integration.

### Main Scope
Backend:
- lesson detail endpoint
- media payload
- workbook/file URL final
- lesson completion/progress data

Flutter:
- lesson screen
- HLS video playback
- audio playback
- workbook/file access
- lesson content rendering
- assessment CTA / related section

### Dependencies
- Modules available
- media contracts stable

### Likely Touched Areas
Backend:
- lesson controller/resource mobile
- media URL helpers/reuse services

Flutter:
- `features/lessons/*`
- media player integration
- file open/download integration

### Expected Artifacts
- lesson detail API
- lesson screen
- video/audio/file support

### Definition of Done
- video plays
- audio plays
- workbook/file can open/download
- lesson content visible
- lesson state matches backend

### Verification
- Bunny Stream HLS playback
- audio playback
- workbook URL open
- compare lesson detail to web

### Non-Goals
- assessment player full flow
- assignment upload

### AI Guidance
This is one of the most critical modules. Stabilize media before moving on.

---

## 11. Module 8 — Progress Sync

### Objective
Menyamakan progress/completion state mobile dengan backend existing.

### Scope
Backend + Flutter integration.

### Main Scope
- lesson progress updates
- watch/completion sync
- module progress refresh
- state refresh when user returns to dashboard/module/lesson

### Dependencies
- Lesson module done
- backend rules understood

### Likely Touched Areas
- lesson progress endpoints
- progress-related services
- Flutter repositories/state sync

### Expected Artifacts
- stable progress sync between mobile and backend

### Definition of Done
- actions on mobile affect progress the same way as web
- returning screens shows consistent state
- no phantom completion divergence

### Verification
- test lesson completion via mobile
- compare progress values on web/backend

### Non-Goals
- assessment scoring logic changes
- new business rules

### AI Guidance
Do not reimplement progress logic in Flutter if backend already owns it.

---

## 12. Module 9 — Assessment Full Flow

### Objective
Bring full assessment parity to mobile.

### Scope
Backend mobile API + Flutter player/result flow.

### Main Scope
- assessment start/load endpoints
- question rendering
- answer submission
- progress/state handling
- scoring/result display
- jump logic parity
- all supported question types

### Dependencies
- lessons and progress stable
- assessment backend existing understood

### Likely Touched Areas
Backend:
- mobile assessment controllers/resources
- existing assessment services

Flutter:
- `features/assessments/*`
- question widgets per type
- result screens

### Expected Artifacts
- full assessment mobile flow
- result parity with web

### Definition of Done
- all existing question types render
- user can complete assessment
- result matches backend/web
- jump logic behaves correctly

### Verification
- run assessment end-to-end on mobile
- compare with web result
- test multiple question types

### Non-Goals
- redesign assessment business rules
- admin assessment builder

### AI Guidance
Assessment is complex. Keep backend canonical and solve Flutter rendering modularly per question type.

---

## 13. Module 10 — Assignment Flow

### Objective
Membawa student assignment flow ke mobile.

### Scope
Backend API + Flutter screens + upload flow.

### Main Scope
- assignment list/detail
- submission status
- feedback display
- upload video file
- success/error handling upload

### Dependencies
- module detail available
- auth/token working
- file upload infrastructure working

### Likely Touched Areas
Backend:
- assignment endpoints
- upload validation
- submission resources

Flutter:
- `features/assignments/*`
- file picker integration
- submission form/status UI

### Expected Artifacts
- assignment mobile flow
- upload capability

### Definition of Done
- student can view assignments
- student can upload video file
- status and feedback visible

### Verification
- upload test file from mobile
- verify status in backend/admin
- verify returned status visible in mobile

### Non-Goals
- direct camera record
- heavy video editing

### AI Guidance
Keep upload flow simple and reliable first.

---

## 14. Module 11 — Certificate Flow

### Objective
Membawa certificate access ke mobile.

### Scope
Backend API + Flutter screens.

### Main Scope
- certificate summary/list endpoint
- certificate detail
- download/open URL
- certificate screen UI

### Dependencies
- student auth
- certificate backend data existing

### Likely Touched Areas
Backend:
- certificate resources/controllers mobile

Flutter:
- `features/certificates/*`

### Expected Artifacts
- certificate mobile screen
- open/download action

### Definition of Done
- student can see certificate
- student can open/download certificate file

### Verification
- certificate list loads
- download/open works

### Non-Goals
- certificate share
- complex PDF preview UX

### AI Guidance
Keep this practical; view + download is enough for initial parity.

---

## 15. Module 12 — Profile & Change Password

### Objective
Membawa profile management student ke mobile.

### Scope
Backend API + Flutter screens.

### Main Scope
- profile view
- profile update
- change password
- validation errors and success states

### Dependencies
- mobile auth
- profile backend existing

### Likely Touched Areas
Backend:
- profile endpoints
- password update endpoint

Flutter:
- `features/profile/*`

### Expected Artifacts
- profile screen
- edit profile flow
- change password flow

### Definition of Done
- student can see/update profile
- student can change password from mobile

### Verification
- profile update test
- password change test
- re-login with new password

### Non-Goals
- forgot/reset password full flow
- admin profile tools

### AI Guidance
Keep forgot/reset password as future module, not mixed into current change password flow.

---

## 16. Module 13 — Hardening & Full Parity Pass

### Objective
Menutup gap antara web student dan mobile student.

### Scope
Cross-cutting stabilization phase.

### Main Scope
- auth stability
- API cleanup
- edge states
- UI polish
- performance pass
- error messaging consistency
- navigation polish
- final missing parity items

### Dependencies
- all core modules implemented

### Likely Touched Areas
- backend resources/endpoints
- Flutter screens/widgets/state
- QA test cases

### Expected Artifacts
- stable release candidate
- parity checklist
- known issues list if any

### Definition of Done
- major student flows are available in mobile
- no critical blocker on core features
- parity checklist mostly green
- remaining gaps are explicitly documented

### Verification
- full student journey QA
- smoke tests on Android
- smoke tests on iOS
- regression checks against web behaviour

### Non-Goals
- admin app
- public scoreboard app
- offline mode
- push notification system

### AI Guidance
This module is not for feature sprawl. It is for stabilization.

---

## 17. Cross-Module Technical Sequence

Di dalam modul-modul di atas, urutan teknis umumnya harus mengikuti:

1. backend/data contract first
2. auth/security check
3. API payload/resource stabilization
4. Flutter repository/data layer
5. Flutter screen/UI
6. verification
7. refinement only if needed

Jangan membangun UI Flutter penuh dulu sebelum endpoint modul terkait stabil.

---

## 18. Expected Artifacts by Phase

Secara keseluruhan, artefak yang mungkin dihasilkan sepanjang modul ini meliputi:

### Backend
- API routes
- controllers
- request validation
- resources/transformers
- service reuse or new services where needed
- auth config
- tests

### Flutter
- app shell
- API client
- repositories
- models/DTO
- screens
- widgets
- token storage
- player/file adapters

### QA
- smoke checklist
- parity checklist
- manual verification steps

---

## 19. Skip / Continue Rule

Saat Codex bekerja, gunakan aturan ini:

### If module already works
- verify
- report that it is already done
- skip to next module

### If module partially works
- identify missing pieces
- patch only the gap
- avoid refactoring unrelated parts

### If module is broken
- fix the module
- verify again
- then continue

---

## 20. Definition of Overall Success

Modular plan dianggap berhasil jika pada akhirnya:

1. student can login on mobile
2. dashboard is usable
3. modules and lessons are browsable
4. lesson video/audio/workbook work
5. assessment works
6. assignment works
7. certificate works
8. profile works
9. app feels Netflix-like and student-friendly
10. backend existing remains stable and canonical

---

## 21. Final Summary

YogaFX Mobile Student App harus dibangun secara modular dengan urutan yang aman:

- foundation first
- API before Flutter feature UI
- one module at a time
- verify before continue
- skip what already works
- keep backend existing as source of truth
- aim for full student parity

Dokumen ini menjadi execution roadmap sekaligus collaboration playbook untuk implementasi mobile app bersama AI.