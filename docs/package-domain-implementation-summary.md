# Package Domain Implementation Summary

Dokumen ini merangkum implementasi `Package` domain yang sudah masuk ke kode YogaFX LMS.

Dokumen ini bukan roadmap baru. Fungsinya adalah:

- menjelaskan apa yang sudah benar-benar terimplementasi
- menandai boundary yang masih future scope
- memudahkan phase installment membaca kondisi commerce terbaru

## 1. Ringkasan Arsitektur

Commerce layer sekarang sudah dipisahkan menjadi:

- `AccessTier` = entitlement / hak akses konten
- `Package` = commercial offer / source of truth harga checkout public

Implementasi yang sudah masuk:

- tabel `packages`
- relasi `Package -> AccessTier`
- auto-unassign package lama saat package baru di-assign ke tier yang sama
- standard package seeding
- admin CRUD package
- public route lama resolve ke package aktif berdasarkan tier
- direct package route `/p/{package_slug}`
- `pending_registrations` menyimpan `package_id`
- `invoices` menyimpan `package_id`
- initial checkout membaca amount dan currency dari `Package`

## 2. File dan Layer Utama

### Data layer

- [database/migrations/2026_06_25_100000_create_packages_table.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/database/migrations/2026_06_25_100000_create_packages_table.php)
- [database/migrations/2026_06_25_100100_add_package_fields_to_pending_registrations_table.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/database/migrations/2026_06_25_100100_add_package_fields_to_pending_registrations_table.php)
- [database/migrations/2026_06_25_100200_add_package_id_to_invoices_table.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/database/migrations/2026_06_25_100200_add_package_id_to_invoices_table.php)

### Models

- [app/Models/Package.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Models/Package.php)
- [app/Models/AccessTier.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Models/AccessTier.php)
- [app/Models/PendingRegistration.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Models/PendingRegistration.php)
- [app/Models/Invoice.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Models/Invoice.php)

### Services

- [app/Services/PackageAssignmentService.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Services/PackageAssignmentService.php)
- [app/Services/PackageResolverService.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Services/PackageResolverService.php)
- [app/Services/PaymentCheckoutService.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Services/PaymentCheckoutService.php)

### Controllers / requests

- [app/Http/Controllers/Admin/PackageController.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Http/Controllers/Admin/PackageController.php)
- [app/Http/Controllers/LeadRegistrationController.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Http/Controllers/LeadRegistrationController.php)
- [app/Http/Controllers/CheckoutController.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Http/Controllers/CheckoutController.php)
- [app/Http/Requests/Admin/PackageRequest.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Http/Requests/Admin/PackageRequest.php)
- [app/Http/Requests/LeadRegistrationRequest.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Http/Requests/LeadRegistrationRequest.php)

### Frontend

- [resources/js/Pages/Admin/Packages/Index.jsx](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/resources/js/Pages/Admin/Packages/Index.jsx)
- [resources/js/Pages/Admin/Packages/Create.jsx](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/resources/js/Pages/Admin/Packages/Create.jsx)
- [resources/js/Pages/Admin/Packages/Edit.jsx](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/resources/js/Pages/Admin/Packages/Edit.jsx)
- [resources/js/Components/PackageForm.jsx](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/resources/js/Components/PackageForm.jsx)
- [resources/js/Pages/Public/Scoreboard.jsx](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/resources/js/Pages/Public/Scoreboard.jsx)
- [resources/js/Pages/Public/Checkout.jsx](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/resources/js/Pages/Public/Checkout.jsx)

### Tests

- [tests/Feature/PackageDomainTest.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/tests/Feature/PackageDomainTest.php)
- [tests/Feature/PublicPaymentLinkTest.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/tests/Feature/PublicPaymentLinkTest.php)
- [tests/Feature/PaymentAndModuleCompletionTest.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/tests/Feature/PaymentAndModuleCompletionTest.php)

## 3. Package Domain yang Sudah Jalan

### 3.1 Package foundation

`packages` sudah menyimpan:

- `access_tier_id`
- `title`
- `slug`
- `description`
- `image`
- `price`
- `currency_code`
- `is_active`
- `installment_enabled`
- billing config fields untuk future installment
- provider plan/product placeholders

### 3.2 Assignment rule

Business rule yang sudah hidup:

- package boleh assign ke satu tier atau `none`
- assign package ke tier baru akan melepas package lama dari tier yang sama

Ini dijalankan oleh `PackageAssignmentService`.

### 3.3 Default package seed

Seeder standard package sudah tersedia:

- `masterclass-standard`
- `online-standard`
- `starter-kit-standard`

Seeder bersifat idempotent.

### 3.4 Admin CRUD

Admin sekarang bisa:

- melihat list packages
- membuat package
- edit package
- assign package ke tier atau `none`
- delete package jika belum punya history checkout

### 3.5 Public route resolution

Public route lama tetap hidup:

- `/masterclass`
- `/online`
- `/starter-kit`
- `/starterkit`

Route tersebut sekarang resolve ke package aktif yang assign ke tier terkait.

Direct route baru juga tersedia:

- `/p/{package_slug}`

Package yang inactive atau `access_tier_id = null` tidak tersedia untuk checkout public.

## 4. Checkout Migration yang Sudah Jalan

### 4.1 Lead registration

Public lead sekarang divalidasi terhadap `package_id`, bukan `access_tier_id`.

`LeadRegistrationRequest` akan:

- resolve package dari route locked
- merge `package_id` ke request
- menolak package inactive atau package tanpa tier

### 4.2 Pending registration snapshot

`pending_registrations` sekarang menyimpan:

- `package_id`
- `access_tier_id`
- `amount_snapshot`
- `currency_code`

Snapshot amount dan currency berasal dari package.

### 4.3 Invoice snapshot

`invoices` sekarang menyimpan:

- `package_id`
- `access_tier_id`
- `total_amount`
- `currency_code`

Initial checkout memakai:

- `Package.price`
- `Package.currency_code`

### 4.4 Entitlement tetap dari tier

Walaupun checkout membaca package, hasil akhir payment success tetap:

- user diberi `access_tier_id` dari package target
- onboarding tetap memakai flow existing
- one-time PayPal order/capture flow tetap dipertahankan

## 5. Boundary yang Sengaja Belum Dikerjakan

Masih future scope:

- PayPal Subscriptions
- installment calculator dinamis
- overdue H+3
- auto inactive/reactive karena cicilan
- subscription webhook lifecycle
- email notification installment
- upgrade installment
- mobile Flutter changes

## 6. Dampak ke Phase Installment

Phase installment sekarang harus membaca:

- `Package.price`
- `Package.currency_code`
- `Package.installment_enabled`
- billing config di `Package`

Bukan lagi membaca:

- `AccessTier.price`
- `AccessTier.currency_code`

Ini menjadi prasyarat utama sebelum membangun:

- `payment_subscriptions`
- `payment_subscription_events`
- installment calculator
- PayPal subscription provider layer

## 7. Test Status Saat Summary Ini Dibuat

Test yang sudah diverifikasi:

- `php artisan test --filter=PackageDomainTest`
- `php artisan test --filter=PublicPaymentLinkTest`
- `php artisan test --filter=PaymentAndModuleCompletionTest`

Semua lolos saat implementasi package domain selesai.

## 8. Kesimpulan

Package domain sudah aktif sebagai commerce layer checkout public.

Arsitektur yang berlaku sekarang:

- `AccessTier` = entitlement
- `Package` = pricing/public offer
- `PendingRegistration` = snapshot package + tier target
- `Invoice` = snapshot package + tier target
- `Payment` / `payment_activities` = ledger pembayaran

Phase berikutnya yang aman adalah memulai **installment schema foundation**, bukan mengubah lagi package domain.
