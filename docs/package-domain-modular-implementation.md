# Package Domain Modular Implementation
# YogaFX LMS

## Purpose

Dokumen ini menjadi modular implementation plan untuk perombakan domain commerce YogaFX dari model lama berbasis `AccessTier.price` menuju model baru berbasis `Package`.

Scope dokumen ini **hanya untuk perombakan Package domain dan migrasi checkout dari AccessTier pricing ke Package pricing**.

Installment PayPal Subscription, dynamic billing sampai 15 Januari, overdue H+3, dan lifecycle subscription **tidak dikerjakan dalam dokumen ini**. Fitur installment hanya disiapkan secara field/config dasar bila diperlukan, tetapi implementasi payment subscription dilakukan belakangan setelah Package domain stabil.

---

## 1. Core Architecture Decision

Keputusan arsitektur baru:

```text
AccessTier = entitlement / hak akses konten
Package    = commercial offer / produk pembayaran / harga / promo
```

### AccessTier setelah perombakan

`AccessTier` hanya menjadi acuan untuk:

- akses module
- akses lesson
- akses ebook
- akses course / video lecture
- level / hierarchy tier
- tier yang diberikan ke user setelah payment sukses

`AccessTier` tidak lagi menjadi source of truth untuk:

- harga
- currency
- public payment image
- public payment description
- package slug
- payment link utama
- installment config
- PayPal product/plan config

### Package setelah perombakan

`Package` menjadi source of truth untuk:

- produk yang dijual
- harga
- currency
- image public checkout
- description public checkout
- slug direct package
- enable/disable package
- assignment ke target `AccessTier`
- future installment config

---

## 2. Final Decisions Lock

Keputusan yang sudah dikunci:

1. Nama tabel baru adalah `packages`.
2. Payment attributes dipindahkan dari `access_tiers` ke `packages`.
3. Package dapat assign ke satu `AccessTier` atau `none`.
4. Package dengan `access_tier_id = null` berarti `none` dan tidak tersedia untuk checkout public.
5. Satu `AccessTier` hanya boleh di-assign oleh satu package aktif pada satu waktu.
6. Saat admin assign package baru ke tier tertentu, sistem boleh otomatis melepas package lama dari tier tersebut.
7. Link public lama tetap dijaga:
   - `/masterclass`
   - `/online`
   - `/starter-kit`
   - `/starterkit`
8. Direct package link baru wajib disediakan:
   - `/p/{package_slug}`
9. Package standard awal minimal:
   - `Masterclass Standard -> Masterclass`
   - `Online Standard -> Online`
   - `Starter-kit Standard -> Starter Kit`
10. Urutan pengerjaan: Package domain + checkout migration dulu, installment belakangan.

---

## 3. Implementation Guardrails

Selama mengerjakan perombakan ini:

- Jangan implement PayPal Subscriptions dulu.
- Jangan implement overdue H+3 dulu.
- Jangan implement dynamic installment calculator dulu.
- Jangan ubah Flutter/mobile.
- Jangan refactor learning/content access.
- Jangan hapus field lama `price`, `currency_code`, atau `payment_link` dari `access_tiers` pada fase awal.
- Jangan rusak one-time PayPal order/capture flow existing.
- Jangan ubah upgrade payment ke installment.
- Jangan memindahkan business truth ke frontend.
- Backend tetap menjadi source of truth.

---

## 4. Target Data Model

### 4.1 `packages`

Schema awal yang direkomendasikan:

```text
packages
- id
- access_tier_id nullable foreign key to access_tiers.id
- title string
- slug string unique
- description text nullable
- image nullable
- price decimal
- currency_code string(3)
- is_active boolean default true
- installment_enabled boolean default false
- billing_interval_unit nullable string
- billing_interval_count nullable integer
- fixed_billing_day nullable integer
- installment_deadline_month nullable integer
- installment_deadline_day nullable integer
- paypal_product_id nullable string
- paypal_plan_id nullable string
- metadata nullable json
- created_at
- updated_at
```

### 4.2 Field meaning

- `access_tier_id`: tier yang diberikan setelah payment sukses; nullable untuk `none`.
- `title`: nama package, contoh `Masterclass Standard`.
- `slug`: direct public slug, contoh `masterclass-standard`.
- `description`: copywriting package.
- `image`: image public checkout/package card.
- `price`: harga package.
- `currency_code`: currency package.
- `is_active`: package aktif secara admin.
- `installment_enabled`: hanya flag kesiapan package; implementation installment belakangan.
- `billing_interval_unit`: future installment config, contoh `MONTH`.
- `billing_interval_count`: future installment config, contoh `1`.
- `fixed_billing_day`: future installment config, contoh `15`.
- `installment_deadline_month`: future installment config, contoh `1`.
- `installment_deadline_day`: future installment config, contoh `15`.
- `paypal_product_id`: future provider mapping.
- `paypal_plan_id`: future provider mapping.
- `metadata`: ruang fleksibel untuk campaign/provider config.

---

## 5. Modular Execution Rules

Gunakan dokumen ini seperti playbook modular.

### Rule 1
Kerjakan satu module aktif pada satu waktu.

### Rule 2
Setiap module harus ditutup dengan verifikasi.

### Rule 3
Jika module sudah selesai dan lolos verifikasi, baru lanjut module berikutnya.

### Rule 4
Jangan lompat ke checkout migration sebelum Package foundation dan admin CRUD stabil.

### Rule 5
Jangan menghapus field lama dari `access_tiers` sampai seluruh flow checkout baru sudah terbukti stabil.

### Rule 6
Jika ada data lama, lakukan backfill/seed dengan aman dan idempotent.

---

# Module 1 — Package Foundation

## Objective

Membuat fondasi data model `Package` tanpa mengubah checkout behavior existing terlebih dahulu.

## Scope

Backend only.

## Main Scope

- migration `packages`
- model `Package`
- relasi `Package` ke `AccessTier`
- relasi dari `AccessTier` ke packages
- factory/seed dasar jika dibutuhkan
- default casts/fillable
- basic unit/feature test model relation

## Likely Touched Areas

- `database/migrations/*_create_packages_table.php`
- `app/Models/Package.php`
- `app/Models/AccessTier.php`
- `database/factories/PackageFactory.php` jika test memakai factory
- `tests/Feature` atau `tests/Unit`

## Expected Artifacts

- tabel `packages`
- model `Package`
- relasi package-tier
- test minimal relasi package ke tier

## Definition of Done

- migration berjalan sukses
- model dapat create/update package
- package bisa punya `access_tier_id` atau `null`
- relasi `Package::accessTier()` berjalan
- relasi dari `AccessTier` ke package(s) berjalan
- tidak ada perubahan behavior checkout existing

## Verification

- `php artisan migrate`
- test create package dengan tier
- test create package dengan `access_tier_id = null`
- test relasi package ke access tier
- test existing payment masih lolos bila test suite dijalankan

## Non-Goals

- belum membuat admin CRUD
- belum mengubah public link
- belum mengubah checkout
- belum implement installment subscription

---

# Module 2 — Package Assignment Service

## Objective

Membuat service/domain rule agar satu `AccessTier` hanya di-assign oleh satu package aktif pada satu waktu.

## Scope

Backend domain service.

## Main Scope

- service untuk assign package ke tier
- auto-unassign package lama dari tier yang sama
- support assign package ke `none`
- DB transaction untuk assignment
- validation rule agar package inactive/invalid tidak menyebabkan state rusak

## Recommended Service

```text
PackageAssignmentService
```

## Behaviour

### Assign package to tier

Saat package A di-assign ke tier X:

```text
1. mulai DB transaction
2. cari semua package lain yang access_tier_id = X
3. set package lain access_tier_id = null
4. set package A access_tier_id = X
5. commit transaction
```

### Assign package to none

Saat package A di-set ke none:

```text
package.access_tier_id = null
```

## Likely Touched Areas

- `app/Services/PackageAssignmentService.php`
- `app/Models/Package.php`
- tests untuk assignment service

## Expected Artifacts

- service assignment
- test auto-unassign
- test assign none
- test one tier only one package assigned

## Definition of Done

- assign package baru ke tier otomatis melepas package lama
- package bisa dilepas ke none
- tidak ada dua package assigned ke tier yang sama melalui service
- logic dibungkus transaction

## Verification

Test scenario:

```text
Masterclass Standard -> Masterclass
Masterclass Easter   -> none

Action:
assign Masterclass Easter -> Masterclass

Expected:
Masterclass Standard -> none
Masterclass Easter   -> Masterclass
```

## Non-Goals

- belum membuat UI admin
- belum mengubah checkout resolver
- belum direct package route

---

# Module 3 — Default Package Backfill / Seeder

## Objective

Membuat default package awal dari tier existing agar sistem punya package standard untuk checkout.

## Scope

Data migration/seeder.

## Main Scope

Buat package standard minimal:

```text
Masterclass Standard -> Masterclass
Online Standard      -> Online
Starter-kit Standard -> Starter Kit
```

Source awal:

- `access_tiers.name`
- `access_tiers.slug`
- `access_tiers.price`
- `access_tiers.currency_code`
- `access_tiers.payment_link` bila masih dibutuhkan sebagai reference
- thumbnail/description bila tersedia

## Important Rule

Backfill harus idempotent.

Jika seeder dijalankan dua kali, tidak boleh membuat duplicate package.

## Likely Touched Areas

- `database/seeders/PackageSeeder.php`
- `database/seeders/DatabaseSeeder.php`
- atau data migration khusus bila lebih sesuai

## Expected Artifacts

- seeder default packages
- package standard terbentuk dari access tiers existing
- slug package standard konsisten

## Suggested Slugs

```text
masterclass-standard
online-standard
starter-kit-standard
```

## Definition of Done

- default package dibuat untuk tier utama yang tersedia
- setiap default package assign ke tier yang benar
- package price/currency mengikuti data lama access tier
- seeder aman dijalankan ulang

## Verification

- jalankan seeder pada database dev
- cek package count minimal 3
- cek package assignment ke tier benar
- cek tidak duplicate setelah seeder dijalankan ulang

## Non-Goals

- belum migrasi checkout
- belum membuat promo package otomatis
- belum hapus field lama access tier

---

# Module 4 — Admin Package CRUD

## Objective

Menyediakan fitur admin untuk mengelola packages secara manual.

## Scope

Admin backend + React/Inertia admin UI.

## Main Scope

- package index
- package create
- package edit
- package update
- package delete/deactivate
- image upload/change
- price/currency input
- package active flag
- installment enabled flag
- billing config fields sebagai future config
- assign package to tier or none
- auto-unassign existing package when assigning a tier

## Admin Form Fields

Minimal:

```text
Title
Slug
Description
Image
Price
Currency
Is Active
Installment Enabled
Billing Interval Unit
Billing Interval Count
Fixed Billing Day
Installment Deadline Month
Installment Deadline Day
Assign to Access Tier:
  - None
  - Starter Kit
  - Online
  - Masterclass
```

## Likely Touched Areas

- `routes/web.php`
- `app/Http/Controllers/Admin/PackageController.php`
- `app/Http/Requests/*Package*Request.php`
- `resources/js/Pages/Admin/Packages/*`
- `resources/js/Components` jika perlu
- admin navigation/sidebar
- storage/upload handling if image uses existing pattern
- tests admin package CRUD

## Expected Artifacts

- admin route package
- controller package CRUD
- validation request
- React/Inertia pages package index/create/edit
- sidebar/menu entry if needed
- tests create/update/assign package

## Definition of Done

- admin bisa melihat package list
- admin bisa create package
- admin bisa edit package
- admin bisa upload/change image jika image required
- admin bisa assign package ke tier
- admin bisa set package ke none
- saat assign package ke tier, package lama otomatis none
- package inactive tidak dipakai untuk public checkout resolver

## Verification

Manual QA:

```text
1. buka admin packages
2. create Masterclass Easter dengan access tier none
3. edit Masterclass Easter -> assign Masterclass
4. cek Masterclass Standard otomatis none
5. edit Masterclass Easter -> none
6. cek tidak ada package assigned ke Masterclass
```

Automated test:

- admin can create package
- admin can update package
- admin can assign package to tier
- assigning package to tier unassigns old package
- non-admin cannot access package admin routes

## Non-Goals

- belum direct public checkout dari package
- belum migrasi payment creation
- belum PayPal Subscription

---

# Module 5 — Public Package Resolution

## Objective

Mengubah public entry agar link lama tetap hidup tetapi resolve ke package aktif yang sedang assign ke tier terkait.

## Scope

Public route/backend resolver first, UI minimal mengikuti existing public product pages.

## Main Scope

- pertahankan route lama:
  - `/masterclass`
  - `/online`
  - `/starter-kit`
  - `/starterkit`
- buat resolver tier intent -> assigned package
- tambah direct package route:
  - `/p/{package_slug}`
- reject package inactive
- reject package `access_tier_id = null`
- tampilkan unavailable state jika tidak ada package aktif

## Recommended Service

```text
PackageResolverService
```

## Resolution Rules

### Legacy tier route

```text
/masterclass
↓
resolve AccessTier masterclass
↓
find active Package where access_tier_id = masterclass.id
↓
show public package registration/payment entry
```

### Direct package route

```text
/p/{package_slug}
↓
find package by slug
↓
require package.is_active = true
↓
require package.access_tier_id != null
↓
show public package registration/payment entry
```

## Likely Touched Areas

- `routes/web.php`
- `LeadRegistrationController`
- new `PackageResolverService`
- public product page props
- tests public route resolution

## Expected Artifacts

- legacy public routes continue working
- direct package route works
- resolver chooses package based on tier assignment
- unavailable state for no assigned package

## Definition of Done

- `/masterclass` opens package assigned to Masterclass
- `/online` opens package assigned to Online
- `/starter-kit` opens package assigned to Starter Kit
- `/starterkit` remains alias/backward compatibility
- `/p/masterclass-standard` opens direct package if active and assigned
- package none cannot checkout
- inactive package cannot checkout

## Verification

Test cases:

```text
Given Masterclass Standard -> Masterclass
When GET /masterclass
Then page uses Masterclass Standard package
```

```text
Given Masterclass Easter -> Masterclass and Masterclass Standard -> none
When GET /masterclass
Then page uses Masterclass Easter package
```

```text
Given Masterclass Easter -> none
When GET /p/masterclass-easter
Then checkout is unavailable / forbidden / not found according to chosen UX
```

## Non-Goals

- belum mengubah invoice creation
- belum mengubah PayPal create order
- belum installment

---

# Module 6 — PendingRegistration Migrates to Package

## Objective

Mengubah lead/pending registration agar dibuat berdasarkan selected package, bukan langsung berdasarkan access tier pricing.

## Scope

Backend checkout preparation.

## Main Scope

- tambah `package_id` ke `pending_registrations`
- pertahankan `access_tier_id` sebagai entitlement snapshot
- amount snapshot berasal dari `packages.price`
- currency snapshot berasal dari `packages.currency_code`
- lead registration store menerima package resolved dari route
- backward compatibility untuk pending registration lama jika ada

## Recommended Schema Change

```text
pending_registrations
- package_id nullable FK packages.id
- currency_code nullable string(3) or currency_code_snapshot
```

Catatan:

- `access_tier_id` tetap ada.
- `amount_snapshot` tetap ada, tetapi sumbernya berubah ke `packages.price`.
- Pilih nama currency field mengikuti existing convention repo.

## Likely Touched Areas

- migration add package fields to pending registrations
- `app/Models/PendingRegistration.php`
- `PaymentCheckoutService::createPendingRegistration(...)`
- `LeadRegistrationController::store(...)`
- tests public lead registration

## Expected Artifacts

- pending registration menyimpan package id
- pending registration menyimpan target access tier id
- pending registration amount/currency snapshot dari package
- existing signed checkout masih bisa dibuka

## Definition of Done

- public lead via `/masterclass` membuat pending registration dengan `package_id = active package`
- `access_tier_id` tetap target tier dari package
- `amount_snapshot = package.price`
- currency snapshot = package currency
- pending registration lama tanpa package tetap tidak menyebabkan fatal error bila masih ada data lama

## Verification

- test lead registration via legacy route
- test lead registration via `/p/{package_slug}`
- test package none tidak bisa membuat pending registration
- test inactive package tidak bisa membuat pending registration

## Non-Goals

- belum final migrate invoice/payment
- belum PayPal subscription

---

# Module 7 — Checkout Payload Migrates to Package

## Objective

Mengubah checkout page/payload agar menampilkan data package sebagai product commercial offer.

## Scope

Backend payload + frontend display minimal.

## Main Scope

Checkout payload harus membaca dari package:

- title
- price
- currency
- image
- description
- installment enabled flag as display/future config

Access tier tetap digunakan untuk target entitlement.

## Likely Touched Areas

- `PaymentCheckoutService::checkoutPayload(...)`
- `CheckoutController::show(...)`
- `resources/js/Pages/Public/Checkout.jsx`
- `resources/js/Components/public/PublicCheckoutPanel.jsx`
- tests checkout payload

## Expected Artifacts

- checkout page menampilkan package title/price/currency/image
- tidak lagi menampilkan harga dari `AccessTier.price`
- access tier tetap tampil bila dibutuhkan sebagai program/access label

## Definition of Done

- checkout amount berasal dari package
- checkout currency berasal dari package
- checkout title/description/image berasal dari package
- signed checkout existing tetap bekerja
- one-time PayPal button tetap muncul dan berjalan seperti sebelumnya

## Verification

Manual QA:

```text
1. assign Masterclass Easter -> Masterclass, price 250 USD
2. buka /masterclass
3. lanjut checkout
4. checkout harus menampilkan 250 USD dari Masterclass Easter
```

Automated tests:

- checkout payload uses package amount
- checkout payload uses package currency
- checkout still includes access tier target

## Non-Goals

- belum mengubah PayPal order creation jika masih bisa memakai invoice total later
- belum installment calculator
- belum subscription

---

# Module 8 — Invoice Creation Migrates to Package

## Objective

Mengubah invoice creation agar invoice menyimpan package snapshot dan amount/currency dari package.

## Scope

Backend payment domain.

## Main Scope

- tambah `package_id` ke `invoices`
- invoice total dari package price
- invoice currency dari package currency
- invoice access tier dari package target tier
- one-time PayPal order tetap pakai invoice total/currency
- finalizer tetap assign user ke `access_tier_id`

## Recommended Schema Change

```text
invoices
- package_id nullable FK packages.id
```

## Important Rule

Invoice tetap menyimpan `access_tier_id`.

Alasan:

```text
package_id = produk/harga yang dibeli
access_tier_id = entitlement yang diberikan setelah payment sukses
```

## Likely Touched Areas

- migration add package_id to invoices
- `app/Models/Invoice.php`
- `PaymentCheckoutService::startInitialCheckout(...)`
- `PaymentFinalizerService::finalizeSuccessfulPayment(...)` if needed
- `PayPalService::createOrder(...)` if it displays item name/description
- payment tests

## Expected Artifacts

- invoice menyimpan package id
- invoice total amount dari package price
- invoice currency dari package currency
- payment activity currency mengikuti invoice
- user tetap mendapat access tier dari invoice/package

## Definition of Done

- create order dari package menghasilkan invoice dengan package id
- invoice total sesuai package price
- payment activity amount sesuai selected payment type behavior existing
- finalizer tetap membuat/menghubungkan user dan assign tier yang benar
- one-time PayPal capture regression tetap lolos

## Verification

Test cases:

```text
Given package Masterclass Easter price 250 USD -> Masterclass
When checkout pay_full
Then invoice.package_id = Masterclass Easter
And invoice.access_tier_id = Masterclass
And invoice.total_amount = 250
And invoice.currency_code = USD
```

```text
When payment success
Then user.access_tier_id = Masterclass
```

## Non-Goals

- belum recurring subscription
- belum overdue scheduler
- belum upgrade package migration kecuali dibutuhkan untuk compile

---

# Module 9 — Public Checkout Regression and Backward Compatibility

## Objective

Memastikan perubahan package tidak merusak checkout one-time payment existing dan public marketing links.

## Scope

Testing and compatibility.

## Main Scope

- legacy routes still work
- direct package route works
- one-time PayPal create/capture still works
- pending registration flow works
- onboarding continuation works
- package none/inactive blocked
- old access tier price fields not removed yet

## Likely Touched Areas

- `tests/Feature/PublicPaymentLinkTest.php`
- `tests/Feature/PaymentAndModuleCompletionTest.php`
- new package checkout tests

## Expected Artifacts

- regression tests for old links
- regression tests for one-time PayPal flow
- package-specific tests

## Definition of Done

- `/masterclass`, `/online`, `/starter-kit`, `/starterkit` all resolve correctly
- `/p/{package_slug}` works for valid package
- inactive/none package cannot checkout
- pay_full one-time checkout still reaches onboarding
- existing tests still pass or are intentionally updated

## Verification

Run relevant tests:

```text
php artisan test --filter=PublicPaymentLinkTest
php artisan test --filter=PaymentAndModuleCompletionTest
```

Run package-specific tests added during implementation.

## Non-Goals

- no PayPal Subscriptions
- no installment scheduler
- no mobile integration

---

# Module 10 — Documentation Update

## Objective

Update project docs to reflect the new Package commerce layer.

## Scope

Documentation only.

## Main Scope

Update relevant docs after code behavior is confirmed:

- current project status
- PRD
- ERD
- user flow
- payment backend foundation
- PayPal payment architecture if needed
- package-specific doc if preferred

## Suggested New Doc

```text
docs/package-commerce-domain.md
```

## Expected Artifacts

- docs explain AccessTier vs Package separation
- docs explain public link strategy
- docs explain checkout now uses Package pricing
- docs mark AccessTier payment fields as deprecated if still present

## Definition of Done

- docs match actual code
- future Codex sessions understand package domain
- no doc claims AccessTier is source of truth for price/currency after migration

## Non-Goals

- no implementation changes
- no installment implementation doc beyond noting it is future phase

---

## Recommended Overall Sequence

```text
1. Package Foundation
2. Package Assignment Service
3. Default Package Backfill / Seeder
4. Admin Package CRUD
5. Public Package Resolution
6. PendingRegistration Migrates to Package
7. Checkout Payload Migrates to Package
8. Invoice Creation Migrates to Package
9. Public Checkout Regression and Backward Compatibility
10. Documentation Update
```

This sequence intentionally delays installment.

---

## Suggested Codex Prompt for Module 1

```text
Read docs/package-domain-modular-implementation.md and docs/package-installment-context-lock.md.

Implement Module 1 only: Package Foundation.

Scope:
- create packages migration
- create Package model
- add relations to AccessTier
- add basic factory/test if project convention supports it

Do not build admin CRUD yet.
Do not modify checkout yet.
Do not implement installment.
Do not remove price/currency/payment_link from access_tiers yet.
Keep existing one-time PayPal payment working.

After implementation, report:
- files changed
- migration created
- model relations added
- tests run
- any risks or follow-up needed
```

---

## Suggested Codex Prompt for Module 2

```text
Continue from Module 1.

Implement Module 2 only: Package Assignment Service.

Scope:
- create PackageAssignmentService
- support assign package to AccessTier
- support assign package to none
- when assigning package to a tier, auto-unassign any other package currently assigned to that tier
- wrap assignment in DB transaction
- add tests for reassignment behavior

Do not build admin CRUD yet.
Do not modify checkout yet.
Do not implement installment.
```

---

## Suggested Codex Prompt for Module 3

```text
Continue from Module 2.

Implement Module 3 only: Default Package Backfill / Seeder.

Scope:
- create idempotent PackageSeeder or equivalent data migration
- create standard packages from existing access tiers:
  - Masterclass Standard
  - Online Standard
  - Starter-kit Standard
- copy price/currency from existing access_tiers into packages
- assign each standard package to the correct access tier

Do not modify checkout yet.
Do not implement installment.
```

---

## Suggested Codex Prompt for Module 4

```text
Continue from Module 3.

Implement Module 4 only: Admin Package CRUD.

Scope:
- add admin routes/controller/request/pages for Package CRUD
- include assign to tier or none
- use PackageAssignmentService for assignment changes
- ensure assigning package to a tier auto-unassigns old package
- include validation and tests

Do not modify public checkout yet.
Do not implement installment.
```

---

## Final Summary

Perombakan ini harus dilakukan sebagai migrasi domain commerce yang aman:

```text
Old:
AccessTier = access + price + currency + payment link

New:
AccessTier = access entitlement only
Package    = price + currency + public offer + checkout source
```

Installment tetap penting, tetapi baru masuk setelah:

1. Package domain stabil
2. public links resolve ke package
3. pending registration menyimpan package
4. invoice menyimpan package
5. one-time checkout regression aman
