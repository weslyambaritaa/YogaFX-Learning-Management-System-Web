# System Requirement Specification / Dokumen Kebutuhan Perangkat Lunak

**YogaFX Learning Management System (LMS) Web**

| | |
|---|---|
| **Dipersiapkan untuk** | YogaFX |
| **Dipersiapkan oleh** | [Group ID] — [Study Program / Year / Class] |
| **Project Code** | [Diisi] |
| **Version** | 1.0 |
| **Date** | 27-07-2026 |
| **Total Page** | — |

> Dokumen ini merupakan bagian dari dokumentasi penyelenggaraan perkuliahan Kerja Praktek mahasiswa Institut Teknologi Del, disusun mengikuti *Template Proyek KP*. Isi teknis dokumen ini disusun berdasarkan implementasi aktual repository YogaFX LMS Web (branch `frontend-kitabisa-2`) serta dokumentasi produk internal (`docs/00-current-project-status.md`, `docs/01-prd.md`, `docs/03-erd.md`).

**Ringkasan dokumen:** Dokumen ini menjabarkan kebutuhan fungsional dan non‑fungsional dari YogaFX LMS Web, sebuah platform pembelajaran daring berbasis Laravel + Inertia + React yang melayani dua sisi pengguna utama — Student (peserta didik) dan Admin (pengelola konten & operasional) — mencakup alur konversi lead menjadi student berbayar, pengelolaan konten pembelajaran bertingkat (tier), asesmen, sertifikasi, serta penagihan installment.

---

## DAFTAR ISI

1. Pembukaan
   1. Tujuan Penulisan Dokumen
   2. Ruang Lingkup Produk/Sistem yang Akan Dibangun
2. Deskripsi Umum
   1. Deskripsi Umum Sistem yang Akan Dibangun
   2. Fungsi Utama
   3. Kelompok dan Karakteristik Pengguna
   4. Batasan Desain dan Implementasi
3. Kebutuhan Rinci
   1. Kebutuhan Antarmuka
   2. Spesifikasi Kebutuhan Fungsional
   3. Kebutuhan Non Fungsional
4. Kebutuhan Lain dan Model Analisis

---

## 1 Pembukaan

### 1.1 Tujuan Penulisan Dokumen

Dokumen ini ditulis untuk mendefinisikan kebutuhan fungsional dan non-fungsional dari sistem **YogaFX LMS Web** secara rinci, sebagai acuan bagi para pengembang sistem (programmer, product owner, dan penguji/QA) dalam membangun, memelihara, dan mengembangkan lebih lanjut sistem tersebut. Dokumen ini juga digunakan sebagai bahan evaluasi Kerja Praktek untuk menunjukkan pemahaman terhadap kebutuhan perangkat lunak dari sistem yang sedang dikerjakan.

### 1.2 Ruang Lingkup Produk/Sistem yang Akan Dibangun

YogaFX LMS Web adalah platform pembelajaran daring (Learning Management System) milik YogaFX yang digunakan untuk mendistribusikan materi pelatihan yoga secara terstruktur dan berjenjang (tier) kepada peserta (student), sekaligus menjadi kanal konversi calon peserta (lead) menjadi peserta berbayar melalui alur checkout dan onboarding.

Ruang lingkup sistem mencakup:
- Halaman publik untuk pendaftaran lead, checkout paket (pembayaran penuh maupun cicilan/installment), serta onboarding peserta baru.
- Sisi **Student**: akses konten pembelajaran (module, lesson, ebook, course/video lecture) sesuai tier keanggotaan, pengerjaan asesmen, pengumpulan tugas (assignment) berupa video, pengunduhan sertifikat, dan pengelolaan profil termasuk upgrade tier.
- Sisi **Admin**: pengelolaan konten pembelajaran, pengelolaan tier & paket komersial, pengelolaan akun student dan admin, peninjauan progres belajar student, penerbitan sertifikat, pengelolaan template notifikasi email, serta pengelolaan tautan unduh aplikasi mobile.
- Backend API mobile (`/mobile/v1/*`) yang melayani aplikasi mobile pendamping dengan fitur setara sisi Student pada web.
- Proses backend terjadwal (cron) untuk pengingat inaktivitas dan sinkronisasi status tagihan installment yang telat.

Sistem ini memberikan manfaat bagi YogaFX berupa satu platform terpadu untuk akuisisi peserta, penjualan paket pelatihan (termasuk skema cicilan dan donasi), penyampaian materi belajar terstruktur, serta penerbitan sertifikasi kelulusan — menggantikan proses manual yang sebelumnya tersebar di berbagai kanal.

---

## 2 Deskripsi Umum

### 2.1 Deskripsi Umum Sistem yang Akan Dibangun

YogaFX LMS Web merupakan produk baru yang dibangun dari nol (bukan migrasi dari sistem lama), menggunakan arsitektur monolith Laravel dengan Inertia.js sebagai jembatan ke frontend React (server-driven SPA), didukung basis data PostgreSQL. Sistem ini terdiri dari tiga permukaan utama yang saling terhubung melalui satu basis data dan basis kode yang sama:

1. **Web publik** — halaman pemasaran/lead-generation dan checkout, tidak memerlukan autentikasi.
2. **Web Student** — area privat setelah login/onboarding, menyajikan pengalaman belajar.
3. **Web Admin** — panel operasional untuk mengelola seluruh konten, komersial, dan data peserta.
4. **API Mobile** — layanan JSON stateless (Laravel Sanctum) yang dikonsumsi aplikasi mobile terpisah, merepresentasikan ulang sebagian besar fitur sisi Student.

Alur tingkat tinggi: pengunjung publik → mengisi lead registration/scoreboard → memilih paket (tier) → checkout (bayar penuh atau cicilan via PayPal) → onboarding (enrollment & signup) → menjadi Student aktif → mengakses konten belajar sesuai tier → mengerjakan asesmen/tugas → menerima sertifikat kelulusan.

### 2.2 Fungsi Utama

Fungsi utama sistem meliputi:
- **Akuisisi & Checkout Publik**: lead registration, landing page per tier/paket, checkout pembayaran penuh maupun cicilan, onboarding enrollment dan signup.
- **Autentikasi & Otorisasi**: login dengan verifikasi OTP email, logout, lupa/reset password, pembatasan akses berbasis role (`super_admin`, `admin`, `student`) dan status aktif akun.
- **Manajemen Konten Pembelajaran** (Admin): CRUD Module, Lesson, Ebook, Course/Video Lecture, Assignment, dan Dialog Content, dengan akses many-to-many terhadap Access Tier.
- **Pengalaman Belajar** (Student): Home, daftar & detail module, detail lesson dengan video (Bunny Stream), progres tonton, ebook preview/download, sequential lesson locking.
- **Asesmen**: builder asesmen (pertanyaan, opsi, jump logic, rentang hasil) oleh Admin; pengerjaan asesmen dan hasil oleh Student.
- **Tugas (Assignment)**: pengumpulan video tugas oleh Student, peninjauan & status oleh Admin.
- **Sertifikasi**: pembuatan, pengunduhan, dan pengelolaan sertifikat berbasis kelayakan (tier, penyelesaian modul, persetujuan tugas).
- **Komersial (Package & Access Tier)**: pengelolaan paket harga, mata uang, konfigurasi cicilan, dan tier keanggotaan (`Starter Kit`, `Online`, `Master Class`).
- **Penagihan Installment**: kalkulasi jadwal cicilan, integrasi PayPal Subscription, webhook pembayaran, deteksi & pemulihan tunggakan (overdue) secara terjadwal.
- **Manajemen Peserta** (Admin): daftar student, aktif/nonaktifkan akun, reset progres, hapus akun.
- **Notifikasi Email**: template, pengiriman uji, log email, serta pemicu otomatis (penyelesaian modul, tugas, sertifikat, pengingat inaktivitas, siklus installment, dsb).
- **Link Control**: pengaturan tautan unduh aplikasi mobile (Google Play/App Store) dan QR code terkait.

Keterhubungan antar fungsi utama mengikuti alur: *Akuisisi & Checkout → Autentikasi/Onboarding → Konten Pembelajaran ↔ Asesmen ↔ Tugas → Sertifikasi*, dengan *Komersial* dan *Notifikasi* sebagai lapisan pendukung lintas fungsi, serta *Manajemen Peserta* dan *Manajemen Konten* sebagai kanal operasional Admin terhadap seluruh domain di atas.

### 2.3 Kelompok dan Karakteristik Pengguna

| Kelompok Pengguna | Karakteristik & Hak Akses |
|---|---|
| **Super Admin** | Hak akses penuh, termasuk seluruh kewenangan Admin biasa, ditambah kewenangan mengelola akun Admin lain (membuat, mengedit, menghapus akun admin). Tidak dapat menurunkan atau menghapus akun Super Admin lain/dirinya sendiri. |
| **Admin** | Staf operasional YogaFX yang mengelola konten pembelajaran, tier & paket, akun student, asesmen, sertifikat, dan notifikasi email. Tidak dapat membuat/mengedit/menghapus akun admin lain. |
| **Student (Peserta)** | Pengguna akhir yang telah menyelesaikan checkout dan onboarding. Mengakses konten belajar sesuai tier keanggotaannya, mengerjakan asesmen dan tugas, mengunduh sertifikat, serta dapat melakukan upgrade tier. Dibatasi lebih lanjut oleh status aktif (`is_active`) dan kelengkapan profil. |
| **Calon Peserta (Lead/Publik)** | Pengunjung anonim yang mengakses halaman publik/landing untuk melakukan pendaftaran minat (lead registration) dan checkout, sebelum menjadi Student resmi melalui proses onboarding. Belum memiliki akun sistem. |
| **Pengguna Aplikasi Mobile** | Student yang mengakses fitur setara (belajar, asesmen, tugas, sertifikat, profil) melalui aplikasi mobile terpisah, terautentikasi via token (Sanctum) dan OTP. |

### 2.4 Batasan Desain dan Implementasi

- **Teknologi**: backend Laravel 13 (PHP ^8.3) dengan Inertia.js sebagai penghubung ke frontend React 18 (Vite, Tailwind CSS 4, komponen Radix UI/shadcn); basis data PostgreSQL 17; queue & cache berbasis database/file; deployment melalui Docker (PHP-CLI + PostgreSQL). Pilihan stack ini mengikat pengembangan lanjutan agar konsisten dan mudah dipelihara oleh tim internal.
- **Peran sistem terbatas**: hanya tiga role yang didukung saat ini — `super_admin`, `admin`, `student`; penambahan role baru berada di luar cakupan implementasi saat ini.
- **Alur pendaftaran**: tidak ada self-signup publik tanpa pembayaran — akun Student hanya dibuat melalui alur checkout & onboarding, atau dibuat langsung oleh Admin.
- **Integrasi pembayaran**: seluruh transaksi (pembayaran penuh maupun cicilan) diproses melalui **PayPal** (Checkout Orders API & Subscriptions API); tidak ada payment gateway lain yang didukung saat ini.
- **Media pembelajaran**: video lesson wajib melalui **Bunny Stream**; penyimpanan berkas lain (thumbnail, ebook, workbook, video tugas) melalui Bunny Storage/lokal, dengan batas ukuran unggah berbeda per jenis berkas (umum/thumbnail 10 MB, ebook 100 MB, lesson workbook 100 MB, lesson audio 50 MB, video tugas 100 MB).
- **Skema cicilan (installment)** hanya berlaku untuk pembayaran awal paket (initial package); belum mendukung cicilan untuk upgrade tier maupun perpanjangan langganan umum.
- **Akses konten** ditentukan oleh relasi many-to-many antara `AccessTier` dan konten (Module, Lesson, Ebook, Course); Student hanya memiliki tepat satu `access_tier_id` aktif pada satu waktu.
- **Bahasa & lokalisasi**: antarmuka menggunakan Bahasa Indonesia dan Inggris sesuai konten produk (tidak ada kebutuhan multi-bahasa formal yang didokumentasikan).
- **Keterpeliharaan dokumentasi**: seluruh keputusan implementasi harus konsisten dengan migration, model, dan route aktual di repository, sebagaimana dijaga dalam dokumen produk internal (`docs/00-current-project-status.md`, `docs/01-prd.md`).

---

## 3 Kebutuhan Rinci

### 3.1 Kebutuhan Antarmuka

#### 3.1.1 Antarmuka Sistem

- **Basis data**: PostgreSQL 17, diakses melalui Eloquent ORM Laravel. Entitas utama meliputi `users`, `access_tiers`, `packages`, `pending_registrations`, `modules`, `lessons`, `ebooks`, `courses`, `assignments`, `assignment_submissions`, `assessments`, `questions`, `question_options`, `assessment_attempts`, `certificates`, `invoices`, `payment_subscriptions`, `payment_subscription_events`, `email_templates`, `email_logs`, `link_control_settings`, dan `personal_access_tokens`.
- **Queue & Job Scheduler**: Laravel queue berbasis database untuk pengiriman email asinkron; Laravel Scheduler (`routes/console.php`) menjalankan job terjadwal `email-notifications:send-reminders` (setiap 10 menit) dan `installments:sync-overdue-status` (setiap jam).
- **Layanan pihak ketiga**: PayPal (Checkout & Subscriptions API + Webhook) untuk pemrosesan pembayaran; Bunny.net Stream API untuk streaming video pembelajaran; Bunny.net Storage untuk penyimpanan berkas media terproteksi.
- **API internal**: `routes/api.php` menyediakan REST API `/mobile/v1/*` yang dikonsumsi aplikasi mobile, menggunakan format request/response JSON dan autentikasi token Sanctum.
- Data yang dipertukarkan mencakup: data profil peserta, status pembayaran/tagihan, progres belajar, jawaban asesmen, dan berkas media (URL bertanda tangan/signed URL).

#### 3.1.2 Antarmuka Pengguna

- Antarmuka web dibangun sebagai *server-driven SPA* menggunakan React + Inertia, dengan komponen UI konsisten berbasis Tailwind CSS dan Radix UI/shadcn (tombol, form, dialog, tabel, dsb.).
- **Sisi Admin**: tata letak menggunakan sidebar kiri yang dapat di-collapse, topbar berisi judul halaman dan menu pengguna; pola kerja *list → create/edit → kembali ke list* diterapkan konsisten pada seluruh modul CRUD, dilengkapi pencarian, filter, dan pagination pada daftar data (student, admin, dsb.).
- **Sisi Student**: halaman `Home` bersifat *content-first* (tidak berbasis tabel), menampilkan continue learning, ringkasan waktu akses, grid module, milestone tugas & sertifikat, serta bagian sumber daya ebook.
- Editor konten kaya (rich text) menggunakan CKEditor 5; pemutaran video menggunakan Video.js (HLS dari Bunny Stream); pemotongan gambar profil menggunakan react-easy-crop.
- Pesan error dan validasi ditampilkan inline pada form, mengikuti mekanisme validasi Laravel yang diteruskan melalui Inertia.
- Antarmuka publik (landing page, checkout) dirancang responsif untuk diakses dari perangkat mobile maupun desktop, mengingat sebagian besar traffic akuisisi berasal dari kanal media sosial.

#### 3.1.3 Antarmuka Perangkat Keras

Tidak ada kebutuhan khusus terhadap perangkat keras tertentu. Sistem diakses melalui peramban web standar pada perangkat desktop/mobile, serta melalui aplikasi mobile pendamping pada perangkat Android/iOS yang berkomunikasi dengan backend melalui API mobile.

#### 3.1.4 Antarmuka Komunikasi

- **HTTP/HTTPS**: seluruh komunikasi web dan API menggunakan protokol HTTP/HTTPS; `APP_FORCE_HTTPS` dapat diaktifkan untuk memaksa koneksi terenkripsi.
- **Email (SMTP)**: digunakan untuk pengiriman kode OTP login, notifikasi transaksional (penyelesaian modul, status tugas, sertifikat terbit, pengingat inaktivitas), dan notifikasi siklus tagihan installment (`installment_payment_success`, `installment_payment_failed`, `installment_overdue_inactive`, `installment_payment_completed`).
- **Webhook**: endpoint webhook PayPal menerima notifikasi status pembayaran/subscription secara asinkron dan memicu pembaruan data tagihan/aktivasi akun.
- **Signed URL**: tautan checkout, onboarding, serta akses media terproteksi (video, ebook, sertifikat) pada web maupun mobile menggunakan mekanisme signed URL Laravel untuk mencegah akses tidak sah/tautan yang dimanipulasi.
- **Format pesan**: REST API mobile menggunakan format JSON standar untuk request dan response.

### 3.2 Spesifikasi Kebutuhan Fungsional

#### 3.2.1 Autentikasi & Kontrol Akses

##### 3.2.1.1 Deskripsi dan Prioritas
Fungsi ini menangani proses masuk, verifikasi, dan pembatasan akses pengguna ke area privat sistem (Student/Admin). Berhubungan dengan fungsi utama Autentikasi & Otorisasi (Bagian 2.2). **Prioritas: Tinggi** — merupakan gerbang keamanan bagi seluruh fungsi lain.

##### 3.2.1.2 Kebutuhan Fungsional
- Sistem harus membedakan akses berdasarkan role: `super_admin`, `admin`, `student`.
- Login harus melalui verifikasi kode OTP (One-Time Password) yang dikirim ke email sebelum sesi final diberikan.
- Sistem harus menyediakan fungsi logout, lupa password, dan reset password.
- Area Admin hanya dapat diakses oleh role `admin`/`super_admin`; area Student hanya dapat diakses oleh Student berstatus aktif (`is_active`).
- Student yang profilnya belum lengkap harus diarahkan ke halaman pelengkapan profil sebelum dapat mengakses konten belajar.
- Sistem harus menolak login/akses apabila kredensial salah, OTP kedaluwarsa, atau akun berstatus nonaktif, disertai pesan error yang jelas.

##### 3.2.1.3 Urutan Stimulus/Respon
1. Pengguna memasukkan email dan password → sistem memvalidasi kredensial.
2. Jika valid, sistem mengirimkan kode OTP ke email terdaftar dan menampilkan form input OTP.
3. Pengguna memasukkan kode OTP → sistem memvalidasi kode dan masa berlakunya.
4. Jika OTP valid, sistem membuat sesi dan mengarahkan pengguna sesuai role (`admin`/`super_admin` → dashboard Admin; `student` → Home atau halaman pelengkapan profil bila belum lengkap).
5. Jika kredensial atau OTP tidak valid, sistem menampilkan pesan error dan tetap berada di halaman terkait.

#### 3.2.2 Akuisisi Publik, Checkout & Onboarding

##### 3.2.2.1 Deskripsi dan Prioritas
Fungsi ini mengelola perjalanan calon peserta (lead) mulai dari pendaftaran minat, pemilihan paket, pembayaran, hingga menjadi Student resmi. Berhubungan dengan fungsi Akuisisi & Checkout Publik (Bagian 2.2). **Prioritas: Tinggi** — merupakan sumber pendapatan dan pertumbuhan basis peserta.

##### 3.2.2.2 Kebutuhan Fungsional
- Pengunjung publik harus dapat mengisi formulir lead registration/scoreboard untuk tier/paket yang tersedia, menghasilkan data `pending_registration`.
- Sistem harus menyediakan landing page publik per tier (`/starter-kit`, `/online`, `/masterclass`) maupun per paket (`/p/{package_slug}`).
- Checkout harus mendukung dua skema pembayaran: **bayar penuh** (PayPal Checkout Order) dan **cicilan/installment** (PayPal Subscription), sesuai konfigurasi kelayakan paket.
- Checkout yang berhasil harus menghasilkan `invoice` dan `payment_activity`; untuk skema cicilan, sistem juga harus membuat `payment_subscription` beserta jadwal penagihan berikutnya.
- Setelah pembayaran berhasil, sistem harus melanjutkan pengguna ke alur onboarding: **enrollment** kemudian **signup**, sebelum akun Student aktif dibuat.
- Sistem harus menolak checkout dengan tautan yang tidak sah/kadaluwarsa (signed URL) atau paket yang tidak aktif/tidak tersedia untuk publik.

##### 3.2.2.3 Urutan Stimulus/Respon
1. Pengunjung mengisi lead registration pada landing tier/paket → sistem menyimpan `pending_registration` berstatus awal.
2. Pengunjung membuka tautan checkout → sistem menampilkan ringkasan paket dan opsi pembayaran (penuh/cicilan bila tersedia).
3. Pengunjung memilih metode pembayaran dan menyelesaikan pembayaran melalui PayPal.
4. Sistem menerima konfirmasi pembayaran (capture order atau approval subscription) → memperbarui status `pending_registration`, membuat `invoice`/`payment_subscription`.
5. Sistem mengarahkan pengunjung ke form enrollment, lalu form signup.
6. Setelah signup selesai, sistem membuat akun `Student` aktif dan mengirim email selamat datang.

#### 3.2.3 Manajemen Konten Pembelajaran & Pengalaman Belajar

##### 3.2.3.1 Deskripsi dan Prioritas
Mencakup pengelolaan konten oleh Admin (Module, Lesson, Ebook, Course, Assignment) serta konsumsi konten oleh Student dengan aturan penguncian berurutan (sequential locking). Berhubungan dengan fungsi Manajemen Konten Pembelajaran dan Pengalaman Belajar (Bagian 2.2). **Prioritas: Tinggi** — merupakan inti nilai produk LMS.

##### 3.2.3.2 Kebutuhan Fungsional
- Admin harus dapat melakukan CRUD terhadap Module, Lesson, Assignment, Ebook, Course/Video Lecture, dan Dialog Content.
- Setiap Module, Lesson, Ebook, dan Course memiliki relasi many-to-many terhadap Access Tier untuk menentukan hak akses konten.
- Lesson harus terhubung ke Module; video Lesson menggunakan `lesson_video_id` yang tervalidasi kesiapannya melalui Bunny Stream (HLS).
- Student hanya dapat melihat Lesson yang sesuai dengan tier keanggotaannya dan status unlock saat ini.
- Sistem harus menyimpan progres tonton (`watch_progress`) per Student per Lesson; Lesson video dianggap selesai bila progres tonton mencapai minimal 95%.
- Lesson berikutnya dapat terkunci sampai prasyarat Lesson sebelumnya terpenuhi (unduh workbook, tonton video ≥95%, dan menyelesaikan asesmen aktif bila ada).
- Sistem harus memicu email otomatis saat modul/kursus diselesaikan, dan saat workbook pertama kali dipicu/diunduh.
- Ebook harus dapat dibuka pada halaman preview terlebih dahulu (bila format mendukung) sebelum diunduh sebagai aksi terpisah.

##### 3.2.3.3 Urutan Stimulus/Respon
1. Admin membuat/mengedit Module beserta Lesson-nya, menetapkan Access Tier yang berhak mengakses.
2. Student membuka halaman Home/Modules → sistem menampilkan module yang sesuai tier dan status progres/unlock.
3. Student membuka detail Lesson dan memutar video → sistem mencatat `watch_progress` secara berkala.
4. Saat progres tonton mencapai ≥95% (dan prasyarat lain terpenuhi), sistem membuka akses Lesson berikutnya.
5. Saat seluruh Lesson dalam Module selesai, sistem mengirim email penyelesaian modul kepada Student.

#### 3.2.4 Asesmen (Assessment)

##### 3.2.4.1 Deskripsi dan Prioritas
Menyediakan mekanisme evaluasi pemahaman Student melalui kuis/asesmen yang dapat dikaitkan dengan Lesson tertentu. **Prioritas: Sedang–Tinggi**, karena menjadi bagian dari syarat kelulusan/sertifikasi.

##### 3.2.4.2 Kebutuhan Fungsional
- Admin harus dapat membangun asesmen melalui builder yang mendukung pertanyaan, opsi jawaban, jump logic (percabangan alur pertanyaan), rentang hasil (result range), dan pengaturan tampilan (design).
- Asesmen hanya dapat dibuka oleh Student jika Lesson terkait memiliki asesmen aktif dan Student memenuhi aturan unlock Lesson tersebut.
- Sistem harus menyimpan percobaan (`assessment_attempt`), jawaban (`assessment_answer`), label hasil, dan ringkasan progres pengerjaan.
- Student harus dapat memulai atau melanjutkan (resume) pengerjaan asesmen yang belum selesai.
- Admin harus dapat melakukan preview asesmen serta meninjau daftar dan detail hasil pengerjaan seluruh Student.

##### 3.2.4.3 Urutan Stimulus/Respon
1. Student membuka halaman intro asesmen pada Lesson terkait → sistem memvalidasi kelayakan akses.
2. Student memulai/melanjutkan pengerjaan → sistem menampilkan pertanyaan sesuai jump logic yang berlaku.
3. Student mengirimkan jawaban tiap tahap → sistem menyimpan jawaban dan memperbarui progres.
4. Setelah asesmen selesai, sistem menghitung hasil berdasarkan rentang hasil yang dikonfigurasi dan menampilkan halaman hasil kepada Student.
5. Admin dapat membuka detail hasil per Student dari menu peninjauan hasil asesmen.

#### 3.2.5 Tugas (Assignment) & Sertifikasi

##### 3.2.5.1 Deskripsi dan Prioritas
Mengelola pengumpulan tugas video oleh Student pada tier yang relevan, peninjauannya oleh Admin, serta penerbitan sertifikat kelulusan. **Prioritas: Sedang–Tinggi**, karena menjadi capaian akhir jalur belajar tier tertentu (saat ini aktif untuk tier `online`).

##### 3.2.5.2 Kebutuhan Fungsional
- Student pada tier yang relevan harus dapat mengunggah video tugas untuk Assignment aktif yang terhubung ke Module.
- Pengunggahan ulang harus menggantikan video tugas sebelumnya.
- Admin harus dapat meninjau, memberi status (`submitted`, `pending_review`, `under_review`, `approved`, `rejected`), dan feedback atas tugas yang dikumpulkan, serta menghapus video tugas bila diperlukan.
- Admin harus dapat men-generate, membuat ulang (recreate), mengunduh, dan menghapus sertifikat.
- Kelayakan penerbitan sertifikat harus mempertimbangkan pemetaan tier, penyelesaian jalur belajar (module), dan status persetujuan tugas yang relevan.
- Student hanya boleh mengunduh sertifikat miliknya sendiri.
- Sistem harus mengirim email notifikasi saat status tugas berubah (`assignment_review`, `assignment_approved`, `assignment_rejected`) dan saat sertifikat diterbitkan (`certificate_created`).

##### 3.2.5.3 Urutan Stimulus/Respon
1. Student membuka halaman Assignment pada Module yang relevan dan mengunggah video tugas → sistem menyimpan submission berstatus `submitted`/`pending_review`.
2. Admin meninjau submission dari menu Student Progress → memberi status `approved`/`rejected` beserta feedback.
3. Sistem mengirim email notifikasi status tugas kepada Student.
4. Setelah seluruh syarat kelayakan terpenuhi (tier, penyelesaian modul, tugas disetujui), Admin men-generate sertifikat.
5. Sistem mengirim email penerbitan sertifikat; Student dapat mengunduh sertifikat dari halamannya.

#### 3.2.6 Komersial & Penagihan Installment

##### 3.2.6.1 Deskripsi dan Prioritas
Mengelola konfigurasi paket harga (termasuk skema bayar penuh, cicilan/installment, dan donasi), Access Tier, serta siklus penagihan berkelanjutan untuk paket cicilan. **Prioritas: Tinggi** — berdampak langsung pada pendapatan dan retensi akun.

##### 3.2.6.2 Kebutuhan Fungsional
- Admin harus dapat melakukan CRUD Package (judul, slug, deskripsi, gambar, harga, mata uang, status aktif, konfigurasi cicilan) dan Access Tier (nama, level hierarki upgrade, thumbnail, status aktif).
- Package hanya tersedia untuk checkout publik apabila memiliki `access_tier_id` aktif.
- Konfigurasi cicilan pada Package hanya boleh aktif bila Package sudah ditautkan ke sebuah Access Tier; jadwal penagihan berikutnya mengikuti tanggal 15 tiap periode dengan batas akhir fase pertama pada tanggal 15 Januari.
- Sistem harus mendukung tipe pembayaran paket: **berbayar (paid)**, **gratis (free)**, dan **donasi (bydonation)** dengan nominal donasi minimum/anjuran yang dapat dikonfigurasi.
- Sistem harus menjalankan job terjadwal per jam untuk menyinkronkan status tagihan yang telat (overdue) dan job pemulihan pembayaran cicilan pertama.
- Akun Student dengan tagihan cicilan yang telat lebih dari 3 hari (H+3) dapat dinonaktifkan otomatis oleh sistem; akun dapat diaktifkan kembali apabila pembayaran berhasil dipulihkan dan subscription tidak berstatus final (dibatalkan/ditangguhkan permanen).
- Setiap event webhook PayPal Subscription harus dicatat pada `payment_subscription_events`; pembayaran cicilan yang berhasil harus membuat catatan pembayaran baru dan memperbarui saldo tagihan (`invoice.balance_due`).
- Student harus dapat melakukan upgrade ke tier dengan level lebih tinggi dari tier saat ini melalui halaman profil.

##### 3.2.6.3 Urutan Stimulus/Respon
1. Job terjadwal `installments:sync-overdue-status` berjalan tiap jam → memeriksa seluruh `payment_subscription` aktif terhadap tanggal jatuh tempo.
2. Jika ditemukan tagihan telat > 3 hari, sistem menonaktifkan akun Student terkait dan mengirim email `installment_overdue_inactive`.
3. Saat webhook PayPal mengonfirmasi pembayaran berhasil, sistem mencatat `payment_subscription_event`, membuat entri pembayaran baru, memperbarui `invoice.balance_due`, mengaktifkan kembali akun bila sebelumnya nonaktif, dan mengirim email `installment_payment_success`/`installment_payment_completed`.
4. Jika pembayaran gagal, sistem mencatat event dan mengirim email `installment_payment_failed`.

#### 3.2.7 Manajemen Peserta, Notifikasi Email & Link Control (Admin)

##### 3.2.7.1 Deskripsi dan Prioritas
Fungsi operasional pendukung bagi Admin: pengelolaan akun Student/Admin, template & log notifikasi email, serta pengaturan tautan unduh aplikasi mobile. **Prioritas: Sedang.**

##### 3.2.7.2 Kebutuhan Fungsional
- Admin harus dapat melihat, mencari, memfilter, dan melakukan paginasi daftar Student dan Admin.
- Admin harus dapat mengaktifkan/menonaktifkan akun Student, mereset progres belajar (penuh atau per lingkup: video, asesmen, lesson, module), serta menghapus akun Student beserta seluruh data belajarnya.
- Super Admin harus dapat membuat, mengedit, dan menghapus akun Admin biasa; Admin biasa tidak dapat membuat/mengedit/menghapus akun Admin lain.
- Admin harus dapat membuat template email, mengunggah media pendukung, mengirim email uji (test send), serta melihat log pengiriman email.
- Sistem harus mendukung minimal 15 tipe notifikasi otomatis aktif, mencakup siklus belajar (penyelesaian modul/kursus, tugas, asesmen, workbook), autentikasi (signup, reset password), dan siklus cicilan.
- Admin harus dapat mengelola satu set tautan global Google Play dan App Store beserta QR code yang digenerate otomatis, yang ditampilkan pada popup web Student dan halaman Home aplikasi mobile.
- Admin harus dapat melihat direktori progres belajar Student per tier, termasuk detail Lesson selesai, tugas, dan sertifikat per Student.

##### 3.2.7.3 Urutan Stimulus/Respon
1. Admin membuka menu Students → mencari/memfilter Student tertentu → membuka detail untuk mengedit profil, mengubah status aktif, atau mereset progres.
2. Admin membuka menu Email → menyunting template notifikasi → mengirim email uji untuk memverifikasi tampilan → menyimpan template.
3. Sistem memicu notifikasi otomatis sesuai event terkait (mis. penyelesaian modul) → mencatatnya pada Email Logs.
4. Admin membuka menu Link Control → memperbarui tautan Google Play/App Store → sistem meregenerasi QR code yang tampil di sisi Student.

### 3.3 Kebutuhan Non Fungsional

#### 3.3.1 Kebutuhan akan Performansi

- Streaming video pembelajaran harus memanfaatkan CDN Bunny Stream (format HLS) agar waktu buffering minimal pada koneksi standar.
- Pencatatan progres tonton (`watch_progress`) harus dilakukan secara periodik/asinkron agar tidak mengganggu pengalaman pemutaran video.
- Proses pengiriman email (notifikasi, OTP, pengingat) harus dijalankan melalui queue asinkron agar tidak memblokir respons permintaan pengguna.
- Daftar data besar (Student, Admin, log email) pada panel Admin harus mendukung paginasi untuk menjaga waktu muat halaman tetap responsif.
- Job terjadwal (`send-reminders`, `sync-overdue-status`) harus selesai dalam rentang waktu antar-eksekusi (10 menit dan 1 jam) tanpa terjadi tumpang tindih eksekusi (job overlap).

#### 3.3.2 Kebutuhan akan Keselamatan

- Kegagalan proses pembayaran (checkout maupun cicilan) tidak boleh menyebabkan aktivasi akun Student tanpa pembayaran tervalidasi; validasi status pembayaran harus dilakukan melalui webhook resmi PayPal, bukan hanya respons sisi klien.
- Penonaktifan otomatis akun akibat tunggakan cicilan harus disertai notifikasi email kepada Student agar tidak terjadi kehilangan akses tanpa pemberitahuan.
- Penghapusan akun Student oleh Admin bersifat permanen dan harus menghapus seluruh data belajar terkait secara konsisten (tidak meninggalkan data yatim/orphan).
- Unggahan berkas dibatasi ukurannya sesuai jenis (lihat Bagian 2.4) dan divalidasi baik di sisi frontend maupun backend; berkas yang melebihi batas tidak boleh disimpan ke penyimpanan.

#### 3.3.3 Kebutuhan akan Keamanan

- Login web wajib melalui verifikasi dua langkah: password + OTP email, untuk mengurangi risiko pengambilalihan akun.
- Password disimpan dengan hashing bcrypt (minimal 12 rounds).
- Akses area Admin dan Student dibatasi melalui middleware berbasis role (`role:admin,super_admin`, `role:student`) serta status aktif akun (`EnsureStudentAccountIsActive`).
- Akses media terproteksi (video, ebook, workbook, sertifikat) baik pada web maupun API mobile harus menggunakan signed URL yang tervalidasi (`ValidateMobileRelativeSignature` pada mobile) untuk mencegah akses langsung tanpa otorisasi.
- API mobile diautentikasi menggunakan token Laravel Sanctum per pengguna, dengan middleware `mobile.student` yang membatasi cakupan akses.
- Sistem harus mencatat aktivitas sesi Student (`UserSession`, `TrackStudentSessionActivity`) serta aktivitas tidak wajar selama pemutaran lesson (`LessonIrregularActivity`) untuk mendukung deteksi anomali/kecurangan.
- Komunikasi produksi harus berjalan melalui HTTPS (`APP_FORCE_HTTPS`).
- Data pembayaran sensitif (kredensial kartu, dsb.) tidak disimpan langsung oleh sistem — seluruh pemrosesan pembayaran didelegasikan ke PayPal sebagai pihak ketiga bersertifikasi.

#### 3.3.4 Atribut Kualitas Perangkat Lunak Lainnya

- **Kemudahan pemeliharaan**: dokumentasi produk internal (`docs/`) wajib selaras dengan migration, model, dan route aktual, guna menjaga konsistensi antara dokumentasi dan implementasi.
- **Konsistensi pola UI**: seluruh modul CRUD pada sisi Admin mengikuti pola *list → create/edit → redirect ke list* yang seragam, dan aksi hapus selalu disertai dialog konfirmasi.
- **Skalabilitas konten**: relasi many-to-many antara Access Tier dan konten (Module/Lesson/Ebook/Course) memungkinkan penambahan tier atau perluasan akses konten tanpa perubahan struktural besar.
- **Kegunaan (usability)**: sisi Student dirancang *content-first* dan bebas dari tabel data yang kompleks agar terasa premium dan tidak membebani pengguna non-teknis; sisi Admin dirancang *task-first* untuk efisiensi kerja operasional harian.
- **Keterujian**: proyek menyertakan test suite berbasis PHPUnit (`tests/`, `phpunit.xml`) untuk memverifikasi perilaku backend.

#### 3.3.5 Aturan Kebutuhan Operasional

- **Super Admin ↔ Manajemen Akun**: hanya Super Admin yang berhubungan dengan fungsi pengelolaan akun Admin (Bagian 3.2.7); Admin biasa tidak memiliki akses ke fungsi ini.
- **Admin ↔ Konten, Komersial, Peserta, Notifikasi**: Admin (dan Super Admin) berhubungan dengan seluruh fungsi pada Bagian 3.2.2 (checkout dilihat dari sisi operasional/monitoring), 3.2.3, 3.2.4, 3.2.5, 3.2.6, dan 3.2.7.
- **Student ↔ Pembelajaran & Sertifikasi**: Student berhubungan dengan fungsi pada Bagian 3.2.1 (autentikasi), 3.2.3 (konsumsi konten), 3.2.4 (pengerjaan asesmen), 3.2.5 (pengumpulan tugas & unduh sertifikat), dan sebagian 3.2.6 (upgrade tier).
- **Lead/Publik ↔ Akuisisi**: pengguna publik hanya berhubungan dengan fungsi pada Bagian 3.2.2 (lead registration & checkout) sebelum resmi menjadi Student.
- **Job Terjadwal ↔ Sistem**: proses pengingat inaktivitas dan sinkronisasi status cicilan (Bagian 3.2.6) berjalan otomatis oleh sistem tanpa keterlibatan langsung Admin, namun hasilnya (perubahan status akun, email terkirim) harus dapat ditelusuri oleh Admin melalui menu terkait (Students, Email Logs).

---

## 4 Kebutuhan Lain dan Model Analisis

**Kebutuhan Data**
- Basis data harus menyimpan riwayat lengkap transaksi (invoice, payment activity, payment subscription events) untuk keperluan audit dan rekonsiliasi keuangan.
- Data profil Student mencakup atribut spesifik domain yoga (`practicing_yoga_for`, `yoga_sequence_experience`, `hours_per_week`, `current_fitness_level`, `flexibility_rating`, `motivation`, dsb.) yang dikumpulkan pada tahap onboarding untuk personalisasi/analitik pembelajaran.

**Kebutuhan Legal/Kepatuhan**
- Data pribadi peserta (email, WhatsApp, tanggal lahir, dsb.) harus dikelola sesuai prinsip kerahasiaan data pengguna; akses ke data ini dibatasi hanya untuk role Admin/Super Admin yang berwenang.
- Transaksi pembayaran didelegasikan sepenuhnya ke PayPal sebagai penyedia layanan pembayaran resmi, sehingga aspek kepatuhan PCI-DSS berada di luar tanggung jawab langsung sistem ini.

**Model Analisis — Ringkasan Entitas Utama (ERD)**

```
User (Student/Admin) ──belongs to──> AccessTier ──has one active──> Package
User ──has many──> LessonProgress, AssessmentAttempt, AssignmentSubmission, Certificate, UserSession, Invoice
AccessTier ──many-to-many──> Module, Lesson, Ebook, Course
Module ──has many──> Lesson, Assignment
Lesson ──has one (optional)──> Assessment
Assessment ──has many──> Question ──has many──> QuestionOption
Package ──has many──> PendingRegistration, Invoice, PaymentSubscription
PaymentSubscription ──has many──> PaymentSubscriptionEvent
```

**Model Analisis — Alur Status Utama**
- **Status Pendaftaran (`PendingRegistration.status`)**: dibuat → checkout dibuka → pembayaran berhasil → onboarding selesai → Student aktif.
- **Status Pengumpulan Tugas (`AssignmentSubmission.status`)**: `submitted` → `pending_review`/`under_review` → `approved` atau `rejected`.
- **Status Akun Student (`is_active`)**: aktif secara default → dapat dinonaktifkan oleh Admin atau otomatis oleh sistem akibat tunggakan cicilan (H+3) → dapat diaktifkan kembali setelah pembayaran dipulihkan atau intervensi Admin.

Referensi lebih lanjut mengenai struktur data lengkap dan alur pengguna tersedia pada dokumentasi produk internal repository: `docs/03-erd.md` (Entity Relationship Design), `docs/02-user-flow.md` (User Flow), dan `docs/06-modular-implementation.md` (Modular Implementation).
