# Certificate Module

# YogaFX LMS

## Purpose

Dokumen ini menjadi source of truth untuk domain **Certificate Module** pada YogaFX LMS.

Modul ini mengatur:

- eligibility sertifikat berdasarkan access tier
- syarat completion sebelum sertifikat bisa digenerate
- admin generate/regenerate/download flow
- student download flow
- penggunaan template sertifikat tetap
- render nama student ke template
- hasil akhir sertifikat dalam format PDF

---

# 1. Scope

Modul Certificate mencakup:

1. Admin generate sertifikat per student
2. Admin regenerate sertifikat per student
3. Admin download sertifikat per student
4. Student membuka modul Certificate
5. Student download sertifikat miliknya

Modul ini **tidak** mencakup:

- certificate revoke/delete
- automatic generate saat student selesai
- student preview browser page khusus
- custom template builder oleh admin
- upload template baru oleh admin

---

# 2. Certificate Templates

## 2.1 Fixed Templates

Sertifikat memakai template tetap yang sudah ditentukan.

### Template 1

- Source image: `1.jpg`
- Label: **Bikram Yoga Certificate**

### Template 2

- Source image: `2.jpg`
- Label: **Yoga Alliance Certificate**

## 2.2 Template Type by Tier

### Starter Kit

Mendapat:

- **Bikram Yoga Certificate** (`1.jpg`)

### Online

Mendapat:

- **Bikram Yoga Certificate** (`1.jpg`)
- **Yoga Alliance Certificate** (`2.jpg`)

### Master Class

Mendapat:

- **Bikram Yoga Certificate** (`1.jpg`)
- **Yoga Alliance Certificate** (`2.jpg`)

---

# 3. Eligibility Rules

## Final Rule

Student **tidak boleh** langsung mendapat sertifikat hanya karena tier.

Student baru eligible jika:

- telah menyelesaikan **semua alur modul di bawahnya**

## “Selesai alur modul” berarti:

- semua modul pembelajaran yang relevan selesai
- semua assignment yang relevan selesai
- semua komponen learning flow lain yang menjadi bagian dari jalur tersebut selesai

## Important Rule

Eligibility certificate harus mempertimbangkan:

1. access tier student
2. completion seluruh learning flow yang relevan

---

# 4. Admin Flow

## Entry Point

Admin mengakses sertifikat dari:

- **Student Progress → pilih student → Certificate**

## 4.1 Certificate Panel for Student

Pada halaman certificate milik satu student, admin harus bisa melihat:

- student name
- email
- access tier
- certificate eligibility
- daftar sertifikat yang berhak didapat
- status generate sertifikat

## 4.2 Available Actions

Admin hanya perlu aksi berikut:

- **Generate**
- **Regenerate**
- **Download**

## 4.3 Generate

Jika student eligible:

- admin dapat klik **Generate**
- sistem memilih template yang sesuai berdasarkan tier
- sistem merender nama student ke template
- sistem menghasilkan file PDF final
- sistem menyimpan hasil sertifikat milik student itu

## 4.4 Regenerate

Admin boleh klik **Regenerate**.

### Final Rule

Regenerate:

- **overwrite file lama**
- tidak membuat versi baru
- tidak membuat histori versi

## 4.5 Download

Admin dapat download PDF sertifikat yang sudah dihasilkan.

---

# 5. Student Flow

## Entry Point

Student membuka modul / menu **Certificate**.

## 5.1 Student Access

Student hanya dapat melihat sertifikat miliknya sendiri.

## 5.2 Student Action

Student hanya perlu:

- **Download PDF**

## 5.3 No Custom Preview Requirement

Tidak perlu halaman preview browser khusus.

Jika browser default membuka PDF saat download, itu boleh, tetapi sistem tidak perlu membuat UI preview khusus.

---

# 6. Output Format

## Final Rule

Hasil akhir sertifikat harus berupa:

- **PDF**

## Source

Template sumber tetap berupa:

- JPG

## Rendering Flow

1. sistem ambil JPG template
2. sistem menempatkan nama student pada template
3. sistem menghasilkan PDF final dari hasil render itu
4. PDF itulah yang disimpan dan diunduh

---

# 7. Student Name Rendering

## Final Rule

Saat generate, nama student harus otomatis masuk ke template.

## Placement Rule

Penempatan nama harus dibuat:

- **semirip mungkin dengan desain pada JPG**

## Important Note

Setiap template dapat punya konfigurasi berbeda untuk:

- posisi X/Y
- ukuran font
- warna font
- alignment

Karena:

- `1.jpg` dan `2.jpg` kemungkinan berbeda layout

---

# 8. Admin UI Requirements

## Student Progress → Student → Certificate

Halaman ini minimal menampilkan:

- Student Name
- Email
- Access Tier
- Eligibility Status
- Available Certificates

Contoh available certificates:

- Bikram Yoga Certificate
- Yoga Alliance Certificate

### Action Per Certificate

- Generate
- Regenerate
- Download

---

# 9. Student UI Requirements

## Student Certificate Page

Halaman student minimal menampilkan:

- daftar sertifikat yang sudah tersedia
- tombol download PDF

Jika belum ada sertifikat yang tersedia:

- tampilkan empty state yang jujur

Contoh pesan:

- `Your certificate is not available yet. Please contact the admin for further confirmation.`

---

# 10. Generate Rules

## Starter Kit

Generate:

- hanya `1.jpg`
- hasil = Bikram Yoga Certificate PDF

## Online

Generate:

- `1.jpg`
- `2.jpg`

## Master Class

Generate:

- `1.jpg`
- `2.jpg`

---

# 11. Completion Dependency

## Final Rule

Sistem tidak boleh mengizinkan generate jika student belum menyelesaikan seluruh flow learning yang relevan.

Admin boleh melihat status eligibility.
Jika belum eligible:

- tombol generate boleh dinonaktifkan
- atau sistem menolak generate dengan pesan yang jelas

---

# 12. Data Model Direction

Dokumen ini tidak mengunci schema final secara detail, tetapi arah entitasnya seperti ini:

## Certificate Template

Mewakili jenis template tetap.

Kemungkinan field:

- `name`
- `slug`
- `template_source`
- `tier_scope`
- `is_active`

## Student Certificate

Mewakili sertifikat final milik satu student.

Kemungkinan field:

- `user_id`
- `certificate_type`
- `template_name`
- `generated_file_path`
- `generated_at`
- `generated_by`
- `access_tier_id`

## Important Rule

Hasil generate harus terikat ke student tertentu.

---

# 13. Business Rules

1. Starter Kit hanya mendapat 1 sertifikat.
2. Online mendapat 2 sertifikat.
3. Master Class mendapat 2 sertifikat.
4. Admin harus generate secara manual.
5. Student tidak generate sendiri.
6. Student hanya download hasil final.
7. Regenerate overwrite file lama.
8. Hasil akhir harus PDF.
9. Nama student otomatis masuk ke template.
10. Generate hanya boleh jika completion flow student sudah selesai.

---

# 14. Out of Scope

Fitur berikut belum masuk scope:

- auto-generate saat student selesai
- certificate revoke/delete
- multi-version certificate history
- certificate preview UI khusus
- custom upload template oleh admin
- certificate email delivery
- certificate expiration

---

# 15. Final Summary

Modul Certificate final bekerja seperti ini:

- admin masuk ke:
    - **Student Progress → pilih student → Certificate**
- sistem cek tier student
- sistem cek apakah student sudah menyelesaikan seluruh alur modul di bawahnya
- jika eligible:
    - admin bisa Generate
    - admin bisa Regenerate
    - admin bisa Download
- sistem memakai template tetap:
    - `1.jpg` untuk Bikram Yoga Certificate
    - `2.jpg` untuk Yoga Alliance Certificate
- nama student otomatis masuk ke template
- hasil akhir sertifikat adalah PDF
- student hanya bisa membuka modul Certificate miliknya dan download PDF yang tersedia

Dokumen ini menjadi source of truth untuk implementasi domain Certificate Module.
