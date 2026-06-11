# Assessment & Student Time Tracking
# YogaFX LMS

## Purpose

Dokumen ini menjadi source of truth untuk 2 hal:

1. **Assessment Time Taken**
2. **Student Login / Access Time**

Dokumen ini dibuat karena saat ini pada hasil assessment masih muncul:

- `Time Taken`
- `00h 00m 00s`

padahal student benar-benar menghabiskan waktu saat mengerjakan.

Dokumen ini juga mengunci bagaimana **login/access time student** harus dihitung dan ditampilkan.

---

# 1. Scope

Dokumen ini hanya membahas:

1. bagaimana waktu pengerjaan assessment dihitung
2. bagaimana waktu login / waktu akses student dihitung
3. bagaimana keduanya ditampilkan di UI
4. bagaimana fallback harus bekerja jika data waktunya belum lengkap

Dokumen ini tidak membahas:
- builder scoreboard
- question types
- scoring logic
- email
- certificate
- domain lain di luar tracking waktu

---

# 2. Problem Statement

## 2.1 Assessment Time Taken
Saat ini result detail assessment bisa menampilkan:
- `Time Taken`
- `00h 00m 00s`

Ini salah jika student benar-benar mengerjakan assessment selama beberapa menit.

## 2.2 Student Login / Access Time
Sistem juga harus punya aturan jelas untuk:
- session login student
- cumulative access duration
- timer yang terus bertambah selama sesi aktif
- tampilan total waktu belajar / akses

---

# 3. Assessment Time Taken Rules

## 3.1 Start Time
Waktu mulai assessment harus diambil dari:
- `assessment_attempt.started_at`

Field ini harus dibuat saat student benar-benar memulai attempt.

## 3.2 End Time
Waktu selesai assessment harus diambil dari:
- `assessment_attempt.submitted_at`
atau
- `assessment_attempt.completed_at`

Jika keduanya ada, prioritas utama:
1. `submitted_at`
2. `completed_at`

## 3.3 Actual Time Taken
`Time Taken` harus berasal dari selisih nyata antara:
- `started_at`
dan
- waktu selesai final

Secara konsep:

`time_taken_seconds = end_time - started_at`

## 3.4 Resume Behaviour
Jika attempt dilanjutkan (resume), maka:
- `started_at` **tidak boleh di-reset**
- waktu tetap dihitung dari awal attempt pertama dimulai

## 3.5 Auto Submit
Jika assessment selesai karena auto-submit / expired:
- tetap harus ada end time yang valid
- time taken tetap harus bisa dihitung
- jangan tampil `00h 00m 00s` hanya karena submit terjadi otomatis

## 3.6 Preview Exclusion
Preview admin **tidak boleh**:
- membuat attempt nyata
- membuat answer nyata
- membuat progress nyata
- membuat time taken nyata

Preview hanya simulasi.

## 3.7 Display Rule
Jika attempt sudah completed, maka UI result detail harus menampilkan:
- hours
- minutes
- seconds

berdasarkan waktu nyata pengerjaan.

## 3.8 Missing Data Rule
Jika attempt completed tetapi data waktu tidak lengkap:
- sistem harus mencoba menghitung dari timestamp yang tersedia
- jika tetap tidak bisa dihitung, tampilkan fallback yang jujur seperti:
  - `Unavailable`
- **jangan** menampilkan `00h 00m 00s` secara palsu

## 3.9 Storage Rule
Sistem boleh:
- menghitung `time_taken_seconds` saat result dibuka
atau
- menyimpan `time_taken_seconds` saat attempt selesai

Tetapi hasil akhirnya harus konsisten dan akurat.

---

# 4. Assessment Result UI Rules

## 4.1 Results List
Di halaman View Results, field waktu tidak wajib tampil sebagai field utama.

Fokus list tetap pada:
- Name
- Email
- Correct Answers
- Percentage
- Completed At / Completion Info

## 4.2 Result Detail
Di halaman detail result per user, summary wajib menampilkan:
- Name
- Email
- Phone
- Correct Answers
- Points
- Percentage
- Time Taken

## 4.3 Time Taken Format
Format final:
- `HH hours`
- `MM minutes`
- `SS seconds`

Contoh:
- `00 hours`
- `04 minutes`
- `17 seconds`

## 4.4 No False Zero
Jika user memang mengerjakan assessment selama beberapa waktu, UI tidak boleh menampilkan:
- `00h 00m 00s`

kecuali memang datanya benar-benar nol, yang dalam praktik hampir tidak masuk akal untuk completed attempt.

---

# 5. Student Login / Access Time Rules

## 5.1 Session Model
Tracking waktu akses student memakai:
- `user_sessions`
- `users.total_access_duration_seconds`

## 5.2 Session Start
Saat student login:
- sistem membuat session aktif
- `login_at` terisi
- `is_active = true`

## 5.3 Session Activity
Selama student aktif:
- `last_activity_at` diperbarui
- session dianggap berjalan

## 5.4 Session End
Saat student logout atau session diakhiri:
- `logout_at` diisi
- `session_duration_seconds` dihitung
- `is_active = false`

## 5.5 Cumulative Total
Setelah session berakhir:
- `session_duration_seconds` ditambahkan ke:
  - `users.total_access_duration_seconds`

## 5.6 Running Timer
Saat student sedang login aktif, tampilan waktu akses harus:
- melanjutkan dari total sebelumnya
- lalu bertambah sesuai durasi session aktif sekarang

Artinya:
- timer tidak reset ke nol setiap login
- timer melanjutkan dari total terakhir

## 5.7 Source of Truth
Source of truth untuk total akses student adalah:
- `users.total_access_duration_seconds`
ditambah
- durasi session aktif yang sedang berjalan, jika ada

---

# 6. Student Time Display Rules

## 6.1 Student Side
Di student dashboard atau area yang relevan, sistem boleh menampilkan:
- total access time
- study time
- total time spent

## 6.2 Admin Side
Di admin student detail / progress area, sistem harus bisa menampilkan:
- total access duration
- last visit
- session-related summary

## 6.3 Format
Gunakan format yang konsisten:
- jam
- menit
- detik

---

# 7. Edge Cases

## 7.1 Assessment Finished But Missing Time
Jika attempt selesai tapi field waktunya belum lengkap:
- backend harus berusaha hitung dari timestamp yang ada
- jika tidak bisa, tampilkan fallback jujur
- jangan sembunyikan bug dengan nol palsu

## 7.2 Session Closed Unexpectedly
Jika student tidak logout normal:
- session tetap harus bisa direkonsiliasi oleh mekanisme timeout / inactivity policy aplikasi
- total waktu akses tidak boleh hilang begitu saja

## 7.3 Multiple Attempts
Jika satu user punya banyak attempt untuk assessment yang sama:
- setiap attempt punya `time_taken` sendiri
- result detail menampilkan waktu untuk attempt yang sedang dilihat
- jangan ambil waktu dari attempt lain

---

# 8. Implementation Expectations

## 8.1 For Assessment
Codex harus memastikan:
- started_at terisi saat attempt dimulai
- submitted_at / completed_at terisi saat attempt selesai
- time taken dihitung dari timestamp nyata
- result detail tidak lagi menampilkan nol palsu

## 8.2 For Student Login Time
Codex harus memastikan:
- session dimulai saat login
- session ditutup saat logout / timeout
- total akses terakumulasi
- student timer melanjutkan dari total sebelumnya

---

# 9. Final Summary

Domain waktu sekarang harus mengikuti aturan ini:

## Assessment Time Taken
- hitung dari `started_at` sampai `submitted_at/completed_at`
- preview admin tidak ikut
- jangan tampilkan nol palsu

## Student Login / Access Time
- hitung dari session login
- akumulasi ke total akses user
- timer student harus terus bertambah dari total sebelumnya

Dokumen ini menjadi source of truth untuk implementasi tracking waktu assessment dan waktu akses student.