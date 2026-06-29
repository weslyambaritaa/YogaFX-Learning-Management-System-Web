# Installment Payment Audit

## 1. Ringkasan kondisi payment saat ini

Arsitektur payment aktif saat ini adalah **PayPal one-time order flow**, bukan subscription flow.

- Public lead masuk dari scoreboard atau product payment link, lalu dibuat `pending_registrations`.
- Backend membuat `invoices` sebagai tagihan induk.
- Backend membuat `payment_activities` sebagai ledger per percobaan / pembayaran.
- Untuk PayPal, backend membuat **PayPal Order** lewat `PayPalService::createOrder(...)`.
- Checkout public aktif memakai **PayPal JS SDK Buttons** dan memanggil backend `create order` lalu `capture`.
- Setelah capture sukses, `PaymentFinalizerService` mengubah status invoice, membuat/menghubungkan user, membuat `onboarding_states`, dan mengirim continuation email onboarding.
- Upgrade payment juga memakai fondasi yang sama, tetapi jalurnya masih **redirect-like flow** dari halaman student upgrade, bukan embedded onsite checkout seperti public checkout.

Kesimpulan audit: fondasi `invoice + payment ledger + finalizer` sudah cukup baik untuk diperluas, tetapi **belum ada lifecycle subscription/installment canonical** di backend. Status `installment` yang ada sekarang hanya berarti **invoice masih punya `balance_due`**, belum berarti sistem punya recurring schedule, due date, retry policy, atau webhook subscription state.

## 2. Route payment/checkout yang aktif

Sumber utama: [routes/web.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/routes/web.php)

### Public lead + checkout entry

| Method | Path | Route Name | Controller | Method | Middleware penting |
|---|---|---|---|---|---|
| `GET` | `/scoreboard` | `lead-registration.create` | `App\Http\Controllers\LeadRegistrationController` | `create` | web default |
| `POST` | `/scoreboard` | `lead-registration.store` | `App\Http\Controllers\LeadRegistrationController` | `store` | web default |
| `GET` | `/scoreboard/submitted/{pendingRegistration}` | `lead-registration.submitted` | `App\Http\Controllers\LeadRegistrationController` | `submitted` | web default |
| `GET` | `/online` | `lead-registration.products.online` | `App\Http\Controllers\LeadRegistrationController` | `showProductPaymentLink` | web default |
| `POST` | `/online` | `lead-registration.products.online.store` | `App\Http\Controllers\LeadRegistrationController` | `store` | web default |
| `GET` | `/starter-kit` | `lead-registration.products.starter-kit` | `App\Http\Controllers\LeadRegistrationController` | `showProductPaymentLink` | web default |
| `POST` | `/starter-kit` | `lead-registration.products.starter-kit.store` | `App\Http\Controllers\LeadRegistrationController` | `store` | web default |
| `GET` | `/starterkit` | none | `App\Http\Controllers\LeadRegistrationController` | `showProductPaymentLink` | web default |
| `POST` | `/starterkit` | none | `App\Http\Controllers\LeadRegistrationController` | `store` | web default |
| `GET` | `/masterclass` | `lead-registration.products.masterclass` | `App\Http\Controllers\LeadRegistrationController` | `showProductPaymentLink` | web default |
| `POST` | `/masterclass` | `lead-registration.products.masterclass.store` | `App\Http\Controllers\LeadRegistrationController` | `store` | web default |

### Signed checkout flow

| Method | Path | Route Name | Controller | Method | Middleware penting |
|---|---|---|---|---|---|
| `GET` | `/checkout/{pendingRegistration}/{accessTierSlug}` | `checkout.show` | `App\Http\Controllers\CheckoutController` | `show` | `signed` |
| `POST` | `/checkout/{pendingRegistration}/{accessTierSlug}/pay` | `checkout.pay` | `App\Http\Controllers\CheckoutController` | `pay` | `signed` |
| `POST` | `/checkout/{pendingRegistration}/{accessTierSlug}/orders` | `checkout.orders.store` | `App\Http\Controllers\CheckoutController` | `createOrder` | `signed` |
| `POST` | `/checkout/{pendingRegistration}/{accessTierSlug}/orders/{invoice}/capture` | `checkout.orders.capture` | `App\Http\Controllers\CheckoutController` | `captureOrder` | `signed` |
| `POST` | `/checkout/{pendingRegistration}/{accessTierSlug}/orders/{invoice}/cancel` | `checkout.orders.cancel` | `App\Http\Controllers\CheckoutController` | `cancelOrder` | `signed` |
| `GET` | `/checkout/invoices/{invoice}/status` | `checkout.status` | `App\Http\Controllers\CheckoutController` | `status` | `signed` |

### PayPal return + webhook

| Method | Path | Route Name | Controller | Method | Middleware penting |
|---|---|---|---|---|---|
| `GET` | `/paypal/checkout/{invoice}/success` | `paypal.success` | `App\Http\Controllers\PayPalCheckoutController` | `success` | web default |
| `GET` | `/paypal/checkout/{invoice}/cancel` | `paypal.cancel` | `App\Http\Controllers\PayPalCheckoutController` | `cancel` | web default |
| `POST` | `/webhooks/paypal` | `paypal.webhook` | `App\Http\Controllers\PayPalWebhookController` | `__invoke` | CSRF dikecualikan |

### Onboarding continuation

| Method | Path | Route Name | Controller | Method | Middleware penting |
|---|---|---|---|---|---|
| `GET` | `/onboarding/{onboardingState}/payment-success` | `onboarding.payment-success.show` | `App\Http\Controllers\OnboardingController` | `showPaymentSuccess` | `signed` |
| `GET` | `/onboarding/{onboardingState}/enrollment` | `onboarding.enrollment.show` | `App\Http\Controllers\OnboardingController` | `showEnrollment` | `signed` |
| `POST` | `/onboarding/{onboardingState}/enrollment` | `onboarding.enrollment.store` | `App\Http\Controllers\OnboardingController` | `storeEnrollment` | `signed` |
| `GET` | `/onboarding/{onboardingState}/signup` | `onboarding.signup.show` | `App\Http\Controllers\OnboardingController` | `showSignup` | `signed` |
| `POST` | `/onboarding/{onboardingState}/signup` | `onboarding.signup.store` | `App\Http\Controllers\OnboardingController` | `storeSignup` | `signed` |

### Upgrade checkout

| Method | Path | Route Name | Controller | Method | Middleware penting |
|---|---|---|---|---|---|
| `GET` | `/upgrades/{accessTier}` | `student.upgrades.show` | `App\Http\Controllers\Student\UpgradeController` | `show` | `auth`, `role:student`, `student.active`, `track.student.session` |
| `POST` | `/upgrades/{accessTier}` | `student.upgrades.pay` | `App\Http\Controllers\Student\UpgradeController` | `pay` | `auth`, `role:student`, `student.active`, `track.student.session` |
| `GET` | `/upgrades/{invoice}/payment-success` | `student.upgrades.success` | `App\Http\Controllers\Student\UpgradeController` | `success` | `signed` |

## 3. Controller payment yang relevan

### `CheckoutController`

- File: [app/Http/Controllers/CheckoutController.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Http/Controllers/CheckoutController.php)
- Method penting:
  - `show(...)`
    - Memastikan `pendingRegistration` cocok dengan `accessTierSlug`.
    - Redirect ke continuation bila payment sudah sukses.
    - Menandai checkout dibuka.
    - Menyiapkan payload checkout + PayPal client config.
    - Efek DB: bisa mengubah `pending_registrations.status` ke `checkout_opened`.
  - `pay(...)`
    - Legacy path; sekarang praktis hanya melayani mock payment.
    - Efek DB: bila mock, membuat invoice/payment lewat service.
  - `createOrder(...)`
    - Endpoint backend utama untuk embedded checkout.
    - Membuat invoice + payment activity melalui `PaymentCheckoutService`.
    - Mengembalikan `order_id`, `capture_url`, `cancel_url`.
    - Efek DB: membuat `invoices` dan `payment_activities`.
  - `captureOrder(...)`
    - Memvalidasi `order_id`, mengambil payment activity berdasarkan `payment_reference`, lalu memanggil `PayPalService::captureOrder(...)`.
    - Bila `COMPLETED`, memanggil finalizer.
    - Bila `PENDING`, payment tetap pending.
    - Bila gagal, payment ditandai failed.
    - Efek DB: update `payment_activities`, `invoices`, `users`, `pending_registrations`, `onboarding_states`.
  - `cancelOrder(...)`
    - Menandai payment pending sebagai cancelled.
    - Efek DB: update `payment_activities.status`.
  - `status(...)`
    - Menampilkan status pending/failed invoice public checkout.
    - Redirect ke success continuation bila invoice sudah `paid_full` atau `installment`.

### `PayPalCheckoutController`

- File: [app/Http/Controllers/PayPalCheckoutController.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Http/Controllers/PayPalCheckoutController.php)
- Method penting:
  - `success(...)`
    - Mengambil payment by `token` query string.
    - Capture order bila payment belum sukses.
    - Memanggil `PaymentFinalizerService::finalizeSuccessfulPayment(...)`.
    - Redirect ke onboarding success atau upgrade success.
    - Efek DB: sama seperti capture flow.
  - `cancel(...)`
    - Mengambil payment by `token`.
    - Menandai payment cancelled.
    - Redirect kembali ke checkout atau upgrade page.
    - Efek DB: update `payment_activities.status`.

### `PayPalWebhookController`

- File: [app/Http/Controllers/PayPalWebhookController.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Http/Controllers/PayPalWebhookController.php)
- Method penting:
  - `__invoke(...)`
    - Verifikasi signature webhook.
    - Extract `event_type` dan `order_id`.
    - Cari `Payment` berdasarkan `payment_reference = order_id`.
    - Untuk `CHECKOUT.ORDER.APPROVED`, lakukan capture bila payment belum success.
    - Panggil finalizer.
    - Efek DB: bisa update `payment_activities`, `invoices`, `users`, `pending_registrations`, `onboarding_states`.

### `UpgradeController`

- File: [app/Http/Controllers/Student/UpgradeController.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Http/Controllers/Student/UpgradeController.php)
- Method penting:
  - `show(...)`
    - Menampilkan amount due upgrade berdasarkan riwayat payment relevan.
  - `pay(...)`
    - Membuat upgrade checkout lewat `PaymentCheckoutService::startUpgradeCheckout(...)`.
    - Efek DB: membuat `invoices` dan `payment_activities`.
  - `success(...)`
    - Menampilkan halaman sukses upgrade jika invoice upgrade `paid_full` atau `installment`.

### `OnboardingController`

- File: [app/Http/Controllers/OnboardingController.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Http/Controllers/OnboardingController.php)
- Method penting:
  - `showPaymentSuccess(...)`
    - Menentukan apakah user lanjut ke enrollment atau signup.
  - `showEnrollment(...)` / `storeEnrollment(...)`
    - Menangani pengisian profil onboarding.
    - Efek DB: update `users`, update `onboarding_states.status` ke `awaiting_signup`.
  - `showSignup(...)` / `storeSignup(...)`
    - Menangani set password dan aktivasi akun.
    - Efek DB: update `users.is_active = true`, update `onboarding_states.status = completed`, update `pending_registrations.status = completed`.

### `LeadRegistrationController`

- File: [app/Http/Controllers/LeadRegistrationController.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Http/Controllers/LeadRegistrationController.php)
- Method penting:
  - `create()` / `showProductPaymentLink(...)`
    - Menampilkan scoreboard/public payment link.
  - `store(...)`
    - Membuat pending registration, menandai checkout opened, lalu:
      - redirect ke signed checkout, atau
      - mengembalikan JSON `checkout_ready` untuk embedded public flow.
    - Efek DB: insert `pending_registrations`, update status ke `checkout_opened`.

## 4. Service payment yang relevan

### `PaymentCheckoutService`

- File: [app/Services/PaymentCheckoutService.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Services/PaymentCheckoutService.php)
- Method penting:
  - `createPendingRegistration(array $attributes): PendingRegistration`
  - `markCheckoutOpened(PendingRegistration $pendingRegistration): PendingRegistration`
  - `startInitialCheckout(PendingRegistration $pendingRegistration, array $attributes): array`
  - `startUpgradeCheckout(User $user, AccessTier $targetTier, array $attributes): array`
  - URL builder methods: `checkoutUrl`, `checkoutOrderCreateUrl`, `checkoutOrderCaptureUrl`, `checkoutStatusUrl`, `paymentSuccessUrl`, `upgradePaymentSuccessUrl`, dst.
  - `checkoutPayload(...)`
  - `completeEnrollment(...)`
  - `completeSignup(...)`
  - `relevantUpgradePaidAmount(...)`
- Input utama:
  - `payment_type`
  - `payment_method`
  - entity `PendingRegistration`, `User`, `AccessTier`, `OnboardingState`
- Return utama:
  - array berisi `invoice`, `payment_activity`, `redirect_url`
  - untuk upgrade juga `amount_due`
- Efek samping:
  - membuat `invoices`
  - membuat `payment_activities`
  - memanggil `PayPalService::createOrder(...)`
  - memanggil `PaymentFinalizerService` untuk mock flow
  - update `users`, `onboarding_states`, `pending_registrations` saat enrollment/signup
- Service lain yang dipanggil:
  - `InvoiceNumberService`
  - `PayPalService`
  - `PaymentFinalizerService`

Catatan audit penting:

- `initialPaymentAmount(...)` saat ini:
  - `pay_full` => bayar penuh
  - `installment` => `round(total / 4, 2)`
- Artinya installment existing masih hardcoded **4 kali**, belum dinamis sampai 15 Januari.

### `PaymentFinalizerService`

- File: [app/Services/PaymentFinalizerService.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Services/PaymentFinalizerService.php)
- Method penting:
  - `finalizeSuccessfulPayment(Payment $paymentActivity, ?string $paymentReference = null): array`
  - `cancelPendingPayment(Payment $paymentActivity): Payment`
  - `failPendingPayment(Payment $paymentActivity, ?string $notes = null): Payment`
  - `keepPaymentPending(Payment $paymentActivity, ?string $notes = null): Payment`
- Input utama:
  - `Payment`
  - optional `paymentReference`
- Return:
  - array berisi `invoice`, `payment_activity`, `user`, `onboarding_state`, `skipped`
- Efek samping:
  - lock row invoice/payment
  - update `payment_activities.status`
  - hitung `balance_due`
  - set `invoices.status` menjadi `installment` atau `paid_full`
  - membuat/menghubungkan `users`
  - update `pending_registrations.status`
  - membuat `onboarding_states`
  - dispatch `SendOnboardingContinuationEmailJob`
  - dispatch `SendUpgradeWelcomeEmailJob`
- Service lain yang dipanggil:
  - queue jobs, hash, DB transaction

Catatan audit penting:

- Idempotency guard sudah ada untuk:
  - invoice yang sudah `paid_full`
  - payment reference yang sudah sukses di activity lain
- Tetapi belum ada idempotency layer untuk subscription webhook event log terpisah.

### `PayPalService`

- File: [app/Services/PayPalService.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Services/PayPalService.php)
- Method penting:
  - `clientId(): string`
  - `generateClientToken(): string`
  - `createOrder(Invoice $invoice, Payment $paymentActivity, string $successUrl, string $cancelUrl): array`
  - `captureOrder(string $orderId): array`
  - `verifyWebhookSignature(array $payload, array $headers): bool`
  - `extractWebhookOrderReference(array $payload): array`
- Input utama:
  - invoice, payment, order id, webhook payload + headers
- Return utama:
  - order id + approval url
  - capture response
  - verification bool
  - extracted `event_type` + `order_id`
- Efek samping:
  - network call ke REST API PayPal
- Service lain yang dipanggil:
  - Laravel `Http` client

### `InvoiceNumberService`

- File: [app/Services/InvoiceNumberService.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Services/InvoiceNumberService.php)
- Method penting:
  - `nextNumber(): string`
- Input:
  - tidak ada argumen
- Return:
  - nomor format `INV-YYYY-####`
- Efek samping:
  - lock query invoice terbaru untuk mencegah collision

## 5. Model dan migration aktual

### `PendingRegistration`

- Model: [app/Models/PendingRegistration.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Models/PendingRegistration.php)
- Tabel fisik: `pending_registrations`
- Fillable penting:
  - `access_tier_id`, `first_name`, `last_name`, `email`, `phone`, `country`, `amount_snapshot`, `status`, `checkout_opened_at`, `payment_succeeded_at`, `completed_at`
- Casts penting:
  - `amount_snapshot`, timestamps
- Relation penting:
  - `accessTier`, `invoices`, `onboardingState`
- Migration utama:
  - [database/migrations/2026_06_16_090100_create_pending_registrations_table.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/database/migrations/2026_06_16_090100_create_pending_registrations_table.php)
- Kolom aktual:
  - `id`, `access_tier_id`, `first_name`, `last_name`, `email`, `phone`, `country`, `amount_snapshot`, `status`, `checkout_opened_at`, `payment_succeeded_at`, `completed_at`, timestamps
- Enum/status:
  - `created`, `checkout_opened`, `payment_success`, `completed`

### `Invoice`

- Model: [app/Models/Invoice.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Models/Invoice.php)
- Tabel fisik: `invoices`
- Fillable penting:
  - `invoice_number`, `pending_registration_id`, `user_id`, `access_tier_id`, `type`, `payment_type`, `total_amount`, `balance_due`, `currency_code`, `status`, `issued_at`, `paid_at`
- Casts penting:
  - amount decimals, `issued_at`, `paid_at`
- Relation penting:
  - `pendingRegistration`, `user`, `accessTier`, `payments`, `paymentActivities`
- Migration terkait:
  - [database/migrations/2026_06_16_090200_create_invoices_table.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/database/migrations/2026_06_16_090200_create_invoices_table.php)
  - [database/migrations/2026_06_18_090000_align_payment_currency_and_module_completion_foundation.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/database/migrations/2026_06_18_090000_align_payment_currency_and_module_completion_foundation.php)
  - [database/migrations/2026_06_19_120000_align_paypal_payment_architecture.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/database/migrations/2026_06_19_120000_align_paypal_payment_architecture.php)
- Kolom aktual hasil align:
  - `id`, `invoice_number`, `pending_registration_id`, `user_id`, `access_tier_id`, `type`, `payment_type`, `total_amount`, `balance_due`, `currency_code`, `status`, `issued_at`, `paid_at`, timestamps
- Enum/status:
  - type: `initial`, `upgrade`
  - status: `unpaid`, `paid_full`, `installment`, `upgraded`

### `Payment`

- Model: [app/Models/Payment.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Models/Payment.php)
- Tabel fisik: `payment_activities`
- Fillable penting:
  - `invoice_id`, `payment_method`, `payment_type`, `amount_paid`, `currency_code`, `status`, `payment_reference`, `notes`
- Casts penting:
  - `amount_paid`
- Relation penting:
  - `invoice`
- Migration terkait:
  - [database/migrations/2026_06_16_090300_create_payment_activities_table.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/database/migrations/2026_06_16_090300_create_payment_activities_table.php)
  - [database/migrations/2026_06_18_090000_align_payment_currency_and_module_completion_foundation.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/database/migrations/2026_06_18_090000_align_payment_currency_and_module_completion_foundation.php)
  - [database/migrations/2026_06_19_120000_align_paypal_payment_architecture.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/database/migrations/2026_06_19_120000_align_paypal_payment_architecture.php)
  - [database/migrations/2026_06_22_130000_repair_payment_activities_column_names.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/database/migrations/2026_06_22_130000_repair_payment_activities_column_names.php)
- Kolom aktual hasil align:
  - `id`, `invoice_id`, `payment_method`, `payment_type`, `amount_paid`, `currency_code`, `status`, `payment_reference`, `notes`, timestamps
- Enum/status:
  - method: `paypal`, `bank_transfer`, `mock`
  - type: `pay_full`, `installment`
  - status: `pending`, `success`, `failed`, `cancelled`

### `OnboardingState`

- Model: [app/Models/OnboardingState.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Models/OnboardingState.php)
- Tabel fisik: `onboarding_states`
- Fillable penting:
  - `pending_registration_id`, `user_id`, `status`, `continuation_sent_at`, `enrollment_completed_at`, `signup_completed_at`
- Casts penting:
  - datetime fields
- Relation penting:
  - `pendingRegistration`, `user`
- Migration utama:
  - [database/migrations/2026_06_16_090400_create_onboarding_states_table.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/database/migrations/2026_06_16_090400_create_onboarding_states_table.php)
- Kolom aktual:
  - `id`, `pending_registration_id`, `user_id`, `status`, `continuation_sent_at`, `enrollment_completed_at`, `signup_completed_at`, timestamps
- Enum/status:
  - `awaiting_enrollment`, `awaiting_signup`, `completed`

### `AccessTier`

- Model: [app/Models/AccessTier.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Models/AccessTier.php)
- Tabel fisik: `access_tiers`
- Fillable penting:
  - `name`, `slug`, `description`, `thumbnail`, `price`, `currency_code`, `level`, `is_active`, `payment_link`
- Casts penting:
  - `price`, `level`, `is_active`
- Relation penting:
  - `users`, `modules`, `lessons`, `ebooks`, `courses`
- Migration terkait:
  - [database/migrations/2026_06_09_090000_create_access_tiers_table.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/database/migrations/2026_06_09_090000_create_access_tiers_table.php)
  - [database/migrations/2026_06_18_090000_align_payment_currency_and_module_completion_foundation.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/database/migrations/2026_06_18_090000_align_payment_currency_and_module_completion_foundation.php)
  - [database/migrations/2026_06_22_120000_add_payment_link_to_access_tiers_table.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/database/migrations/2026_06_22_120000_add_payment_link_to_access_tiers_table.php)
- Kolom aktual penting:
  - `id`, `name`, `slug`, `payment_link`, `description`, `thumbnail`, `price`, `currency_code`, `level`, `is_active`, timestamps

### `User`

- Model: [app/Models/User.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Models/User.php)
- Tabel fisik: `users`
- Fillable penting untuk payment/onboarding:
  - `name`, `role`, `is_active`, `access_tier_id`, `email`, `password`, profile fields
- Casts penting:
  - `is_active`, `password`, `birth_date`
- Relation penting:
  - `accessTier`, `invoices`, `payments`, `paymentActivities`, `onboardingState`
- Migration terkait:
  - [database/migrations/0001_01_01_000000_create_users_table.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/database/migrations/0001_01_01_000000_create_users_table.php)
  - [database/migrations/2026_06_09_090100_add_access_tier_id_to_users_table.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/database/migrations/2026_06_09_090100_add_access_tier_id_to_users_table.php)
  - [database/migrations/2026_06_11_130000_add_is_active_to_users_table.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/database/migrations/2026_06_11_130000_add_is_active_to_users_table.php)
- Enum/status:
  - role: `admin`, `student`
  - student account active flag: `is_active` boolean

## 6. Klarifikasi tabel payment

Tabel fisik payment **saat ini adalah `payment_activities`**.

- Model `App\Models\Payment` secara eksplisit memetakan:
  - `protected $table = 'payment_activities';`
- Riwayat migration menunjukkan tabel sempat di-rename ke `payments`, lalu dikembalikan lagi ke `payment_activities`.
- Jadi source of truth implementasi sekarang:
  - **Model:** `Payment`
  - **Tabel fisik:** `payment_activities`

## 7. Status enum aktual

### Invoice

- type:
  - `initial`
  - `upgrade`
- status:
  - `unpaid`
  - `paid_full`
  - `installment`
  - `upgraded`
- payment_type:
  - `pay_full`
  - `installment`

### Payment

- method:
  - `paypal`
  - `bank_transfer`
  - `mock`
- status:
  - `pending`
  - `success`
  - `failed`
  - `cancelled`
- payment_type:
  - `pay_full`
  - `installment`

### PendingRegistration

- `created`
- `checkout_opened`
- `payment_success`
- `completed`

### OnboardingState

- `awaiting_enrollment`
- `awaiting_signup`
- `completed`

## 8. Flow one-time PayPal saat ini

Urutan aktual:

1. User submit lead dari `/scoreboard`, `/online`, `/starter-kit`, atau `/masterclass`.
   - File: [app/Http/Controllers/LeadRegistrationController.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Http/Controllers/LeadRegistrationController.php)
   - Method: `store(...)`
2. Backend membuat `PendingRegistration`.
   - File: [app/Services/PaymentCheckoutService.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Services/PaymentCheckoutService.php)
   - Method: `createPendingRegistration(...)`
3. Backend menandai checkout opened.
   - Method: `markCheckoutOpened(...)`
4. Frontend public checkout memanggil backend `POST /checkout/.../orders`.
   - File: [resources/js/Components/public/PublicCheckoutPanel.jsx](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/resources/js/Components/public/PublicCheckoutPanel.jsx)
   - Function: `createOrderSession(...)`
5. Backend membuat `Invoice`.
   - File: `PaymentCheckoutService`
   - Method: `startInitialCheckout(...)`
6. Backend membuat `Payment` / `payment_activity` status `pending`.
   - Method: `startInitialCheckout(...)`
7. Backend memanggil PayPal create order.
   - File: [app/Services/PayPalService.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Services/PayPalService.php)
   - Method: `createOrder(...)`
8. Backend menyimpan `payment_reference = PayPal order id`.
9. Frontend PayPal Buttons menerima `order_id`.
10. Setelah user approve, frontend memanggil backend `POST /checkout/.../capture`.
    - File: [app/Http/Controllers/CheckoutController.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Http/Controllers/CheckoutController.php)
    - Method: `captureOrder(...)`
11. Backend memanggil PayPal capture.
    - File: `PayPalService`
    - Method: `captureOrder(...)`
12. Jika capture `COMPLETED`, backend memanggil finalizer.
    - File: [app/Services/PaymentFinalizerService.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Services/PaymentFinalizerService.php)
    - Method: `finalizeSuccessfulPayment(...)`
13. Finalizer:
    - update `payment_activities.status = success`
    - hitung `balance_due`
    - set `invoices.status = installment` atau `paid_full`
    - untuk initial payment:
      - buat atau temukan `users`
      - set `users.access_tier_id`
      - set `pending_registrations.status = payment_success`
      - buat `onboarding_states`
      - dispatch onboarding continuation email
14. Controller redirect ke `onboarding.payment-success.show`.
15. `OnboardingController::showPaymentSuccess(...)` mengarahkan user ke:
    - enrollment, atau
    - signup

Fallback flow yang tetap aktif:

- `PayPalCheckoutController::success(...)`
- `PayPalWebhookController::__invoke(...)`

## 9. Webhook PayPal aktual

- Route:
  - `/webhooks/paypal`
  - name `paypal.webhook`
- File controller:
  - [app/Http/Controllers/PayPalWebhookController.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Http/Controllers/PayPalWebhookController.php)
- Signature verification:
  - `PayPalService::verifyWebhookSignature(...)`
  - memerlukan `services.paypal.webhook_id`
- Event type yang benar-benar diproses sekarang:
  - `CHECKOUT.ORDER.APPROVED`
  - `CHECKOUT.ORDER.COMPLETED`
  - extraction dilakukan di `PayPalService::extractWebhookOrderReference(...)`
- Cara sistem menemukan payment:
  - cari `Payment::where('payment_reference', $orderId)->latest('id')->first()`
- Idempotency guard yang sudah ada:
  - `PaymentFinalizerService` skip bila invoice sudah `paid_full`
  - skip bila payment reference sukses yang sama sudah ada di payment activity lain
- Gap untuk PayPal Subscriptions:
  - belum ada extractor event subscription seperti:
    - `BILLING.SUBSCRIPTION.CREATED`
    - `BILLING.SUBSCRIPTION.ACTIVATED`
    - `BILLING.SUBSCRIPTION.PAYMENT.FAILED`
    - `PAYMENT.SALE.COMPLETED`
    - `PAYMENT.SALE.DENIED`
    - `BILLING.SUBSCRIPTION.CANCELLED`
    - `BILLING.SUBSCRIPTION.SUSPENDED`
    - `BILLING.SUBSCRIPTION.EXPIRED`
  - belum ada tabel event log webhook khusus provider
  - belum ada mapping `subscription_id -> invoice/subscription aggregate`
  - belum ada retry/deactivation logic dari webhook failure

## 10. Behaviour invoice installment existing

Perilaku saat ini di `PaymentFinalizerService`:

- `remainingBalance = max(0, round(invoice.balance_due - payment.amount_paid, 2))`
- Bila `remainingBalance <= 0`:
  - `invoice.status = paid_full`
  - `invoice.paid_at = now()`
- Bila `remainingBalance > 0`:
  - `invoice.status = installment`
  - `invoice.paid_at` tidak diisi oleh pembayaran ini

Hal penting:

- User **sudah dibuat / dihubungkan** pada pembayaran pertama walaupun invoice masih `installment`.
- `pending_registrations.status` langsung menjadi `payment_success` pada pembayaran pertama sukses.
- `onboarding_states` juga langsung dibuat pada pembayaran pertama sukses.
- Jadi untuk initial checkout installment existing, student **sudah bisa lanjut enrollment, signup, dan memakai sistem** setelah pembayaran pertama.
- Tidak ada logic recurring payment:
  - tidak ada due schedule
  - tidak ada next installment date
  - tidak ada retry logic
  - tidak ada grace period H+3
  - tidak ada auto deactivate / reactivate
  - tidak ada provider subscription state

Kesimpulan: status `installment` sekarang hanyalah **sisa balance invoice**, bukan installment engine.

## 11. Scheduler / command existing

### `routes/console.php`

- File: [routes/console.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/routes/console.php)
- Command yang ada:
  - `inspire`
  - `email-notifications:send-reminders`
- Scheduler yang ada:
  - `Schedule::command('email-notifications:send-reminders')->everyTenMinutes();`

### `app/Console/Commands`

- Hasil audit: folder command custom tidak berisi command aktif.

### `bootstrap/app.php`

- File: [bootstrap/app.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/bootstrap/app.php)
- Routing console di-load dari:
  - `commands: __DIR__.'/../routes/console.php'`

### `app/Console/Kernel.php`

- Hasil audit: file ini **tidak ada** di repo saat ini.

### Titik terbaik menambahkan command overdue installment

Paling aman:

- buat dedicated command class baru untuk overdue installment checking
- register via `routes/console.php`
- jadwalkan minimal `daily()` atau `dailyAt(...)`

Rekomendasi audit:

- command terpisah, misalnya `installments:sync-overdue-status`
- dijalankan harian setelah window H+3 relevan
- karena requirement grace period adalah **H+3 setelah tanggal 15**, command sebaiknya:
  - berjalan **setiap hari**
  - memeriksa semua subscription/installment yang lewat due date + 3 hari
  - tidak di-hardcode hanya tanggal 18, agar bisa memproses keterlambatan / retry state dengan aman

## 12. Email notification system

### Model/table

- Model template:
  - [app/Models/EmailTemplate.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Models/EmailTemplate.php)
  - Tabel: `email_templates`
- Model log:
  - [app/Models/EmailLog.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Models/EmailLog.php)
  - Tabel: `email_logs`

### Service/helper pengirim otomatis

- Service utama:
  - [app/Services/EmailNotificationService.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Services/EmailNotificationService.php)
- Registry type:
  - [app/Support/EmailNotificationTypeRegistry.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Support/EmailNotificationTypeRegistry.php)
- Default template:
  - [app/Support/EmailNotificationTemplateDefaults.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Support/EmailNotificationTemplateDefaults.php)

### Notification types yang sudah ada

- `module_completion`
- `assignment_review`
- `assignment_approved`
- `assignment_rejected`
- `certificate_created`
- `signup`
- `reset_password`
- `assessment_complete`
- `course_complete`
- `reminder`
- `workbook_sent`

### Payment-related email saat ini

- Ada onboarding continuation:
  - [app/Jobs/SendOnboardingContinuationEmailJob.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Jobs/SendOnboardingContinuationEmailJob.php)
  - memakai type `signup`
- Ada upgrade welcome:
  - [app/Jobs/SendUpgradeWelcomeEmailJob.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Jobs/SendUpgradeWelcomeEmailJob.php)
  - hardcoded mail, bukan template registry type baru

### Admin recipients configurable?

Ya.

- `EmailTemplate.admin_recipients` dipakai oleh `EmailNotificationService::buildDeliveries(...)`.
- Artinya recipient admin untuk email installment bisa dibuat configurable dengan pola yang sama.

### Cara menambah notification type baru untuk installment

File yang perlu disentuh:

- [app/Support/EmailNotificationTypeRegistry.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Support/EmailNotificationTypeRegistry.php)
- [app/Support/EmailNotificationTemplateDefaults.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Support/EmailNotificationTemplateDefaults.php)
- event/job/service yang memicu email baru
- kemungkinan admin email notification UI sudah reusable, jadi type baru akan otomatis bisa dibuka lewat route admin bila registry valid

### File yang harus disentuh untuk email installment

Untuk requirement:

- installment success
- installment failed
- overdue H+3 inactive
- payment completed

Paling mungkin:

- [app/Support/EmailNotificationTypeRegistry.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Support/EmailNotificationTypeRegistry.php)
- [app/Support/EmailNotificationTemplateDefaults.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Support/EmailNotificationTemplateDefaults.php)
- [app/Services/EmailNotificationService.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Services/EmailNotificationService.php)
- event/job baru untuk trigger payment lifecycle
- listener/provider bila ingin event-driven

## 13. PayPal service readiness untuk Subscriptions

File utama: [app/Services/PayPalService.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Services/PayPalService.php)

### Config/env yang dipakai

- File config:
  - [config/services.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/config/services.php)
- Keys:
  - `PAYPAL_CLIENT_ID`
  - `PAYPAL_SECRET`
  - `PAYPAL_BASE_URL`
  - `PAYPAL_WEBHOOK_ID`

### Sandbox/live switch

- Saat ini diatur lewat `PAYPAL_BASE_URL`
- default:
  - `https://api-m.sandbox.paypal.com`
- Jadi switch sandbox/live sudah ada, tetapi manual via env base URL

### Yang sudah tersedia

- client id lookup
- access token retrieval
- client token generation
- create order
- capture order
- webhook signature verification

### Access token handling

- setiap call memakai `accessToken()` baru
- belum ada token caching layer
- untuk phase subscription awal, ini masih bisa jalan, walau caching bisa menjadi optimisasi nanti

### Titik paling aman menambah capability Subscriptions

Masih paling aman menambah di service boundary yang sama, tetapi **jangan campur method order dan subscription dalam controller**. Tambahkan method terpisah di service provider boundary.

Method yang disarankan:

- `createProduct(...)`
- `createPlan(...)`
- `createSubscription(...)`
- `getSubscription(...)`
- `reviseSubscription(...)` bila nanti perlu
- `cancelSubscription(...)`
- `suspendSubscription(...)`
- `activateSubscription(...)` bila dibutuhkan
- `extractSubscriptionWebhookReference(...)`

Alasan:

- `PayPalService` sudah menjadi boundary eksternal PayPal
- signature verification sudah ada di sana
- controller existing tidak perlu tahu detail REST endpoint baru

Tetapi audit ini merekomendasikan secara domain:

- method provider-level boleh tetap di `PayPalService`
- orchestration bisnis installment sebaiknya **bukan** di `PayPalService`, melainkan di service domain baru seperti `PaymentSubscriptionService` / `InstallmentWebhookHandler`

## 14. Frontend checkout aktif

### Public checkout

- Page:
  - [resources/js/Pages/Public/Checkout.jsx](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/resources/js/Pages/Public/Checkout.jsx)
- Main panel:
  - [resources/js/Components/public/PublicCheckoutPanel.jsx](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/resources/js/Components/public/PublicCheckoutPanel.jsx)

### Cara PayPal JS SDK dimuat

- Frontend membangun URL:
  - `https://www.paypal.com/sdk/js?...`
- Parameter utama:
  - `client-id`
  - `components=buttons`
  - `currency`
  - `intent=capture`

Catatan:

- Backend mengirim `components = buttons,card-fields`
- Tetapi frontend yang aktif saat ini tetap load SDK dengan `components=buttons`
- Jadi card fields belum aktif digunakan.

### Cara PayPal Buttons memanggil backend

- `createOrder`
  - memanggil `createOrderSession("paypal")`
  - POST ke `checkout.create_order_url`
- `onApprove`
  - memanggil `captureApprovedOrder(orderID)`
  - POST ke `capture_url`
- `onCancel`
  - memanggil `cancelActiveOrder(orderID)`
  - POST ke `cancel_url`

### `payment_type` dipilih bagaimana

- Dropdown `payment_type`:
  - `pay_full`
  - `installment`
- UI installment existing:
  - label "Pay in 4 Installments"
  - nominal today = `checkout.amount / 4`

### Gap frontend terhadap installment subscription

- UI sekarang masih mengasumsikan:
  - installment = 4 pembayaran
  - first payment = `amount / 4`
- Requirement baru butuh:
  - installment count dinamis sampai 15 Januari
  - first payment bisa lebih besar dari monthly base
  - subscription setup, bukan order capture tunggal
- Jadi nanti frontend perlu berubah **setelah backend siap**, bukan sebaliknya.

### Upgrade checkout frontend

- File:
  - [resources/js/Pages/Student/Upgrade/Checkout.jsx](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/resources/js/Pages/Student/Upgrade/Checkout.jsx)
- Masih memakai select `payment_type` dan redirect-like submit ke backend.
- Juga masih menampilkan "Pay in 4 Installments".

Karena requirement phase pertama hanya untuk **initial checkout**, UI upgrade sebaiknya **tidak ikut disentuh** pada fase pertama installment subscriptions.

## 15. Test coverage existing

### File test payment terkait

- [tests/Feature/PaymentAndModuleCompletionTest.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/tests/Feature/PaymentAndModuleCompletionTest.php)
- [tests/Feature/PublicPaymentLinkTest.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/tests/Feature/PublicPaymentLinkTest.php)
- [tests/Feature/EmailNotificationTest.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/tests/Feature/EmailNotificationTest.php)

### Coverage yang sudah ada

- pending registration dibuat dari public payment link
- embedded checkout payload tersedia
- PayPal order create untuk public checkout
- PayPal capture success
- PayPal capture pending
- mock payment diblok di production
- onboarding signup completion
- upgrade payment success via mock
- email reminder scheduling

### Coverage yang belum terlihat

- tidak ada test webhook PayPal khusus
- tidak ada test `PayPalCheckoutController` success/cancel flow khusus
- tidak ada test subscription lifecycle
- tidak ada test overdue deactivation/reactivation
- tidak ada test payment failed after grace period
- tidak ada test payment completed email untuk installment lunas
- tidak ada test recurring ledger append beberapa payment activity terhadap satu aggregate installment subscription

## 16. Gap utama untuk installment

### Database gap

- belum ada tabel aggregate subscription/installment
- belum ada tabel event log webhook subscription
- belum ada due schedule canonical
- belum ada field grace period / overdue / provider subscription state

### Service/business logic gap

- `initialPaymentAmount(...)` masih hardcoded 4 installment
- belum ada dynamic calculator sampai 15 Januari
- belum ada first-payment-vs-recurring split formula yang diminta
- belum ada overdue enforcement
- belum ada reactivation setelah later success

### PayPal subscription gap

- current provider integration hanya order/capture
- belum ada product/plan/subscription API handling
- belum ada subscription id mapping ke domain

### Webhook gap

- webhook extractor hanya order events
- belum ada subscription events
- belum ada event dedup log table

### Scheduler gap

- belum ada command overdue installment
- belum ada recurring reconciliation/sync job

### Email notification gap

- belum ada template types installment success/failed/overdue/completed
- belum ada trigger service untuk event-event tersebut

### Frontend checkout gap

- UI installment masih "Pay in 4"
- frontend masih order-capture mental model
- belum siap menampilkan dynamic schedule summary

### Test coverage gap

- belum ada test calculator dinamis ke 15 Januari
- belum ada test subscription webhook success/failure
- belum ada test account inactive/active otomatis
- belum ada regression tests yang membuktikan one-time payment lama tetap aman

## 17. Rekomendasi database

Audit ini merekomendasikan **tabel baru**.

Schema existing `invoices + payment_activities` tetap dipertahankan, tetapi installment subscription tidak aman bila hanya ditambal di dua tabel itu.

### Tabel yang disarankan: `payment_subscriptions`

Tujuan:

- aggregate canonical untuk lifecycle installment subscription
- penghubung antara invoice induk, pending registration, user, dan provider subscription id

Kolom yang disarankan:

- `id`
- `invoice_id` FK ke `invoices`
- `pending_registration_id` nullable FK ke `pending_registrations`
- `user_id` nullable FK ke `users`
- `access_tier_id` FK ke `access_tiers`
- `provider` string, contoh `paypal`
- `provider_product_id` nullable
- `provider_plan_id` nullable
- `provider_subscription_id` unique nullable
- `status` string
- `installment_count` integer
- `installments_paid_count` integer default 0
- `currency_code` string(3)
- `total_amount` decimal(12,2)
- `monthly_base_amount` decimal(12,2)
- `first_payment_amount` decimal(12,2)
- `next_billing_amount` decimal(12,2) nullable
- `started_at` datetime nullable
- `first_payment_paid_at` datetime nullable
- `next_due_at` datetime nullable
- `final_due_at` datetime nullable
- `grace_deadline_at` datetime nullable
- `completed_at` datetime nullable
- `suspended_at` datetime nullable
- `cancelled_at` datetime nullable
- `last_payment_failed_at` datetime nullable
- `last_synced_at` datetime nullable
- `metadata` json nullable
- timestamps

Relasi:

- ke `invoice` satu-ke-satu atau satu invoice satu subscription
- ke `pending_registration` untuk initial checkout sebelum user final aktif
- ke `user` setelah user sudah terhubung
- ke `access_tier` untuk audit tier snapshot

### Tabel yang disarankan: `payment_subscription_events`

Tujuan:

- audit trail webhook/provider events
- idempotency event processing

Kolom yang disarankan:

- `id`
- `payment_subscription_id` nullable FK
- `invoice_id` nullable FK
- `payment_activity_id` nullable FK
- `provider` string
- `provider_event_id` nullable unique
- `provider_event_type` string
- `provider_subscription_id` nullable
- `provider_order_id` nullable
- `provider_capture_id` nullable
- `occurred_at` datetime nullable
- `processed_at` datetime nullable
- `status` string
- `payload` json
- `notes` text nullable
- timestamps

### Kenapa tidak cukup pakai `payment_activities` saja

- `payment_activities` bagus sebagai ledger pembayaran
- tetapi tidak cukup untuk menyimpan:
  - schedule cicilan
  - provider subscription identity
  - next due date
  - grace deadline
  - current subscription lifecycle status

## 18. Rekomendasi service/domain baru

### `InstallmentPlanCalculator`

- Tanggung jawab:
  - hitung jumlah installment dinamis sampai 15 Januari
  - hitung `monthly_base`
  - hitung `first_payment`
  - hitung due schedule tanggal 15
  - menghasilkan breakdown per installment

### `PaymentSubscriptionService`

- Tanggung jawab:
  - membuat aggregate `payment_subscriptions`
  - menghubungkan invoice, pending registration, user
  - meng-update progress installment saat payment sukses/gagal
  - menentukan kapan invoice tetap `installment` vs menjadi `paid_full`

### `PayPalSubscriptionService`

- Tanggung jawab:
  - wrapper provider operation khusus subscription:
    - create product
    - create plan
    - create subscription
    - get/sync subscription
    - suspend/cancel

Catatan:

- bisa berupa service baru, atau ekspansi `PayPalService` dengan facade domain terpisah.
- secara maintainability, audit ini lebih condong ke service provider khusus subscription.

### `InstallmentWebhookHandler`

- Tanggung jawab:
  - map webhook event ke aggregate subscription
  - simpan raw event ke `payment_subscription_events`
  - idempotent processing
  - append `payment_activities`
  - trigger email dan status account

### `OverdueInstallmentService`

- Tanggung jawab:
  - scan subscription overdue
  - menerapkan grace period H+3
  - deactivate student account bila lewat grace
  - reactivate bila payment belakangan sukses

## 19. Rekomendasi flow implementasi bertahap

1. Tambahkan schema baru untuk `payment_subscriptions` dan `payment_subscription_events`.
2. Tambahkan `InstallmentPlanCalculator` untuk menghitung jadwal sampai 15 Januari.
3. Tambahkan provider layer untuk PayPal Subscriptions.
4. Tambahkan backend orchestration untuk initial checkout installment subscription.
5. Simpan payment pertama sukses ke `payment_activities` dan update `payment_subscriptions`.
6. Pastikan `PaymentFinalizerService` hanya dipakai di titik yang aman, atau ekstrak path subscription-first-payment yang jelas.
7. Tambahkan webhook handler subscription events + event log idempotent.
8. Tambahkan scheduler overdue H+3 + reactivation sync.
9. Tambahkan email notification types dan trigger service.
10. Ubah frontend public checkout agar membaca summary installment dari backend.
11. Tambahkan test baru untuk calculator, webhook, overdue, reactivation, dan regression one-time payment.
12. Setelah initial checkout stabil, baru evaluasi apakah upgrade installment perlu atau tetap out of scope.

## 20. File yang boleh disentuh dan tidak boleh disentuh

### File yang kemungkinan harus disentuh

- [app/Services/PaymentCheckoutService.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Services/PaymentCheckoutService.php)
- [app/Services/PaymentFinalizerService.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Services/PaymentFinalizerService.php)
- [app/Services/PayPalService.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Services/PayPalService.php)
- [app/Http/Controllers/CheckoutController.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Http/Controllers/CheckoutController.php)
- [app/Http/Controllers/PayPalWebhookController.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Http/Controllers/PayPalWebhookController.php)
- [app/Support/EmailNotificationTypeRegistry.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Support/EmailNotificationTypeRegistry.php)
- [app/Support/EmailNotificationTemplateDefaults.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Support/EmailNotificationTemplateDefaults.php)
- [app/Services/EmailNotificationService.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Services/EmailNotificationService.php)
- [routes/console.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/routes/console.php)
- [resources/js/Components/public/PublicCheckoutPanel.jsx](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/resources/js/Components/public/PublicCheckoutPanel.jsx)
- migration baru
- model/service baru untuk subscription lifecycle
- test files di `tests/Feature` dan `tests/Unit`

### File yang sebaiknya tidak disentuh pada fase pertama

- [app/Http/Controllers/Student/UpgradeController.php](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/app/Http/Controllers/Student/UpgradeController.php)
- [resources/js/Pages/Student/Upgrade/Checkout.jsx](D:/Semester6/Kerja/YogaFX-Learning-Management-System-Web/resources/js/Pages/Student/Upgrade/Checkout.jsx)
- domain learning/progress yang tidak terkait payment
- mobile API area yang tidak membaca billing recurring

### Area yang rawan merusak payment existing

- logic `PaymentFinalizerService::finalizeSuccessfulPayment(...)`
- mapping `payment_reference` current order flow
- existing `checkout.orders.capture`
- PayPal webhook order flow yang sudah hidup
- enum invoice/payment status yang sudah dipakai banyak tempat

## 21. Kesimpulan

Schema existing **tidak cukup aman** bila installment subscription hanya ditambahkan sebagai patch di UI atau sebagai tambahan kecil pada `payment_activities`. Fondasi `invoice + payment ledger` sudah benar untuk dipertahankan, tetapi requirement recurring sampai 15 Januari, grace H+3, deactivate/reactivate, dan admin notification menuntut **aggregate subscription baru** di backend.

Rekomendasi final audit:

- **Ya, perlu tabel baru** seperti `payment_subscriptions` dan `payment_subscription_events`.
- **Ya, implementation harus memakai PayPal Subscriptions** untuk recurring yang canonical, bukan mengulang create/capture one-time order manual tiap bulan.
- Urutan paling aman untuk mulai coding:
  1. schema + model lifecycle subscription
  2. calculator installment dinamis
  3. PayPal subscription provider methods
  4. backend initial checkout orchestration
  5. webhook subscription handling
  6. overdue scheduler + account activation rules
  7. email notifications
  8. frontend public checkout update
  9. regression tests untuk one-time payment existing

Kesimpulan singkatnya: **payment existing siap menjadi fondasi, tetapi belum siap langsung dipakai untuk PayPal installment subscriptions tanpa domain extension baru di backend.**
