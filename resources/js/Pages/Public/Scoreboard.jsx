import PublicCheckoutPanel from "@/Components/public/PublicCheckoutPanel";
import FlagOptionSelect from "@/Components/FlagOptionSelect";
import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import TextInput from "@/Components/TextInput";
import YogaFXText from "@/Components/YogaFXText";
import { Button } from "@/Components/ui/button";
import PublicFlowLayout from "@/Layouts/PublicFlowLayout";
import {
    enrichCountryOptions,
    findCountryOptionByDialCode,
} from "@/lib/countryFlags";
import { formatCurrency } from "@/lib/currency";
import { usePage } from "@inertiajs/react";
import { useMemo, useRef, useState, useEffect } from "react";

function getCsrfToken() {
    return document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content");
}

async function parseJsonSafely(response) {
    const text = await response.text();

    if (!text) {
        return {};
    }

    try {
        return JSON.parse(text);
    } catch {
        return {};
    }
}

const FONT_FAMILY = "'Montserrat', sans-serif";

const PUBLIC_FORM_FIELD_CLASS =
    "min-h-[52px] rounded-[5px] border-2 border-white/50 bg-black/45 px-4 py-3.5 text-sm font-normal text-white placeholder:text-white/55 shadow-[0_0_0_1px_rgba(255,255,255,0.14)] transition-all duration-200 focus:border-white focus:bg-black/60 focus:ring-2 focus:ring-white/35 disabled:opacity-60";

const FIELD_ORDER = [
    "first_name",
    "last_name",
    "email",
    "phone_country_code",
    "phone_number",
    "country",
    "package_id",
];

const FIELD_ELEMENT_IDS = {
    first_name: "first_name",
    last_name: "last_name",
    email: "email",
    phone_country_code: "phone_country_code",
    phone_number: "phone_number",
    country: "country",
    package_id: "package_id",
};

function normalizePhoneNumberInput(value) {
    return String(value ?? "")
        .replace(/^\s+/, "")
        .replace(/^0+/, "");
}

function isValidEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value ?? "").trim());
}

function normalizeBillingDays(value) {
    if (!Array.isArray(value)) {
        return [];
    }

    return value
        .map((day) => Number(day))
        .filter((day) => [1, 15].includes(day))
        .filter((day, index, array) => array.indexOf(day) === index)
        .sort((a, b) => a - b);
}

function normalizeInstallmentMaximumCount(value) {
    const numericValue = Number(value);

    if (!Number.isFinite(numericValue) || numericValue < 2) {
        return 2;
    }

    return Math.trunc(numericValue);
}

function normalizeInstallmentMinimumCount(value, fallback = 2) {
    const numericValue = Number(value);

    if (!Number.isFinite(numericValue) || numericValue < 2) {
        return fallback;
    }

    return Math.trunc(numericValue);
}

function amountToCents(value) {
    return Math.round(Number(value ?? 0) * 100);
}

function centsToAmount(value) {
    return Number((Number(value ?? 0) / 100).toFixed(2));
}

function toDateString(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");

    return `${year}-${month}-${day}`;
}

function daysInMonth(year, monthIndex) {
    return new Date(year, monthIndex + 1, 0).getDate();
}

function dateForBillingDay(year, monthIndex, billingDay) {
    const safeBillingDay = Number(billingDay) === 1 ? 1 : 15;
    const safeDay = Math.min(safeBillingDay, daysInMonth(year, monthIndex));

    return new Date(year, monthIndex, safeDay, 12, 0, 0, 0);
}

function buildRecurringDueDates({
    billingDay,
    recurringCount,
    startDate = new Date(),
}) {
    const normalizedRecurringCount = Number(recurringCount);

    if (
        !Number.isFinite(normalizedRecurringCount) ||
        normalizedRecurringCount <= 0
    ) {
        return [];
    }

    const normalizedBillingDay = Number(billingDay) === 1 ? 1 : 15;
    const dates = [];
    let cursorMonth = startDate.getMonth();
    let cursorYear = startDate.getFullYear();

    while (dates.length < normalizedRecurringCount) {
        let candidate = dateForBillingDay(
            cursorYear,
            cursorMonth,
            normalizedBillingDay,
        );

        if (candidate <= startDate) {
            cursorMonth += 1;

            if (cursorMonth > 11) {
                cursorMonth = 0;
                cursorYear += 1;
            }

            candidate = dateForBillingDay(
                cursorYear,
                cursorMonth,
                normalizedBillingDay,
            );
        }

        dates.push(toDateString(candidate));

        cursorMonth += 1;

        if (cursorMonth > 11) {
            cursorMonth = 0;
            cursorYear += 1;
        }
    }

    return dates;
}

function buildPreviewInstallmentSummary({
    amount,
    installmentCount,
    maximumInstallmentCount,
    minimumInstallmentCount = 2,
    billingDay,
    installmentCalculationMethod = "date",
    installmentCountMode = null,
    installmentCountSelectable = true,
    configuredInstallmentCount = null,
    fixedInstallmentCount = null,
}) {
    const normalizedAmount = Number(amount ?? 0);
    const normalizedMaximumInstallmentCount = normalizeInstallmentMaximumCount(
        maximumInstallmentCount,
    );
    const normalizedInstallmentCount = normalizeInstallmentMaximumCount(
        installmentCount ?? normalizedMaximumInstallmentCount,
    );

    if (!normalizedAmount || normalizedAmount <= 0) {
        return null;
    }

    const totalAmountCents = amountToCents(normalizedAmount);
    const recurringAmountCents = Math.floor(
        totalAmountCents / normalizedInstallmentCount,
    );
    const firstPaymentAmountCents =
        totalAmountCents -
        recurringAmountCents * (normalizedInstallmentCount - 1);

    const firstPaymentAmount = centsToAmount(firstPaymentAmountCents);
    const recurringAmount = centsToAmount(recurringAmountCents);
    const firstPaymentDate = toDateString(new Date());
    const recurringDueDates = buildRecurringDueDates({
        billingDay,
        recurringCount: normalizedInstallmentCount - 1,
    });
    const finalDueAt =
        recurringDueDates[recurringDueDates.length - 1] ?? firstPaymentDate;

    return {
        total_amount: normalizedAmount.toFixed(2),
        first_payment_amount: firstPaymentAmount.toFixed(2),
        first_payment_date: firstPaymentDate,
        recurring_payment_amount: recurringAmount.toFixed(2),
        monthly_base_amount: recurringAmount.toFixed(2),
        installment_calculation_method: installmentCalculationMethod,
        installment_count_mode: installmentCountMode,
        installment_count: normalizedInstallmentCount,
        maximum_installment_count: normalizedMaximumInstallmentCount,
        installment_maximum_count: normalizedMaximumInstallmentCount,
        minimum_installment_count: minimumInstallmentCount,
        installment_count_selectable: installmentCountSelectable,
        configured_installment_count: configuredInstallmentCount,
        fixed_installment_count: fixedInstallmentCount,
        billing_day: billingDay,
        recurring_due_dates: recurringDueDates,
        available_recurring_due_dates: recurringDueDates,
        schedule_breakdown: [
            {
                cycle_number: 1,
                type: "first_payment",
                amount: firstPaymentAmount.toFixed(2),
                due_at: firstPaymentDate,
                grace_deadline: null,
            },
            ...Array.from(
                { length: normalizedInstallmentCount - 1 },
                (_, index) => ({
                    cycle_number: index + 2,
                    type: "recurring",
                    amount: recurringAmount.toFixed(2),
                    due_at: recurringDueDates[index] ?? null,
                    grace_deadline: null,
                }),
            ),
        ],
        final_due_at: finalDueAt,
    };
}

export default function Scoreboard({
    packages,
    submit_url,
    selected_package_id,
    is_package_locked = false,
}) {
    const { directory = {}, paypal = {} } = usePage().props;

    const packageOptions = Array.isArray(packages)
        ? packages
        : Object.values(packages ?? {});

    const countryOptions = enrichCountryOptions(directory.countries ?? []);
    const phoneCountryCodeOptions = enrichCountryOptions(
        directory.phone_country_codes ?? [],
    );

    const [data, setData] = useState({
        first_name: "",
        last_name: "",
        email: "",
        phone_country_code: "+62",
        phone_number: "",
        country: "",
        package_id: selected_package_id ?? packageOptions[0]?.id ?? "",
    });

    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);
    const [checkout, setCheckout] = useState(null);

    const fieldContainersRef = useRef({});
    const dataRef = useRef(data);

    useEffect(() => {
        dataRef.current = data;
    }, [data]);

    const selectedPackage =
        packageOptions.find(
            (pkg) => String(pkg.id) === String(data.package_id),
        ) ?? null;

    const selectedCountryOption =
        countryOptions.find((option) => option.value === data.country) ?? null;

    const selectedPhoneCountryOption = findCountryOptionByDialCode(
        phoneCountryCodeOptions,
        data.phone_country_code,
        data.country,
    );

    const selectedPackagePaymentType = String(
        selectedPackage?.payment_type ?? "paid",
    );
    const selectedPackageHasPrice =
        selectedPackagePaymentType !== "paid" ||
        Number(selectedPackage?.price ?? 0) > 0;
    const isIdentityLocked = checkout !== null;
    const packageTitle = selectedPackage?.title ?? "YogaFX Package";

    const packagePrice =
        selectedPackagePaymentType === "free"
            ? "Free"
            : selectedPackagePaymentType === "donation"
              ? `Minimum ${formatCurrency(
                    Number(selectedPackage?.minimum_donation_amount ?? 0),
                    selectedPackage?.currency_code ?? "USD",
                )}`
              : selectedPackageHasPrice
                ? formatCurrency(
                      selectedPackage.price,
                      selectedPackage.currency_code,
                  )
                : "Price not set yet";

    const normalizedEmail = (data.email ?? "").trim().toLowerCase();

    const identityIsComplete = useMemo(() => {
        return (
            data.first_name.trim() !== "" &&
            data.last_name.trim() !== "" &&
            normalizedEmail !== "" &&
            isValidEmail(normalizedEmail) &&
            data.phone_country_code.trim() !== "" &&
            data.phone_number.trim() !== "" &&
            data.country.trim() !== "" &&
            String(data.package_id ?? "") !== "" &&
            selectedPackageHasPrice
        );
    }, [
        data.first_name,
        data.last_name,
        normalizedEmail,
        data.phone_country_code,
        data.phone_number,
        data.country,
        data.package_id,
        selectedPackageHasPrice,
    ]);

    const selectedPackageAllowedBillingDays = useMemo(() => {
        return normalizeBillingDays(
            selectedPackage?.checkout_billing_day_options ??
                selectedPackage?.allowed_billing_days ??
                selectedPackage?.installment_billing_day_options ??
                [],
        );
    }, [selectedPackage]);

    const selectedPackageMaximumInstallmentCount = useMemo(() => {
        return normalizeInstallmentMaximumCount(
            selectedPackage?.installment_maximum_count ??
                selectedPackage?.maximum_installment_count ??
                selectedPackage?.max_installment_count ??
                selectedPackage?.installment_count ??
                2,
        );
    }, [selectedPackage]);

    const selectedPackageMinimumInstallmentCount = useMemo(() => {
        return normalizeInstallmentMinimumCount(
            selectedPackage?.minimum_installment_count,
            2,
        );
    }, [selectedPackage]);

    const selectedPackageInstallmentCalculationMethod =
        selectedPackage?.installment_calculation_method ?? "date";
    const selectedPackageInstallmentCountMode =
        selectedPackage?.installment_count_mode ?? null;
    const selectedPackageInstallmentCountSelectable =
        selectedPackage?.installment_count_selectable ?? true;
    const selectedPackageConfiguredInstallmentCount =
        selectedPackage?.configured_installment_count ??
        selectedPackage?.installment_count ??
        null;
    const selectedPackageFixedInstallmentCount =
        selectedPackage?.fixed_installment_count ?? null;

    const selectedPackageInstallmentEnabled = Boolean(
        selectedPackage?.installment_enabled &&
        selectedPackageAllowedBillingDays.length > 0 &&
        selectedPackageMaximumInstallmentCount >= 2,
    );

    const previewCheckout = useMemo(() => {
        const paymentType = String(selectedPackage?.payment_type ?? "paid");
        const amount =
            paymentType === "donation"
                ? Number(
                      selectedPackage?.suggested_donation_amount ??
                          selectedPackage?.minimum_donation_amount ??
                          0,
                  )
                : paymentType === "free"
                  ? 0
                  : Number(selectedPackage?.price ?? 0);
        const currencyCode =
            selectedPackage?.currency_code ?? paypal?.currency_code ?? "USD";
        const billingDay = selectedPackageAllowedBillingDays[0] ?? 15;
        const maximumInstallmentCount = selectedPackageMaximumInstallmentCount;
        const previewInstallmentCount =
            selectedPackageInstallmentCountSelectable === false
                ? normalizeInstallmentMaximumCount(
                      selectedPackageFixedInstallmentCount ??
                          selectedPackageConfiguredInstallmentCount ??
                          maximumInstallmentCount,
                  )
                : maximumInstallmentCount;

        const previewInstallmentSummary = buildPreviewInstallmentSummary({
            amount,
            installmentCount: previewInstallmentCount,
            maximumInstallmentCount,
            minimumInstallmentCount: selectedPackageMinimumInstallmentCount,
            billingDay,
            installmentCalculationMethod:
                selectedPackageInstallmentCalculationMethod,
            installmentCountMode: selectedPackageInstallmentCountMode,
            installmentCountSelectable:
                selectedPackageInstallmentCountSelectable,
            configuredInstallmentCount:
                selectedPackageConfiguredInstallmentCount,
            fixedInstallmentCount: selectedPackageFixedInstallmentCount,
        });

        const paymentOptions =
            paymentType === "free"
                ? [
                      {
                          type: "pay_full",
                          label: "Continue to Enrollment",
                          amount_due_today: amount.toFixed(2),
                          currency_code: currencyCode,
                          checkout_variant: "free",
                      },
                  ]
                : paymentType === "donation"
                  ? [
                        {
                            type: "pay_full",
                            label: "Donate with PayPal",
                            amount_due_today: amount.toFixed(2),
                            currency_code: currencyCode,
                            checkout_variant: "donation",
                            minimum_donation_amount: Number(
                                selectedPackage?.minimum_donation_amount ?? 0,
                            ).toFixed(2),
                            suggested_donation_amount: amount.toFixed(2),
                        },
                    ]
                  : [
                        {
                            type: "pay_full",
                            label: "Pay in full",
                            amount_due_today: amount.toFixed(2),
                            currency_code: currencyCode,
                            checkout_variant: "paid",
                        },
                    ];

        if (paymentType === "paid" && selectedPackageInstallmentEnabled) {
            paymentOptions.push({
                type: "installment",
                label: "Pay in installments",
                amount_due_today:
                    previewInstallmentSummary?.first_payment_amount ??
                    (amount > 0 ? (amount / 2).toFixed(2) : "0.00"),
                currency_code: currencyCode,
                billing_day: billingDay,
                allowed_billing_days: selectedPackageAllowedBillingDays,
                installment_count: previewInstallmentCount,
                maximum_installment_count: maximumInstallmentCount,
                installment_maximum_count: maximumInstallmentCount,
                minimum_installment_count:
                    selectedPackageMinimumInstallmentCount,
                installment_count_selectable:
                    selectedPackageInstallmentCountSelectable,
                configured_installment_count:
                    selectedPackageConfiguredInstallmentCount,
                fixed_installment_count: selectedPackageFixedInstallmentCount,
                installment_calculation_method:
                    selectedPackageInstallmentCalculationMethod,
                installment_count_mode: selectedPackageInstallmentCountMode,
                summary: previewInstallmentSummary,
                checkout_variant: "paid",
            });
        }

        return {
            id: null,
            amount,
            currency_code: currencyCode,
            status: "preview",
            package: selectedPackage
                ? {
                      id: selectedPackage.id,
                      title: selectedPackage.title,
                      slug: selectedPackage.slug,
                      description: selectedPackage.description,
                      payment_type: paymentType,
                      price: amount,
                      minimum_donation_amount: Number(
                          selectedPackage.minimum_donation_amount ?? 0,
                      ),
                      suggested_donation_amount: Number(
                          selectedPackage.suggested_donation_amount ?? amount,
                      ),
                      currency_code: currencyCode,
                      installment_enabled: selectedPackageInstallmentEnabled,
                      installment_calculation_method:
                          selectedPackageInstallmentCalculationMethod,
                      installment_count_mode:
                          selectedPackageInstallmentCountMode,
                      installment_count:
                          selectedPackageConfiguredInstallmentCount,
                      installment_count_selectable:
                          selectedPackageInstallmentCountSelectable,
                      configured_installment_count:
                          selectedPackageConfiguredInstallmentCount,
                      minimum_installment_count:
                          selectedPackageMinimumInstallmentCount,
                      fixed_installment_count:
                          selectedPackageFixedInstallmentCount,
                      allowed_billing_days: selectedPackageAllowedBillingDays,
                      checkout_billing_day_options:
                          selectedPackageAllowedBillingDays,
                      installment_billing_day_options:
                          selectedPackageAllowedBillingDays,
                      installment_deadline_date:
                          selectedPackage.installment_deadline_date ?? null,
                      installment_maximum_count: maximumInstallmentCount,
                      maximum_installment_count: maximumInstallmentCount,
                  }
                : null,
            access_tier: selectedPackage?.access_tier ?? null,
            payment_options: paymentOptions,
            payment_method_options: [
                ...(paymentType === "free"
                    ? [{ value: "internal", label: "Continue" }]
                    : [{ value: "paypal", label: "PayPal" }]),
            ],
            installment_summary: previewInstallmentSummary,
            installment_summaries: billingDay
                ? {
                      [String(billingDay)]: previewInstallmentSummary,
                  }
                : {},
            installment_allowed_billing_days: selectedPackageAllowedBillingDays,
            installment_selected_billing_day: billingDay,
            installment_accepts_billing_day: selectedPackageInstallmentEnabled,
            installment_requires_billing_day_choice:
                selectedPackageAllowedBillingDays.length > 1,
            installment_billing_day_options: selectedPackageAllowedBillingDays,
            installment_billing_interval_unit: "MONTH",
            installment_billing_interval_count: 1,
            installment_calculation_method:
                selectedPackageInstallmentCalculationMethod,
            installment_count_mode: selectedPackageInstallmentCountMode,
            installment_count_selectable:
                selectedPackageInstallmentCountSelectable,
            configured_installment_count:
                selectedPackageConfiguredInstallmentCount,
            minimum_installment_count: selectedPackageMinimumInstallmentCount,
            fixed_installment_count: selectedPackageFixedInstallmentCount,
            installment_count:
                previewInstallmentSummary?.installment_count ??
                previewInstallmentCount,
            total_amount:
                previewInstallmentSummary?.total_amount ?? amount.toFixed(2),
            first_payment_amount:
                previewInstallmentSummary?.first_payment_amount ?? null,
            first_payment_date:
                previewInstallmentSummary?.first_payment_date ?? null,
            recurring_payment_amount:
                previewInstallmentSummary?.recurring_payment_amount ?? null,
            monthly_base_amount:
                previewInstallmentSummary?.monthly_base_amount ?? null,
            billing_day: previewInstallmentSummary?.billing_day ?? billingDay,
            recurring_due_dates:
                previewInstallmentSummary?.recurring_due_dates ?? [],
            available_recurring_due_dates:
                previewInstallmentSummary?.available_recurring_due_dates ?? [],
            schedule_breakdown:
                previewInstallmentSummary?.schedule_breakdown ?? [],
            final_due_at: previewInstallmentSummary?.final_due_at ?? null,
            installment_maximum_count: maximumInstallmentCount,
            maximum_installment_count: maximumInstallmentCount,
            installment_available_recurring_due_dates:
                previewInstallmentSummary?.available_recurring_due_dates ?? [],
            installment_deadline_date:
                selectedPackage?.installment_deadline_date ?? null,
            create_order_url: null,
            installment_approve_url: null,
            installment_status_url: null,
            paypal: {
                client_id: paypal?.client_id ?? null,
                client_token: paypal?.client_token ?? null,
                currency_code: paypal?.currency_code ?? currencyCode,
                components: paypal?.components ?? "buttons",
                intent: paypal?.intent ?? "capture",
                environment: paypal?.environment ?? null,
                subscription: paypal?.subscription ?? {
                    components: "buttons",
                    vault: "true",
                    intent: "subscription",
                },
            },
            is_preview: true,
        };
    }, [
        selectedPackage,
        selectedPackageAllowedBillingDays,
        selectedPackageInstallmentEnabled,
        selectedPackageInstallmentCalculationMethod,
        selectedPackageInstallmentCountMode,
        selectedPackageInstallmentCountSelectable,
        selectedPackageConfiguredInstallmentCount,
        selectedPackageMinimumInstallmentCount,
        selectedPackageMaximumInstallmentCount,
        selectedPackageFixedInstallmentCount,
        paypal,
    ]);

    const activeCheckout = checkout ?? previewCheckout;

    const setFieldContainerRef = (field, node) => {
        if (node) {
            fieldContainersRef.current[field] = node;
            return;
        }

        delete fieldContainersRef.current[field];
    };

    const focusFirstInvalidField = (field) => {
        const fieldElementId = FIELD_ELEMENT_IDS[field];
        const focusTarget = fieldElementId
            ? document.getElementById(fieldElementId)
            : null;
        const scrollTarget =
            fieldContainersRef.current[field] ?? focusTarget ?? null;

        if (scrollTarget && typeof scrollTarget.scrollIntoView === "function") {
            scrollTarget.scrollIntoView({
                behavior: "smooth",
                block: "center",
            });
        }

        if (focusTarget && typeof focusTarget.focus === "function") {
            window.setTimeout(() => {
                focusTarget.focus({ preventScroll: true });
            }, 220);
        }
    };

    const validateIdentityFields = ({
        shouldFocus = true,
        shouldSetErrors = true,
        values = dataRef.current,
    } = {}) => {
        const nextErrors = {};
        const emailValue = String(values.email ?? "")
            .trim()
            .toLowerCase();

        if (String(values.first_name ?? "").trim() === "") {
            nextErrors.first_name = "First name is required.";
        }

        if (String(values.last_name ?? "").trim() === "") {
            nextErrors.last_name = "Last name is required.";
        }

        if (emailValue === "") {
            nextErrors.email = "Email is required.";
        } else if (!isValidEmail(emailValue)) {
            nextErrors.email = "Enter a valid email address.";
        }

        if (String(values.phone_country_code ?? "").trim() === "") {
            nextErrors.phone_country_code = "Phone country code is required.";
        }

        if (String(values.phone_number ?? "").trim() === "") {
            nextErrors.phone_number = "Mobile phone is required.";
        }

        if (String(values.country ?? "").trim() === "") {
            nextErrors.country = "Country is required.";
        }

        if (String(values.package_id ?? "") === "") {
            nextErrors.package_id = "Package is required.";
        } else if (
            selectedPackagePaymentType === "paid" &&
            !selectedPackageHasPrice
        ) {
            nextErrors.package_id =
                "This package is not ready for checkout yet.";
        }

        if (shouldSetErrors) {
            setErrors((current) => ({
                ...current,
                ...nextErrors,
                general: "",
            }));
        }

        const firstInvalidField = FIELD_ORDER.find(
            (field) => nextErrors[field],
        );

        if (shouldFocus && firstInvalidField) {
            focusFirstInvalidField(firstInvalidField);
        }

        return {
            isValid: Object.keys(nextErrors).length === 0,
            nextErrors,
        };
    };

    const setFieldValue = (field, value) => {
        setData((current) => {
            const nextData = {
                ...current,
                [field]: value,
            };

            dataRef.current = nextData;

            return nextData;
        });

        setErrors((current) => ({
            ...current,
            [field]: "",
            general: "",
        }));

        if (checkout) {
            setCheckout(null);
        }
    };

    const prepareCheckout = async ({
        shouldFocus = true,
        shouldSetErrors = true,
    } = {}) => {
        if (processing) {
            return null;
        }

        if (checkout) {
            return checkout;
        }

        const currentData = dataRef.current;

        const validation = validateIdentityFields({
            shouldFocus,
            shouldSetErrors,
            values: currentData,
        });

        if (!validation.isValid) {
            return null;
        }

        setProcessing(true);

        if (shouldSetErrors) {
            setErrors({});
        }

        try {
            const response = await fetch(submit_url, {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-CSRF-TOKEN": getCsrfToken() ?? "",
                    "X-Requested-With": "XMLHttpRequest",
                },
                body: JSON.stringify(currentData),
            });

            const payload = await parseJsonSafely(response);

            if (!response.ok) {
                if (response.status === 422 && payload.errors) {
                    if (shouldSetErrors) {
                        setErrors(
                            Object.fromEntries(
                                Object.entries(payload.errors).map(
                                    ([key, value]) => [
                                        key,
                                        Array.isArray(value) ? value[0] : value,
                                    ],
                                ),
                            ),
                        );
                    }

                    return null;
                }

                if (shouldSetErrors) {
                    setErrors({
                        general:
                            payload.message ??
                            "The checkout flow could not be prepared. Please try again.",
                    });
                }

                return null;
            }

            if (!payload.checkout) {
                if (shouldSetErrors) {
                    setErrors({
                        general:
                            "The checkout flow was prepared, but the payment panel could not be opened. Please try again.",
                    });
                }

                return null;
            }

            setCheckout(payload.checkout);

            return payload.checkout;
        } catch {
            if (shouldSetErrors) {
                setErrors({
                    general:
                        "The checkout flow could not be reached right now. Please check your connection and try again.",
                });
            }

            return null;
        } finally {
            setProcessing(false);
        }
    };

    const submit = async (event) => {
        event.preventDefault();

        await prepareCheckout({
            shouldFocus: true,
        });
    };

    const resetCheckoutPreparation = () => {
        setCheckout(null);
        setErrors({});
    };

    return (
        <PublicFlowLayout
            title={packageTitle}
            showBackButton={!is_package_locked}
            heading={
                <span
                    className="block text-balance"
                    style={{
                        fontFamily: FONT_FAMILY,
                        fontSize: "clamp(26px, 3.4vw, 34px)",
                        fontWeight: 700,
                        lineHeight: 1.2,
                    }}
                >
                    <>
                        We Are Thrilled That You Will Be Joining Our{" "}
                        <YogaFXText text={packageTitle} />
                    </>
                </span>
            }
            description={
                <span
                    className="block text-balance"
                    style={{
                        fontFamily: FONT_FAMILY,
                        fontSize: "18px",
                        fontWeight: 500,
                        lineHeight: 1.6,
                    }}
                >
                    {`Let's Get Started!`}
                </span>
            }
            aside={<div className="space-y-6" />}
        >
            <div className="space-y-10" style={{ fontFamily: FONT_FAMILY }}>
                <form
                    id="scoreboard-form"
                    onSubmit={submit}
                    className="space-y-8"
                    style={{ fontFamily: FONT_FAMILY }}
                >
                    {errors.general && (
                        <div
                            className="rounded-xl border border-rose-400/30 bg-rose-500/10 px-5 py-4 text-sm font-medium text-rose-100"
                            style={{ fontFamily: FONT_FAMILY }}
                        >
                            {errors.general}
                        </div>
                    )}

                    {checkout ? (
                        <div className="flex justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={resetCheckoutPreparation}
                                className="rounded-[5px] border-white/20 bg-transparent px-2.5 py-2 text-sm font-medium text-white hover:bg-white/10"
                                style={{
                                    fontFamily: FONT_FAMILY,
                                    fontSize: "14px",
                                    fontWeight: 500,
                                }}
                            >
                                Edit Details
                            </Button>
                        </div>
                    ) : null}

                    <div className="grid gap-6 md:grid-cols-2">
                        <div
                            ref={(node) =>
                                setFieldContainerRef("first_name", node)
                            }
                        >
                            <InputLabel
                                htmlFor="first_name"
                                value="First Name"
                                className="text-sm font-medium text-white/90"
                                style={{
                                    fontFamily: FONT_FAMILY,
                                    fontSize: "14px",
                                    fontWeight: 500,
                                }}
                            />
                            <TextInput
                                id="first_name"
                                value={data.first_name}
                                disabled={isIdentityLocked}
                                className={`mt-2 block w-full ${PUBLIC_FORM_FIELD_CLASS}`}
                                style={{
                                    fontFamily: FONT_FAMILY,
                                    fontSize: "14px",
                                    fontWeight: 400,
                                }}
                                onChange={(event) =>
                                    setFieldValue(
                                        "first_name",
                                        event.target.value,
                                    )
                                }
                                required
                            />
                            <InputError
                                className="mt-2 text-sm font-medium text-rose-400"
                                style={{ fontFamily: FONT_FAMILY }}
                                message={errors.first_name}
                            />
                        </div>

                        <div
                            ref={(node) =>
                                setFieldContainerRef("last_name", node)
                            }
                        >
                            <InputLabel
                                htmlFor="last_name"
                                value="Last Name"
                                className="text-sm font-medium text-white/90"
                                style={{
                                    fontFamily: FONT_FAMILY,
                                    fontSize: "14px",
                                    fontWeight: 500,
                                }}
                            />
                            <TextInput
                                id="last_name"
                                value={data.last_name}
                                disabled={isIdentityLocked}
                                className={`mt-2 block w-full ${PUBLIC_FORM_FIELD_CLASS}`}
                                style={{
                                    fontFamily: FONT_FAMILY,
                                    fontSize: "14px",
                                    fontWeight: 400,
                                }}
                                onChange={(event) =>
                                    setFieldValue(
                                        "last_name",
                                        event.target.value,
                                    )
                                }
                                required
                            />
                            <InputError
                                className="mt-2 text-sm font-medium text-rose-400"
                                style={{ fontFamily: FONT_FAMILY }}
                                message={errors.last_name}
                            />
                        </div>

                        <div
                            className="md:col-span-2"
                            ref={(node) => setFieldContainerRef("email", node)}
                        >
                            <InputLabel
                                htmlFor="email"
                                value="Email"
                                className="text-sm font-medium text-white/90"
                                style={{
                                    fontFamily: FONT_FAMILY,
                                    fontSize: "14px",
                                    fontWeight: 500,
                                }}
                            />
                            <TextInput
                                id="email"
                                type="email"
                                value={data.email}
                                disabled={isIdentityLocked}
                                className={`mt-2 block w-full ${PUBLIC_FORM_FIELD_CLASS}`}
                                style={{
                                    fontFamily: FONT_FAMILY,
                                    fontSize: "14px",
                                    fontWeight: 400,
                                }}
                                onChange={(event) =>
                                    setFieldValue("email", event.target.value)
                                }
                                required
                            />
                            <InputError
                                className="mt-2 text-sm font-medium text-rose-400"
                                style={{ fontFamily: FONT_FAMILY }}
                                message={errors.email}
                            />
                        </div>

                        <div
                            className="md:col-span-2"
                            ref={(node) =>
                                setFieldContainerRef("phone_country_code", node)
                            }
                        >
                            <InputLabel
                                htmlFor="phone_number"
                                value="Mobile Phone"
                                className="text-sm font-medium text-white/90"
                                style={{
                                    fontFamily: FONT_FAMILY,
                                    fontSize: "14px",
                                    fontWeight: 500,
                                }}
                            />
                            <div className="mt-2 grid grid-cols-[128px_minmax(0,1fr)] items-start gap-4 sm:grid-cols-[160px_minmax(0,1fr)] md:grid-cols-[180px_minmax(0,1fr)]">
                                <FlagOptionSelect
                                    id="phone_country_code"
                                    value={data.phone_country_code}
                                    selectedOption={selectedPhoneCountryOption}
                                    options={phoneCountryCodeOptions}
                                    onChange={(option) =>
                                        setFieldValue(
                                            "phone_country_code",
                                            option.value,
                                        )
                                    }
                                    displayMode="phone-code"
                                    searchPlaceholder="Search phone code or country"
                                    disabled={isIdentityLocked}
                                    buttonClassName={`block w-full ${PUBLIC_FORM_FIELD_CLASS}`}
                                    buttonTextClassName="text-sm font-normal text-white"
                                    placeholderClassName="text-sm font-normal text-white/50"
                                    panelClassName="border-white/10 bg-[#161616] text-white"
                                    optionClassName="px-4 py-3 text-sm"
                                    optionActiveClassName="bg-white/10"
                                    optionSelectedClassName="text-[#DB202C]"
                                    optionTextClassName="text-sm font-normal text-white"
                                    chevronClassName="text-white/60"
                                    fallbackClassName="bg-white/10 text-white/70"
                                />
                                <TextInput
                                    id="phone_number"
                                    value={data.phone_number}
                                    disabled={isIdentityLocked}
                                    className={`block w-full ${PUBLIC_FORM_FIELD_CLASS}`}
                                    style={{
                                        fontFamily: FONT_FAMILY,
                                        fontSize: "14px",
                                        fontWeight: 400,
                                    }}
                                    onChange={(event) =>
                                        setFieldValue(
                                            "phone_number",
                                            normalizePhoneNumberInput(
                                                event.target.value,
                                            ),
                                        )
                                    }
                                    placeholder="81234567890"
                                    required
                                />
                            </div>
                            <InputError
                                className="mt-2 text-sm font-medium text-rose-400"
                                style={{ fontFamily: FONT_FAMILY }}
                                message={
                                    errors.phone_number ??
                                    errors.phone_country_code ??
                                    errors.phone
                                }
                            />
                        </div>

                        <div
                            className="md:col-span-2"
                            ref={(node) =>
                                setFieldContainerRef("country", node)
                            }
                        >
                            <InputLabel
                                htmlFor="country"
                                value="Country"
                                className="text-sm font-medium text-white/90"
                                style={{
                                    fontFamily: FONT_FAMILY,
                                    fontSize: "14px",
                                    fontWeight: 500,
                                }}
                            />
                            <div className="mt-2">
                                <FlagOptionSelect
                                    id="country"
                                    value={data.country}
                                    selectedOption={selectedCountryOption}
                                    options={countryOptions}
                                    onChange={(option) =>
                                        setFieldValue("country", option.value)
                                    }
                                    placeholder="Select a country"
                                    disabled={isIdentityLocked}
                                    buttonClassName={`block w-full ${PUBLIC_FORM_FIELD_CLASS}`}
                                    buttonTextClassName="text-sm font-normal text-white"
                                    placeholderClassName="text-sm font-normal text-white/50"
                                    panelClassName="border-white/10 bg-[#161616] text-white"
                                    optionClassName="px-4 py-3 text-sm"
                                    optionActiveClassName="bg-white/10"
                                    optionSelectedClassName="text-[#DB202C]"
                                    optionTextClassName="text-sm font-normal text-white"
                                    chevronClassName="text-white/60"
                                    fallbackClassName="bg-white/10 text-white/70"
                                />
                            </div>
                            <InputError
                                className="mt-2 text-sm font-medium text-rose-400"
                                style={{ fontFamily: FONT_FAMILY }}
                                message={errors.country}
                            />
                        </div>

                        <div className="md:col-span-2">
                            <InputLabel
                                htmlFor="package_id"
                                value="Package"
                                className="text-sm font-medium text-white/90"
                                style={{
                                    fontFamily: FONT_FAMILY,
                                    fontSize: "14px",
                                    fontWeight: 500,
                                }}
                            />
                            {is_package_locked && selectedPackage ? (
                                <div
                                    className="mt-2 min-h-[52px] rounded-[5px] bg-[#ffffff] px-5 py-4 text-black shadow-sm"
                                    style={{ fontFamily: FONT_FAMILY }}
                                >
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="min-w-0">
                                            <div
                                                className="text-sm font-medium text-black"
                                                style={{
                                                    fontFamily: FONT_FAMILY,
                                                    fontSize: "14px",
                                                    fontWeight: 500,
                                                }}
                                            >
                                                <YogaFXText text={selectedPackage.title} />
                                            </div>
                                        </div>
                                        <div
                                            className="shrink-0 text-right text-sm font-medium text-black"
                                            style={{
                                                fontFamily: FONT_FAMILY,
                                                fontSize: "14px",
                                                fontWeight: 500,
                                            }}
                                        >
                                            {selectedPackage.payment_type ===
                                            "free"
                                                ? "Free"
                                                : selectedPackage.payment_type ===
                                                    "donation"
                                                  ? `Minimum ${formatCurrency(
                                                        Number(
                                                            selectedPackage.minimum_donation_amount ??
                                                                0,
                                                        ),
                                                        selectedPackage.currency_code,
                                                    )}`
                                                  : Number(
                                                          selectedPackage.price,
                                                      ) > 0
                                                    ? formatCurrency(
                                                          selectedPackage.price,
                                                          selectedPackage.currency_code,
                                                      )
                                                    : "Price not set yet"}
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <select
                                    id="package_id"
                                    value={data.package_id}
                                    disabled={isIdentityLocked}
                                    onChange={(event) =>
                                        setFieldValue(
                                            "package_id",
                                            event.target.value,
                                        )
                                    }
                                    className="mt-2 block w-full min-h-[52px] rounded-[5px] border border-white/20 bg-black/20 px-4 py-3.5 text-sm font-normal text-white shadow-sm transition-all duration-200 focus:border-white/40 focus:ring-2 focus:ring-white/20 disabled:opacity-60"
                                    style={{
                                        fontFamily: FONT_FAMILY,
                                        fontSize: "14px",
                                        fontWeight: 400,
                                    }}
                                    required
                                >
                                    {packageOptions.map((pkg) => (
                                        <option
                                            key={pkg.id}
                                            value={pkg.id}
                                            className="bg-gray-900 text-white"
                                            style={{ fontFamily: FONT_FAMILY }}
                                        >
                                            {pkg.title}
                                            {pkg.access_tier?.name
                                                ? ` (${pkg.access_tier.name})`
                                                : ""}{" "}
                                            -{" "}
                                            {pkg.payment_type === "free"
                                                ? "Free"
                                                : pkg.payment_type ===
                                                    "donation"
                                                  ? `Minimum ${formatCurrency(
                                                        Number(
                                                            pkg.minimum_donation_amount ??
                                                                0,
                                                        ),
                                                        pkg.currency_code,
                                                    )}`
                                                  : Number(pkg.price) > 0
                                                    ? formatCurrency(
                                                          pkg.price,
                                                          pkg.currency_code,
                                                      )
                                                    : "Price not set yet"}
                                        </option>
                                    ))}
                                </select>
                            )}
                            <InputError
                                className="mt-2 text-sm font-medium text-rose-400"
                                style={{ fontFamily: FONT_FAMILY }}
                                message={errors.package_id}
                            />
                        </div>
                    </div>

                    <button type="submit" className="hidden" aria-hidden="true">
                        Prepare Checkout
                    </button>
                </form>

                <section className="space-y-6">
                    <PublicCheckoutPanel
                        checkout={activeCheckout}
                        isPreview={!checkout}
                        isPreparingCheckout={processing}
                        canInteractWithPayment={identityIsComplete}
                        onBeforePayment={prepareCheckout}
                    />
                </section>
            </div>
        </PublicFlowLayout>
    );
}
