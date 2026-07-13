# 02-user-flow.md
# User Flow Document
# YogaFX LMS

## 1. Purpose

Dokumen ini menjelaskan flow produk yang aktif saat ini di YogaFX LMS.

Flow yang belum tertulis di sini tidak boleh dianggap sebagai source of truth implementasi aktif.

---

## 2. Authentication Flow

### Scope
- login
- OTP verification
- logout
- forgot password
- reset password

### Main Flow
1. User membuka halaman login.
2. User mengisi email dan password.
3. Sistem memvalidasi kredensial dasar.
4. Jika akun valid dan role diizinkan, sistem membuat email OTP challenge.
5. Session login awal dibersihkan.
6. User diarahkan ke halaman verifikasi OTP email.
7. User memasukkan 6-digit OTP.
8. Jika OTP valid:
   - Admin diarahkan ke `admin.dashboard`
   - Student inactive diarahkan ke `student.inactive`
   - Student tanpa profile lengkap diarahkan ke `profile.edit`
   - Student aktif dengan profile lengkap diarahkan ke `student.dashboard`
9. Saat logout, sistem mengakhiri student session tracking bila relevan lalu kembali ke login.

### Important Notes
- public register route Laravel tidak aktif
- forgot password dan reset password tetap aktif

---

## 3. Public Lead, Checkout, dan Onboarding Flow

### 3.1 Lead Registration
1. Visitor membuka `/scoreboard` atau halaman tier publik seperti `/online`.
2. Visitor memilih tier dan mengisi data dasar.
3. Sistem membuat `pending_registration`.
4. Visitor diarahkan ke checkout.

### 3.2 Checkout
1. Visitor membuka signed checkout URL.
2. Sistem memuat ringkasan package, tier entitlement, amount, dan payment configuration.
3. Sistem menampilkan opsi:
   - `Pay in full`
   - `Installment` jika package eligible
4. Jika visitor memilih `Pay in full`, sistem memakai flow PayPal order/capture existing.
5. Jika visitor memilih `Installment`, sistem membuat invoice installment dan payment subscription lalu mengarahkan visitor ke approval PayPal Subscription.
6. Jika payment pertama sukses, visitor diarahkan ke payment success continuation.
7. Jika payment pending, cancel, atau gagal, visitor masuk ke status page checkout.

### 3.3 Enrollment
1. Setelah payment sukses, user diarahkan ke halaman enrollment.
2. User melengkapi profile enrollment.
3. Sistem menyimpan data ke user dan menandai onboarding siap ke tahap signup.

### 3.4 Signup Completion
1. User membuka halaman signup onboarding.
2. User membuat password.
3. Sistem menyelesaikan onboarding.
4. User diarahkan ke login.

---

## 4. Student Profile & Upgrade Flow

### 4.1 Profile Completion
1. Student login.
2. Jika profile belum lengkap, student dipaksa membuka profile edit.
3. Student menyimpan semua field wajib.
4. Setelah sukses, student dapat mengakses Home.

### 4.2 Student Upgrade
1. Student membuka profile.
2. Sistem menampilkan upgrade options berdasarkan `level` tier yang lebih tinggi.
3. Student memilih salah satu upgrade.
4. Sistem membuka halaman checkout upgrade.
5. Student menyelesaikan payment.
6. Setelah payment sukses, student diarahkan ke halaman success lalu kembali ke dashboard.

### 4.3 Mobile Upgrade Handoff
1. Mobile app memuat student identity / profile payload.
2. Backend mobile mengirim `upgrade_options` dinamis berdasarkan tier aktif student.
3. Setiap option membawa `upgrade_url` web yang siap dibuka mobile app.
4. Mobile app membuka web upgrade flow tanpa hardcode tier id.

---

## 5. Student Home Flow

### Main Flow
1. Student membuka `Home`.
2. Sistem memuat:
   - continue learning
   - progress summary
   - next step guidance
   - available modules
   - assignment milestone
   - certificate milestone
   - ebook resource summary
   - access time summary
3. Student menggunakan CTA utama untuk melanjutkan lesson terakhir atau memulai lesson pertama.

### Desktop First-Open Welcome
1. Student membuka `Home` dari desktop untuk pertama kali.
2. Sistem menampilkan welcome popup multi-slide.
3. Salah satu slide menampilkan QR image dari `Link Control`.
4. Student dapat next, skip, atau close popup lalu masuk ke Home normal.

### Mobile App Download CTA
1. Student membuka `Home` dari mobile.
2. Student scroll ke bagian bawah halaman.
3. Jika `Link Control` sudah berisi store links, sistem menampilkan tombol Google Play dan App Store.
4. Student menekan salah satu tombol lalu diarahkan ke store link terkait.

### Public App Download Page
1. Visitor atau student scan QR dari YogaFX.
2. QR selalu mengarah ke satu public page download app.
3. Halaman menampilkan tombol App Store dan Google Play berdasarkan link terbaru dari `Link Control`.
4. Jika salah satu link kosong, tombol store tersebut tampil disabled.

### Important Notes
- Home adalah dashboard student aktif saat ini
- Home sengaja fokus pada guidance, bukan tabel atau statistik admin-like

---

## 6. Student Module dan Lesson Flow

### 6.1 Modules
1. Student membuka modules index.
2. Sistem memfilter module berdasarkan tier student.
3. Module yang belum terbuka tetap dapat terlihat, tetapi dapat menunjukkan status lock jika prerequisite belum terpenuhi.
4. Student membuka module.
5. Sistem menampilkan daftar lesson, assignment, dan resource pendukung yang relevan.

### 6.2 Lesson Detail
1. Student membuka lesson.
2. Sistem memastikan:
   - tier lesson cocok
   - tier module cocok
   - lesson sudah unlocked dalam urutan belajar
3. Halaman lesson menampilkan:
   - thumbnail
   - Bunny Stream video state
   - audio
   - workbook
   - text content
   - assessment info jika ada dan aktif
4. Student menonton lesson video.
5. Frontend mengirim update watch progress.
6. Sistem hanya menaikkan progress, tidak menurunkan progress yang sudah lebih tinggi.
7. Lesson video dianggap complete saat watch progress mencapai minimal 95% dan assessment aktif yang relevan juga selesai bila diperlukan.

### 6.3 Sequential Unlock Rule
Lesson berikutnya dapat terkunci sampai lesson sebelumnya memenuhi rule aktif:
- workbook sudah dipicu/download bila lesson punya workbook
- watch progress video minimal 95% bila lesson punya video
- assessment aktif sudah completed bila lesson punya assessment live

### 6.4 Workbook Trigger
1. Student membuka lesson dengan workbook.
2. Sistem dapat memicu workbook auto-delivery satu kali per student.
3. Sistem menandai workbook downloaded state.
4. Sistem mengirim email `workbook_sent`.
5. Student tetap punya manual download path.

---

## 7. Student Assessment Flow

### Main Flow
1. Student membuka assessment intro dari lesson.
2. Sistem memeriksa assessment aktif, tier access, dan unlock state.
3. Student memulai assessment.
4. Sistem membuat atau melanjutkan `assessment_attempt`.
5. Student mengerjakan pertanyaan satu per satu.
6. Sistem menyimpan jawaban per question.
7. Jika timer habis, attempt dapat di-expire dan diselesaikan otomatis.
8. Saat pertanyaan terakhir selesai, sistem menghitung score dan result range.
9. Student diarahkan ke halaman result.

### Important Notes
- back navigation tergantung pengaturan assessment
- result dapat menampilkan next lesson jika ada

---

## 8. Student Assignment Flow

### Main Flow
1. Student membuka assignment yang relevan dari module.
2. Sistem memeriksa:
   - student punya tier yang mengizinkan flow assignment
   - assignment berstatus `live`
   - module assignment termasuk dalam tier student
3. Student upload video assignment.
4. Sistem menyimpan atau mengganti submission terakhir student.
5. Sistem menandai status submission sebagai `submitted`.
6. Sistem memicu email `assignment_review`.

### Important Notes
- flow assignment student saat ini dibatasi untuk path tier `online`

---

## 9. Student Ebook dan Course Flow

### 9.1 Ebook
1. Student membuka daftar ebooks.
2. Sistem memfilter ebook berdasarkan tier.
3. Student membuka halaman preview ebook.
4. Jika format mendukung, file dipreview inline.
5. Download tetap menjadi aksi eksplisit terpisah.

### 9.2 Course / Video Lecture
1. Student membuka daftar courses.
2. Sistem memfilter course berdasarkan tier.
3. Student membuka detail course.
4. Sistem menampilkan data video lecture yang relevan.

---

## 10. Student Certificate Flow

### Main Flow
1. Student melihat certificate milestone dari Home.
2. Jika certificate sudah digenerate admin, Home menampilkan download CTA.
3. Student menekan download.
4. Sistem memeriksa ownership certificate.
5. Sistem mencatat certificate download event.
6. File certificate dikirim ke student.

---

## 11. Admin Dashboard Flow

### Main Flow
1. Admin login dan menyelesaikan OTP verification.
2. Sistem mengarahkan admin ke dashboard.
3. Admin melihat halaman sambutan sederhana.
4. Admin menggunakan left sidebar untuk berpindah area kerja.

### Sidebar Active
- Dashboard
- Modules
- Lessons
- Assessment
- Student Progress
- Students
- Dialog
- Video Lecture
- E-Book
- Email
- Supporting Pages -> Packages
- Supporting Pages -> Access Tiers

<<<<<<< Updated upstream
### Topbar Structure
- page title
- sidebar toggle
- user menu
- logout

### User Menu Extension
- di student desktop, user menu dapat menampilkan:
  - Profile
  - Download Application
  - Logout
- `Download Application` membuka popup QR berbasis `Link Control`

### 8.1 Admin Account Profile
1. Admin menekan user menu di kanan atas.
2. Admin memilih `Profile`.
3. Sistem membuka halaman profile admin.
4. Admin dapat memperbarui first name, last name, email, dan password.
5. Setelah save berhasil, sistem tetap berada di halaman profile admin dengan flash success message.

### 8.2 Admin User Management
#### Students
1. Admin membuka menu `Students`.
2. Sistem menampilkan directory student dengan search, filter, dan pagination.
3. Admin dapat menekan `Add Student`.
4. Admin mengisi email, password, dan access tier.
5. Setelah save berhasil, sistem kembali ke list student.
6. Student yang dibuat dapat login dan akan tetap diarahkan ke profile edit jika profile belum lengkap.

#### Admin
1. Admin membuka menu `Admin`.
2. Sistem menampilkan list admin dengan search, filter, dan pagination.
3. Jika user adalah super admin, sistem menampilkan aksi `Add Admin`, `Edit`, dan `Delete` sesuai aturan.
4. Jika super admin menambah admin, user mengisi name, email, dan password.
5. Setelah save berhasil, sistem kembali ke list admin.
6. Admin biasa hanya melihat directory admin tanpa aksi admin management.
7. Super admin tidak dapat menghapus dirinya sendiri.
8. Super admin tidak dapat menurunkan role dirinya sendiri.

=======
>>>>>>> Stashed changes
---

## 12. Admin Content Management Flow

### 12.1 Core CRUD
1. Admin membuka salah satu area:
   - Packages
   - Access Tiers
   - Modules
   - Lessons
   - Assignments
   - Assessment
   - E-Book
   - Video Lecture
   - Dialog
2. Admin membuka list atau edit page.
3. Saat create/edit, sistem memvalidasi form.
4. Saat delete, sistem meminta konfirmasi bila aksi destruktif tersedia.

### 12.2 Assessment Builder
1. Admin membuat assessment meta terlebih dahulu.
2. Admin masuk ke builder.
3. Admin mengatur:
   - questions
   - options
   - jumps
   - design
   - result ranges
4. Admin dapat preview assessment dari sisi admin.

### 12.3 Link Control
1. Admin membuka `Supporting Pages -> Link Control`.
2. Admin mengisi link Google Play / App Store.
3. Saat save, sistem mengenerate ulang QR image otomatis untuk public download app page.
4. Setelah save, student home memakai data terbaru tersebut.

---

## 13. Admin Student Management Flow

### Main Flow
1. Admin membuka menu `Students`.
2. Sistem menampilkan daftar semua student.
3. Admin membuka `Student Detail`.
4. Admin dapat:
   - edit profile
   - ubah access tier
   - ubah status active/inactive
   - reset progress
   - delete student

---

## 14. Student Progress Admin Flow

### 14.1 Directory
1. Admin membuka `Student Progress`.
2. Sistem menampilkan 3 section tabel berdasarkan tier.
3. Tiap row menampilkan progress dan assignment status.
4. Admin memakai action menu untuk membuka:
   - Completed Lesson
   - Assignment
   - Certificate

### 14.2 Completed Lesson
1. Admin membuka detail completed lessons per student.
2. Sistem menampilkan lesson yang complete.
3. Admin dapat reset satu lesson progress.

### 14.3 Assignment Review
1. Admin membuka detail assignment per student.
2. Sistem menampilkan status, feedback, dan video.
3. Admin dapat update status, save feedback, send email, atau delete video.

### 14.4 Certificate
1. Admin membuka detail certificate per student.
2. Sistem menghitung eligibility row sesuai tier student.
3. Admin dapat generate, recreate, download, send graduation email, atau delete certificate.

---

## 15. Email Notification Flow

### Main Flow
1. Admin membuka parent menu `Email`.
2. Admin memilih salah satu notification type.
3. Sistem memuat template detail.
4. Admin dapat enable/disable, edit content, upload media, save, dan send test.
5. Saat event bisnis terjadi, sistem merender template dan mencatat email log.

### Notification Types Active
- module_completion
- assignment_review
- assignment_approved
- assignment_rejected
- certificate_created
- signup
- reset_password
- assessment_complete
- course_complete
- reminder
- workbook_sent
- installment_payment_success
- installment_payment_failed
- installment_overdue_inactive
- installment_payment_completed

---

## 16. Flows That Are Not Active Yet

Flow berikut belum menjadi alur produk aktif final:
- public free signup tanpa checkout
- installment untuk upgrade
- subscription renewal / expiry management di luar initial installment package
- analytics dashboard kaya data
- web student certificate center terpisah dari Home/download flow
