# PRODUCT REQUIREMENTS DOCUMENT
## YogaFX Online Learning Management System

**Status:** Updated to reflect current implementation in repository (branch `frontend-kitabisa-2`), 2026-07-28.
**Sumber:** dokumen ini adalah hasil pembaruan dari PRD awal (versi cetak), diverifikasi langsung terhadap migration, model, seeder, config, dan route aktual — bukan hanya dokumentasi internal (beberapa file di `docs/` seperti `00-current-project-status.md` dan `01-prd.md` saat ini memiliki konflik merge yang belum diselesaikan sehingga tidak dijadikan satu-satunya acuan).

Penyusun (dokumen asli):
Moses Simangunsong, Wesly Ambarita, Tasya Marbun, Maharani Sitorus, Rahel Hasibuan

---

## 1. OVERVIEW

YogaFX Premium Online Lecture adalah platform Learning Management System (LMS) berbasis web yang dirancang khusus untuk mendukung program pelatihan yoga bersertifikasi. Sejak dokumen PRD awal, sistem telah berkembang dari sekadar "distribusi konten + sertifikasi" menjadi platform end-to-end yang juga menangani **akuisisi lead publik, checkout pembayaran (penuh maupun cicilan via PayPal), dan onboarding peserta baru** sebelum peserta masuk ke pengalaman belajar.

Platform ini kini mencakup empat permukaan utama:
1. **Web Publik** — landing/lead registration dan checkout, tanpa login.
2. **Web Admin** — pengelolaan konten, komersial (package/tier), siswa, asesmen, sertifikat, dan notifikasi.
3. **Web Student** — akses pembelajaran, asesmen, tugas, dan sertifikasi.
4. **API Mobile** (`/mobile/v1/*`) — merepresentasikan ulang sebagian besar fitur sisi Student untuk aplikasi mobile pendamping.

Stack teknis: Laravel + Inertia.js + React (Vite, Tailwind CSS, Radix UI/shadcn), PostgreSQL, queue berbasis database, Bunny Stream/Storage untuk media, PayPal untuk seluruh pemrosesan pembayaran, DomPDF untuk sertifikat & invoice.

---

## 2. TUJUAN PRODUK

- Menyediakan sistem LMS yang terstruktur untuk distribusi konten pembelajaran yoga secara digital.
- Mengonversi calon peserta (lead) publik menjadi peserta berbayar melalui alur landing → checkout → onboarding, tanpa self-signup bebas.
- Mengelola akses konten berdasarkan tier keanggotaan (**Starter Kit**, **Online**, **Master Class**) melalui relasi many-to-many terhadap konten.
- Mendukung skema pembayaran **bayar penuh**, **cicilan (installment)**, **gratis (free)**, dan **donasi (donation)** pada tingkat Package.
- Mengotomatiskan notifikasi email (Student dan Admin) pada setiap milestone pembelajaran maupun siklus penagihan cicilan.
- Memfasilitasi proses sertifikasi yoga mulai dari assignment hingga penerbitan sertifikat (dua jenis: **Bikram Yoga Certificate** dan **Yoga Alliance Certificate**).
- Memberikan kemudahan admin dalam memantau progres, data seluruh siswa, dan status penagihan.
- Menyediakan backend API bagi aplikasi mobile pendamping dengan fitur setara sisi Student pada web.

---

## 3. RUANG LINGKUP

### 3.1 Modul Publik (Baru — belum ada di PRD awal)
- Lead Registration / Scoreboard
- Landing per tier (`/starter-kit`, `/online`, `/masterclass`) dan per package (`/p/{package_slug}`)
- Checkout: bayar penuh (PayPal Checkout Order) dan cicilan (PayPal Subscription)
- Onboarding: enrollment lalu signup

### 3.2 Modul Admin
- Manajemen Access Tier & Package (komersial)
- Manajemen Modul
- Manajemen Lesson
- Manajemen Assignment
- Manajemen QSM (Assessment) — builder pertanyaan, opsi, jump logic, result range
- Manajemen Dialog Content
- Student Management (akun, aktif/nonaktif, reset progress, hapus akun)
- Student Progress & Monitoring (terpisah dari Student Management)
- Admin Management (khusus Super Admin)
- E-Book Management
- Course / Video Lecture Management
- Email Notification Configuration
- Link Control (Google Play / App Store / QR code)
- Invoice & Payment Monitoring

### 3.3 Modul Pengguna (Student)
- Dashboard & Profil Pengguna (`Home` — content-first, bukan berbasis tabel)
- Akses Modul dan Lesson (dengan sequential locking)
- Assessment & Quiz
- Assignment (Graduation Video) — saat ini hanya aktif untuk tier `online`
- Sertifikasi
- E-Book Access
- Course / Video Lecture Access
- Upgrade Tier (checkout upgrade ke tier lebih tinggi)

### 3.4 Modul Mobile API (Baru — belum ada di PRD awal)
- Autentikasi (login, OTP, forgot/reset password)
- Dashboard/home, modules, lessons, ebooks, courses
- Assessment (intro, start, answer, result)
- Assignment (show, submit)
- Certificate (index, show, download)
- Profile (show, update, change password)

---

## 4. SISTEM TIER AKSES

Sama seperti dokumen awal, platform membagi pengguna ke dalam tiga tingkatan akses (tier), kini dengan `level` numerik eksplisit untuk hierarki upgrade.

| Tier | Level | Akses Modul | Final Assignment | Sertifikat |
|---|---|---|---|---|
| Starter Kit | 1 | Sebagian Modul | Tidak | Tidak |
| Online | 2 | Semua Modul | Ya | Ya (Bikram + Yoga Alliance) |
| Master Class | 3 | Semua Modul | Tidak | Ya (Bikram + Yoga Alliance) |

Catatan pembaruan terhadap dokumen awal:
- Tier Starter Kit hanya berhak atas sertifikat **Bikram Yoga Certificate**; Online dan Master Class berhak atas **kedua** jenis sertifikat.
- Setiap tier memiliki `level` (1/2/3) yang menjadi dasar validasi **upgrade tier** oleh Student — upgrade hanya diperbolehkan ke tier dengan level lebih tinggi dari tier saat ini.
- Akses konten (Module, Lesson, Ebook, Course) ditentukan melalui relasi **many-to-many** ke Access Tier, bukan pemetaan satu-ke-satu — satu konten bisa terbuka untuk lebih dari satu tier sekaligus.
- Tier dijual melalui entitas komersial terpisah bernama **Package** (lihat Bagian 6.7), yang menyimpan harga, mata uang, dan konfigurasi cicilan; satu Package terhubung ke maksimal satu Access Tier aktif.

---

## 5. PROFIL PENGGUNA (STUDENT PROFILE)

Struktur field profil pada dokumen awal masih berlaku penuh pada implementasi saat ini (terverifikasi pada migration `users` table).

### 5.1 Informasi Pribadi

| Field | Tipe | Keterangan |
|---|---|---|
| First Name | Text | Wajib diisi |
| Last Name | Text | Wajib diisi |
| Email | Email | Wajib diisi, unique secara global lintas role |
| WhatsApp | Phone Number | Wajib diisi |
| Preferred Certificate Picture | Image Upload | Foto untuk sertifikat |
| Instagram | Text | Opsional |
| Country | Dropdown | Wajib diisi |
| Birth Date | Date Picker | Wajib diisi |
| Gender | Radio Button | Wajib diisi |

### 5.2 Pengalaman Yoga

| Field | Tipe | Keterangan |
|---|---|---|
| Practicing Yoga For | Text/Number Input | Wajib diisi |
| Yoga Sequence Experience | Text/Dropdown | Wajib diisi |
| Hours Per Week Practicing Yoga | Text Input | Wajib diisi (kini disimpan sebagai string, bukan angka murni, untuk mengakomodasi format bebas seperti "3-4 jam") |
| Current Fitness Level | Dropdown/Scale | Wajib diisi |
| Flexibility Rating | Dropdown/Scale | Wajib diisi |
| Motivation Becoming Yoga Teacher | Textarea | Wajib diisi |
| Why Chose YogaFX | Textarea | Wajib diisi |
| How Did You Find Us | Textarea/Dropdown | Wajib diisi |

Catatan pembaruan: profil Student kini wajib dilengkapi (profile completion gate) sebelum Student dapat mengakses `Home`/konten belajar — bukan sekadar form terpisah yang bisa dilewati.

---

## 6. FITUR ADMIN DASHBOARD

### 6.1 Access Tier & Package Management *(baru dibanding PRD awal)*
Sebelum mengelola konten, Admin terlebih dahulu mengelola dua entitas komersial:
- **Access Tier**: nama, level hierarki, status aktif (`is_active`). Tidak dapat dihapus bila masih dipakai relasi aktif (Student/konten).
- **Package**: title, slug, description, gambar, harga, `currency_code`, `payment_type` (`paid` / `free` / `donation`), konfigurasi cicilan (`installment_enabled`, metode kalkulasi tanggal/jumlah cicilan, hari billing tanggal 1 atau 15), serta cache product/plan PayPal. Package hanya tersedia untuk checkout publik apabila terhubung ke satu Access Tier aktif; konfigurasi cicilan hanya boleh aktif bila Package sudah ditautkan ke Access Tier.

### 6.2 Modul Management
Tidak berubah signifikan dari dokumen awal:

#### 6.2.1 Daftar Modul
- Menampilkan tabel semua modul yang sudah ditambahkan.
- Kolom: UUID, Title, URL Slug, Thumbnail, Access Tier (kini bisa lebih dari satu tier per modul), Aksi (Edit/Hapus).

#### 6.2.2 Tambah Modul Baru
- Form pengisian: Title, Thumbnail (upload, maks 10 MB), Access Tier (multi-select, bukan dropdown tunggal).
- Atribut otomatis: UUID, URL Slug, `sort_order`.
- Flag tambahan pada modul: `certificate_enabled`, `ebook_enabled`, `video_lecturer_enabled` — menentukan apakah suatu modul menjadi jalur menuju sertifikat/ebook/video lecture.
- Setelah disimpan, sistem kembali ke halaman daftar modul.

#### 6.2.3 Edit Modul
- Admin dapat mengedit: Title, Thumbnail, Access Tier, flag pendukung.
- Klik tombol Update untuk menyimpan perubahan.

#### 6.2.4 Hapus Modul
- Klik tombol Hapus → dialog konfirmasi ketik kalimat khusus → klik Hapus.

### 6.3 Lesson Management

#### 6.3.1 Tambah Lesson
- Form pengisian: Judul, Thumbnail, Workbook (upload, maks 100 MB), Video (upload ke Bunny Stream via `lesson_video_id`, bukan URL bebas), Audio (`audio_url`, maks 50 MB), Konten (CKEditor).
- Assignment: Pilih modul dan assessment yang terhubung.

#### 6.3.2 Edit Lesson
- Semua field pada form tambah lesson dapat diedit.

#### 6.3.3 Hapus Lesson
- Mekanisme konfirmasi kalimat sebelum hapus (sama dengan modul).

#### 6.3.4 Aturan Akses Lesson (Student Side) — diverifikasi pada `LessonCatalogController::lessonAdvanceGate`

| Kondisi | Aturan |
|---|---|
| Lesson memiliki workbook | Siswa wajib download workbook sebelum akses video, audio, dan konten dianggap selesai |
| Lesson memiliki video | Siswa dapat mengerjakan assessment/lanjut setelah `watch_progress` mencapai minimal 95% |
| Lesson memiliki assessment aktif | Lesson dianggap selesai hanya setelah assessment tersebut diselesaikan |
| Lesson tidak ada assessment | Lesson dianggap selesai setelah workbook (bila ada) + video ≥95% terpenuhi |
| Lesson sebelumnya belum memenuhi gate di atas | Lesson berikutnya TIDAK dapat diakses (sequential locking) |

### 6.4 QSM (Assessment) Management
Tidak berubah secara struktural dari dokumen awal, dengan penambahan jump logic dan result range:
- **Tambah Assessment**: judul, soal-soal, opsi jawaban.
- **Builder lanjutan**: jump logic (percabangan alur pertanyaan) dan result range (rentang hasil berbasis skor).
- **Edit / Hapus Assessment**: konfirmasi kalimat sebelum dihapus.
- **Preview Assessment**: simulasi tampilan pengerjaan dari sisi siswa.
- **View Result**: lihat skor, jawaban, dan hasil pengerjaan assessment oleh siswa (index + detail).

### 6.5 Student Management *(dipisah dari Student Progress — perubahan struktural dibanding PRD awal)*

Pada implementasi saat ini, menu **Students** (manajemen akun) terpisah dari menu **Student Progress** (monitoring pembelajaran). PRD awal menggabungkan keduanya dalam satu bagian "Student Progress".

#### 6.5.1 Daftar Siswa (menu Students)

| Kolom | Keterangan |
|---|---|
| No | Nomor urut |
| Foto | Foto profil siswa |
| Name | Nama lengkap siswa |
| Access Tier | Tier keanggotaan aktif |
| Status | Aktif / Nonaktif (`is_active`) |
| Registration Date | Tanggal pendaftaran |

Aksi yang tersedia: edit profil student, aktifkan/nonaktifkan akun, **reset progress** (penuh atau per-scope: video, assessment, lesson, module), dan **hapus akun** beserta seluruh data belajar terkait secara permanen. Daftar mendukung search, filter, dan pagination.

#### 6.5.2 Student Progress (menu terpisah — monitoring)
Directory progres belajar per tier, dengan detail per siswa:

1. **Complete Lesson** — daftar lesson yang sudah diselesaikan, dengan fitur Reset Progress.
2. **Assignment (Graduation Video)** — tabel Title, Video, Status (`submitted`, `pending_review`, `under_review`, `approved`, `rejected`), Feedback (khusus rejected). Aksi: Save, Send Email, Delete Video.
3. **Certificate** — Generate Certificate dan Send Graduation Email. Dua jenis sertifikat: **Bikram Yoga Certificate** dan **Yoga Alliance Certificate**. Setiap sertifikat memiliki 3 opsi: Recreate Certificate, Download (.jpg/PDF via DomPDF), Hapus Certificate.

### 6.6 Admin Management *(baru — khusus Super Admin)*
- Super Admin dapat membuat, mengedit, dan menghapus akun Admin biasa.
- Admin biasa dapat mengelola profil akun sendiri (nama, email, password) tetapi tidak dapat membuat/mengedit/menghapus akun Admin lain.
- Super Admin tidak dapat menghapus atau menurunkan role Super Admin lain maupun dirinya sendiri.
- Email user harus unik secara global lintas seluruh role.

### 6.7 E-Book Management
Tidak berubah dari dokumen awal, dengan batas ukuran file yang diperbarui:
- Daftar E-Book: Checkbox (individual & select all), Title, Date.
- Tambah/Edit E-Book: Title dan upload file (**maks 500 MB**, naik dari asumsi umum 10 MB pada dokumen awal).
- Hapus E-Book: konfirmasi kalimat validasi sebelum dihapus final.
- Sisi Student: ebook dibuka di halaman preview terlebih dahulu sebelum diunduh sebagai aksi terpisah.

### 6.8 Course / Video Lecture Management
Struktur sama seperti dokumen awal (Daftar, Tambah, Edit, Hapus dengan pola UUID/Slug/Thumbnail/Access Tier), namun akses tier kini many-to-many (bukan dropdown tunggal), dan Course kini setara dengan Module/Lesson/Ebook dalam hal kontrol akses berbasis tier.

### 6.9 Link Control *(baru — belum ada di PRD awal)*
- Admin mengelola satu set tautan global: Google Play link dan App Store link.
- QR code digenerate otomatis mengarah ke satu halaman publik download app.
- Ditampilkan pada welcome popup Student desktop dan bagian bawah halaman Home Student mobile.

### 6.10 Invoice & Payment Monitoring *(baru — belum ada di PRD awal)*
- Admin dapat melihat daftar invoice dan payment activity hasil checkout (bayar penuh maupun cicilan).
- Invoice dapat diunduh sebagai PDF (DomPDF).
- Setiap event webhook PayPal Subscription tercatat pada `payment_subscription_events` untuk audit.

---

## 7. EMAIL NOTIFICATION

Sistem notifikasi email terdiri dari 2 metode operasional yang sama seperti dokumen awal (Automated Notification dan Send Test manual), namun jumlah tipe notifikasi bertambah signifikan — dari 7 tipe pada dokumen awal menjadi **18 tipe** pada `EmailNotificationTypeRegistry`.

| Metode | Keterangan |
|---|---|
| Automated Notification | Email terkirim otomatis oleh sistem saat trigger terpenuhi, dikirimkan kepada Student dan/atau Admin, diproses melalui queue background. |
| Send Test (Manual) | Tersedia di setiap konfigurasi notifikasi. Admin mengisi field Send To untuk menguji fungsionalitas SMTP. |

### 7.1 Tipe Notifikasi Aktif (18 tipe, sudah diverifikasi di kode)

| # | Tipe | Keterangan Tambahan |
|---|---|---|
| 1 | `signup` | Sama seperti PRD awal (Sign Up Notification) |
| 2 | `reset_password` | Sama seperti PRD awal |
| 3 | `module_completion` | Sama seperti PRD awal |
| 4 | `assessment_complete` | Sama seperti PRD awal |
| 5 | `course_complete` | Sama seperti PRD awal |
| 6 | `assignment_review` | Sama seperti PRD awal |
| 7 | `assignment_approved` | Sama seperti PRD awal |
| 8 | `assignment_rejected` | Sama seperti PRD awal |
| 9 | `certificate_created` | Sama seperti PRD awal |
| 10 | `reminder` | **Baru** — pengingat inaktivitas Student, dipicu job terjadwal tiap 10 menit |
| 11 | `workbook_sent` | **Baru** — dikirim saat workbook lesson pertama kali dipicu/diunduh |
| 12 | `payment_success` | **Baru** — konfirmasi pembayaran checkout berhasil |
| 13 | `enrollment_success` | **Baru** — konfirmasi enrollment pasca pembayaran |
| 14 | `installment_payment_success` | **Baru** — bagian siklus cicilan |
| 15 | `installment_payment_failed` | **Baru** — bagian siklus cicilan |
| 16 | `installment_overdue_inactive` | **Baru** — akun dinonaktifkan otomatis akibat tunggakan |
| 17 | `installment_payment_completed` | **Baru** — seluruh siklus cicilan lunas |
| 18 | `irregular_activity_suspended` | **Baru** — notifikasi saat aktivitas mencurigakan pada pemutaran lesson terdeteksi |

Struktur konfigurasi tiap tipe (Enable checkbox, Admin Recipients, Subject Template + Body CKEditor terpisah Admin/User, Dynamic Parameters, tombol Save Changes & Send Test) tetap konsisten dengan pola pada dokumen awal (lihat contoh Sign Up Notification, Module Completion, dsb. di dokumen awal — parameter dinamis tersebut masih berlaku).

### 7.2 Catatan Tambahan
- Certificate Created tetap wajib melampirkan file PDF sertifikat secara otomatis menggunakan DomPDF (tidak berubah dari dokumen awal).
- Assignment Rejected tetap wajib menyertakan parameter `{feedback}`.

---

## 8. TEKNOLOGI VIDEO

Tidak berubah dari dokumen awal — seluruhnya terverifikasi masih berlaku pada implementasi saat ini:

| Komponen | Teknologi | Fungsi |
|---|---|---|
| Video Player | VideoJS (`video.js ^8.23.7`) | Pemutaran video di halaman lesson |
| Video Hosting | BunnyStream | Streaming video ke pengguna |
| Video Storage | Bunny Storage | Penyimpanan file video |

- Siswa dapat menyelesaikan lesson setelah menonton minimal 95% durasi video (dikombinasikan dengan syarat workbook dan assessment bila ada).
- Sistem mencatat progres menonton (`watch_progress`) per lesson per siswa.
- **Baru**: sistem juga mencatat aktivitas tidak wajar (`irregular activity`) selama pemutaran lesson untuk mendukung deteksi kecurangan/anomali, memicu notifikasi `irregular_activity_suspended`.

---

## 9. KEBUTUHAN NON-FUNGSIONAL

| Kategori | Kebutuhan |
|---|---|
| Keamanan | Konfirmasi penghapusan dengan validasi ketik teks khusus (tidak berubah). Login web kini **wajib dua langkah**: password + OTP email (baru dibanding PRD awal yang tidak menyebutkan OTP). |
| Performa Video | Streaming menggunakan arsitektur CDN BunnyStream untuk latensi rendah (tidak berubah). |
| Reliabilitas Email & PDF | Pengiriman email berjalan di background process (Queue berbasis database). Generate PDF DomPDF ditargetkan di bawah 3 detik (tidak berubah). |
| Integrasi | Terintegrasi penuh dengan BunnyStream, Bunny Storage, SMTP Server/Gateway Email, dan **PayPal (Checkout Orders API + Subscriptions API + Webhook)** — integrasi pembayaran baru dibanding PRD awal yang tidak menyebutkan payment gateway sama sekali. |
| Responsivitas | Antarmuka responsif dan dapat diakses di perangkat desktop dan mobile (tidak berubah), ditambah **API Mobile khusus** untuk aplikasi mobile pendamping native. |
| Keandalan Transaksi *(baru)* | Aktivasi akun Student tidak boleh terjadi tanpa validasi webhook resmi PayPal (bukan hanya respons sisi klien). Penonaktifan otomatis akun akibat tunggakan cicilan (H+3) wajib disertai email notifikasi. |
| Batas Unggah File *(baru — detail per jenis)* | File umum/thumbnail: 10 MB. Ebook: 500 MB. Lesson workbook: 100 MB. Lesson audio: 50 MB. Video tugas (assignment): 100 MB. Validasi dilakukan di frontend dan backend; file yang melebihi batas tidak boleh disimpan. |
| Job Terjadwal *(baru)* | `email-notifications:send-reminders` berjalan tiap 10 menit; `installments:sync-overdue-status` berjalan tiap jam, tanpa tumpang tindih eksekusi. |

---

## 10. GLOSARIUM

| Istilah | Definisi |
|---|---|
| LMS | Learning Management System - sistem manajemen pembelajaran digital. |
| Tier | Tingkatan akses pengguna: Starter Kit, Online, Master Class — kini memiliki `level` numerik untuk hierarki upgrade. |
| QSM | Quiz/Assessment Module - sistem penilaian dalam platform. |
| CKEditor | Editor konten berbasis WYSIWYG yang digunakan untuk mengisi konten lesson dan body email. |
| Slug | Bagian URL yang bersifat human-readable, contoh: `/module/yoga-dasar`. |
| BunnyStream | Platform streaming video CDN pihak ketiga. |
| Assignment | Tugas akhir berupa video untuk proses kelulusan; saat ini hanya aktif untuk tier `online`. |
| DomPDF | Library PHP untuk render HTML/CSS menjadi file PDF (dipakai untuk sertifikat dan invoice). |
| Queue | Antrian proses background berbasis database untuk eksekusi tugas berat (terutama pengiriman email) agar tidak memblokir halaman. |
| **Package** *(baru)* | Entitas komersial yang menyimpan harga, mata uang, dan konfigurasi checkout (penuh/cicilan/gratis/donasi); terhubung ke maksimal satu Access Tier aktif. |
| **Installment** *(baru)* | Skema pembayaran cicilan berbasis PayPal Subscription; hanya berlaku untuk pembayaran awal Package, belum mendukung cicilan untuk upgrade tier. |
| **Pending Registration** *(baru)* | Catatan lead publik yang mengisi form registrasi/checkout sebelum resmi menjadi akun Student melalui onboarding. |
| **Onboarding (Enrollment & Signup)** *(baru)* | Dua langkah pasca-pembayaran yang harus diselesaikan pengunjung sebelum akun Student aktif dibuat. |
| **Sequential Locking** *(baru)* | Aturan pengait lesson berikutnya terhadap penyelesaian lesson sebelumnya (workbook, watch progress ≥95%, assessment). |
| **Signed URL** *(baru)* | Tautan bertanda tangan Laravel untuk mengamankan akses checkout, onboarding, dan media terproteksi (video, ebook, sertifikat) agar tidak dapat dimanipulasi. |
| **OTP (One-Time Password)** *(baru)* | Kode sekali pakai yang dikirim ke email sebagai lapisan verifikasi kedua saat login, di web maupun mobile. |

---

## LAMPIRAN: RINGKASAN PERUBAHAN UTAMA DIBANDING PRD AWAL

1. **Akuisisi publik & pembayaran** — seluruh alur lead registration, checkout (PayPal, penuh maupun cicilan), dan onboarding tidak ada di PRD awal; kini menjadi pintu masuk utama menjadi Student (tidak ada self-signup bebas).
2. **OTP login** — login web/mobile kini wajib verifikasi OTP email, bukan hanya password.
3. **Peran Super Admin vs Admin** — PRD awal hanya mengenal satu peran "Admin"; kini ada pemisahan wewenang Super Admin (kelola akun Admin) vs Admin biasa.
4. **Student Management terpisah dari Student Progress** — dua menu berbeda: satu untuk administrasi akun, satu untuk monitoring pembelajaran.
5. **Access Tier & konten kini many-to-many**, bukan pemetaan tunggal seperti tersirat di PRD awal.
6. **Package sebagai lapisan komersial baru**, terpisah dari Access Tier sebagai lapisan entitlement.
7. **Installment/cicilan** — fitur penagihan berkelanjutan (PayPal Subscription, overdue detection H+3, auto-deactivate/reactivate akun) sepenuhnya baru.
8. **Jumlah tipe notifikasi email** naik dari 7 menjadi 18 tipe, termasuk siklus cicilan dan deteksi aktivitas tidak wajar.
9. **Link Control** (Google Play/App Store/QR code) dan **API Mobile** (`/mobile/v1/*`) sepenuhnya baru, mendukung aplikasi mobile pendamping.
10. **Batas ukuran unggah file** kini berbeda per jenis berkas, bukan seragam.
11. **Sequential lesson locking dan deteksi aktivitas tidak wajar** saat pemutaran video adalah kapabilitas baru yang tidak ada di PRD awal.

---

*Catatan metodologi: dokumen ini disusun dengan memverifikasi klaim terhadap kode aktual (migration, model, seeder, config, route, controller) di repository per 2026-07-28, bukan hanya menyalin dokumentasi internal — karena `docs/00-current-project-status.md` dan `docs/01-prd.md` saat ini memiliki konflik merge Git yang belum diselesaikan dan berpotensi menyesatkan bila dikutip langsung. Disarankan menyelesaikan konflik pada kedua file tersebut sebagai tindak lanjut terpisah.*
