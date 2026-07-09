# 03-erd.md
# Entity Relationship Design
# YogaFX LMS

## 1. Purpose

Dokumen ini mendeskripsikan model data aktif YogaFX LMS berdasarkan migration dan model yang benar-benar ada saat ini.

Dokumen ini tidak lagi memperlakukan assessment, assignment, payment, onboarding, dan tracking sebagai future-only domain karena semuanya sudah punya schema aktif.

---

## 2. Active Data Domains

Domain data aktif saat ini:
- users and roles
- access tiers and package commerce
- public registration, checkout, invoice, payment, subscription, onboarding
- learning content and tier access
- assessments
- assignments
- lesson progress and module visits
- certificates
- email templates and logs
- dialog content
- student access time and sessions
- link control settings

---

## 3. Active Entities

### 3.1 User
Purpose:
- identitas utama Admin dan Student

Key fields:
- `id`
- `name`
<<<<<<< Updated upstream
- `role` (`super_admin`, `admin`, `student`)
=======
- `role`
>>>>>>> Stashed changes
- `is_active`
- `access_tier_id`
- `total_access_duration_seconds`
- `email`
- `password`
- `first_name`
- `last_name`
- `whatsapp`
- `preferred_certificate_picture`
- `profile_photo`
- `instagram`
- `country`
- `birth_date`
- `gender`
- `practicing_yoga_for`
- `yoga_sequence_experience`
- `hours_per_week`
- `current_fitness_level`
- `flexibility_rating`
- `motivation`
- `why_yogafx`
- `how_did_you_find_us`

Relationships:
- belongs to one `AccessTier`
- has many `LessonProgress`
- has many `AssignmentSubmission`
- has many `AssessmentAttempt`
- has many `AssessmentProgress`
- has many `Certificate`
- has many `UserSession`
- has many `StudentModuleVisit`
- has many `Invoice`
- has one `OnboardingState`

### 3.2 AccessTier
Purpose:
- membership tier dan entitlement/access control

Key fields:
- `id`
- `name`
- `slug`
- `description`
- `thumbnail`
- `level`
- `is_active`

Relationships:
- has many `User`
- belongs to many `Module`
- belongs to many `Lesson`
- belongs to many `Ebook`
- belongs to many `Course`
- has one active `Package` assignment at a time
- has many `PendingRegistration`
- has many `Invoice`

### 3.3 Package
Purpose:
- commerce/payment offer layer untuk initial checkout

Key fields:
- `id`
- `access_tier_id`
- `title`
- `slug`
- `description`
- `image`
- `price`
- `currency_code`
- `billing_interval_unit`
- `billing_interval_count`
- `installment_enabled`
- `fixed_billing_day`
- `installment_deadline_month`
- `installment_deadline_day`
- `paypal_product_id`
- `paypal_plan_id`
- `is_active`

Relationships:
- belongs to `AccessTier` nullable
- has many `PendingRegistration`
- has many `Invoice`
- has many `PaymentSubscription`

### 3.4 PendingRegistration
Purpose:
- lead / pre-student record sebelum checkout selesai

Key fields:
- `id`
- `access_tier_id`
- `package_id`
- `first_name`
- `last_name`
- `email`
- `phone`
- `country`
- `amount_snapshot`
- `status`
- `checkout_opened_at`
- `payment_succeeded_at`
- `completed_at`

Relationships:
- belongs to `AccessTier`
- belongs to `Package` nullable
- has many `Invoice`
- has many `PaymentSubscription`
- has one `OnboardingState`

### 3.5 OnboardingState
Purpose:
- mengontrol continuation flow setelah payment sukses

Key fields:
- `id`
- `pending_registration_id`
- `user_id`
- `status`
- `continuation_sent_at`
- `enrollment_completed_at`
- `signup_completed_at`

Relationships:
- belongs to `PendingRegistration`
- belongs to `User`

### 3.6 Invoice
Purpose:
- dokumen tagihan untuk initial checkout dan student upgrade

Key fields:
- `id`
- `invoice_number`
- `pending_registration_id`
- `user_id`
- `package_id`
- `access_tier_id`
- `type`
- `payment_type`
- `total_amount`
- `balance_due`
- `currency_code`
- `status`
- `issued_at`
- `paid_at`

Relationships:
- belongs to `PendingRegistration` nullable
- belongs to `User` nullable
- belongs to `Package` nullable
- belongs to `AccessTier`
- has many `Payment`
- has many `PaymentSubscription`

### 3.7 Payment
Purpose:
- aktivitas pembayaran per invoice

Key fields:
- `id`
- `invoice_id`
- `payment_method`
- `payment_type`
- `amount_paid`
- `currency_code`
- `status`
- `payment_reference`
- `notes`

Relationships:
- belongs to `Invoice`

### 3.8 PaymentSubscription
Purpose:
- aggregate canonical untuk lifecycle installment package

Key fields:
- `id`
- `invoice_id`
- `package_id`
- `pending_registration_id`
- `user_id`
- `access_tier_id`
- `provider`
- `provider_product_id`
- `provider_plan_id`
- `provider_subscription_id`
- `status`
- `installment_count`
- `installments_paid_count`
- `currency_code`
- `total_amount`
- `monthly_base_amount`
- `first_payment_amount`
- `next_billing_amount`
- `started_at`
- `first_payment_paid_at`
- `next_due_at`
- `final_due_at`
- `grace_deadline_at`
- `completed_at`
- `suspended_at`
- `cancelled_at`
- `last_payment_failed_at`
- `last_synced_at`
- `metadata`

Relationships:
- belongs to `Invoice`
- belongs to `Package`
- belongs to `PendingRegistration` nullable
- belongs to `User` nullable
- belongs to `AccessTier`
- has many `PaymentSubscriptionEvent`

### 3.9 PaymentSubscriptionEvent
Purpose:
- event log idempotent untuk webhook PayPal subscription

Key fields:
- `id`
- `payment_subscription_id`
- `invoice_id`
- `payment_activity_id`
- `provider`
- `provider_event_id`
- `provider_event_type`
- `provider_subscription_id`
- `provider_order_id`
- `provider_capture_id`
- `occurred_at`
- `processed_at`
- `status`
- `payload`
- `notes`

Relationships:
- belongs to `PaymentSubscription` nullable
- belongs to `Invoice` nullable
- belongs to `Payment` as payment activity nullable

### 3.10 Module
Purpose:
- container utama pembelajaran

Key fields:
- `id`
- `title`
- `description`
- `url_slug`
- `thumbnail`
- `sort_order`
- `certificate_enabled`
- `ebook_enabled`
- `video_lecturer_enabled`

Relationships:
- belongs to many `AccessTier`
- has many `Lesson`
- has many `Assignment`
- has many `StudentModuleVisit`

### 3.11 Lesson
Purpose:
- unit pembelajaran di dalam module

Key fields:
- `id`
- `module_id`
- `assessment_id`
- `title`
- `thumbnail`
- `workbook`
- `lesson_video_id`
- `audio_url`
- `content`
- `sort_order`

Relationships:
- belongs to `Module`
- belongs to many `AccessTier`
- belongs to `Assessment` nullable
- has many `LessonProgress`

### 3.12 Ebook
Purpose:
- resource file mandiri

Key fields:
- `id`
- `title`
- `file`
- `sort_order`

Relationships:
- belongs to many `AccessTier`

### 3.13 Course
Purpose:
- video lecture / course resource mandiri

Key fields:
- `id`
- `title`
- `url_slug`
- `description`
- `thumbnail`
- `video`

Relationships:
- belongs to many `AccessTier`

### 3.14 Assignment
Purpose:
- tugas student yang ditempelkan ke module

Key fields:
- `id`
- `module_id`
- `title`
- `description`
- `sort_order`
- `status`
- `is_required`

Relationships:
- belongs to `Module`
- has many `AssignmentSubmission`

### 3.15 AssignmentSubmission
Purpose:
- submission video assignment student

Key fields:
- `id`
- `user_id`
- `assignment_id`
- `assignment_type`
- `assignment_video`
- `assignment_status`
- `assignment_feedback`
- `submitted_at`
- `graded_at`
- `reviewed_at`
- `reviewed_by`

Relationships:
- belongs to `User`
- belongs to `Assignment` nullable
- belongs to `User` as reviewer nullable

### 3.16 Assessment
Purpose:
- definisi assessment / scoreboard

Key fields:
- `id`
- `title`
- `slug`
- `description`
- `thumbnail`
- `status`
- `duration_minutes`
- `scoring_mode`
- `result_mode`
- `is_active`
- `show_progress_bar`
- `allow_back_navigation`

Relationships:
- has one `AssessmentDesign`
- has many `Question`
- has many `AssessmentResultRange`
- has many `AssessmentAttempt`
- has many `AssessmentProgress`
- has one `Lesson`

### 3.17 AssessmentDesign
Purpose:
- konfigurasi visual assessment

Key fields:
- `id`
- `assessment_id`
- `logo`
- `logo_max_width`
- `logo_alignment`
- `logo_link`
- `header_position`
- `section_background`
- `top_margin`
- `bottom_margin`
- `footer_content`

Relationships:
- belongs to `Assessment`

### 3.18 Question
Purpose:
- screen/question di dalam assessment

Key fields:
- `id`
- `assessment_id`
- `title`
- `question_text`
- `question_type`
- `sort_order`
- `required`
- `randomize_answers_order`
- `jump_enabled`
- `jump_to_question_id`

Relationships:
- belongs to `Assessment`
- has many `QuestionOption`
- self references optional jump target

### 3.19 QuestionOption
Purpose:
- opsi jawaban assessment

Key fields:
- `id`
- `question_id`
- `label`
- `internal_value`
- `image`
- `sort_order`
- `scoring_enabled`
- `score_value`
- `jump_enabled`
- `jump_to_question_id`
- `is_other_option`
- `is_fixed_option`
- `is_correct`

Relationships:
- belongs to `Question`
- self references optional jump target via `questions.id`

### 3.20 AssessmentResultRange
Purpose:
- pemetaan rentang skor ke label hasil

Key fields:
- `id`
- `assessment_id`
- `title`
- `description`
- `min_score`
- `max_score`
- `sort_order`

Relationships:
- belongs to `Assessment`

### 3.21 AssessmentAttempt
Purpose:
- attempt assessment per student

Key fields:
- `id`
- `user_id`
- `assessment_id`
- `attempt_number`
- `status`
- `started_at`
- `expires_at`
- `submitted_at`
- `completed_at`
- `current_question_id`
- `last_answered_question_id`
- `total_score`
- `result_range_id`
- `result_label`
- `finished_reason`

Relationships:
- belongs to `User`
- belongs to `Assessment`
- belongs to `Question` as current question nullable
- belongs to `Question` as last answered question nullable
- belongs to `AssessmentResultRange` nullable
- has many `AssessmentAnswer`

### 3.22 AssessmentAnswer
Purpose:
- jawaban yang tersimpan dalam satu attempt

Key fields:
- `id`
- `assessment_attempt_id`
- `question_id`
- `question_option_id`
- `answer_text`
- `answer_number`
- `answer_boolean`
- `score_awarded`
- `is_final`
- `answered_at`

Relationships:
- belongs to `AssessmentAttempt`
- belongs to `Question`
- belongs to `QuestionOption` nullable

### 3.23 AssessmentProgress
Purpose:
- ringkasan progress assessment per student

Key fields:
- `id`
- `user_id`
- `assessment_id`
- `latest_score`
- `highest_score`
- `total_attempts`
- `is_done`
- `completed_at`

Relationships:
- belongs to `User`
- belongs to `Assessment`

### 3.24 LessonProgress
Purpose:
- progress lesson student

Key fields:
- `id`
- `user_id`
- `lesson_id`
- `watch_progress`
- `is_workbook_downloaded`
- `workbook_downloaded_at`
- `video_completed_at`
- `is_done`
- `completed_at`

Relationships:
- belongs to `User`
- belongs to `Lesson`

### 3.25 StudentModuleVisit
Purpose:
- menandai module yang pernah dibuka student

Key fields:
- `id`
- `user_id`
- `module_id`
- `opened_at`

Relationships:
- belongs to `User`
- belongs to `Module`

### 3.26 Certificate
Purpose:
- record certificate yang dihasilkan admin

Key fields:
- `id`
- `user_id`
- `generated_by_user_id`
- `certificate_type`
- `file_path`
- `file_name`
- `version`
- `generated_at`
- `deleted_at`

Relationships:
- belongs to `User` as owner
- belongs to `User` as generator
- has many `CertificateDownloadEvent`

### 3.27 CertificateDownloadEvent
Purpose:
- tracking download certificate per student/module

Key fields:
- `id`
- `user_id`
- `module_id`
- `certificate_id`
- `downloaded_at`

Relationships:
- belongs to `User`
- belongs to `Module`
- belongs to `Certificate` nullable

### 3.28 DialogContent
Purpose:
- konten dialog student instant access

Key fields:
- `id`
- `key`
- `title`
- `content`

### 3.29 EmailTemplate
Purpose:
- konfigurasi template email per notification type

Key fields:
- `id`
- `notification_type`
- `notification_name`
- `is_enabled`
- `admin_recipients`
- `subject_user`
- `body_user`
- `subject_admin`
- `body_admin`

Relationships:
- has many `EmailLog`

### 3.30 EmailLog
Purpose:
- histori email automated dan send test

Key fields:
- `id`
- `email_template_id`
- `notification_type`
- `reference_type`
- `reference_id`
- `recipient_type`
- `recipient_email`
- `subject`
- `body_snapshot`
- `status`
- `error_message`
- `sent_at`

Relationships:
- belongs to `EmailTemplate` nullable

### 3.31 UserSession
Purpose:
- tracking sesi login student

Key fields:
- `id`
- `user_id`
- `session_id`
- `login_at`
- `last_activity_at`
- `logout_at`
- `session_duration_seconds`
- `is_active`

Relationships:
- belongs to `User`

### 3.32 LinkControlSetting
Purpose:
- single global configuration untuk QR app dan store links student

Key fields:
- `id`
- `qr_image`
- `google_play_url`
- `app_store_url`

### 3.33 Pivot Tables

#### `access_tier_module`
- `access_tier_id`
- `module_id`

#### `access_tier_lesson`
- `access_tier_id`
- `lesson_id`

#### `access_tier_ebook`
- `access_tier_id`
- `ebook_id`

#### `access_tier_course`
- `access_tier_id`
- `course_id`

---

## 4. Important Current Rules Reflected In Data Model

### 4.1 Student Tetap Punya Satu Tier Aktif
Student masih memakai `users.access_tier_id` tunggal.

### 4.2 Content Access Sudah Banyak yang Many-to-Many
`Module`, `Lesson`, `Ebook`, dan `Course` memakai pivot tier access.

### 4.3 Assessment Bukan Lagi Placeholder
Assessment sudah punya schema lengkap untuk:
- design
- questions
- options
- result ranges
- attempts
- answers
- progress

### 4.4 Payment, Package, dan Onboarding Sudah Menjadi Domain Aktif
`Package`, `PendingRegistration`, `OnboardingState`, `Invoice`, `Payment`, `PaymentSubscription`, dan `PaymentSubscriptionEvent` adalah bagian aktif dari product flow.

### 4.5 Certificate Tetap Soft Delete
`Certificate` memakai soft delete agar recreate dan riwayat file tetap bisa dilacak.

---

## 5. Mermaid ERD

Diagram Mermaid aktif disimpan di:
- `docs/mermaid/ERD.mmd`

Diagram tersebut sudah disinkronkan dengan package commerce dan installment lifecycle yang aktif.

---

## 6. Notes

- tabel Laravel bawaan seperti `password_reset_tokens` dan `sessions` tetap ada, tetapi bukan domain produk utama yang dijelaskan penuh di sini
- jika nanti muncul domain baru yang benar-benar aktif, dokumen ini harus diperbarui mengikuti migration dan model aktual
