Simpan sebagai **`docs/mobile-api-requirements.md`**:

````md
# YogaFX Mobile Student App — API Requirements

## Purpose

Dokumen ini menjadi source of truth untuk kebutuhan **API mobile** pada YogaFX Mobile Student App.

Dokumen ini menjelaskan apa saja contract API yang harus disediakan oleh backend existing agar Flutter mobile app bisa mencapai **full parity sedekat mungkin** dengan web student.

Fokus dokumen ini adalah:

- ruang lingkup API mobile
- prinsip desain API
- kebutuhan endpoint per domain
- bentuk data minimum yang harus tersedia
- aturan auth, ownership, dan error handling
- batasan awal fase implementasi

Dokumen ini tidak membahas detail implementasi controller satu per satu, tetapi cukup spesifik untuk menjadi dasar eksekusi backend mobile API.

---

## 1. API Role in the System

Mobile API adalah layer baru di atas backend existing.

Arsitektur utamanya:

```text
Existing YogaFX Backend
↓
Mobile API Layer
↓
Flutter Mobile Student App
````

Artinya:

* database tetap di backend existing
* business logic tetap di backend existing
* API mobile hanya membuka contract yang aman, versioned, dan mobile-friendly
* Flutter tidak boleh menebak business rules sendiri

---

## 2. Scope of Mobile API

Mobile API hanya melayani **student mobile app**.

### Included

* student authentication
* dashboard
* modules
* lessons
* media access
* assessments
* assignments
* certificates
* profile
* change password
* progress/completion data

### Excluded

* admin endpoints
* public scoreboard system
* lead funnel endpoints
* marketing landing page endpoints
* internal operator/admin-only actions

---

## 3. Versioning Rule

Semua endpoint mobile harus berada di namespace versioned.

### Required Base Prefix

```text
/api/mobile/v1
```

### Reason

* menjaga kontrak mobile tetap stabil
* memisahkan mobile dari web/inertia implementation
* memudahkan perubahan ke versi berikutnya
* aman untuk deployment jangka panjang di Android/iOS

---

## 4. Authentication Model

Mobile memakai **token-based authentication**.

### Login Method

* email
* password

### Token Behaviour

* student bisa tetap login lama pada satu device
* token dipakai untuk semua endpoint protected
* logout harus menghapus / revoke akses token device tersebut

### API Requirement

Backend harus menyediakan endpoint mobile auth yang jelas, tidak memakai session web sebagai contract utama.

### Current Product Boundary

Fase awal wajib mendukung:

* login
* logout
* current authenticated user

Forgot/reset password akan dipersiapkan sebagai fase berikutnya, bukan blocker fase awal mobile.

---

## 5. API Design Principles

## 5.1 Student-safe

Semua endpoint harus memastikan student hanya bisa mengakses data miliknya sendiri.

## 5.2 JSON-first

Semua response mobile harus berupa JSON yang stabil dan konsisten.

## 5.3 Backend as Source of Truth

Flutter tidak boleh menghitung rules inti sendiri jika backend sudah punya rule tersebut.

Contoh:

* lesson unlocked/locked
* module completion
* assessment completion
* assignment status
* certificate visibility

## 5.4 Media-ready

API harus mengirim payload media yang siap dipakai mobile, bukan mengharuskan Flutter menebak struktur backend.

## 5.5 Parity-oriented

API harus dirancang untuk mendukung parity sedekat mungkin dengan web student.

---

## 6. Response Contract Principles

API mobile sebaiknya memakai pola response yang konsisten.

### Success Response

Minimal mengandung:

* success / status
* data
* message jika perlu

### Error Response

Harus konsisten untuk:

* validation error
* unauthorized
* forbidden
* not found
* business rule conflict
* server error

### Important Rule

Flutter harus bisa mengandalkan error format yang sama lintas endpoint.

---

## 7. Authentication Endpoints

## 7.1 Login

### Purpose

Autentikasi student mobile.

### Input

* email
* password

### Output

Minimal:

* auth token
* user summary
* profile basics
* app-relevant flags jika perlu

## 7.2 Logout

### Purpose

Mengakhiri sesi mobile device.

## 7.3 Current Authenticated Student

### Purpose

Mengambil data student yang sedang login untuk bootstrap state app.

---

## 8. Dashboard API Requirements

## Purpose

Mobile dashboard harus bisa menampilkan experience seperti home screen streaming-style.

### Dashboard API must provide

* student identity summary
* continue learning / latest lesson
* module highlights
* progress summary
* assignment summary jika relevan
* certificate summary jika relevan
* dashboard banners/sections bila ada

### Important Rule

Dashboard payload harus cukup kaya untuk mendukung layout Netflix-like tanpa Flutter perlu memanggil terlalu banyak endpoint tambahan saat first load.

---

## 9. Module API Requirements

## 9.1 Module List

### Purpose

Menampilkan daftar module yang bisa diakses student.

### Each module should provide at minimum

* id
* title
* slug
* description ringkas jika ada
* thumbnail/poster
* progress summary
* lock/access state
* ordering info

## 9.2 Module Detail

### Purpose

Menampilkan isi module.

### Module detail must provide

* module metadata
* ordered lessons
* ordered assignments
* progress state
* completion state
* access/lock state
* CTA state per item jika relevan

### Important Rule

Urutan lessons dan assignments harus datang dari backend, bukan diurutkan manual oleh mobile.

---

## 10. Lesson API Requirements

## 10.1 Lesson Detail

### Purpose

Menjadi pusat data untuk lesson screen.

### Lesson detail payload minimum

* id
* title
* content/body
* thumbnail
* progress data
* completion state
* lock/access state
* related assessment info jika ada
* workbook/file info jika ada
* media info

## 10.2 Lesson Progress Update

### Purpose

Menyimpan progress lesson dari mobile.

### Must support

* watch progress / completion update
* state sync yang sama dengan web jika memang sudah ada business rule existing

---

## 11. Media Payload Requirements

## 11.1 Video

Lesson video memakai:

* Bunny Stream
* HLS playback

### API should provide

Backend sebaiknya memberi payload video yang siap dipakai mobile, misalnya:

* `video_id`
* `hls_url`
* poster/thumbnail jika relevan

### Strong Recommendation

Flutter sebaiknya **tidak perlu** membentuk URL Bunny dari nol jika backend bisa mengirimkan URL final atau structured media object yang aman.

## 11.2 Audio

### API should provide

* URL final audio
* metadata minimum jika perlu

## 11.3 Workbook / Lesson Files

### API should provide

* URL final file
* label/name jika perlu
* open/download-ready data

### Important Rule

Flutter tidak perlu tahu object key mentah storage.

---

## 12. Assessment API Requirements

Mobile harus mendukung assessment secara penuh seperti web.

## Required capabilities

* start assessment
* fetch assessment structure/question flow
* fetch current state/progress jika diperlukan
* submit answers
* submit final assessment
* fetch result

## Must support

* question flow parity
* scoring parity
* result parity
* jump logic parity
* required fields parity
* progress/completion parity

### Important Rule

Semua logic inti assessment tetap di backend.
Flutter hanya menjalankan experience berdasarkan contract API.

---

## 13. Assignment API Requirements

## Required capabilities

* list assignments in module
* assignment detail
* upload assignment video file
* get latest submission status
* get admin feedback/review result

## Phase 1 media rule

Untuk fase awal:

* upload file video assignment
* belum perlu direct camera recording
* belum perlu editor video
* belum perlu offline upload queue

## Assignment payload minimum

* assignment metadata
* submission status
* feedback if any
* ability to upload new submission jika diizinkan

---

## 14. Certificate API Requirements

## Required capabilities

* get certificate summary/list
* get certificate detail
* get certificate download/open URL

Untuk fase awal mobile:

* view
* open/download

sudah cukup.

---

## 15. Profile API Requirements

## Required capabilities

* get profile
* update profile
* change password

### Important Rule

Change password harus menjadi flow authenticated student.

Forgot/reset password akan dikembangkan kemudian sebagai flow terpisah.

---

## 16. Progress and Completion API Requirements

Mobile app tidak boleh menghitung completion state utama sendiri.

## Backend must provide or enforce

* lesson progress state
* module progress state
* lesson completion
* assessment completion
* assignment state
* certificate eligibility visibility jika memang existing

### Important Rule

Kalau web sudah punya rules completion, mobile harus memakai rule backend yang sama.

---

## 17. Media-heavy Lesson Experience Requirements

Karena mobile harus parity dengan web, API harus cukup kuat untuk mendukung lesson yang berisi:

* video
* audio
* workbook
* content
* related assessment
* related completion status

Jadi lesson payload tidak boleh terlalu tipis.

---

## 18. Token Security Requirements

API auth harus memenuhi prinsip:

* token hanya untuk student authenticated mobile
* token bisa dicabut saat logout
* endpoint protected wajib cek ownership
* student tidak boleh mengakses data student lain

---

## 19. Compatibility Principle

API mobile harus memanfaatkan backend/data existing tanpa merusak web existing.

### Meaning

* jangan refactor web student/inertia contract tanpa alasan
* mobile layer bisa reuse service/domain logic existing
* response mobile boleh punya transformer/resource sendiri

---

## 20. Suggested Endpoint Families

Struktur keluarga endpoint yang disarankan:

```text
/api/mobile/v1/auth/*
/api/mobile/v1/me
/api/mobile/v1/dashboard
/api/mobile/v1/modules
/api/mobile/v1/modules/{id}
/api/mobile/v1/lessons/{id}
/api/mobile/v1/lessons/{id}/progress
/api/mobile/v1/assessments/*
/api/mobile/v1/assignments/*
/api/mobile/v1/certificates/*
/api/mobile/v1/profile/*
```

Catatan:
Ini arahan keluarga endpoint, bukan final exhaustive list.

---

## 21. Out of Scope for This API Phase

Belum fokus pada:

* admin API
* public scoreboard API
* advanced analytics API
* push notification API
* offline sync API
* deep device management
* outbound integrations untuk mobile
* camera recording-specific API

---

## 22. Verification Expectations

Sebelum dianggap siap dipakai Flutter, API layer harus bisa diverifikasi untuk hal-hal berikut:

1. login/logout/me berjalan
2. dashboard payload cukup untuk home screen
3. modules dan lessons bisa dibuka
4. lesson media data benar
5. assessment flow berjalan
6. assignment upload flow berjalan
7. certificate data tersedia
8. profile/change password tersedia
9. ownership dan auth aman
10. web existing tidak rusak

---

## 23. Final Summary

Mobile API untuk YogaFX Student App harus:

* hidup di repo backend existing
* versioned di `/api/mobile/v1`
* memakai token-based auth
* hanya untuk student
* mendukung full parity sedekat mungkin dengan web student
* menyediakan payload media, lesson, assessment, assignment, certificate, dan profile yang cukup
* menjaga backend tetap sebagai source of truth
* aman, konsisten, dan mobile-friendly

Dokumen ini menjadi dasar untuk:

* mobile modular implementation
* endpoint planning
* Codex implementation prompts
* repo Flutter integration work

