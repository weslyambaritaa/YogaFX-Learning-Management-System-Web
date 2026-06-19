# Password Reset Flow Context Report

Dokumen ini merangkum kondisi **aktual** flow forgot/reset/change password yang aktif di repo saat ini, tanpa mengusulkan redesign arsitektur.

Dokumen acuan yang dibaca sebelum audit:
- `docs/00-current-project-status.md`
- `docs/01-prd.md`
- `docs/02-user-flow.md`
- `docs/03-erd.md`
- `docs/04-design-system.md`
- `docs/05-information-architecture.md`
- `docs/06-modular-implementation.md`
- `docs/email-notification-flow.md`

## 1. Current flow overview

### 1.1 Public forgot/reset password flow

Flow public yang aktif saat ini adalah flow Laravel reset password berbasis email link:

1. User membuka halaman login `resources/js/Pages/Auth/Login.jsx`.
2. User klik link `Forgot your password?` ke route `password.request`.
3. Halaman forgot password `resources/js/Pages/Auth/ForgotPassword.jsx` submit email ke `password.email`.
4. `App\Http\Controllers\Auth\PasswordResetLinkController@store` memanggil `Password::sendResetLink(...)`.
5. Karena model `App\Models\User` override `sendPasswordResetNotification($token)`, email reset **tidak** memakai notification default Laravel, tetapi dialihkan ke `App\Services\EmailNotificationService::sendPasswordResetRequested(...)`.
6. Email yang terkirim membawa URL web `route('password.reset', ['token' => $token, 'email' => $user->email])`.
7. Link email membuka halaman web `resources/js/Pages/Auth/ResetPassword.jsx`.
8. Form submit ke `password.store`.
9. `App\Http\Controllers\Auth\NewPasswordController@store` memanggil `Password::reset(...)`.
10. Jika berhasil, user diarahkan ke halaman login dengan flash status. Tidak ada auto-login.

Karakter flow ini:
- berbasis **token link**
- **tidak** memakai OTP field terpisah
- success state berupa **redirect ke login**, bukan success page/message di form yang sama

### 1.2 Student signed-in change password flow

Selain flow public di atas, ada flow student khusus dari halaman profile:

1. Student login lalu buka `resources/js/Pages/Profile/Edit.jsx`.
2. Tombol `Change Password` mem-post ke route `profile.password.request`.
3. `App\Http\Controllers\Student\ProfilePasswordController@request`:
   - menghapus token broker lama
   - menghapus record lama di `student_password_change_requests`
   - membuat token reset baru via `Password::broker()->createToken($user)`
   - membuat OTP 6 digit
   - menyimpan `token_hash`, `otp_hash`, dan `expires_at` ke tabel `student_password_change_requests`
   - mengirim email melalui `EmailNotificationService::sendStudentPasswordChangeRequested(...)`
4. Email membawa link web `route('profile.password.change.edit', ['token' => $token, 'email' => $user->email])` dan OTP code.
5. Link email membuka halaman web `resources/js/Pages/Auth/StudentPasswordChange.jsx`.
6. Form meminta `OTP Code`, `New Password`, dan `Confirm New Password`.
7. `App\Http\Controllers\Student\ProfilePasswordController@update` memverifikasi token broker, OTP hash, expiry, lalu menjalankan `Password::reset(...)`.
8. Jika berhasil, request ditandai `used_at`, sesi user di-logout, lalu user diarahkan ke login dengan flash success message.

Karakter flow ini:
- berbasis **token link + OTP 6 digit**
- menampilkan info **email destination** dan **expiry time**
- success state tetap berupa **redirect ke login**

### 1.3 Mobile relation

Repo juga punya endpoint mobile terpisah:
- `POST /api/mobile/v1/auth/forgot-password`
- `POST /api/mobile/v1/auth/reset-password`
- `POST /api/mobile/v1/profile/change-password`

Jadi flow **dibedakan** antara web dan mobile pada level endpoint/controller.

Namun untuk forgot password:
- endpoint mobile `PasswordRecoveryController@forgot` tetap memanggil `Password::sendResetLink(...)`
- email yang dikirim tetap membentuk URL ke **web** route `password.reset`
- tidak ada page reset password khusus mobile di repo

Untuk authenticated mobile change password:
- mobile memakai `App\Http\Controllers\Mobile\V1\ProfileController@changePassword`
- flow ini langsung meminta `current_password` dan `new_password`
- flow ini **tidak** memakai email link dan **tidak** memakai OTP

### 1.4 Admin password change

Admin punya change password aktif di halaman profile admin:
- halaman: `resources/js/Pages/Admin/Profile/Edit.jsx`
- submit: `PATCH /admin/profile`
- controller: `App\Http\Controllers\Admin\AdminProfileController@update`

Flow admin ini bukan forgot/reset password flow. Ia adalah inline profile update dengan field `current_password`, `password`, dan `password_confirmation`.

## 2. Relevant routes

| Area | Method + path | Route name | Kegunaan |
| --- | --- | --- | --- |
| Public web | `GET /forgot-password` | `password.request` | Halaman forgot password |
| Public web | `POST /forgot-password` | `password.email` | Submit forgot password |
| Email link web | `GET /reset-password/{token}` | `password.reset` | Link email reset password public/web/mobile forgot |
| Public web | `POST /reset-password` | `password.store` | Submit reset password public |
| Student web | `POST /profile/password/request` | `profile.password.request` | Trigger student change password dari profile |
| Email link web | `GET /profile/password/change/{token}` | `profile.password.change.edit` | Link email untuk student password change |
| Student web | `POST /profile/password/change` | `profile.password.change.update` | Submit student password change |
| Mobile API | `POST /api/mobile/v1/auth/forgot-password` | `mobile.api.v1.auth.password.forgot` | Trigger forgot password mobile |
| Mobile API | `POST /api/mobile/v1/auth/reset-password` | `mobile.api.v1.auth.password.reset` | Submit reset password mobile |
| Mobile API | `POST /api/mobile/v1/profile/change-password` | `mobile.api.v1.profile.password.change` | Authenticated change password mobile |
| Admin web | `PATCH /admin/profile` | `admin.profile.update` | Inline change password admin, bukan flow email reset |

Route target halaman form web yang aktif:
- `password.request` -> `resources/js/Pages/Auth/ForgotPassword.jsx`
- `password.reset` -> `resources/js/Pages/Auth/ResetPassword.jsx`
- `profile.password.change.edit` -> `resources/js/Pages/Auth/StudentPasswordChange.jsx`
- `profile.edit` -> `resources/js/Pages/Profile/Edit.jsx` sebagai trigger student change password

## 3. Relevant controllers / actions

| Kebutuhan | Controller / action | Status pemakaian saat ini |
| --- | --- | --- |
| Forgot password request web | `App\Http\Controllers\Auth\PasswordResetLinkController@create` | Aktif |
| Forgot password submit web | `App\Http\Controllers\Auth\PasswordResetLinkController@store` | Aktif |
| Reset link page public | `App\Http\Controllers\Auth\NewPasswordController@create` | Aktif |
| Reset submit public | `App\Http\Controllers\Auth\NewPasswordController@store` | Aktif |
| Student password change trigger | `App\Http\Controllers\Student\ProfilePasswordController@request` | Aktif |
| Student password change link page | `App\Http\Controllers\Student\ProfilePasswordController@edit` | Aktif |
| Student password change submit | `App\Http\Controllers\Student\ProfilePasswordController@update` | Aktif |
| Forgot password mobile | `App\Http\Controllers\Mobile\V1\PasswordRecoveryController@forgot` | Aktif |
| Reset password mobile | `App\Http\Controllers\Mobile\V1\PasswordRecoveryController@reset` | Aktif |
| Change password mobile | `App\Http\Controllers\Mobile\V1\ProfileController@changePassword` | Aktif |
| Change password admin | `App\Http\Controllers\Admin\AdminProfileController@update` | Aktif, tapi bukan flow reset email |

Action yang terlihat ada di repo tetapi **tidak aktif dipakai sekarang**:
- `App\Http\Controllers\Auth\PasswordController@update`
- `resources/js/Pages/Profile/Partials/UpdatePasswordForm.jsx`

Keduanya tidak punya route aktif di `php artisan route:list --path=password`.

## 4. Email / notification source

### 4.1 Source utama email reset password

Email reset password aktif saat ini dibentuk oleh kombinasi berikut:

- `App\Models\User::sendPasswordResetNotification($token)`
- `App\Services\EmailNotificationService`
- `App\Events\EmailNotifications\ResetPasswordRequested`
- `App\Listeners\SendResetPasswordEmailNotification`
- `App\Mail\TemplatedNotificationMail`
- `App\Support\EmailNotificationTemplateDefaults`
- tabel `email_templates` untuk override isi template dari admin UI

### 4.2 Bagaimana URL di email dibentuk

#### Public forgot password dan mobile forgot password

`EmailNotificationService::sendPasswordResetRequested(...)` membentuk:
- `reset_url` = `route('password.reset', ['token' => $token, 'email' => $user->email])`

Artinya link email selalu mengarah ke **halaman web** `password.reset`.

#### Student profile change password

`ProfilePasswordController@request` membentuk:
- `changePasswordUrl` = `route('profile.password.change.edit', ['token' => $token, 'email' => $user->email])`

Lalu URL ini dikirim ke:
- `reset_url`
- `password_change_url`

di payload `EmailNotificationService::sendStudentPasswordChangeRequested(...)`.

### 4.3 OTP / token yang dikirim

#### Public forgot/reset

- token broker Laravel dibuat dan disimpan di tabel `password_reset_tokens`
- token tidak ditampilkan sebagai field terpisah di form; ia dibawa di URL email
- **tidak ada OTP code**

#### Student change password

- token broker Laravel tetap dibuat lewat `Password::broker()->createToken($user)`
- token hash disalin juga ke tabel `student_password_change_requests`
- OTP 6 digit dibuat di `ProfilePasswordController@request`
- OTP disimpan sebagai `otp_hash` di `student_password_change_requests`
- email berisi **link + OTP**

### 4.4 Data yang masuk ke email

Payload public forgot/reset:
- `user_name`
- `user_email`
- `reset_url`
- `reset_expiry_minutes`
- `login_url`

Payload student password change:
- `user_name`
- `user_email`
- `reset_url`
- `password_change_url`
- `otp_code`
- `reset_expiry_minutes`
- `login_url`

### 4.5 Template/body default

Default untuk notification type `reset_password` ada di:
- `App\Support\EmailNotificationTemplateDefaults::for(EmailNotificationTypeRegistry::RESET_PASSWORD)`

Default body saat ini:
- berisi greeting
- info ada request reset password
- link reset password
- info expiry minutes
- tidak memuat OTP secara default

Untuk student password change:
- `EmailNotificationService::sendStudentPasswordChangeRequested(...)` akan menambahkan blok OTP dan/atau URL jika template aktif belum menyertakan placeholder itu

### 4.6 Catatan template/view

Class pengirim aktif adalah `App\Mail\TemplatedNotificationMail`.

File Blade `resources/views/emails/templated-notification.blade.php` ada di repo, tetapi class `TemplatedNotificationMail` saat ini merender HTML langsung via `->html(...)` / `withSymfonyMessage(...)`, jadi Blade itu bukan sumber utama yang dipakai flow reset password sekarang.

## 5. Web page / form source

### 5.1 Forgot password page

- File: `resources/js/Pages/Auth/ForgotPassword.jsx`
- Stack: Inertia + React
- Field aktif:
  - `email`
- Success flow:
  - tetap di halaman yang sama
  - menampilkan flash `status`

### 5.2 Public reset password page

- File: `resources/js/Pages/Auth/ResetPassword.jsx`
- Stack: Inertia + React
- Field aktif:
  - `email`
  - `password`
  - `password_confirmation`
- Yang **tidak** ada:
  - OTP code
  - info email destination khusus
  - info expiry time
- Success flow:
  - redirect ke login dengan flash status dari `NewPasswordController@store`

### 5.3 Student password change page

- File: `resources/js/Pages/Auth/StudentPasswordChange.jsx`
- Stack: Inertia + React
- Field aktif:
  - `otp_code`
  - `new_password`
  - `new_password_confirmation`
- Informasi yang tampil:
  - email destination
  - expiry time dari `expires_at`
- Success flow:
  - redirect ke login dengan status `Your password has been changed successfully. Please log in again.`

### 5.4 Student trigger page

- File: `resources/js/Pages/Profile/Edit.jsx`
- Stack: Inertia + React
- Tombol aktif:
  - `Change Password`
- Tombol ini memulai flow student password change dengan email link + OTP

### 5.5 Admin change password page

- File: `resources/js/Pages/Admin/Profile/Edit.jsx`
- Stack: Inertia + React
- Field aktif:
  - `current_password`
  - `password`
  - `password_confirmation`
- Success flow:
  - tetap di halaman admin profile dengan flash `admin-profile-updated`

## 6. Current gaps vs desired flow

Requirement target yang dibandingkan:
- link email harus mengarah ke flow web yang sama
- tidak perlu membuat page baru khusus mobile
- form harus mendukung `OTP Code`, `New Password`, `Confirm New Password`, info email destination, info expiry time
- setelah berhasil cukup tampil pesan sukses
- tidak perlu auto-login
- tidak perlu redirect ke aplikasi mobile

Perbandingan dengan kondisi repo saat ini:

1. Link email harus mengarah ke flow web yang sama
- **Belum terpenuhi penuh**
- saat ini ada dua web form berbeda:
  - `Auth/ResetPassword.jsx` untuk forgot/reset public
  - `Auth/StudentPasswordChange.jsx` untuk student profile change
- mobile forgot password juga mengirim link ke `password.reset`, bukan ke satu unified form bersama student flow

2. Tidak perlu page baru khusus mobile
- **Sudah sesuai**
- repo tidak punya page reset password khusus mobile
- tetapi mobile tetap punya endpoint API terpisah

3. Form harus mendukung OTP Code + New Password + Confirm + info email + info expiry
- **Baru terpenuhi di student flow**
- `StudentPasswordChange.jsx` sudah punya semua elemen itu
- `ResetPassword.jsx` belum punya OTP dan belum menampilkan info expiry/destination seperti requirement target

4. Setelah berhasil cukup tampil pesan sukses
- **Belum sesuai**
- public reset dan student change sama-sama redirect ke login
- tidak ada success page/message final di form yang sama

5. Tidak perlu auto-login
- **Sudah sesuai**
- tidak ada auto-login setelah reset

6. Tidak perlu redirect ke aplikasi mobile
- **Sudah sesuai**
- URL email yang dibentuk saat ini semuanya tetap menuju web route

Tambahan gap implementasi penting:
- route `profile.password.change.edit` dan `profile.password.change.update` berada di dalam middleware `auth`, sehingga link email student change hanya bisa dibuka saat masih punya sesi login web aktif
- default template `reset_password` masih berorientasi link reset biasa; OTP hanya pasti muncul untuk student flow karena ada fallback append di service, bukan karena default template sudah spesifik OTP flow

## 7. Feature-by-feature audit

| Bagian | Status | File yang terlibat | Masalah yang ditemukan |
| --- | --- | --- | --- |
| forgot password trigger | done | `resources/js/Pages/Auth/Login.jsx`, `resources/js/Pages/Auth/ForgotPassword.jsx`, `app/Http/Controllers/Auth/PasswordResetLinkController.php`, `routes/auth.php`, `app/Http/Controllers/Mobile/V1/PasswordRecoveryController.php`, `routes/api.php` | Trigger web dan mobile sama-sama aktif |
| reset password email | partial | `app/Models/User.php`, `app/Services/EmailNotificationService.php`, `app/Listeners/SendResetPasswordEmailNotification.php`, `app/Support/EmailNotificationTemplateDefaults.php`, `app/Support/EmailNotificationTypeRegistry.php`, `app/Mail/TemplatedNotificationMail.php` | Public forgot email hanya link reset biasa; OTP hanya ada di student change flow; satu notification type dipakai untuk dua semantik flow |
| URL generation in email | partial | `app/Services/EmailNotificationService.php`, `app/Http/Controllers/Student/ProfilePasswordController.php` | Ada dua target URL web berbeda: `password.reset` dan `profile.password.change.edit`; belum satu web flow yang sama |
| OTP/token generation | partial | `app/Http/Controllers/Student/ProfilePasswordController.php`, `app/Http/Controllers/Auth/PasswordResetLinkController.php`, `app/Http/Controllers/Mobile/V1/PasswordRecoveryController.php`, `database/migrations/0001_01_01_000000_create_users_table.php`, `database/migrations/2026_06_15_000001_create_student_password_change_requests_table.php` | Public forgot hanya token; student change token + OTP; perilaku belum seragam |
| reset password route target | partial | `routes/auth.php`, `routes/web.php`, `resources/js/Pages/Auth/ResetPassword.jsx`, `resources/js/Pages/Auth/StudentPasswordChange.jsx` | Link email tidak menuju satu target form yang sama; student email target juga masih di bawah middleware `auth` |
| reset/change password web form | partial | `resources/js/Pages/Auth/ResetPassword.jsx`, `resources/js/Pages/Auth/StudentPasswordChange.jsx` | Student form sudah dekat dengan requirement; public reset form masih belum punya OTP, expiry info, dan destination info |
| submit handler | partial | `app/Http/Controllers/Auth/NewPasswordController.php`, `app/Http/Controllers/Student/ProfilePasswordController.php`, `app/Http/Controllers/Mobile/V1/PasswordRecoveryController.php` | Ada dua submit handler web dengan perilaku berbeda; student handler masih redirect ke login setelah success |
| success page / success message | wrong flow | `app/Http/Controllers/Auth/NewPasswordController.php`, `app/Http/Controllers/Student/ProfilePasswordController.php`, `resources/js/Pages/Auth/Login.jsx` | Success akhir bukan pesan sukses final di form/reset page, tetapi redirect ke login dengan flash |

## 8. Recommended minimal changes

Perubahan minimum agar flow lebih dekat ke requirement target:

1. Satukan target link email ke satu web form yang sama
- paling kecil kemungkinan dengan menjadikan flow final berbasis `resources/js/Pages/Auth/StudentPasswordChange.jsx`
- file yang kemungkinan diubah:
  - `app/Services/EmailNotificationService.php`
  - `app/Http/Controllers/Auth/NewPasswordController.php`
  - `routes/auth.php`
  - `routes/web.php`

2. Jadikan form final mendukung OTP untuk semua reset email flow yang relevan
- gunakan pola `StudentPasswordChangeRequest` sebagai lapisan verifikasi tambahan
- file yang kemungkinan diubah:
  - `app/Http/Controllers/Auth/PasswordResetLinkController.php`
  - `app/Http/Controllers/Auth/NewPasswordController.php`
  - `app/Http/Controllers/Mobile/V1/PasswordRecoveryController.php`
  - `app/Models/StudentPasswordChangeRequest.php`
  - `resources/js/Pages/Auth/ResetPassword.jsx`
  - atau langsung re-route ke `resources/js/Pages/Auth/StudentPasswordChange.jsx`

3. Lepas ketergantungan `auth` dari halaman email-link student flow jika link harus benar-benar usable dari inbox
- file yang kemungkinan diubah:
  - `routes/web.php`
  - `app/Http/Controllers/Student/ProfilePasswordController.php`

4. Ubah success flow agar cukup menampilkan pesan sukses, tanpa redirect ke login sebagai layar akhir
- file yang kemungkinan diubah:
  - `app/Http/Controllers/Auth/NewPasswordController.php`
  - `app/Http/Controllers/Student/ProfilePasswordController.php`
  - `resources/js/Pages/Auth/ResetPassword.jsx`
  - `resources/js/Pages/Auth/StudentPasswordChange.jsx`

5. Rapikan template reset password agar eksplisit mendukung placeholder OTP flow
- file yang kemungkinan diubah:
  - `app/Support/EmailNotificationTemplateDefaults.php`
  - kemungkinan data `email_templates` untuk type `reset_password`

6. Biarkan mobile tetap tanpa page khusus
- tidak perlu menambah page mobile baru
- jika web flow disatukan, mobile forgot password cukup tetap mengirim email yang mengarah ke web flow tersebut

## 9. Summary praktis

Flow aktif saat ini belum benar-benar satu alur:
- public forgot/reset memakai web form `Auth/ResetPassword.jsx` tanpa OTP
- student change password memakai web form `Auth/StudentPasswordChange.jsx` dengan OTP
- mobile forgot/reset punya endpoint sendiri, tetapi link email tetap menuju web

Jika tujuan task berikutnya adalah patch minimal, area paling penting untuk disentuh kemungkinan hanya:
- `routes/auth.php`
- `routes/web.php`
- `app/Http/Controllers/Auth/NewPasswordController.php`
- `app/Http/Controllers/Student/ProfilePasswordController.php`
- `app/Services/EmailNotificationService.php`
- `resources/js/Pages/Auth/ResetPassword.jsx`
- `resources/js/Pages/Auth/StudentPasswordChange.jsx`
