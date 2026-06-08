# 05-information-architecture.md
# Information Architecture
# YogaFX LMS

## 1. Purpose of This Document

Dokumen ini mendefinisikan Information Architecture (IA) untuk YogaFX LMS.

Tujuan utama dokumen ini adalah:
- memetakan struktur informasi sistem
- menentukan bagaimana konten, halaman, dan fitur dikelompokkan
- membantu pengambilan keputusan UX dan navigasi
- menjadi acuan untuk admin side dan student side
- membantu AI Coding Assistant membangun struktur halaman tanpa menebak-nebak

Dokumen ini tidak membahas:
- database schema
- API endpoint
- class diagram
- kode program
- implementasi teknis routing

Dokumen ini fokus pada:
- struktur navigasi
- grouping fitur
- hirarki halaman
- hubungan antar area produk
- prioritas informasi

---

## 2. Information Architecture Principles

## 2.1 Role-Based Structure
Arsitektur informasi dipisahkan jelas berdasarkan role:
- Admin
- Student

Kedua role tidak boleh berbagi struktur navigasi utama yang membingungkan.

## 2.2 Task-First Organization
Menu dan halaman harus disusun berdasarkan tugas user, bukan berdasarkan istilah teknis internal.

Contoh:
- Student mencari “lanjut belajar”, bukan “lesson progress table”
- Admin mencari “Assignments”, bukan “submission processing entity”

## 2.3 Content Hierarchy Before Feature Depth
Sistem harus menampilkan hirarki pembelajaran secara jelas:
- Dashboard
- Modules
- Lessons
- Assessment
- Assignment
- Certificate

## 2.4 Clarity Over Density
Setiap area harus punya fokus yang jelas. Hindari halaman yang memuat terlalu banyak level informasi sekaligus.

## 2.5 Streaming-Like Student Discovery
Student side harus disusun seperti platform konten premium:
- continue learning
- discoverable content
- content grouping yang nyaman
- progress yang ringan namun jelas

## 2.6 Operational Efficiency for Admin
Admin side harus disusun untuk efisiensi kerja:
- cepat menemukan data
- cepat mengeksekusi aksi
- cepat memahami status
- cepat berpindah antar area kerja

---

## 3. Top-Level Architecture

Sistem dibagi menjadi 2 area besar:

1. **Student Side**
2. **Admin Side**

Selain itu ada area umum:
3. **Authentication & Session Entry**

---

## 4. Global Site Map

## 4.1 Public / Entry Layer
- Login
- Forgot Password
- Reset Password

## 4.2 Student Area
- Student Dashboard
- Profile
- Modules
- Module Detail
- Lesson Detail
- Assessment Intro
- Assessment Player
- Assessment Result
- Assignments
- Certificates
- Ebooks
- Courses

## 4.3 Admin Area
- Admin Dashboard
- Access Tiers
- Students
- Student Detail
- Modules
- Lessons
- Assessments
- Assessment Builder
- Assignment Review
- Certificates
- Ebooks
- Courses
- Email Notifications
- Email Logs

---

## 5. Role-Based Information Architecture

# 5.1 Student Information Architecture

Student side harus sederhana, konten-first, dan progress-driven.

## Primary Student Sections
1. Dashboard
2. Modules
3. Assignments
4. Certificates
5. Ebooks
6. Courses
7. Profile

### Student IA Priorities
Urutan kepentingan informasi:
1. Continue Learning
2. Current Progress
3. Available Learning Content
4. Required Action
5. Resources
6. Profile

---

# 5.2 Admin Information Architecture

Admin side harus data-oriented, task-oriented, dan structured.

## Primary Admin Sections
1. Dashboard
2. Students
3. Access Tiers
4. Modules
5. Lessons
6. Assessments
7. Assignments
8. Certificates
9. Ebooks
10. Courses
11. Email Notifications
12. Email Logs

### Admin IA Priorities
Urutan kepentingan informasi:
1. Operational Status
2. Content Management
3. Student Management
4. Review Workflow
5. Notification Control
6. Resource Maintenance

---

## 6. Student Side Information Architecture

# 6.1 Student Dashboard

## Purpose
Menjadi titik masuk utama student setelah login.

## Information Priority
1. Continue Learning
2. Current Progress
3. Current Module / Lesson
4. Assignment Status
5. Certificate Status
6. Available Content Discovery
7. Extra Resources

## Main Blocks
- Welcome / Greeting
- Continue Learning
- In Progress Modules
- Available Modules
- Assignment Status
- Certificate Status
- Ebooks / Courses Preview

## Key Navigation Paths
- Dashboard -> Continue Learning -> Lesson
- Dashboard -> Modules
- Dashboard -> Assignments
- Dashboard -> Certificates
- Dashboard -> Ebooks
- Dashboard -> Courses

---

# 6.2 Student Profile

## Purpose
Mengelola data profile student.

## Information Groups
- Personal Identity
- Contact Information
- Learning Background
- Yoga Experience
- Certificate Photo Preference

## Key Navigation Paths
- Dashboard -> Profile
- Profile -> Save Changes

---

# 6.3 Modules

## Purpose
Menampilkan katalog module yang tersedia untuk student.

## Information Groups
- Available Modules
- In Progress Modules
- Completed Modules
- Locked Modules

## Main Information per Module
- Title
- Thumbnail
- Progress
- Status
- Access State

## Key Navigation Paths
- Modules -> Module Detail
- Module Detail -> Lesson Detail

---

# 6.4 Module Detail

## Purpose
Menjadi container navigasi ke lesson dalam satu module.

## Information Groups
- Module Overview
- Lesson List
- Lesson Status
- Progress Summary

## Main Information per Lesson
- Lesson Title
- Locked / Available / Completed
- Current Position
- Assessment Presence

## Key Navigation Paths
- Module Detail -> Lesson Detail
- Module Detail -> Resume Latest Lesson

---

# 6.5 Lesson Detail

## Purpose
Menjadi halaman pembelajaran utama untuk satu lesson.

## Information Groups
- Lesson Header
- Workbook Section
- Video Learning Section
- Audio Section
- Text Content Section
- Assessment Section
- Completion State
- Next Step Guidance

## Required Visibility
- Apakah workbook wajib didownload
- Apakah assessment masih locked
- Watch progress
- Apakah lesson sudah complete
- Apa langkah berikutnya

## Key Navigation Paths
- Lesson Detail -> Download Workbook
- Lesson Detail -> Watch Video
- Lesson Detail -> Open Assessment
- Lesson Detail -> Next Lesson

---

# 6.6 Assessment Area

Assessment area sebaiknya dipisah menjadi 3 lapisan:

## 6.6.1 Assessment Intro
Menampilkan:
- title
- description
- duration
- start action

## 6.6.2 Assessment Player
Menampilkan:
- current page
- timer
- progress
- question content
- answer area
- page navigation
- submit action

## 6.6.3 Assessment Result
Menampilkan:
- score
- status
- completion summary
- next step

## Key Navigation Paths
- Lesson Detail -> Assessment Intro
- Assessment Intro -> Start Assessment
- Assessment Player -> Result
- Result -> Return to Learning Journey

---

# 6.7 Assignments

## Purpose
Mengelola area student untuk assignment submission.

## Information Groups
- Assignment Types
- Assignment Status
- Submission History
- Feedback
- Upload Action

## Status States
- not_submitted
- pending_review
- approved
- rejected

## Key Navigation Paths
- Dashboard -> Assignments
- Assignments -> Upload Submission
- Assignments -> View Feedback

---

# 6.8 Certificates

## Purpose
Menampilkan certificate yang dimiliki student.

## Information Groups
- Certificate List
- Certificate Type
- Availability Status
- Download Action

## Key Navigation Paths
- Dashboard -> Certificates
- Certificates -> Download Certificate

---

# 6.9 Ebooks

## Purpose
Menampilkan resource ebook yang tersedia untuk student.

## Information Groups
- Available Ebooks
- Access Tier Eligibility
- Download / Open Action

## Key Navigation Paths
- Dashboard -> Ebooks
- Ebooks -> Open or Download Ebook

---

# 6.10 Courses

## Purpose
Menampilkan course independen di luar struktur utama module -> lesson.

## Information Groups
- Course Catalog
- Title
- Thumbnail
- Description
- Access State

## Key Navigation Paths
- Dashboard -> Courses
- Courses -> Course Detail or Open Course

---

## 7. Admin Side Information Architecture

# 7.1 Admin Dashboard

## Purpose
Menjadi pusat orientasi operasional admin.

## Information Priority
1. Pending Assignment Reviews
2. Student Summary
3. Content Summary
4. Certificate Activity
5. Email Activity
6. Quick Actions

## Main Blocks
- Summary Cards
- Pending Work
- Quick Links
- Activity Overview

## Key Navigation Paths
- Dashboard -> Assignments
- Dashboard -> Students
- Dashboard -> Modules
- Dashboard -> Certificates
- Dashboard -> Email Notifications

---

# 7.2 Students

## Purpose
Mengelola data student dan memantau progres belajar.

## Information Groups
- Student List
- Student Detail
- Progress Summary
- Assignment Status
- Certificate Status
- Session Summary

## Key Navigation Paths
- Students -> Student Detail
- Student Detail -> Progress Area
- Student Detail -> Assignment Review Context
- Student Detail -> Certificate Action

---

# 7.3 Access Tiers

## Purpose
Mengelola membership tier yang menentukan hak akses.

## Information Groups
- Tier List
- Tier Definition
- Access Scope

## Key Navigation Paths
- Access Tiers -> Create/Edit Tier

---

# 7.4 Modules

## Purpose
Mengelola struktur module.

## Information Groups
- Module List
- Module Form
- Tier
- Order

## Key Navigation Paths
- Modules -> Create Module
- Modules -> Edit Module
- Modules -> Module Context -> Lessons

---

# 7.5 Lessons

## Purpose
Mengelola lesson di dalam module.

## Information Groups
- Lesson List
- Lesson Form
- Linked Module
- Access Tier
- Assessment Link
- Workbook / Video / Audio / Content

## Key Navigation Paths
- Lessons -> Create Lesson
- Lessons -> Edit Lesson
- Lesson -> Assessment Link

---

# 7.6 Assessments

## Purpose
Mengelola assessment sebagai evaluasi pembelajaran.

## Information Groups
- Assessment List
- Assessment Form
- Assessment Builder
- Assessment Results

## Sub-areas
### Assessment List
Daftar assessment

### Assessment Form
General info:
- title
- description
- duration

### Assessment Builder
- pages
- questions
- options
- reordering

### Assessment Results
- per attempt
- score
- completion time
- student identity

## Key Navigation Paths
- Assessments -> Create
- Assessments -> Edit
- Assessments -> Builder
- Assessments -> Results

---

# 7.7 Assignments

## Purpose
Mengelola review assignment submission dari student.

## Information Groups
- Submission List
- Review Queue
- Submission Detail
- Video Review
- Status Decision
- Feedback

## Key Navigation Paths
- Assignments -> Submission Detail
- Submission Detail -> Approve
- Submission Detail -> Reject

---

# 7.8 Certificates

## Purpose
Mengelola generate, recreate, dan akses file certificate.

## Information Groups
- Certificate List
- Student Association
- Certificate Type
- Generation Status
- Version
- Actions

## Key Navigation Paths
- Certificates -> Generate
- Certificates -> Recreate
- Certificates -> Download
- Certificates -> Delete

---

# 7.9 Ebooks

## Purpose
Mengelola resource ebook.

## Information Groups
- Ebook List
- Ebook Form
- Tier Assignment
- File Reference

## Key Navigation Paths
- Ebooks -> Create/Edit
- Ebooks -> Delete

---

# 7.10 Courses

## Purpose
Mengelola course independen.

## Information Groups
- Course List
- Course Form
- Access Tier
- Media Reference

## Key Navigation Paths
- Courses -> Create/Edit
- Courses -> Delete

---

# 7.11 Email Notifications

## Purpose
Mengelola template email otomatis dan test manual.

## Structure
Email Notifications memiliki 6 sub menu utama:
1. Module Completion
2. Assignments Review
3. Assignments Approved
4. Assignments Rejected
5. Certificate Created
6. Signup

## Main Information per Sub Menu
- Enable Notification
- Admin Recipients
- Admin Subject
- Admin Body
- User Subject
- User Body
- Available Merge Tags
- Save Changes
- Send Test

## Key Navigation Paths
- Email Notifications -> Sub Menu
- Sub Menu -> Save Changes
- Sub Menu -> Send Test

---

# 7.12 Email Logs

## Purpose
Menampilkan histori email otomatis dan test email.

## Information Groups
- Notification Type
- Recipient
- Subject
- Status
- Error
- Sent Time

## Key Navigation Paths
- Email Logs -> Filter / Search
- Email Logs -> Detail View if needed

---

## 8. Hierarchical Content Structure

## 8.1 Learning Hierarchy

Urutan struktur pembelajaran utama:

- Module
  - Lesson
    - Workbook
    - Video
    - Audio
    - Content
    - Assessment
      - Assessment Page
        - Question
          - Question Option

Ini adalah backbone utama student journey.

## 8.2 Resource Hierarchy

Resource tambahan berada di luar hierarchy utama:
- Ebooks
- Courses

Keduanya berdiri sendiri dan dibatasi oleh tier access.

---

## 9. Navigation Model

# 9.1 Student Navigation Model

### Primary Navigation
- Dashboard
- Modules
- Assignments
- Certificates
- Ebooks
- Courses
- Profile

### Secondary Navigation
- Module Detail
- Lesson Detail
- Assessment
- Result
- Assignment Detail / Feedback

### Navigation Behaviour
- fokus pada “continue learning”
- progress-driven
- content discovery friendly
- minim dead-end

---

# 9.2 Admin Navigation Model

### Primary Navigation
- Dashboard
- Students
- Access Tiers
- Modules
- Lessons
- Assessments
- Assignments
- Certificates
- Ebooks
- Courses
- Email Notifications
- Email Logs

### Secondary Navigation
- Create/Edit Form
- Detail View
- Builder View
- Review View
- Result View

### Navigation Behaviour
- task-driven
- section-based
- cepat dipindai
- mudah berpindah antar operasional area

---

## 10. Information Priority by Role

# 10.1 Student Priority Model

Urutan prioritas informasi:
1. Continue Learning
2. Current Progress
3. Next Required Action
4. Available Learning Content
5. Assignment Status
6. Certificate Status
7. Extra Resources
8. Profile

# 10.2 Admin Priority Model

Urutan prioritas informasi:
1. Pending Operational Tasks
2. Content Management
3. Student Monitoring
4. Assignment Review
5. Certificate Management
6. Notification Management
7. Logs and Reports

---

## 11. Content Visibility Rules in IA

Informasi yang ditampilkan kepada user harus mengikuti aturan berikut:

## 11.1 Student Side
Student hanya boleh melihat:
- konten yang sesuai tier
- lesson yang unlocked
- progress miliknya sendiri
- assignment miliknya sendiri
- certificate miliknya sendiri

## 11.2 Admin Side
Admin boleh melihat:
- seluruh data operasional
- seluruh student
- seluruh progress
- seluruh submissions
- seluruh certificate records
- seluruh email templates dan logs

---

## 12. Key User Pathways

## 12.1 Student Key Pathways
- Login -> Dashboard -> Continue Learning -> Lesson -> Assessment -> Next Lesson
- Login -> Dashboard -> Assignments -> Submit Assignment
- Login -> Dashboard -> Certificates -> Download Certificate
- Login -> Dashboard -> Ebooks / Courses

## 12.2 Admin Key Pathways
- Login -> Dashboard -> Modules / Lessons -> Content Update
- Login -> Dashboard -> Assessments -> Builder
- Login -> Dashboard -> Assignments -> Review -> Approve/Reject
- Login -> Dashboard -> Certificates -> Generate/Recreate
- Login -> Dashboard -> Email Notifications -> Edit Template / Send Test

---

## 13. IA Structure Summary

## Student Side
- learning-first
- content-driven
- progress-aware
- streaming-inspired
- reduced operational complexity

## Admin Side
- management-first
- action-driven
- data-visible
- structured and efficient

---

## 14. Design Notes

### Note 1
Student side tidak boleh terasa seperti portal sekolah atau LMS kampus. Information architecture harus mendukung discovery, continue learning, dan sense of premium content access.

### Note 2
Admin side boleh lebih padat informasi, tetapi tetap harus tersusun berdasarkan tugas utama, bukan berdasarkan struktur backend atau istilah teknis.

### Note 3
Assessment harus diperlakukan sebagai domain penuh sendiri dalam IA karena memiliki builder, player, result, progress, dan lifecycle yang kompleks.

### Note 4
Email Notifications harus diperlakukan sebagai modul operasional penting, bukan sekadar settings biasa, karena ada save template, trigger logic, merge tags, dan send test.

### Note 5
Courses dan Ebooks dipertahankan sebagai area independen karena secara requirement keduanya bukan bagian langsung dari struktur Module -> Lesson.

---

## 15. Assumptions

### Assumption 1
Semua user masuk melalui auth gateway yang sama, lalu diarahkan ke dashboard sesuai role.

### Assumption 2
Student side memerlukan navigasi sederhana dan tidak terlalu banyak level menu agar pengalaman tetap ringan.

### Assumption 3
Admin side akan menggunakan sidebar sebagai navigasi utama dan content area sebagai workspace.

### Assumption 4
Assessment result dilihat baik oleh student maupun admin, tetapi konteks dan tujuan tampilannya berbeda.

### Assumption 5
Email Notification memiliki halaman index dan halaman detail per notification type, meskipun implementasi final bisa berupa tab atau sub-route.

### Assumption 6
Student Dashboard adalah halaman paling penting dalam IA student side dan harus menjadi pusat orientasi utama.

### Assumption 7
Assignments dan Certificates adalah area terpisah dalam student side karena secara mental model keduanya adalah milestone/administrative learning artifacts, bukan content catalog.

### Assumption 8
Lesson Detail adalah pusat experience belajar, sehingga ia menjadi node terpenting dalam jalur Module -> Lesson -> Assessment.

---

## 16. Final IA Conclusion

Information Architecture YogaFX LMS dibangun di atas dua pengalaman utama:

### Student Side
- premium learning platform
- content-centric
- progress-guided
- streaming-inspired

### Admin Side
- structured operations console
- content and student management
- decision and review oriented

Dokumen ini menjadi dasar untuk:
- navigation design
- layout planning
- page grouping
- feature placement
- UI implementation consistency