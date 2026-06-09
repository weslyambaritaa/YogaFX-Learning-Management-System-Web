````md id="jlwm0v"
# 03-erd.md
# Entity Relationship Design Document
# YogaFX LMS

## 1. Purpose of This Document

Dokumen ini mendefinisikan arsitektur data YogaFX LMS berdasarkan:
- PRD
- User Flow
- Business Rules
- seluruh keputusan terbaru yang sudah disepakati

Dokumen ini berfokus pada:
- domain analysis
- entity identification
- relationship analysis
- ERD specification
- design notes
- assumptions

Dokumen ini **bukan** implementasi database, migration, SQL, API, atau kode program.

Tujuan utamanya adalah menyediakan fondasi yang cukup matang agar tahap berikutnya dapat langsung masuk ke:
- database schema
- migration plan
- model implementation
- system architecture
- API design

---

## 2. Domain Analysis

Berikut domain utama dalam sistem YogaFX LMS.

---

## 2.1 Authentication & Access Control

### Purpose
Mengelola identitas user, sesi login, role, dan access tier.

### Scope
- login / logout
- user session tracking
- role separation
- membership tier access

### Main Entities
- User
- User Session
- Access Tier

---

## 2.2 Student Management

### Purpose
Mengelola data profile student, identitas belajar, dan informasi yang dibutuhkan untuk pembelajaran dan certificate.

### Scope
- profile student
- personal information
- yoga background
- preferred certificate picture
- progress visibility

### Main Entities
- User

---

## 2.3 Learning Content

### Purpose
Menyimpan struktur pembelajaran inti yang diakses student.

### Scope
- modules
- lessons
- workbook
- lesson video
- audio
- content
- tier-based access
- course independen
- ebooks

### Main Entities
- Module
- Lesson
- Ebook
- Course

---

## 2.4 Learning Progress

### Purpose
Menyimpan status progres student terhadap lesson dan durasi akses sistem.

### Scope
- watch progress
- workbook downloaded
- lesson completed
- cumulative access duration
- progress summary

### Main Entities
- Lesson Progress
- User Session
- Assessment Progress

---

## 2.5 Assessment

### Purpose
Menyimpan struktur assessment, attempt student, jawaban, dan hasil evaluasi.

### Scope
- assessments
- assessment pages
- questions
- options
- timer
- attempts
- autosave
- score
- result

### Main Entities
- Assessment
- Assessment Page
- Question
- Question Option
- Assessment Attempt
- Assessment Answer
- Assessment Progress

---

## 2.6 Assignment

### Purpose
Mengelola submission assignment video student dan proses review oleh admin.

### Scope
- assignment submission
- dynamic assignment type
- review
- approval / rejection
- feedback

### Main Entities
- Assignment Submission

---

## 2.7 Certificate

### Purpose
Mengelola certificate student yang dibuat oleh admin.

### Scope
- certificate generation
- recreate
- file storage reference
- versioning
- certificate access

### Main Entities
- Certificate

---

## 2.8 Email Notification

### Purpose
Mengelola template email otomatis, pengiriman email, dan log pengiriman.

### Scope
- notification template
- send test
- automatic trigger
- logs

### Main Entities
- Email Template
- Email Log

---

## 2.9 Resource Management

### Purpose
Mengelola resource tambahan di luar lesson utama.

### Scope
- ebook
- course
- content access by tier

### Main Entities
- Ebook
- Course

---

## 2.10 Activity Tracking

### Purpose
Menyimpan aktivitas operasional tertentu untuk analitik atau audit ringan.

### Scope
- user activity
- reference activity
- activity duration

### Main Entities
- User Activity Log

---

## 3. Entity Identification

Berikut seluruh entity utama yang dibutuhkan sistem berdasarkan requirement yang sudah disepakati.

---

## 3.1 User

### Purpose
Menjadi entitas utama untuk semua akun dalam sistem.

### Description
User mewakili baik Admin maupun Student. Untuk phase 1, role utama yang dipakai adalah Admin dan Student.

### Key Attributes
- id
- access_tier_id
- first_name
- last_name
- email
- whatsapp
- preferred_certificate_picture
- instagram
- country
- birth_date
- gender
- last_visit_at
- total_access_duration_seconds
- practicing_yoga_for
- yoga_sequence_experience
- hours_per_week
- current_fitness_level
- flexibility_rating
- motivation
- why_yogafx
- how_did_you_find_us
- created_at
- updated_at

### Relationships
- User belongs to one Access Tier
- User has many User Sessions
- User has many Lesson Progress records
- User has many Assessment Progress records
- User has many Assessment Attempts
- User has many Assignment Submissions
- User has many Certificates
- User can generate many Certificates as Admin
- User has many User Activity Logs

---

## 3.2 User Session

### Purpose
Menyimpan histori sesi login user untuk perhitungan durasi akses kumulatif.

### Description
Setiap login menghasilkan satu session aktif atau histori session. Session ini dipakai untuk menghitung total access duration user.

### Key Attributes
- id
- user_id
- login_at
- logout_at
- last_activity_at
- session_duration_seconds
- is_active
- created_at
- updated_at

### Relationships
- User Session belongs to one User

---

## 3.3 Access Tier

### Purpose
Mengelola tier akses membership.

### Description
Access Tier menentukan konten apa yang bisa diakses oleh user dan menentukan hak terhadap assignment serta certificate.

### Key Attributes
- id
- name
- description
- created_at
- updated_at

### Relationships
- Access Tier has many Users
- Access Tier has many Modules
- Access Tier has many Lessons
- Access Tier has many Ebooks
- Access Tier has many Courses

---

## 3.4 Module

### Purpose
Menjadi unit pembelajaran utama yang berisi sekumpulan lesson.

### Description
Module merupakan parent dari lesson. Module dibatasi oleh tier access dan memiliki urutan tampilan.

### Key Attributes
- id
- title
- url_slug
- thumbnail
- access_tier_id
- sort_order
- created_at
- updated_at

### Relationships
- Module belongs to one Access Tier
- Module has many Lessons

---

## 3.5 Lesson

### Purpose
Menyimpan unit pembelajaran yang diakses student di dalam module.

### Description
Lesson adalah item pembelajaran yang dapat berisi workbook, video, audio, content, dan assessment optional.

### Key Attributes
- id
- module_id
- access_tier_id
- assessment_id
- title
- thumbnail
- workbook
- video
- audio
- content
- sort_order
- created_at
- updated_at

### Relationships
- Lesson belongs to one Module
- Lesson belongs to one Access Tier
- Lesson may belong to one Assessment
- Lesson has many Lesson Progress records

---

## 3.6 Lesson Progress

### Purpose
Menyimpan progres student terhadap lesson.

### Description
Lesson Progress menyimpan apakah student sudah download workbook, berapa watch progress video, apakah lesson selesai, dan kapan selesai.

### Key Attributes
- id
- user_id
- lesson_id
- watch_progress
- is_workbook_downloaded
- workbook_downloaded_at
- video_completed_at
- is_done
- completed_at
- created_at
- updated_at

### Relationships
- Lesson Progress belongs to one User
- Lesson Progress belongs to one Lesson

---

## 3.7 Assessment

### Purpose
Menyimpan definisi assessment yang dapat dihubungkan ke lesson.

### Description
Assessment adalah wadah evaluasi yang memiliki title, description, dan duration_minutes.

### Key Attributes
- id
- title
- description
- duration_minutes
- created_at
- updated_at

### Relationships
- Assessment may be used by one Lesson in current design
- Assessment has many Assessment Pages
- Assessment has many Assessment Progress records
- Assessment has many Assessment Attempts

---

## 3.8 Assessment Page

### Purpose
Membagi assessment menjadi beberapa halaman.

### Description
Assessment Page digunakan agar builder dan player assessment mendukung struktur multi-page.

### Key Attributes
- id
- assessment_id
- title
- sort_order
- created_at
- updated_at

### Relationships
- Assessment Page belongs to one Assessment
- Assessment Page has many Questions

---

## 3.9 Question

### Purpose
Menyimpan butir soal di dalam assessment page.

### Description
Question memiliki type yang menentukan cara render dan cara penyimpanan jawaban.

### Key Attributes
- id
- assessment_page_id
- question_text
- type
- sort_order
- created_at
- updated_at

### Relationships
- Question belongs to one Assessment Page
- Question has many Question Options
- Question has many Assessment Answers

---

## 3.10 Question Option

### Purpose
Menyimpan pilihan jawaban untuk question yang membutuhkan option.

### Description
Question Option dipakai untuk question type seperti single_choice, multiple_choice, atau true_false jika diimplementasikan berbasis pilihan.

### Key Attributes
- id
- question_id
- text_option
- is_true
- sort_order
- created_at
- updated_at

### Relationships
- Question Option belongs to one Question
- Question Option has many Assessment Answers

---

## 3.11 Assessment Progress

### Purpose
Menyimpan ringkasan hasil assessment per user per assessment.

### Description
Assessment Progress menyimpan latest score, highest score, jumlah attempt, dan status selesai.

### Key Attributes
- id
- user_id
- assessment_id
- latest_score
- highest_score
- total_attempts
- is_done
- completed_at
- created_at
- updated_at

### Relationships
- Assessment Progress belongs to one User
- Assessment Progress belongs to one Assessment

---

## 3.12 Assessment Attempt

### Purpose
Menyimpan percobaan pengerjaan assessment oleh student.

### Description
Setiap kali student memulai assessment, sistem membuat satu attempt. Attempt menyimpan timer lifecycle dan score final.

### Key Attributes
- id
- user_id
- assessment_id
- attempt_number
- status
- score
- is_passed
- started_at
- expires_at
- submitted_at
- completed_at
- created_at
- updated_at

### Relationships
- Assessment Attempt belongs to one User
- Assessment Attempt belongs to one Assessment
- Assessment Attempt has many Assessment Answers

---

## 3.13 Assessment Answer

### Purpose
Menyimpan jawaban student pada suatu assessment attempt.

### Description
Assessment Answer menyimpan pilihan yang dipilih atau text answer. Untuk multiple choice, satu question dapat menghasilkan lebih dari satu row jawaban.

### Key Attributes
- id
- assessment_attempt_id
- question_id
- question_option_id
- answer_text
- is_correct
- is_final
- answered_at
- created_at
- updated_at

### Relationships
- Assessment Answer belongs to one Assessment Attempt
- Assessment Answer belongs to one Question
- Assessment Answer may belong to one Question Option

---

## 3.14 Assignment Submission

### Purpose
Menyimpan submission assignment video dari student.

### Description
Assignment Submission mewakili pengiriman tugas praktik oleh student berdasarkan assignment_type dan menyimpan status review serta feedback.

### Key Attributes
- id
- user_id
- assignment_type
- assignment_video
- assignment_status
- assignment_feedback
- submitted_at
- graded_at
- created_at
- updated_at

### Relationships
- Assignment Submission belongs to one User

---

## 3.15 Certificate

### Purpose
Menyimpan certificate yang dibuat untuk student.

### Description
Certificate menyimpan tipe certificate, lokasi file, versi, dan informasi generation.

### Key Attributes
- id
- user_id
- certificate_type
- file_path
- file_name
- version
- generated_by_user_id
- generated_at
- deleted_at
- created_at
- updated_at

### Relationships
- Certificate belongs to one User as owner
- Certificate may belong to one User as generator/admin

---

## 3.16 Ebook

### Purpose
Menyimpan resource ebook yang dapat diakses berdasarkan tier.

### Description
Ebook adalah resource independen yang tidak harus berada di dalam lesson.

### Key Attributes
- id
- title
- file
- access_tier_id
- created_at
- updated_at

### Relationships
- Ebook belongs to one Access Tier

---

## 3.17 Course

### Purpose
Menyimpan course independen di luar struktur module -> lesson -> assessment.

### Description
Course adalah resource/konten terpisah yang dapat berisi video dan deskripsi serta dibatasi oleh tier.

### Key Attributes
- id
- title
- url_slug
- access_tier_id
- description
- thumbnail
- video
- created_at
- updated_at

### Relationships
- Course belongs to one Access Tier

---

## 3.18 Email Template

### Purpose
Menyimpan konfigurasi template email per notification type.

### Description
Email Template digunakan untuk mengelola template email admin dan user, status aktif, admin recipients, dan isi template.

### Key Attributes
- id
- notification_type
- notification_name
- is_enabled
- admin_recipients
- subject_user
- body_user
- subject_admin
- body_admin
- created_at
- updated_at

### Relationships
- Email Template has many Email Logs

---

## 3.19 Email Log

### Purpose
Menyimpan histori pengiriman email otomatis maupun test manual.

### Description
Email Log menyimpan snapshot subject/body, recipient, status pengiriman, dan error jika ada.

### Key Attributes
- id
- email_template_id
- notification_type
- reference_type
- reference_id
- recipient_type
- recipient_email
- subject
- body_snapshot
- status
- error_message
- sent_at
- created_at
- updated_at

### Relationships
- Email Log may belong to one Email Template

---

## 3.20 User Activity Log

### Purpose
Menyimpan aktivitas tertentu user untuk audit ringan atau analitik.

### Description
Entity ini bersifat opsional tetapi tetap dipertahankan karena berguna untuk tracking aktivitas user pada level aplikasi.

### Key Attributes
- id
- user_id
- activity_type
- reference_type
- reference_id
- description
- duration_seconds
- created_at
- updated_at

### Relationships
- User Activity Log belongs to one User

---

## 4. Relationship Analysis

Berikut relasi antar entity dalam bentuk analisis domain.

### User and Access
- User belongs to one Access Tier
- User has many User Sessions
- User has many Lesson Progress records
- User has many Assessment Attempts
- User has many Assessment Progress records
- User has many Assignment Submissions
- User has many Certificates
- User has many User Activity Logs

### Learning Content
- Module belongs to one Access Tier
- Module has many Lessons
- Lesson belongs to one Module
- Lesson belongs to one Access Tier
- Lesson may have one Assessment

### Learning Progress
- Lesson Progress belongs to one User
- Lesson Progress belongs to one Lesson

### Assessment Structure
- Assessment has many Assessment Pages
- Assessment Page belongs to one Assessment
- Assessment Page has many Questions
- Question belongs to one Assessment Page
- Question has many Question Options
- Question Option belongs to one Question

### Assessment Execution
- Assessment Progress belongs to one User
- Assessment Progress belongs to one Assessment
- Assessment Attempt belongs to one User
- Assessment Attempt belongs to one Assessment
- Assessment Attempt has many Assessment Answers
- Assessment Answer belongs to one Assessment Attempt
- Assessment Answer belongs to one Question
- Assessment Answer may belong to one Question Option

### Assignment
- Assignment Submission belongs to one User

### Certificate
- Certificate belongs to one User as owner
- Certificate may belong to one User as generator/admin

### Resources
- Ebook belongs to one Access Tier
- Course belongs to one Access Tier

### Email
- Email Template has many Email Logs
- Email Log may reference operational entities through reference_type and reference_id

### Activity
- User Activity Log belongs to one User

---

## 5. ERD Specification

Berikut spesifikasi ERD per entity dalam format yang lebih terstruktur.

---

## 5.1 Entity: User

### Primary Key
- id

### Important Attributes
- access_tier_id
- first_name
- last_name
- email
- whatsapp
- preferred_certificate_picture
- country
- birth_date
- gender
- total_access_duration_seconds

### Foreign Keys
- access_tier_id -> Access Tier.id

### Relationships
- belongs to Access Tier
- has many User Sessions
- has many Lesson Progress
- has many Assessment Progress
- has many Assessment Attempts
- has many Assignment Submissions
- has many Certificates
- has many User Activity Logs

---

## 5.2 Entity: User Session

### Primary Key
- id

### Important Attributes
- login_at
- logout_at
- last_activity_at
- session_duration_seconds
- is_active

### Foreign Keys
- user_id -> User.id

### Relationships
- belongs to User

---

## 5.3 Entity: Access Tier

### Primary Key
- id

### Important Attributes
- name
- description

### Foreign Keys
- none

### Relationships
- has many Users
- has many Modules
- has many Lessons
- has many Ebooks
- has many Courses

---

## 5.4 Entity: Module

### Primary Key
- id

### Important Attributes
- title
- url_slug
- thumbnail
- sort_order

### Foreign Keys
- access_tier_id -> Access Tier.id

### Relationships
- belongs to Access Tier
- has many Lessons

---

## 5.5 Entity: Lesson

### Primary Key
- id

### Important Attributes
- title
- thumbnail
- workbook
- video
- audio
- content
- sort_order

### Foreign Keys
- module_id -> Module.id
- access_tier_id -> Access Tier.id
- assessment_id -> Assessment.id

### Relationships
- belongs to Module
- belongs to Access Tier
- may belong to Assessment
- has many Lesson Progress records

---

## 5.6 Entity: Lesson Progress

### Primary Key
- id

### Important Attributes
- watch_progress
- is_workbook_downloaded
- workbook_downloaded_at
- video_completed_at
- is_done
- completed_at

### Foreign Keys
- user_id -> User.id
- lesson_id -> Lesson.id

### Relationships
- belongs to User
- belongs to Lesson

---

## 5.7 Entity: Assessment

### Primary Key
- id

### Important Attributes
- title
- description
- duration_minutes

### Foreign Keys
- none

### Relationships
- may be linked to Lesson
- has many Assessment Pages
- has many Assessment Progress records
- has many Assessment Attempts

---

## 5.8 Entity: Assessment Page

### Primary Key
- id

### Important Attributes
- title
- sort_order

### Foreign Keys
- assessment_id -> Assessment.id

### Relationships
- belongs to Assessment
- has many Questions

---

## 5.9 Entity: Question

### Primary Key
- id

### Important Attributes
- question_text
- type
- sort_order

### Foreign Keys
- assessment_page_id -> Assessment Page.id

### Relationships
- belongs to Assessment Page
- has many Question Options
- has many Assessment Answers

---

## 5.10 Entity: Question Option

### Primary Key
- id

### Important Attributes
- text_option
- is_true
- sort_order

### Foreign Keys
- question_id -> Question.id

### Relationships
- belongs to Question
- may be referenced by Assessment Answers

---

## 5.11 Entity: Assessment Progress

### Primary Key
- id

### Important Attributes
- latest_score
- highest_score
- total_attempts
- is_done
- completed_at

### Foreign Keys
- user_id -> User.id
- assessment_id -> Assessment.id

### Relationships
- belongs to User
- belongs to Assessment

---

## 5.12 Entity: Assessment Attempt

### Primary Key
- id

### Important Attributes
- attempt_number
- status
- score
- is_passed
- started_at
- expires_at
- submitted_at
- completed_at

### Foreign Keys
- user_id -> User.id
- assessment_id -> Assessment.id

### Relationships
- belongs to User
- belongs to Assessment
- has many Assessment Answers

---

## 5.13 Entity: Assessment Answer

### Primary Key
- id

### Important Attributes
- answer_text
- is_correct
- is_final
- answered_at

### Foreign Keys
- assessment_attempt_id -> Assessment Attempt.id
- question_id -> Question.id
- question_option_id -> Question Option.id

### Relationships
- belongs to Assessment Attempt
- belongs to Question
- may belong to Question Option

---

## 5.14 Entity: Assignment Submission

### Primary Key
- id

### Important Attributes
- assignment_type
- assignment_video
- assignment_status
- assignment_feedback
- submitted_at
- graded_at

### Foreign Keys
- user_id -> User.id

### Relationships
- belongs to User

---

## 5.15 Entity: Certificate

### Primary Key
- id

### Important Attributes
- certificate_type
- file_path
- file_name
- version
- generated_at
- deleted_at

### Foreign Keys
- user_id -> User.id
- generated_by_user_id -> User.id

### Relationships
- belongs to User as owner
- may belong to User as generator

---

## 5.16 Entity: Ebook

### Primary Key
- id

### Important Attributes
- title
- file

### Foreign Keys
- access_tier_id -> Access Tier.id

### Relationships
- belongs to Access Tier

---

## 5.17 Entity: Course

### Primary Key
- id

### Important Attributes
- title
- url_slug
- description
- thumbnail
- video

### Foreign Keys
- access_tier_id -> Access Tier.id

### Relationships
- belongs to Access Tier

---

## 5.18 Entity: Email Template

### Primary Key
- id

### Important Attributes
- notification_type
- notification_name
- is_enabled
- admin_recipients
- subject_user
- body_user
- subject_admin
- body_admin

### Foreign Keys
- none

### Relationships
- has many Email Logs

---

## 5.19 Entity: Email Log

### Primary Key
- id

### Important Attributes
- notification_type
- reference_type
- reference_id
- recipient_type
- recipient_email
- subject
- body_snapshot
- status
- error_message
- sent_at

### Foreign Keys
- email_template_id -> Email Template.id

### Relationships
- may belong to Email Template

---

## 5.20 Entity: User Activity Log

### Primary Key
- id

### Important Attributes
- activity_type
- reference_type
- reference_id
- description
- duration_seconds

### Foreign Keys
- user_id -> User.id

### Relationships
- belongs to User

---

## 6. Mermaid ERD

```mermaid
erDiagram

    USERS {
        bigint id PK
        bigint access_tier_id FK
        string first_name
        string last_name
        string email
        string whatsapp
        string preferred_certificate_picture
        string instagram
        string country
        date birth_date
        string gender
        datetime last_visit_at
        int total_access_duration_seconds
        string practicing_yoga_for
        string yoga_sequence_experience
        int hours_per_week
        string current_fitness_level
        string flexibility_rating
        text motivation
        text why_yogafx
        text how_did_you_find_us
    }

    USER_SESSIONS {
        bigint id PK
        bigint user_id FK
        datetime login_at
        datetime logout_at
        datetime last_activity_at
        int session_duration_seconds
        boolean is_active
    }

    ACCESS_TIERS {
        bigint id PK
        string name
        text description
    }

    MODULES {
        bigint id PK
        bigint access_tier_id FK
        string title
        string url_slug
        string thumbnail
        int sort_order
    }

    LESSONS {
        bigint id PK
        bigint module_id FK
        bigint access_tier_id FK
        bigint assessment_id FK
        string title
        string thumbnail
        string workbook
        string video
        string audio
        text content
        int sort_order
    }

    LESSON_PROGRESS {
        bigint id PK
        bigint user_id FK
        bigint lesson_id FK
        decimal watch_progress
        boolean is_workbook_downloaded
        datetime workbook_downloaded_at
        datetime video_completed_at
        boolean is_done
        datetime completed_at
    }

    ASSESSMENTS {
        bigint id PK
        string title
        text description
        int duration_minutes
    }

    ASSESSMENT_PAGES {
        bigint id PK
        bigint assessment_id FK
        string title
        int sort_order
    }

    QUESTIONS {
        bigint id PK
        bigint assessment_page_id FK
        text question_text
        string type
        int sort_order
    }

    QUESTION_OPTIONS {
        bigint id PK
        bigint question_id FK
        text text_option
        boolean is_true
        int sort_order
    }

    ASSESSMENT_PROGRESS {
        bigint id PK
        bigint user_id FK
        bigint assessment_id FK
        decimal latest_score
        decimal highest_score
        int total_attempts
        boolean is_done
        datetime completed_at
    }

    ASSESSMENT_ATTEMPTS {
        bigint id PK
        bigint user_id FK
        bigint assessment_id FK
        int attempt_number
        string status
        decimal score
        boolean is_passed
        datetime started_at
        datetime expires_at
        datetime submitted_at
        datetime completed_at
    }

    ASSESSMENT_ANSWERS {
        bigint id PK
        bigint assessment_attempt_id FK
        bigint question_id FK
        bigint question_option_id FK
        text answer_text
        boolean is_correct
        boolean is_final
        datetime answered_at
    }

    ASSIGNMENT_SUBMISSIONS {
        bigint id PK
        bigint user_id FK
        string assignment_type
        string assignment_video
        string assignment_status
        text assignment_feedback
        datetime submitted_at
        datetime graded_at
    }

    CERTIFICATES {
        bigint id PK
        bigint user_id FK
        bigint generated_by_user_id FK
        string certificate_type
        string file_path
        string file_name
        int version
        datetime generated_at
        datetime deleted_at
    }

    EBOOKS {
        bigint id PK
        bigint access_tier_id FK
        string title
        string file
    }

    COURSES {
        bigint id PK
        bigint access_tier_id FK
        string title
        string url_slug
        text description
        string thumbnail
        string video
    }

    EMAIL_TEMPLATES {
        bigint id PK
        string notification_type
        string notification_name
        boolean is_enabled
        text admin_recipients
        string subject_user
        text body_user
        string subject_admin
        text body_admin
    }

    EMAIL_LOGS {
        bigint id PK
        bigint email_template_id FK
        string notification_type
        string reference_type
        bigint reference_id
        string recipient_type
        string recipient_email
        string subject
        text body_snapshot
        string status
        text error_message
        datetime sent_at
    }

    USER_ACTIVITY_LOGS {
        bigint id PK
        bigint user_id FK
        string activity_type
        string reference_type
        bigint reference_id
        text description
        int duration_seconds
    }

    ACCESS_TIERS ||--o{ USERS : has
    ACCESS_TIERS ||--o{ MODULES : controls
    ACCESS_TIERS ||--o{ LESSONS : controls
    ACCESS_TIERS ||--o{ EBOOKS : controls
    ACCESS_TIERS ||--o{ COURSES : controls

    USERS ||--o{ USER_SESSIONS : has
    USERS ||--o{ LESSON_PROGRESS : has
    USERS ||--o{ ASSESSMENT_PROGRESS : has
    USERS ||--o{ ASSESSMENT_ATTEMPTS : has
    USERS ||--o{ ASSIGNMENT_SUBMISSIONS : submits
    USERS ||--o{ CERTIFICATES : owns
    USERS ||--o{ CERTIFICATES : generates
    USERS ||--o{ USER_ACTIVITY_LOGS : has

    MODULES ||--o{ LESSONS : contains
    LESSONS ||--o{ LESSON_PROGRESS : tracked_by
    ASSESSMENTS ||--o| LESSONS : linked_to

    ASSESSMENTS ||--o{ ASSESSMENT_PAGES : has
    ASSESSMENT_PAGES ||--o{ QUESTIONS : contains
    QUESTIONS ||--o{ QUESTION_OPTIONS : has

    ASSESSMENTS ||--o{ ASSESSMENT_PROGRESS : summarized_by
    ASSESSMENTS ||--o{ ASSESSMENT_ATTEMPTS : attempted_in
    ASSESSMENT_ATTEMPTS ||--o{ ASSESSMENT_ANSWERS : contains
    QUESTIONS ||--o{ ASSESSMENT_ANSWERS : answered_by
    QUESTION_OPTIONS ||--o{ ASSESSMENT_ANSWERS : selected_in

    EMAIL_TEMPLATES ||--o{ EMAIL_LOGS : produces
````

---

## 7. Design Notes

## 7.1 User as Core Identity

Satu entitas User dipakai sebagai pusat identity untuk Admin dan Student. Ini menjaga arsitektur tetap sederhana pada phase 1 dan sesuai dengan keputusan bahwa role utama hanya dua.

## 7.2 Access Tier as First-Class Domain

Access Tier dipisahkan sebagai entitas tersendiri karena memengaruhi banyak area sistem:

* user access
* module access
* lesson access
* ebook access
* course access

Ini membuat business rule tier bisa diterapkan lebih konsisten.

## 7.3 Module -> Lesson as Main Learning Backbone

Struktur pembelajaran utama dibangun melalui Module dan Lesson karena user flow dan business rule paling banyak berputar di area ini.

## 7.4 Assessment Detached but Linkable

Assessment dipisahkan dari Lesson sebagai entitas mandiri, lalu dihubungkan secara optional ke Lesson. Ini memberi fleksibilitas builder dan menjaga struktur evaluasi tetap modular.

## 7.5 Assessment Page Needed for Real Builder Flow

Karena assessment builder final mendukung tombol `Add New Page` dan `Add New Question`, maka Assessment Page adalah entity wajib. Question tidak lagi langsung berada di Assessment.

## 7.6 Question Type Drives Answer Structure

Question memiliki type karena sistem perlu mendukung:

* single_choice
* multiple_choice
* text
* true_false

Akibatnya, Assessment Answer harus cukup fleksibel untuk menyimpan:

* selected option
* multiple selected options
* free text answer

## 7.7 Assessment Attempt and Assessment Progress Must Be Separate

Assessment Attempt menyimpan histori percobaan individual, sedangkan Assessment Progress menyimpan ringkasan agregat per user per assessment. Keduanya dibutuhkan karena sistem ingin mendukung multiple attempts sekaligus summary view yang cepat.

## 7.8 Assignment Submission Kept Dynamic

Assignment disimpan dengan `assignment_type`, bukan kolom fixed standing/floor, agar tetap fleksibel bila ke depan assignment type bertambah.

## 7.9 Certificate as Explicit Entity

Certificate dipisahkan sebagai entity sendiri karena:

* perlu menyimpan tipe certificate
* perlu file reference
* perlu version
* perlu siapa yang generate
* perlu bisa recreate

## 7.10 Email Template and Email Log Must Be Separate

Email Template digunakan untuk konfigurasi, sedangkan Email Log digunakan untuk histori pengiriman. Ini penting karena template bisa berubah, tetapi histori pengiriman harus tetap mempertahankan snapshot isi email.

## 7.11 Course and Ebook Stay Independent

Course dan Ebook tidak dipaksa masuk ke struktur Module -> Lesson karena keputusan sistem terbaru mempertahankan keduanya sebagai resource independen.

## 7.12 User Session Needed for Cumulative Time

User Session dipertahankan terpisah dari User karena sistem perlu:

* menyimpan histori login
* menghitung session duration
* melanjutkan timer dari total sebelumnya

## 7.13 User Activity Log Kept Optional but Valuable

Walaupun tidak semua fitur phase 1 bergantung langsung pada activity logs, entity ini tetap masuk karena berguna untuk audit ringan atau analitik operasional.

---

## 8. Assumptions

## Assumption 1

Role user tidak dimodelkan sebagai tabel terpisah dalam dokumen ini karena phase 1 hanya mengenal dua role utama dan belum ada requirement role hierarchy yang final.

## Assumption 2

Lesson hanya mengacu ke paling banyak satu Assessment pada desain saat ini karena business flow yang disepakati adalah satu lesson dapat memiliki assessment optional, bukan banyak assessment aktif sekaligus.

## Assumption 3

Course tetap dipisahkan dari Module karena keputusan terbaru menempatkannya sebagai konten independen, walaupun secara bisnis bisa saja di masa depan dihubungkan lebih erat ke pembelajaran utama.

## Assumption 4

Assignment reviewer tidak dimodelkan sebagai entitas terpisah pada phase 1 karena keputusan final belum mensyaratkan multi-reviewer formal.

## Assumption 5

Email placeholder tidak dibuat sebagai entity tersendiri karena requirement hanya meminta pengelolaan template dan rendering merge tags, bukan manajemen placeholder katalog berbasis data.

## Assumption 6

Question type `true_false` tetap dipertahankan sebagai type tersendiri walaupun implementasi visualnya dapat menggunakan option di layer aplikasi.

## Assumption 7

Certificate format fisik file tidak dibekukan pada level domain ini; yang penting adalah sistem menyimpan reference file certificate yang dapat diakses atau diunduh.

## Assumption 8

Generated_by_user_id pada Certificate diasumsikan merujuk ke User yang bertindak sebagai Admin pada saat certificate dibuat.

## Assumption 9

Reference_type dan reference_id pada Email Log serta User Activity Log dibiarkan generic karena requirement membutuhkan fleksibilitas untuk mengaitkan log ke berbagai domain bisnis.

