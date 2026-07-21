# Requirement 1 — Free Package, ByDonation Package, Multi-Package per Tier, and Upgrade Credit Logic

## 1. Project Context

This project is the YogaFX Learning Management System built with Laravel, React/Inertia, PostgreSQL, Tailwind, and shadcn/ui.

The current system already has a payment/onboarding flow for student registration. The existing flow generally works like this:

1. A prospective student accesses a package/checkout link.
2. The user enters registration/checkout data.
3. The system creates payment-related records such as invoice/payment activity/onboarding state.
4. For paid packages, the user pays through PayPal.
5. After payment succeeds, the user goes to the onboarding/enrollment flow.
6. After enrollment completion, the user gets access to LMS content based on the access tier assigned to the purchased package.

The system already has concepts such as:

- `users`
- `access_tiers`
- package/payment-related models and services
- invoices
- payment activities
- onboarding states
- PayPal checkout/order/subscription services
- installment subscriptions
- upgrade flow
- email notification/logging

The system currently treats access control mainly through `access_tier_id` on the user. A package/product points to an access tier. When a student completes checkout/enrollment, the user receives the corresponding access tier.

This requirement introduces a new package/payment model while preserving the existing paid PayPal flow.

---

## 2. Current Important Behavior That Must Be Changed

### 2.1 Current package-to-tier assignment limitation

At the moment, the system appears to enforce this behavior:

- If one package is assigned to an access tier, for example `masterclass`, and then another package is assigned to the same `masterclass` tier, the old package is automatically unassigned or changed to `none`.

Example of current unwanted behavior:

```text
Package A → Masterclass
Package B → Masterclass

After Package B is saved:
Package A → none
Package B → Masterclass
```

This is no longer allowed.

### 2.2 Required new behavior

The system must allow multiple packages to point to the same access tier.

Example of required behavior:

```text
Package A: Masterclass Standard → Masterclass tier
Package B: Masterclass Promo → Masterclass tier
Package C: Masterclass Donation → Masterclass tier
```

All three packages must remain valid. No package should automatically become `none` just because another package uses the same access tier.

Conceptually:

```text
Package = product / offer / pricing entry point
AccessTier = LMS access level / entitlement
```

A package is only a way to sell or grant access to a tier. It should not own the tier exclusively.

---

## 3. New Business Requirement from Client

The client wants to support three package payment types:

```text
paid
free
donation
```

These payment types must be explicitly selected by admin through a dropdown/select field, not inferred only from price.

Admin should not only set price to `0` and have the system automatically guess that the package is free. The package must have a clear explicit payment type.

Recommended field name:

```text
payment_type
```

Allowed values:

```text
paid
free
donation
```

---

## 4. Payment Type Definitions

## 4.1 Paid Package

A paid package is the existing normal package behavior.

Expected behavior:

- Admin selects `payment_type = paid`.
- Admin must set a fixed package price.
- Price must be greater than 0.
- PayPal is required.
- Existing Pay in Full flow should continue to work.
- Existing installment flow should continue to work if the package supports installment.
- Existing invoice/payment activity/onboarding behavior should remain compatible.

Validation rule:

```text
payment_type = paid
price > 0 is required
```

The system must reject a paid package with price 0.

---

## 4.2 Free Package

A free package allows a user to register without PayPal payment.

Expected behavior:

- Admin selects `payment_type = free`.
- Package price should be 0.
- PayPal must not be shown.
- PayPal order/subscription must not be created.
- User should continue directly to the onboarding/enrollment flow.
- The system should still create invoice/payment activity records with amount 0 for audit consistency.
- The package still assigns the user to its configured access tier.

Recommended internal behavior:

```text
invoice.amount = 0
payment_activity.amount = 0
payment provider = none/internal/free
payment status = paid/completed
```

The reason to still create invoice/payment activity records is to keep the registration/onboarding audit trail consistent across paid, free, and donation packages.

Expected flow:

```text
Free package checkout
↓
No PayPal
↓
Create zero-amount invoice/payment activity
↓
Finalize payment/onboarding internally
↓
Show success page
↓
Continue to enrollment
```

---

## 4.3 ByDonation Package

A ByDonation package allows the user to enter their own donation amount, subject to a minimum amount configured by admin.

Expected behavior:

- Admin selects `payment_type = donation`.
- Admin can set a minimum donation amount.
- Admin may also set a suggested donation amount if the system already supports or can cleanly add this.
- User enters the actual donation amount during checkout.
- User must pay at least the minimum donation amount.
- Donation is one-time payment only.
- Donation must not support installment.
- Donation should use PayPal one-time order if the amount is greater than 0.
- After successful PayPal payment, user continues to onboarding/enrollment.
- The package still assigns the user to its configured access tier.

Important distinction:

```text
minimum_donation_amount = minimum allowed checkout amount configured by admin
actual_donation_amount = amount entered by user and actually paid
```

The minimum donation amount is not the final fixed package price. It is only the minimum validation rule. The actual transaction amount is the amount entered by the user.

Example:

```text
Package: Donation Online
Assigned tier: Online
Minimum donation: USD 25

User A pays USD 25
User B pays USD 100
User C pays USD 3000
```

All of these are valid as long as the amount is greater than or equal to the minimum donation amount.

For invoice/payment activity:

```text
invoice.amount = actual donation amount entered by user
payment_activity.amount = actual donation amount paid through PayPal
```

Donation checkout UI must show a clear warning/notice about the minimum amount.

Example wording:

```text
Minimum donation for this package is USD 25. You may donate more if you wish.
```

or:

```text
Please enter at least USD 25 to continue.
```

Validation rule:

```text
payment_type = donation
actual donation amount >= minimum_donation_amount
installment is not allowed
```

---

## 5. Admin Package Form Requirements

Admin should explicitly choose the package payment type.

Recommended UI:

```text
Payment Type:
- Paid
- Free
- By Donation
```

### If admin selects Paid

Show/require:

- Price
- Currency
- Existing PayPal/payment settings
- Existing installment settings if supported

Validation:

```text
price must be greater than 0
```

### If admin selects Free

Behavior:

- Price should be automatically set to 0 or forced to 0.
- Price input can be hidden or disabled.
- Donation fields should be hidden.
- Installment settings should be hidden/disabled.

Validation:

```text
price must equal 0
PayPal is not required
Installment is not allowed
```

### If admin selects By Donation

Show/require:

- Currency
- Minimum donation amount
- Suggested donation amount if implemented cleanly

Hide/disable:

- Fixed price as mandatory sale price
- Installment options

Validation:

```text
minimum_donation_amount must be >= 0 or > 0 depending on existing business decision
installment is not allowed
```

Current business decision:

- Admin can configure the minimum donation amount.
- Donation is one-time only.
- No installment for donation packages.

---

## 6. Checkout UI Requirements

Checkout behavior must depend on package `payment_type`.

### 6.1 Paid checkout UI

Existing paid checkout behavior should remain.

Expected UI:

- Fixed package price
- Existing Pay in Full / Installment options if allowed
- PayPal button/payment section

### 6.2 Free checkout UI

Expected UI:

- Show that the package is free.
- Do not show PayPal.
- Show a direct continue button.

Example wording:

```text
This package is free. No payment is required.
```

Button example:

```text
Continue to Enrollment
```

### 6.3 Donation checkout UI

Expected UI:

- Show donation amount input.
- Show currency.
- Show minimum donation warning.
- Show PayPal one-time payment button after a valid donation amount is entered.
- Do not show installment options.

Example:

```text
Enter your donation amount
[ USD 25.00 ]

Minimum donation: USD 25
You may donate more if you wish.

[Continue with PayPal]
```

---

## 7. Success Page Wording

The existing payment success page must become dynamic based on payment type.

### Paid package

Suggested wording:

```text
Payment Success
Your payment was received.
Continue to enrollment to complete your YogaFX account.
```

### Free package

Suggested wording:

```text
Registration Ready
Your free access is ready.
Continue to enrollment to complete your YogaFX account.
```

### Donation package

Suggested wording:

```text
Donation Received
Thank you for your donation. Your payment was received.
Continue to enrollment to complete your YogaFX account.
```

---

## 8. Email Notification Wording

Email wording should not assume every successful registration came from a normal payment.

### Paid package email

Use normal payment success wording.

Example:

```text
Your payment was received.
```

### Free package email

Use free access / registration wording.

Example:

```text
Your free registration is ready.
```

### Donation package email

Donation is still a successful payment, but wording should thank the user for the donation.

Example:

```text
Thank you for your donation. Your payment was received.
```

If the existing email template system supports conditional placeholders or separate templates, implement in the least invasive way that fits the current architecture.

---

## 9. Upgrade Flow Requirements

Upgrade logic must be package-based, not only tier-based.

The user upgrades by choosing a target package. The target package determines:

- target access tier
- target package price or target required amount
- payment type
- amount due

Example:

```text
Current user package: Donation Online
Current access tier: Online
Target package: Masterclass Standard
Target access tier: Masterclass
Target package price: USD 2000
```

The system must calculate how much the user still needs to pay.

---

## 10. Previous Successful Payment Must Reduce Upgrade Amount

The system must prevent the student from paying twice for access.

If the user has already paid money to YogaFX through a previous package, that amount must be considered when calculating upgrade amount due.

Use this conceptual formula:

```text
amount_due = max(target_package_price - previous_successfully_paid_amount, 0)
```

Important: `previous_successfully_paid_amount` means actual money successfully paid, not merely invoice total.

### Example 1 — Donation partially covers target package

```text
Previous package: Donation Online
Previous successful payment: USD 1500
Target package: Masterclass Standard
Target package price: USD 2000

amount_due = max(2000 - 1500, 0)
amount_due = USD 500
```

The user only pays USD 500.

### Example 2 — Donation exceeds target package price

```text
Previous package: Donation Online
Previous successful payment: USD 3000
Target package: Masterclass Standard
Target package price: USD 2000

amount_due = max(2000 - 3000, 0)
amount_due = USD 0
```

The user does not need to pay again. Upgrade should be finalized internally without PayPal.

### Example 3 — Free package upgrade

```text
Previous package: Free Starter Kit
Previous successful payment: USD 0
Target package: Masterclass Standard
Target package price: USD 2000

amount_due = max(2000 - 0, 0)
amount_due = USD 2000
```

The user pays full target package amount.

---

## 11. Installment Upgrade Rule

For installment payments, only the amount that has actually been successfully paid should reduce the upgrade amount.

Do not count the full invoice total or total installment plan if the user has not paid all installments yet.

Example:

```text
Current package: Online installment
Total package/installment amount: USD 1000
Amount actually paid so far: USD 250
Target package: Masterclass Standard
Target package price: USD 2000

previous_successfully_paid_amount = USD 250
amount_due = max(2000 - 250, 0)
amount_due = USD 1750
```

This is required so the system does not give credit for money that has not actually been received.

---

## 12. Zero Amount Upgrade Still Needs Audit Records

If the upgrade amount due becomes 0, the system must still create audit records.

Example:

```text
Previous donation: USD 3000
Target package price: USD 2000
Amount due: USD 0
```

Expected records:

```text
Upgrade invoice:
- amount = 0
- status = paid/completed
- provider = none/internal/credit_applied

Payment activity:
- amount = 0
- status = completed
- provider = none/internal/credit_applied
```

The exact provider/status names should match the existing system conventions.

The important rule is:

- Do not call PayPal when amount due is 0.
- Still finalize the upgrade.
- Still keep invoice/payment activity audit trail.

---

## 13. No Wallet / No Visible Remaining Balance

If the previous payment exceeds the target package price, the system should not introduce a wallet or visible balance feature.

Example:

```text
Previous donation: USD 3000
Target package price: USD 2000
Excess: USD 1000
```

Do not display:

```text
You have USD 1000 remaining balance.
```

Do not create a visible wallet feature.

Only use previous successful payment internally to ensure:

```text
amount_due = 0
```

This requirement is only to prevent double payment during upgrade, not to create a full stored-credit/wallet feature.

---

## 14. User Cannot Buy Multiple Packages Except Through Upgrade

A user/email should not be able to buy unrelated packages repeatedly as separate purchases.

Current business rule:

- A user buys one package and then enters the LMS.
- The same email cannot buy another package separately.
- The exception is upgrade.

Therefore, if a user already exists/has already purchased a package, the only valid way to move to a higher tier/package is through the upgrade flow.

---

## 15. Tier Ranking / Upgrade Order

The project should not rely only on package price to determine tier hierarchy, especially now that multiple packages can point to the same tier and donation amounts can vary.

Accepted decision:

- Use an explicit tier ranking/level/order for access tiers if it exists.
- If no such field exists, audit and propose the safest minimal change.

Recommended concept:

```text
starter_kit → level 1
online → level 2
masterclass → level 3
```

The highest tier cannot upgrade further.

Package price/donation amount should not be the only source of truth for tier order.

---

## 16. Data Model Direction

Codex must inspect the existing code before implementing, but conceptually the system may need fields similar to these.

### Package

Potential fields:

```text
payment_type: paid/free/donation
price
currency_code
minimum_donation_amount
suggested_donation_amount
access_tier_id
is_active
```

### AccessTier

Potential field if not already available:

```text
level / rank / sort_order
```

### Invoice / Payment Activity

Should store snapshots relevant to checkout:

```text
package_id
access_tier_id snapshot
payment_type snapshot
amount
currency_code
status
provider
metadata
```

Do not blindly add these exact fields if the system already has equivalent fields. First inspect current migrations/models/services and reuse existing conventions where possible.

---

## 17. Important Implementation Boundaries

Do not break the existing paid PayPal flow.

Existing behaviors that must remain working:

- paid fixed package checkout
- PayPal one-time full payment
- PayPal installment flow for paid packages
- PayPal webhook processing
- payment success/onboarding flow
- upgrade flow, but updated to support previous successful payment deduction
- email logging
- admin package management

Donation packages must not support installment.

Free packages must not call PayPal.

A paid package with price 0 must be rejected.

Multiple packages must be allowed to point to the same access tier.

---

## 18. Recommended Work Strategy

Implement carefully and incrementally.

First audit the current code and identify:

1. Where package/access tier assignment is saved.
2. Where the current logic unassigns old packages from a tier.
3. Where package price/payment settings are validated.
4. Where checkout payload is prepared.
5. Where PayPal order/subscription is created.
6. Where invoices/payment activities are created.
7. Where payment is finalized.
8. Where upgrade amount is calculated.
9. Where payment success page wording is generated.
10. Where payment success/enrollment emails are generated.
11. Existing tests around payment, package, and upgrade.

Then implement the new behavior using the existing architecture and naming conventions.

---

# Short Codex Instructions

Please implement the new package payment requirements described above.

Core tasks:

1. Remove or modify the current restriction that allows only one package per access tier.
2. Allow multiple packages to point to the same access tier without setting older packages to `none`.
3. Add explicit package `payment_type` support: `paid`, `free`, `donation`.
4. Add admin validation and UI behavior for paid/free/donation package types.
5. Implement free package checkout that skips PayPal but still creates invoice/payment activity records with amount 0.
6. Implement donation checkout with admin-configured minimum donation, user-entered actual donation amount, one-time PayPal only, and no installment option.
7. Make checkout/payment success page wording dynamic for paid/free/donation.
8. Adjust email wording for paid/free/donation where applicable.
9. Update upgrade calculation so only actual successful previous payments reduce the target package amount due.
10. For installment users, count only installments that were actually paid, not the full installment total.
11. If upgrade amount due is 0, finalize upgrade internally without PayPal but still create invoice/payment activity audit records.
12. Do not create wallet or visible remaining balance UI.
13. Use explicit access tier ranking/order for upgrade hierarchy if available; if not available, propose or add a minimal safe field after auditing current code.
14. Add/update tests for paid, free, donation, multi-package-per-tier, and upgrade deduction scenarios.

Before coding, briefly summarize the existing files/logic you found and the planned changes. Then implement in small, safe steps.

