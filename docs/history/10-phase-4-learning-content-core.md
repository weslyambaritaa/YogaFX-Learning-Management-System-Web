# 10-phase-4-learning-content-core.md
# Phase 4 Documentation
# Learning Content Core

## 1. Purpose

Dokumen ini merangkum pekerjaan yang diselesaikan pada Phase 4 untuk fondasi `Learning Content Core` YogaFX LMS.

Phase ini berfokus pada struktur konten utama sistem, termasuk:
- modules
- lessons
- ebooks
- courses

Phase ini belum masuk ke domain:
- progress tracking
- lesson locking
- workbook completion rule
- watch progress
- assessment player
- assignment
- certificate

---

## 2. Phase Objective

Tujuan Phase 4 adalah menyiapkan fondasi agar:
- admin dapat mengelola struktur konten utama sistem
- student dapat melihat konten sesuai tier
- module dapat memiliki lesson
- lesson dapat menyimpan workbook, video, audio, content, dan placeholder assessment relation
- ebook dan course dapat dipakai sebagai resource independen

---

## 3. Scope Implemented

Fitur yang diimplementasikan pada phase ini:
- module CRUD
- lesson CRUD
- ebook CRUD
- course CRUD
- relasi `module -> lesson`
- tier filtering untuk module, lesson, ebook, dan course
- upload thumbnail untuk module, lesson, dan course
- upload workbook untuk lesson
- upload file ebook
- local protected file delivery untuk thumbnail/workbook/ebook
- lesson detail student
- module detail student
- ebook list student
- course list student

Fitur yang sengaja belum diimplementasikan pada phase ini:
- progress learning
- sequential locking
- workbook completion logic
- video watch tracking
- assessment execution
- continue learning flow
- assignment and certificate logic

---

## 4. Technical Direction

Fondasi content core phase ini dibangun dengan:
- entity terpisah untuk `Module`, `Lesson`, `Ebook`, dan `Course`
- relasi satu content ke satu `access_tier_id`
- upload file lokal ke storage Laravel untuk asset yang memang file nyata
- reference string untuk `video` dan `audio`
- student-side catalog dan detail pages berbasis Inertia
- route file protected agar media/file tidak diperlakukan sebagai public asset biasa

Alasan arah ini dipilih:
- sesuai PRD, ERD, IA, dan keputusan implementasi yang sudah dikunci sebelum coding
- cukup ringan untuk foundation content phase
- menyiapkan struktur yang stabil untuk Phase 5 dan Phase 6
- menghindari implementasi progress/assessment terlalu dini sebelum backbone konten selesai

---

## 5. Data Changes

Perubahan data utama pada phase ini:

### Tabel baru `modules`
Field yang dibuat:
- `title`
- `url_slug`
- `thumbnail`
- `access_tier_id`
- `sort_order`

### Tabel baru `lessons`
Field yang dibuat:
- `module_id`
- `access_tier_id`
- `assessment_id` nullable
- `title`
- `thumbnail`
- `workbook`
- `video`
- `audio`
- `content`
- `sort_order`

### Tabel baru `ebooks`
Field yang dibuat:
- `title`
- `file`
- `access_tier_id`

### Tabel baru `courses`
Field yang dibuat:
- `title`
- `url_slug`
- `access_tier_id`
- `description`
- `thumbnail`
- `video`

### Catatan data model
- satu content memiliki satu tier, tanpa many-to-many
- lesson tetap memiliki `access_tier_id` sendiri meskipun berada di dalam module
- `assessment_id` hanya disediakan sebagai nullable placeholder relation
- `video` dan `audio` disimpan sebagai URL/reference string
- `thumbnail`, `workbook`, dan `ebook file` disimpan di local storage Laravel

---

## 6. Content Rules Implemented

Aturan bisnis yang diterapkan pada phase ini:
- module, lesson, ebook, dan course dibatasi oleh `access_tier_id`
- student hanya melihat content yang sesuai dengan tier miliknya
- student tidak dapat membuka lesson dari tier lain
- module tidak dapat dihapus jika masih memiliki lesson
- ebook dan course dapat dihapus langsung jika tidak ada dependency lain
- `sort_order` dikelola manual dengan input numerik

Catatan:
- tier filtering baru berlaku pada visibilitas content
- lesson locking, workbook prerequisite, dan watch progress belum aktif di phase ini

---

## 7. Route Structure

Route student baru yang aktif setelah Phase 4:
- `/modules`
- `/modules/{module:url_slug}`
- `/lessons/{lesson}`
- `/ebooks`
- `/courses`

Route admin baru yang aktif setelah Phase 4:
- `/admin/modules`
- `/admin/modules/create`
- `/admin/modules/{module}/edit`
- `/admin/lessons`
- `/admin/lessons/create`
- `/admin/lessons/{lesson}/edit`
- `/admin/ebooks`
- `/admin/ebooks/create`
- `/admin/ebooks/{ebook}/edit`
- `/admin/courses`
- `/admin/courses/create`
- `/admin/courses/{course}/edit`

Route file protected:
- `/media/{entity}/{id}/{field}`

Perilaku route:
- admin mengelola konten dari menu terpisah: `Modules`, `Lessons`, `Ebooks`, `Courses`
- student melihat katalog konten sesuai tier
- file workbook, ebook, dan thumbnail dilayani lewat route auth-protected

---

## 8. Authorization & Access Rules

Aturan akses pada phase ini:
- hanya `admin` yang dapat mengakses CRUD content management
- hanya `student` yang dapat mengakses katalog content student side
- file media/content lokal hanya dapat diakses oleh user yang berhak
- admin dapat mengakses file untuk seluruh content
- student hanya dapat mengakses file untuk content yang tier-nya sesuai

Middleware yang dipakai:
- `auth`
- `role:admin`
- `role:student`

---

## 9. UI Surfaces Added Or Updated

Surface utama yang aktif pada phase ini:
- admin modules list/create/edit
- admin lessons list/create/edit
- admin ebooks list/create/edit
- admin courses list/create/edit
- student modules list
- student module detail
- student lesson detail
- student ebooks list
- student courses list
- authenticated navigation yang kini menampilkan menu content sesuai role

Navigasi yang aktif:
- admin: `Dashboard`, `Modules`, `Lessons`, `Ebooks`, `Courses`, `Access Tiers`, `Students`
- student: `Dashboard`, `Modules`, `Ebooks`, `Courses`, `Profile`

---

## 10. Files Changed In Phase 4

### Database
- `database/migrations/2026_06_09_120000_create_modules_table.php`
- `database/migrations/2026_06_09_120100_create_lessons_table.php`
- `database/migrations/2026_06_09_120200_create_ebooks_table.php`
- `database/migrations/2026_06_09_120300_create_courses_table.php`
- `database/factories/ModuleFactory.php`
- `database/factories/LessonFactory.php`
- `database/factories/EbookFactory.php`
- `database/factories/CourseFactory.php`

### Backend
- `app/Models/Module.php`
- `app/Models/Lesson.php`
- `app/Models/Ebook.php`
- `app/Models/Course.php`
- `app/Models/AccessTier.php`
- `app/Http/Requests/Admin/ModuleRequest.php`
- `app/Http/Requests/Admin/LessonRequest.php`
- `app/Http/Requests/Admin/EbookRequest.php`
- `app/Http/Requests/Admin/CourseRequest.php`
- `app/Http/Controllers/Concerns/HandlesLocalUploads.php`
- `app/Http/Controllers/ContentFileController.php`
- `app/Http/Controllers/Admin/ModuleController.php`
- `app/Http/Controllers/Admin/LessonController.php`
- `app/Http/Controllers/Admin/EbookController.php`
- `app/Http/Controllers/Admin/CourseController.php`
- `app/Http/Controllers/Student/ModuleCatalogController.php`
- `app/Http/Controllers/Student/LessonCatalogController.php`
- `app/Http/Controllers/Student/EbookCatalogController.php`
- `app/Http/Controllers/Student/CourseCatalogController.php`
- `routes/web.php`

### Frontend
- `resources/js/Components/ModuleForm.jsx`
- `resources/js/Components/LessonForm.jsx`
- `resources/js/Components/EbookForm.jsx`
- `resources/js/Components/CourseForm.jsx`
- `resources/js/Layouts/AuthenticatedLayout.jsx`
- `resources/js/Pages/Admin/Dashboard.jsx`
- `resources/js/Pages/Student/Dashboard.jsx`
- `resources/js/Pages/Admin/Modules/Index.jsx`
- `resources/js/Pages/Admin/Modules/Create.jsx`
- `resources/js/Pages/Admin/Modules/Edit.jsx`
- `resources/js/Pages/Admin/Lessons/Index.jsx`
- `resources/js/Pages/Admin/Lessons/Create.jsx`
- `resources/js/Pages/Admin/Lessons/Edit.jsx`
- `resources/js/Pages/Admin/Ebooks/Index.jsx`
- `resources/js/Pages/Admin/Ebooks/Create.jsx`
- `resources/js/Pages/Admin/Ebooks/Edit.jsx`
- `resources/js/Pages/Admin/Courses/Index.jsx`
- `resources/js/Pages/Admin/Courses/Create.jsx`
- `resources/js/Pages/Admin/Courses/Edit.jsx`
- `resources/js/Pages/Student/Modules/Index.jsx`
- `resources/js/Pages/Student/Modules/Show.jsx`
- `resources/js/Pages/Student/Lessons/Show.jsx`
- `resources/js/Pages/Student/Ebooks/Index.jsx`
- `resources/js/Pages/Student/Courses/Index.jsx`

### Testing
- `tests/Feature/LearningContentTest.php`

---

## 11. File Handling Decisions

Keputusan file handling pada phase ini:
- `thumbnail` module/lesson/course menggunakan upload file lokal
- `workbook` lesson menggunakan upload file lokal
- `ebook file` menggunakan upload file lokal
- `video` lesson dan course menggunakan URL/reference string
- `audio` lesson menggunakan URL/reference string

Proteksi file:
- file tidak dipublikasikan sebagai public asset biasa
- akses file dilakukan melalui route terproteksi
- student hanya dapat membuka file yang tier-nya sesuai

---

## 12. Validation Completed

Validasi yang berhasil dijalankan pada phase ini:
- admin dapat membuat module dengan thumbnail upload
- admin dapat membuat lesson dengan workbook upload
- admin tidak dapat menghapus module yang masih memiliki lesson
- student hanya melihat module sesuai tier
- student tidak dapat membuka lesson dari tier lain
- student hanya melihat ebook dan course sesuai tier
- seluruh test feature yang ada lulus
- migrasi content tables berhasil dijalankan di PostgreSQL

Command validasi yang berhasil dijalankan:
- `php artisan route:list`
- `php artisan test tests/Feature/LearningContentTest.php`
- `php artisan test`
- `php artisan migrate`
- `php artisan migrate:status`
- `php artisan db:table modules --database=pgsql`
- `php artisan db:table lessons --database=pgsql`
- `php artisan db:table ebooks --database=pgsql`
- `php artisan db:table courses --database=pgsql`

---

## 13. Known Notes

- Dokumen `03-business-rules.md` tetap tidak tersedia di repo saat phase ini dikerjakan.
- Source of truth struktur entity yang dipakai pada phase ini adalah `docs/03-erd.md`.
- Source of truth struktur halaman yang dipakai pada phase ini adalah `docs/05-information-architecture.md`.
- `assessment_id` di lesson baru berfungsi sebagai placeholder relation dan belum aktif secara fungsional.
- `npm run build` masih gagal karena issue CSS existing `var(--spacing(4))`, bukan karena implementasi Phase 4 ini.
- lesson locking, workbook requirement, watch progress, dan assessment unlock sengaja ditunda ke phase berikutnya.

---

## 14. Outcome

Phase 4 menghasilkan fondasi content core yang siap dipakai oleh domain berikutnya:
- sistem sekarang memiliki struktur module, lesson, ebook, dan course yang eksplisit
- admin sudah dapat mengelola konten inti dari dashboard admin
- student sudah dapat melihat konten sesuai tier
- upload file dasar untuk thumbnail, workbook, dan ebook sudah tersedia
- relasi `module -> lesson` sudah aktif
- placeholder relation lesson ke assessment sudah tersedia

Fondasi ini menjadi dependency langsung untuk:
- Learning Progress
- Assessment
- Assignment
- Certificate

Phase berikutnya dapat mulai membangun progress dan session tracking dengan asumsi bahwa struktur konten utama sudah tersedia dan stabil.
