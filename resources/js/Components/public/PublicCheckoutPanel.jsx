import InputError from "@/Components/InputError";
import { Button } from "@/Components/ui/button";
import { formatCurrency } from "@/lib/currency";
import { AlertCircle, LoaderCircle } from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";

const INITIAL_ERRORS = {
    billing_day: "",
    installment_count: "",
    terms_accepted: "",
    payment_method: "",
};

const FONT_FAMILY = "'Montserrat', sans-serif";
const PAYPAL_FULL_NAMESPACE = "paypalPayFullCheckout";
const PAYPAL_INSTALLMENT_NAMESPACE = "paypalInstallmentCheckout";

function firstErrorMessage(nextErrors) {
    return (
        Object.values(nextErrors).find(
            (message) => typeof message === "string" && message.length > 0,
        ) ?? ""
    );
}

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

function formatScheduleDate(value) {
    if (!value) {
        return "-";
    }

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat("en-US", {
        day: "numeric",
        month: "short",
        year: "numeric",
    }).format(parsed);
}

function formatBillingDayLabel(value) {
    return Number(value) === 1 ? "1st" : "15th";
}

function amountToCents(value) {
    return Math.round(Number(value ?? 0) * 100);
}

function centsToAmount(value) {
    return Number((Number(value ?? 0) / 100).toFixed(2));
}

function normalizeInstallmentCount(value) {
    const numericValue = Number(value);

    if (!Number.isFinite(numericValue)) {
        return null;
    }

    return Math.trunc(numericValue);
}

function normalizeMaximumInstallmentCount(value) {
    const numericValue = Number(value);

    if (!Number.isFinite(numericValue) || numericValue < 2) {
        return null;
    }

    return Math.trunc(numericValue);
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

function resolveBillingDay(summary, checkout, selectedPaymentOption) {
    const value =
        summary?.billing_day ??
        selectedPaymentOption?.billing_day ??
        checkout?.installment_selected_billing_day ??
        checkout?.installment_billing_day_options?.[0] ??
        checkout?.package?.installment_billing_day_options?.[0] ??
        checkout?.package?.allowed_billing_days?.[0] ??
        15;

    return Number(value) === 1 ? 1 : 15;
}

function formatInstallmentDateAmount(dueAt, amount, currencyCode) {
    const formattedAmount = formatCurrency(Number(amount ?? 0), currencyCode);

    if (!dueAt) {
        return `Date will be confirmed — ${formattedAmount}`;
    }

    return `${formatScheduleDate(dueAt)} — ${formattedAmount}`;
}

function resolveMaximumInstallmentCount(
    summary,
    checkout,
    selectedPaymentOption,
) {
    const candidates = [
        summary?.maximum_installment_count,
        summary?.installment_maximum_count,
        checkout?.maximum_installment_count,
        checkout?.installment_maximum_count,
        checkout?.package?.maximum_installment_count,
        checkout?.package?.installment_maximum_count,
        selectedPaymentOption?.maximum_installment_count,
        selectedPaymentOption?.installment_maximum_count,
        selectedPaymentOption?.installment_count,
        summary?.installment_count,
    ];

    for (const candidate of candidates) {
        const normalized = normalizeMaximumInstallmentCount(candidate);

        if (normalized) {
            return normalized;
        }
    }

    return 2;
}

function buildInstallmentCountOptions(
    summary,
    checkout,
    selectedPaymentOption,
) {
    const maximumInstallmentCount = resolveMaximumInstallmentCount(
        summary,
        checkout,
        selectedPaymentOption,
    );

    return Array.from(
        { length: maximumInstallmentCount - 1 },
        (_, index) => index + 2,
    );
}

function buildInstallmentSummaryForCount(
    summary,
    selectedInstallmentCount,
    checkout,
    selectedPaymentOption,
    activeBillingDay = null,
) {
    if (!summary) {
        return null;
    }

    const maximumInstallmentCount = resolveMaximumInstallmentCount(
        summary,
        checkout,
        selectedPaymentOption,
    );

    const normalizedInstallmentCount = normalizeInstallmentCount(
        selectedInstallmentCount,
    );

    if (
        !normalizedInstallmentCount ||
        normalizedInstallmentCount < 2 ||
        normalizedInstallmentCount > maximumInstallmentCount
    ) {
        return {
            ...summary,
            maximum_installment_count: maximumInstallmentCount,
            installment_maximum_count: maximumInstallmentCount,
        };
    }

    const totalAmountCents = amountToCents(summary.total_amount ?? 0);
    const recurringAmountCents = Math.floor(
        totalAmountCents / normalizedInstallmentCount,
    );
    const firstPaymentAmountCents =
        totalAmountCents -
        recurringAmountCents * (normalizedInstallmentCount - 1);

    const recurringAmount = centsToAmount(recurringAmountCents);
    const firstPaymentAmount = centsToAmount(firstPaymentAmountCents);

    const existingRecurringDueDates = Array.isArray(
        summary.available_recurring_due_dates,
    )
        ? summary.available_recurring_due_dates
        : Array.isArray(summary.recurring_due_dates)
          ? summary.recurring_due_dates
          : [];

    const billingDay =
        Number(activeBillingDay) === 1 || Number(activeBillingDay) === 15
            ? Number(activeBillingDay)
            : resolveBillingDay(summary, checkout, selectedPaymentOption);

    const fallbackRecurringDueDates = buildRecurringDueDates({
        billingDay,
        recurringCount: normalizedInstallmentCount - 1,
    });

    const selectedRecurringDueDates =
        fallbackRecurringDueDates.length > 0
            ? fallbackRecurringDueDates
            : Array.from(
                  { length: normalizedInstallmentCount - 1 },
                  (_, index) => existingRecurringDueDates[index] ?? null,
              );

    const finalDueAt =
        selectedRecurringDueDates[selectedRecurringDueDates.length - 1] ?? null;

    const scheduleBreakdown = [
        {
            cycle_number: 1,
            type: "first_payment",
            amount: firstPaymentAmount.toFixed(2),
            due_at: summary.first_payment_date ?? toDateString(new Date()),
            grace_deadline: null,
        },
        ...Array.from(
            { length: normalizedInstallmentCount - 1 },
            (_, index) => ({
                cycle_number: index + 2,
                type: "recurring",
                amount: recurringAmount.toFixed(2),
                due_at: selectedRecurringDueDates[index] ?? null,
                grace_deadline: null,
            }),
        ),
    ];

    return {
        ...summary,
        billing_day: billingDay,
        installment_count: normalizedInstallmentCount,
        maximum_installment_count: maximumInstallmentCount,
        installment_maximum_count: maximumInstallmentCount,
        first_payment_amount: firstPaymentAmount.toFixed(2),
        monthly_base_amount: recurringAmount.toFixed(2),
        recurring_payment_amount: recurringAmount.toFixed(2),
        recurring_due_dates: selectedRecurringDueDates,
        available_recurring_due_dates: selectedRecurringDueDates,
        final_due_at: finalDueAt,
        schedule_breakdown: scheduleBreakdown,
    };
}

function buildPreviewInstallmentSummary(checkout, selectedPaymentOption) {
    const totalAmount = Number(checkout.amount ?? 0);

    if (!totalAmount || totalAmount <= 0) {
        return null;
    }

    const maximumInstallmentCount = resolveMaximumInstallmentCount(
        null,
        checkout,
        selectedPaymentOption,
    );

    const billingDay = resolveBillingDay(null, checkout, selectedPaymentOption);

    const recurringDueDates = buildRecurringDueDates({
        billingDay,
        recurringCount: maximumInstallmentCount - 1,
    });

    const recurringAmountCents = Math.floor(
        amountToCents(totalAmount) / maximumInstallmentCount,
    );
    const firstPaymentAmountCents =
        amountToCents(totalAmount) -
        recurringAmountCents * (maximumInstallmentCount - 1);

    const recurringAmount = centsToAmount(recurringAmountCents);
    const firstPaymentAmount = centsToAmount(firstPaymentAmountCents);

    return {
        billing_day: billingDay,
        total_amount: totalAmount.toFixed(2),
        first_payment_amount: firstPaymentAmount.toFixed(2),
        recurring_payment_amount: recurringAmount.toFixed(2),
        monthly_base_amount: recurringAmount.toFixed(2),
        installment_count: maximumInstallmentCount,
        maximum_installment_count: maximumInstallmentCount,
        installment_maximum_count: maximumInstallmentCount,
        recurring_due_dates: recurringDueDates,
        available_recurring_due_dates: recurringDueDates,
        schedule_breakdown: [
            {
                cycle_number: 1,
                type: "first_payment",
                amount: firstPaymentAmount.toFixed(2),
                due_at: toDateString(new Date()),
                grace_deadline: null,
            },
            ...Array.from(
                { length: maximumInstallmentCount - 1 },
                (_, index) => ({
                    cycle_number: index + 2,
                    type: "recurring",
                    amount: recurringAmount.toFixed(2),
                    due_at: recurringDueDates[index] ?? null,
                    grace_deadline: null,
                }),
            ),
        ],
        final_due_at: recurringDueDates[recurringDueDates.length - 1] ?? null,
    };
}

function clearPayPalContainer(containerRef) {
    if (containerRef?.current) {
        containerRef.current.innerHTML = "";
    }
}

function getPayPalNamespace(namespace) {
    if (typeof window === "undefined") {
        return null;
    }

    return window[namespace] ?? null;
}

export default function PublicCheckoutPanel({
    checkout,
    isPreview = false,
    isPreparingCheckout = false,
    canInteractWithPayment = false,
    onBeforePayment = null,
}) {
    const rawPaymentOptions = Array.isArray(checkout.payment_options)
        ? checkout.payment_options
        : [];

    const paypalConfig = checkout.paypal ?? {};
    const installmentSummaries = checkout.installment_summaries ?? {};

    const rawIncludesInstallment = rawPaymentOptions.some(
        (option) => option.type === "installment",
    );

    const hasInstallmentSummary =
        checkout.installment_summary !== null &&
        checkout.installment_summary !== undefined;

    const hasInstallmentSummaries =
        Object.keys(installmentSummaries ?? {}).length > 0;

    const packageInstallmentEnabled =
        checkout.package?.installment_enabled === true ||
        checkout.installment_accepts_billing_day === true ||
        (Array.isArray(checkout.installment_billing_day_options) &&
            checkout.installment_billing_day_options.length > 0);

    const installmentIsAvailable =
        rawIncludesInstallment &&
        (isPreview ||
            hasInstallmentSummary ||
            hasInstallmentSummaries ||
            packageInstallmentEnabled);

    const paymentOptions = rawPaymentOptions.filter((option) => {
        if (option.type !== "installment") {
            return true;
        }

        return installmentIsAvailable;
    });

    const mockAvailable = (checkout.payment_method_options ?? []).some(
        (option) => option.value === "mock",
    );

    const installmentAllowedBillingDays = Array.isArray(
        checkout.installment_billing_day_options,
    )
        ? checkout.installment_billing_day_options
        : [];

    const installmentAcceptsBillingDay =
        checkout.installment_accepts_billing_day === true;

    const installmentRequiresBillingDayChoice =
        checkout.installment_requires_billing_day_choice === true;

    const installmentIntervalUnit = String(
        checkout.installment_billing_interval_unit ?? "MONTH",
    ).toUpperCase();

    const initialBillingDay =
        checkout.installment_selected_billing_day ??
        installmentAllowedBillingDays[0] ??
        15;

    const initialInstallmentSummary =
        installmentSummaries[String(initialBillingDay)] ??
        checkout.installment_summary ??
        null;

    const initialInstallmentOptions = buildInstallmentCountOptions(
        initialInstallmentSummary,
        checkout,
        null,
    );

    const [paymentType, setPaymentType] = useState(
        paymentOptions[0]?.type ?? "pay_full",
    );

    const [billingDay, setBillingDay] = useState(initialBillingDay);

    const [selectedInstallmentCount, setSelectedInstallmentCount] = useState(
        initialInstallmentOptions[0] ?? 2,
    );

    const [formData, setFormData] = useState({
        terms_accepted: false,
    });

    const formDataRef = useRef(formData);
    const [fieldErrors, setFieldErrors] = useState(INITIAL_ERRORS);
    const [generalError, setGeneralError] = useState("");
    const [payFullSdkReady, setPayFullSdkReady] = useState(false);
    const [installmentSdkReady, setInstallmentSdkReady] = useState(false);
    const [payFullSdkError, setPayFullSdkError] = useState("");
    const [installmentSdkError, setInstallmentSdkError] = useState("");
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [isConfirmingPayment, setIsConfirmingPayment] = useState(false);
    const [debugInfo, setDebugInfo] = useState(null);
    const [installmentSession, setInstallmentSession] = useState(null);
    const [installmentStatus, setInstallmentStatus] = useState(null);
    const [installmentApprovalMessage, setInstallmentApprovalMessage] =
        useState("");

    const payFullButtonsRef = useRef(null);
    const installmentButtonsRef = useRef(null);
    const activeOrderRef = useRef(null);
    const installmentSessionRef = useRef(installmentSession);
    const checkoutForPaymentRef = useRef(checkout);

    const selectedPaymentOption =
        paymentOptions.find((option) => option.type === paymentType) ??
        paymentOptions[0] ??
        null;

    const isInstallmentSelected = paymentType === "installment";

    const baseInstallmentSummary =
        installmentSummaries[String(billingDay)] ??
        selectedPaymentOption?.summary ??
        checkout.installment_summary ??
        (isPreview && isInstallmentSelected
            ? buildPreviewInstallmentSummary(checkout, selectedPaymentOption)
            : null);

    const availableInstallmentCounts = buildInstallmentCountOptions(
        baseInstallmentSummary,
        checkout,
        selectedPaymentOption,
    );

    const minimumInstallmentCount = availableInstallmentCounts[0] ?? 2;

    const maximumInstallmentCount =
        availableInstallmentCounts[availableInstallmentCounts.length - 1] ?? 2;

    const sliderProgressPercent =
        maximumInstallmentCount > minimumInstallmentCount
            ? ((selectedInstallmentCount - minimumInstallmentCount) /
                  (maximumInstallmentCount - minimumInstallmentCount)) *
              100
            : 0;

    const activeInstallmentSummary = isInstallmentSelected
        ? buildInstallmentSummaryForCount(
              baseInstallmentSummary,
              selectedInstallmentCount,
              checkout,
              selectedPaymentOption,
              billingDay,
          )
        : baseInstallmentSummary;

    const amountDueToday = Number(
        isInstallmentSelected
            ? (activeInstallmentSummary?.first_payment_amount ??
                  selectedPaymentOption?.amount_due_today ??
                  checkout.amount ??
                  0)
            : (selectedPaymentOption?.amount_due_today ?? checkout.amount ?? 0),
    );

    const activeCurrencyCode =
        selectedPaymentOption?.currency_code ?? checkout.currency_code;

    const recurringAmount = Number(
        activeInstallmentSummary?.recurring_payment_amount ??
            selectedPaymentOption?.recurring_amount ??
            0,
    );

    const installmentCount = Number(
        isInstallmentSelected
            ? selectedInstallmentCount
            : (selectedPaymentOption?.installment_count ??
                  activeInstallmentSummary?.installment_count ??
                  0),
    );

    const finalDueAt =
        activeInstallmentSummary?.final_due_at ??
        selectedPaymentOption?.final_due_at ??
        null;

    const installmentScheduleBreakdown = Array.isArray(
        activeInstallmentSummary?.schedule_breakdown,
    )
        ? activeInstallmentSummary.schedule_breakdown
        : [];

    const nextInstallment =
        installmentScheduleBreakdown.find(
            (item) => item.type === "recurring",
        ) ??
        (activeInstallmentSummary?.recurring_due_dates?.[0]
            ? {
                  amount:
                      activeInstallmentSummary?.recurring_payment_amount ??
                      recurringAmount,
                  due_at: activeInstallmentSummary.recurring_due_dates[0],
              }
            : null);

    const lastInstallment =
        installmentScheduleBreakdown
            .filter((item) => item.type === "recurring")
            .at(-1) ??
        installmentScheduleBreakdown[installmentScheduleBreakdown.length - 1] ??
        null;

    const nextInstallmentAmount = Number(
        nextInstallment?.amount ??
            activeInstallmentSummary?.recurring_payment_amount ??
            recurringAmount ??
            amountDueToday,
    );

    const lastInstallmentAmount = Number(
        lastInstallment?.amount ??
            activeInstallmentSummary?.recurring_payment_amount ??
            recurringAmount ??
            amountDueToday,
    );

    const showBillingDaySelector =
        isInstallmentSelected &&
        installmentAcceptsBillingDay &&
        installmentRequiresBillingDayChoice &&
        installmentAllowedBillingDays.length > 1;

    const usesMonthlyInstallmentSchedule = installmentIntervalUnit === "MONTH";
    const canUseMock = mockAvailable && !isInstallmentSelected && !isPreview;

    const paymentButtonGridClass =
        paymentOptions.length > 1 ? "grid gap-3 sm:grid-cols-2" : "grid gap-3";

    const payFullScriptUrl = useMemo(() => {
        const params = new URLSearchParams({
            "client-id": paypalConfig.client_id ?? "",
            components: "buttons",
            currency: paypalConfig.currency_code ?? checkout.currency_code,
            intent: paypalConfig.intent ?? "capture",
            "enable-funding": "card",
        });

        return `https://www.paypal.com/sdk/js?${params.toString()}`;
    }, [
        checkout.currency_code,
        paypalConfig.client_id,
        paypalConfig.currency_code,
        paypalConfig.intent,
    ]);

    const installmentScriptUrl = useMemo(() => {
        const params = new URLSearchParams({
            "client-id": paypalConfig.client_id ?? "",
            components: "buttons",
            vault: "true",
            intent: "subscription",
        });

        return `https://www.paypal.com/sdk/js?${params.toString()}`;
    }, [paypalConfig.client_id]);

    const shouldRenderPayFullButtons =
        !isInstallmentSelected && Boolean(paypalConfig.client_id);

    const shouldRenderInstallmentButtons =
        isInstallmentSelected &&
        Boolean(paypalConfig.client_id) &&
        !installmentSession?.provider_subscription_id;

    const visibleSdkError = isInstallmentSelected
        ? shouldRenderInstallmentButtons
            ? installmentSdkError
            : ""
        : payFullSdkError;

    const paymentIsLocked =
        !canInteractWithPayment || isPreparingCheckout || isConfirmingPayment;

    useEffect(() => {
        checkoutForPaymentRef.current = checkout;
    }, [checkout]);

    useEffect(() => {
        if (!paymentOptions.some((option) => option.type === paymentType)) {
            setPaymentType(paymentOptions[0]?.type ?? "pay_full");
        }
    }, [paymentOptions, paymentType]);

    useEffect(() => {
        setBillingDay(initialBillingDay);
    }, [initialBillingDay]);

    useEffect(() => {
        installmentSessionRef.current = installmentSession;
    }, [installmentSession]);

    useEffect(() => {
        if (!isInstallmentSelected) {
            setInstallmentApprovalMessage("");
            setInstallmentStatus(null);
            setIsConfirmingPayment(false);
        }
    }, [isInstallmentSelected]);

    useEffect(() => {
        if (!isInstallmentSelected || availableInstallmentCounts.length === 0) {
            return;
        }

        setSelectedInstallmentCount((current) => {
            const normalizedCurrent = normalizeInstallmentCount(current);

            if (
                normalizedCurrent &&
                availableInstallmentCounts.includes(normalizedCurrent)
            ) {
                return normalizedCurrent;
            }

            return availableInstallmentCounts[0];
        });
    }, [
        isInstallmentSelected,
        billingDay,
        baseInstallmentSummary,
        availableInstallmentCounts.join(","),
    ]);

    useEffect(() => {
        if (!isInstallmentSelected) {
            return;
        }

        if (installmentSession) {
            setInstallmentSession(null);
            installmentSessionRef.current = null;
            setInstallmentStatus(null);
            setInstallmentApprovalMessage("");
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [billingDay, selectedInstallmentCount]);

    useEffect(() => {
        if (!paypalConfig.client_id) {
            setPayFullSdkError("PayPal client configuration is missing.");
            return undefined;
        }

        let cancelled = false;
        let existingScript = document.querySelector(
            'script[data-paypal-checkout-sdk="pay_full"]',
        );
        const existingSrc = existingScript?.getAttribute("src") ?? "";

        if (
            existingScript &&
            existingSrc === payFullScriptUrl &&
            getPayPalNamespace(PAYPAL_FULL_NAMESPACE)
        ) {
            setPayFullSdkReady(true);
            setPayFullSdkError("");
            return undefined;
        }

        const handleReady = () => {
            if (!cancelled) {
                setPayFullSdkReady(true);
                setPayFullSdkError("");
            }
        };

        const handleError = () => {
            if (!cancelled) {
                setPayFullSdkError(
                    "PayPal checkout could not be loaded right now. Please refresh and try again.",
                );
            }
        };

        if (existingScript && existingSrc !== payFullScriptUrl) {
            existingScript.remove();
            existingScript = null;
            delete window[PAYPAL_FULL_NAMESPACE];
            setPayFullSdkReady(false);
        }

        if (existingScript) {
            existingScript.addEventListener("load", handleReady);
            existingScript.addEventListener("error", handleError);

            return () => {
                cancelled = true;
                existingScript.removeEventListener("load", handleReady);
                existingScript.removeEventListener("error", handleError);
            };
        }

        const script = document.createElement("script");
        script.src = payFullScriptUrl;
        script.async = true;
        script.dataset.paypalCheckoutSdk = "pay_full";
        script.dataset.namespace = PAYPAL_FULL_NAMESPACE;
        script.setAttribute("data-namespace", PAYPAL_FULL_NAMESPACE);
        script.addEventListener("load", handleReady);
        script.addEventListener("error", handleError);
        document.body.appendChild(script);

        return () => {
            cancelled = true;
            script.removeEventListener("load", handleReady);
            script.removeEventListener("error", handleError);
        };
    }, [payFullScriptUrl, paypalConfig.client_id]);

    useEffect(() => {
        if (!paypalConfig.client_id || !shouldRenderInstallmentButtons) {
            return undefined;
        }

        let cancelled = false;
        let existingScript = document.querySelector(
            'script[data-paypal-checkout-sdk="installment"]',
        );
        const existingSrc = existingScript?.getAttribute("src") ?? "";

        if (
            existingScript &&
            existingSrc === installmentScriptUrl &&
            getPayPalNamespace(PAYPAL_INSTALLMENT_NAMESPACE)
        ) {
            setInstallmentSdkReady(true);
            setInstallmentSdkError("");
            return undefined;
        }

        const handleReady = () => {
            if (!cancelled) {
                setInstallmentSdkReady(true);
                setInstallmentSdkError("");
            }
        };

        const handleError = () => {
            if (!cancelled) {
                setInstallmentSdkError(
                    "PayPal installment approval could not be loaded right now. Please refresh and try again.",
                );
            }
        };

        if (existingScript && existingSrc !== installmentScriptUrl) {
            existingScript.remove();
            existingScript = null;
            delete window[PAYPAL_INSTALLMENT_NAMESPACE];
            setInstallmentSdkReady(false);
        }

        if (existingScript) {
            existingScript.addEventListener("load", handleReady);
            existingScript.addEventListener("error", handleError);

            return () => {
                cancelled = true;
                existingScript.removeEventListener("load", handleReady);
                existingScript.removeEventListener("error", handleError);
            };
        }

        const script = document.createElement("script");
        script.src = installmentScriptUrl;
        script.async = true;
        script.dataset.paypalCheckoutSdk = "installment";
        script.dataset.namespace = PAYPAL_INSTALLMENT_NAMESPACE;
        script.setAttribute("data-namespace", PAYPAL_INSTALLMENT_NAMESPACE);
        script.addEventListener("load", handleReady);
        script.addEventListener("error", handleError);
        document.body.appendChild(script);

        return () => {
            cancelled = true;
            script.removeEventListener("load", handleReady);
            script.removeEventListener("error", handleError);
        };
    }, [
        installmentScriptUrl,
        paypalConfig.client_id,
        shouldRenderInstallmentButtons,
    ]);

    useEffect(() => {
        if (!shouldRenderPayFullButtons || !payFullSdkReady) {
            return undefined;
        }

        const paypal = getPayPalNamespace(PAYPAL_FULL_NAMESPACE);

        if (!paypal) {
            return undefined;
        }

        clearPayPalContainer(payFullButtonsRef);

        if (typeof paypal.Buttons !== "function") {
            setGeneralError(
                "The official PayPal button API is not available in the loaded SDK.",
            );
            return undefined;
        }

        const buttons = paypal.Buttons({
            style: {
                layout: "vertical",
                shape: "rect",
                color: "gold",
                label: "buynow",
                height: 48,
            },
            onClick: async (_data, actions) => {
                setGeneralError("");
                setDebugInfo(null);
                setIsConfirmingPayment(false);

                if (!validateCheckoutFields()) {
                    return actions.reject();
                }

                const preparedCheckout = await prepareRealCheckoutForPreview();

                if (!preparedCheckout?.create_order_url) {
                    return actions.reject();
                }

                return actions.resolve();
            },
            createOrder: async () => {
                const createdOrder = await createOrderSession("paypal");

                if (!createdOrder?.order_id) {
                    throw new Error("order-id-missing");
                }

                return createdOrder.order_id;
            },
            onApprove: async (data) => {
                await captureApprovedOrder(data.orderID);
            },
            onCancel: () => {
                handleDismissedOrder();
            },
            onError: () => {
                setGeneralError(
                    "PayPal could not start the embedded checkout flow. Please try again.",
                );
                setIsConfirmingPayment(false);
                setIsSubmitting(false);
            },
        });

        if (buttons.isEligible() && payFullButtonsRef.current) {
            clearPayPalContainer(payFullButtonsRef);
            buttons.render(payFullButtonsRef.current).catch(() => {
                setGeneralError(
                    "The official PayPal button could not be rendered. Please refresh and try again.",
                );
            });
        } else {
            setGeneralError(
                "The official PayPal button is not eligible for this merchant context right now.",
            );
        }

        return () => {
            clearPayPalContainer(payFullButtonsRef);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        shouldRenderPayFullButtons,
        payFullSdkReady,
        checkout.create_order_url,
        isPreview,
    ]);

    useEffect(() => {
        if (!shouldRenderInstallmentButtons || !installmentSdkReady) {
            return undefined;
        }

        const paypal = getPayPalNamespace(PAYPAL_INSTALLMENT_NAMESPACE);

        if (!paypal) {
            return undefined;
        }

        clearPayPalContainer(installmentButtonsRef);

        if (typeof paypal.Buttons !== "function") {
            setGeneralError(
                "The official PayPal subscription button API is not available in the loaded SDK.",
            );
            return undefined;
        }

        const buttons = paypal.Buttons({
            style: {
                layout: "vertical",
                shape: "rect",
                color: "gold",
                label: "paypal",
                height: 48,
            },
            onClick: async (_data, actions) => {
                setGeneralError("");
                setInstallmentApprovalMessage("");
                setDebugInfo(null);
                setIsConfirmingPayment(false);

                if (!validateCheckoutFields()) {
                    return actions.reject();
                }

                const preparedCheckout = await prepareRealCheckoutForPreview();

                if (!preparedCheckout?.create_order_url) {
                    return actions.reject();
                }

                return actions.resolve();
            },
            createSubscription: async (_data, actions) => {
                const session =
                    installmentSessionRef.current ??
                    (await createOrderSession("paypal"));

                if (!session?.provider_plan_id) {
                    throw new Error("subscription-plan-missing");
                }

                if (session.provider_subscription_id) {
                    throw new Error("subscription-already-attached");
                }

                return actions.subscription.create({
                    plan_id: session.provider_plan_id,
                });
            },
            onApprove: async (data) => {
                await attachApprovedSubscription(data.subscriptionID);
            },
            onCancel: () => {
                setGeneralError(
                    "The PayPal installment approval popup was cancelled.",
                );
                setIsConfirmingPayment(false);
                setIsSubmitting(false);
            },
            onError: () => {
                setGeneralError(
                    "PayPal could not start the subscription approval popup. Please try again.",
                );
                setIsConfirmingPayment(false);
                setIsSubmitting(false);
            },
        });

        if (buttons.isEligible() && installmentButtonsRef.current) {
            clearPayPalContainer(installmentButtonsRef);
            buttons.render(installmentButtonsRef.current).catch(() => {
                setGeneralError(
                    "The PayPal installment button could not be rendered. Please refresh and try again.",
                );
            });
        } else {
            setGeneralError(
                "The PayPal installment button is not eligible for this merchant context right now.",
            );
        }

        return () => {
            clearPayPalContainer(installmentButtonsRef);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        installmentSdkReady,
        shouldRenderInstallmentButtons,
        checkout.create_order_url,
        isPreview,
    ]);

    useEffect(() => {
        if (isInstallmentSelected) {
            clearPayPalContainer(payFullButtonsRef);
            return;
        }

        clearPayPalContainer(installmentButtonsRef);
    }, [isInstallmentSelected]);

    useEffect(() => {
        const checkoutForPayment = checkoutForPaymentRef.current;

        if (
            isPreview ||
            !isInstallmentSelected ||
            !installmentSession?.provider_subscription_id ||
            !checkoutForPayment?.installment_status_url
        ) {
            return undefined;
        }

        let cancelled = false;

        const pollInstallmentStatus = async () => {
            try {
                const response = await fetch(
                    checkoutForPayment.installment_status_url,
                    {
                        method: "GET",
                        credentials: "same-origin",
                        headers: {
                            Accept: "application/json",
                            "X-Requested-With": "XMLHttpRequest",
                        },
                    },
                );

                const payload = await parseJsonSafely(response);

                if (cancelled || !response.ok) {
                    return;
                }

                setInstallmentStatus(payload);

                if (payload.onboarding_ready && payload.onboarding_url) {
                    setIsConfirmingPayment(true);
                    window.location.assign(payload.onboarding_url);
                    return;
                }

                setIsConfirmingPayment(true);

                if (payload.message) {
                    setInstallmentApprovalMessage(payload.message);
                }
            } catch {
                if (!cancelled) {
                    setInstallmentApprovalMessage(
                        "PayPal approval received. We are still confirming your first payment.",
                    );
                }
            }
        };

        pollInstallmentStatus();
        const intervalId = window.setInterval(pollInstallmentStatus, 4000);

        return () => {
            cancelled = true;
            window.clearInterval(intervalId);
        };
    }, [isPreview, installmentSession, isInstallmentSelected]);

    const setFieldValue = (field, value) => {
        setFormData((current) => {
            const updated = { ...current, [field]: value };
            formDataRef.current = updated;
            return updated;
        });

        setFieldErrors((current) => ({
            ...current,
            [field]: "",
        }));

        setGeneralError("");
    };

    const setValidationState = (nextErrors, fallbackMessage) => {
        setFieldErrors(nextErrors);
        setGeneralError(firstErrorMessage(nextErrors) || fallbackMessage);
    };

    const validateCheckoutFields = () => {
        const nextErrors = { ...INITIAL_ERRORS };
        const currentData = formDataRef.current;

        if (isInstallmentSelected && activeInstallmentSummary) {
            if (installmentAcceptsBillingDay && !billingDay) {
                nextErrors.billing_day =
                    "Please select your preferred billing day.";
            }

            const normalizedInstallmentCount = normalizeInstallmentCount(
                selectedInstallmentCount,
            );

            if (availableInstallmentCounts.length > 0) {
                if (!normalizedInstallmentCount) {
                    nextErrors.installment_count =
                        "Please select your installment count.";
                } else if (normalizedInstallmentCount < 2) {
                    nextErrors.installment_count =
                        "Installment count must be at least 2.";
                } else if (
                    maximumInstallmentCount > 0 &&
                    normalizedInstallmentCount > maximumInstallmentCount
                ) {
                    nextErrors.installment_count = `Installment count cannot be greater than ${maximumInstallmentCount}.`;
                }
            }
        }

        if (!currentData.terms_accepted) {
            nextErrors.terms_accepted = "You must agree before continuing.";
        }

        const isValid = !Object.values(nextErrors).some(Boolean);

        if (!isValid) {
            setValidationState(
                nextErrors,
                "Please complete the required checkout details and agree to terms before paying.",
            );
        }

        return isValid;
    };

    const prepareRealCheckoutForPreview = async () => {
        const cachedCheckout = checkoutForPaymentRef.current;

        if (cachedCheckout?.create_order_url) {
            return cachedCheckout;
        }

        if (!isPreview) {
            checkoutForPaymentRef.current = checkout;
            return checkout;
        }

        if (typeof onBeforePayment !== "function") {
            setGeneralError(
                "The payment panel is not connected to the checkout preparation flow.",
            );

            return null;
        }

        setGeneralError("");
        setDebugInfo(null);

        const preparedCheckout = await onBeforePayment({
            shouldFocus: true,
            shouldSetErrors: true,
        });

        if (!preparedCheckout?.create_order_url) {
            setGeneralError(
                "Please complete your personal details before continuing to payment.",
            );

            return null;
        }

        checkoutForPaymentRef.current = preparedCheckout;

        return preparedCheckout;
    };

    const createOrderSession = async (paymentMethod) => {
        if (!validateCheckoutFields()) {
            setIsSubmitting(false);
            throw new Error("Checkout form is incomplete.");
        }

        setIsSubmitting(true);
        setGeneralError("");
        setDebugInfo(null);

        const checkoutForPayment = await prepareRealCheckoutForPreview();

        if (!checkoutForPayment?.create_order_url) {
            setGeneralError(
                "Please complete your personal details before continuing to payment.",
            );
            setIsConfirmingPayment(false);
            setIsSubmitting(false);
            throw new Error("checkout-not-ready");
        }

        const currentData = formDataRef.current;

        const response = await fetch(checkoutForPayment.create_order_url, {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-CSRF-TOKEN": getCsrfToken() ?? "",
                "X-Requested-With": "XMLHttpRequest",
            },
            body: JSON.stringify({
                payment_type: paymentType,
                payment_method: paymentMethod,
                checkout_mode: "paypal",
                billing_day:
                    isInstallmentSelected && installmentAcceptsBillingDay
                        ? Number(billingDay)
                        : null,
                installment_count:
                    isInstallmentSelected &&
                    availableInstallmentCounts.length > 0
                        ? Number(selectedInstallmentCount)
                        : null,
                terms_accepted: currentData.terms_accepted,
            }),
        });

        const payload = await parseJsonSafely(response);

        if (!response.ok) {
            const nextDebugInfo = {
                stage: "create_order_error",
                http_status: response.status,
                payload,
                checkout_mode: "paypal",
                payment_method: paymentMethod,
            };

            setDebugInfo(nextDebugInfo);

            if (payload.errors) {
                setFieldErrors((current) => ({
                    ...current,
                    ...Object.fromEntries(
                        Object.entries(payload.errors).map(([key, value]) => [
                            key,
                            Array.isArray(value) ? value[0] : value,
                        ]),
                    ),
                }));
            }

            setGeneralError(
                payload.message ??
                    "The checkout session could not be created. Please try again.",
            );
            setIsConfirmingPayment(false);
            setIsSubmitting(false);
            throw new Error("create-order");
        }

        if (payload.redirect_url && payload.status === "success") {
            setIsConfirmingPayment(true);
            window.location.assign(payload.redirect_url);
            return payload;
        }

        if (payload.flow === "subscription") {
            activeOrderRef.current = null;
            setInstallmentSession(payload);
            installmentSessionRef.current = payload;
            setInstallmentStatus(null);
            setInstallmentApprovalMessage("");
            setIsSubmitting(false);
            return payload;
        }

        setInstallmentSession(null);
        installmentSessionRef.current = null;
        activeOrderRef.current = payload;
        setIsSubmitting(false);
        return payload;
    };

    const attachApprovedSubscription = async (providerSubscriptionId) => {
        const activeSubscription = installmentSessionRef.current;
        const checkoutForPayment = checkoutForPaymentRef.current;

        if (
            !activeSubscription?.payment_subscription_id ||
            !checkoutForPayment?.installment_approve_url
        ) {
            setGeneralError(
                "The installment approval route was not prepared correctly.",
            );
            setIsConfirmingPayment(false);
            setIsSubmitting(false);
            return;
        }

        setIsSubmitting(true);
        setGeneralError("");
        setInstallmentApprovalMessage("");

        const response = await fetch(
            checkoutForPayment.installment_approve_url,
            {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-CSRF-TOKEN": getCsrfToken() ?? "",
                    "X-Requested-With": "XMLHttpRequest",
                },
                body: JSON.stringify({
                    payment_subscription_id:
                        activeSubscription.payment_subscription_id,
                    provider_subscription_id: providerSubscriptionId,
                }),
            },
        );

        const payload = await parseJsonSafely(response);

        if (!response.ok) {
            setDebugInfo({
                stage: "attach_subscription_error",
                http_status: response.status,
                payload,
                payment_subscription_id:
                    activeSubscription.payment_subscription_id,
            });
            setGeneralError(
                payload.message ??
                    "The PayPal installment approval could not be attached to this checkout.",
            );
            setIsConfirmingPayment(false);
            setIsSubmitting(false);
            return;
        }

        const nextSession = {
            ...activeSubscription,
            provider_subscription_id:
                payload.provider_subscription_id ?? providerSubscriptionId,
        };

        installmentSessionRef.current = nextSession;
        setInstallmentSession(nextSession);
        setInstallmentStatus(payload);
        setInstallmentApprovalMessage(
            payload.message ??
                "PayPal approval received. We are confirming your first payment now.",
        );
        setIsConfirmingPayment(true);
        setIsSubmitting(false);
    };

    const captureApprovedOrder = async (orderId) => {
        setIsConfirmingPayment(true);

        const activeOrder = activeOrderRef.current;

        if (!activeOrder?.capture_url) {
            setGeneralError(
                "The payment capture route was not prepared correctly.",
            );
            setIsConfirmingPayment(false);
            setIsSubmitting(false);
            return;
        }

        const response = await fetch(activeOrder.capture_url, {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-CSRF-TOKEN": getCsrfToken() ?? "",
                "X-Requested-With": "XMLHttpRequest",
            },
            body: JSON.stringify({ order_id: orderId }),
        });

        const payload = await parseJsonSafely(response);
        const redirectUrl = payload.redirect_url;

        if (redirectUrl) {
            window.location.assign(redirectUrl);
            return;
        }

        setGeneralError(
            payload.message ??
                "The payment was processed, but the next onboarding step could not be opened automatically.",
        );
        setIsConfirmingPayment(false);
        setIsSubmitting(false);
    };

    const handleDismissedOrder = () => {
        activeOrderRef.current = null;
        setDebugInfo(null);
        setGeneralError("");
        setIsConfirmingPayment(false);
        setIsSubmitting(false);
    };

    const runMockCheckout = async () => {
        try {
            await createOrderSession("mock");
        } catch {
            // Validation and server errors are already surfaced in state.
        }
    };

    const showLockedMessage = () => {
        setGeneralError(
            "Please complete your personal details above before continuing to payment.",
        );
    };

    const lockedOverlay = paymentIsLocked ? (
        <button
            type="button"
            onClick={showLockedMessage}
            className="absolute inset-0 z-10 cursor-not-allowed rounded-[5px] bg-black/10"
            aria-label="Complete your details before payment"
        />
    ) : null;

    return (
        <div className="w-full space-y-8" style={{ fontFamily: FONT_FAMILY }}>
            <style>{`
                .yogafx-installment-slider {
                    appearance: none;
                    -webkit-appearance: none;
                    height: 6px;
                    border-radius: 9999px;
                    background: rgba(255, 255, 255, 0.9);
                    outline: none;
                    cursor: pointer;
                }

                .yogafx-installment-slider:disabled {
                    cursor: not-allowed;
                }

                .yogafx-installment-slider::-webkit-slider-runnable-track {
                    height: 6px;
                    border-radius: 9999px;
                    background: rgba(255, 255, 255, 0.9);
                }

                .yogafx-installment-slider::-webkit-slider-thumb {
                    -webkit-appearance: none;
                    appearance: none;
                    width: 18px;
                    height: 18px;
                    margin-top: -6px;
                    border-radius: 9999px;
                    background: #DB202C;
                    border: 2px solid #ffffff;
                    cursor: pointer;
                }

                .yogafx-installment-slider::-moz-range-track {
                    height: 6px;
                    border-radius: 9999px;
                    background: rgba(255, 255, 255, 0.9);
                }

                .yogafx-installment-slider::-moz-range-progress {
                    height: 6px;
                    border-radius: 9999px;
                    background: rgba(255, 255, 255, 0.9);
                }

                .yogafx-installment-slider::-moz-range-thumb {
                    width: 18px;
                    height: 18px;
                    border-radius: 9999px;
                    background: #DB202C;
                    border: 2px solid #ffffff;
                    cursor: pointer;
                }
            `}</style>


            {isConfirmingPayment && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/75 px-6 backdrop-blur-sm"
                    style={{ fontFamily: FONT_FAMILY }}
                    role="status"
                    aria-live="polite"
                >
                    <div className="w-full max-w-md rounded-[18px] border border-white/15 bg-[#111111] px-7 py-8 text-center shadow-[0_24px_80px_rgba(0,0,0,0.55)]">
                        <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full border border-[#DB202C]/35 bg-[#DB202C]/12">
                            <LoaderCircle className="h-8 w-8 animate-spin text-[#DB202C]" />
                        </div>

                        <p className="mt-6 text-xs font-semibold uppercase tracking-[0.22em] text-[#ffb8bf]">
                            Payment received
                        </p>

                        <h2 className="mt-3 text-2xl font-semibold text-white">
                            Confirming your payment
                        </h2>

                        <p className="mt-3 text-sm leading-6 text-white/70">
                            Please do not close this page. We are confirming
                            your first payment and preparing your enrollment
                            access.
                        </p>

                        <div className="mt-6 overflow-hidden rounded-full bg-white/10">
                            <div className="h-1.5 w-2/3 animate-pulse rounded-full bg-[#DB202C]" />
                        </div>
                    </div>
                </div>
            )}

            {(generalError || visibleSdkError) && (
                <div
                    className="rounded-xl border border-rose-400/30 bg-rose-500/10 px-5 py-4 text-sm font-medium text-rose-100"
                    style={{ fontFamily: FONT_FAMILY }}
                >
                    <div className="flex items-start gap-3">
                        <AlertCircle className="mt-0.5 h-5 w-5 flex-shrink-0" />
                        <p>{generalError || visibleSdkError}</p>
                    </div>
                </div>
            )}

            <div className={paymentButtonGridClass}>
                {paymentOptions.map((option) => {
                    const optionIsActive = paymentType === option.type;
                    const optionIsInstallment = option.type === "installment";

                    return (
                        <div key={option.type} className="relative">
                            <button
                                type="button"
                                onClick={() => setPaymentType(option.type)}
                                className={`w-full rounded-[5px] border-2 px-5 py-4 text-center text-sm font-semibold transition-all duration-200 ${
                                    optionIsActive
                                        ? "border-[#DB202C] bg-[#DB202C] text-white shadow-[0_0_0_2px_rgba(219,32,44,0.28)]"
                                        : "border-white/35 bg-black/25 text-white/85 hover:border-white/55 hover:bg-white/10"
                                }`}
                                style={{
                                    fontFamily: FONT_FAMILY,
                                    fontSize: "14px",
                                    fontWeight: 600,
                                }}
                            >
                                {optionIsInstallment
                                    ? "Pay in installment"
                                    : "Pay in full"}
                            </button>

                            {paymentIsLocked && (
                                <button
                                    type="button"
                                    onClick={showLockedMessage}
                                    className="absolute inset-0 z-10 cursor-not-allowed rounded-[5px] bg-black/10"
                                    aria-label="Complete your details before choosing payment type"
                                />
                            )}
                        </div>
                    );
                })}
            </div>

            {isInstallmentSelected && activeInstallmentSummary && (
                <div className="space-y-5">
                    {showBillingDaySelector && (
                        <div className="space-y-3">
                            <p className="text-xs uppercase tracking-[0.16em] text-white/45">
                                Monthly billing date
                            </p>

                            <div className="grid gap-3 md:grid-cols-2">
                                {installmentAllowedBillingDays.map(
                                    (allowedDay) => (
                                        <label
                                            key={allowedDay}
                                            className={`flex cursor-pointer items-center gap-3 rounded-[5px] border px-4 py-3 text-sm transition ${
                                                billingDay === allowedDay
                                                    ? "border-[#DB202C] bg-[#DB202C]/12 text-white"
                                                    : "border-white/10 bg-white/5 text-white/75"
                                            }`}
                                        >
                                            <input
                                                type="radio"
                                                name="installment_billing_day"
                                                value={allowedDay}
                                                checked={
                                                    billingDay === allowedDay
                                                }
                                                onChange={() => {
                                                    setBillingDay(allowedDay);
                                                    setFieldErrors(
                                                        (current) => ({
                                                            ...current,
                                                            billing_day: "",
                                                        }),
                                                    );
                                                }}
                                                disabled={paymentIsLocked}
                                                className="h-4 w-4 border-white/20 bg-black/30 text-[#DB202C] focus:ring-[#DB202C] disabled:opacity-60"
                                            />
                                            <span>
                                                Every{" "}
                                                {formatBillingDayLabel(
                                                    allowedDay,
                                                )}{" "}
                                                of the month
                                            </span>
                                        </label>
                                    ),
                                )}
                            </div>

                            <InputError
                                className="mt-2 text-sm font-medium text-rose-400"
                                style={{ fontFamily: FONT_FAMILY }}
                                message={fieldErrors.billing_day}
                            />
                        </div>
                    )}

                    {availableInstallmentCounts.length > 0 && (
                        <div className="space-y-4">
                            <div className="flex items-center justify-between gap-4">
                                <p className="text-xs uppercase tracking-[0.16em] text-white/45">
                                    Number of installments
                                </p>
                                <p className="rounded-full bg-[#DB202C] px-4 py-1.5 text-sm font-semibold text-white">
                                    {selectedInstallmentCount} payments
                                </p>
                            </div>

                            <div className="relative pt-7">
                                <div
                                    className="pointer-events-none absolute top-0 -translate-x-1/2 rounded-full bg-[#DB202C] px-2 py-0.5 text-xs font-semibold text-white"
                                    style={{
                                        left: `${sliderProgressPercent}%`,
                                    }}
                                >
                                    {selectedInstallmentCount}
                                </div>

                                <input
                                    type="range"
                                    min={minimumInstallmentCount}
                                    max={maximumInstallmentCount}
                                    step="1"
                                    value={selectedInstallmentCount}
                                    disabled={paymentIsLocked}
                                    onChange={(event) => {
                                        setSelectedInstallmentCount(
                                            Number(event.target.value),
                                        );
                                        setFieldErrors((current) => ({
                                            ...current,
                                            installment_count: "",
                                        }));
                                    }}
                                    className="yogafx-installment-slider w-full disabled:opacity-60"
                                />
                            </div>

                            <div className="flex items-center justify-between text-xs font-medium text-white/55">
                                <span>{minimumInstallmentCount} payments</span>
                                <span>{maximumInstallmentCount} payments</span>
                            </div>

                            <InputError
                                className="mt-2 text-sm font-medium text-rose-400"
                                style={{ fontFamily: FONT_FAMILY }}
                                message={fieldErrors.installment_count}
                            />
                        </div>
                    )}

                    <div>
                        <p
                            className="text-sm uppercase tracking-[0.18em] text-[#ffb8bf]"
                            style={{
                                fontFamily: FONT_FAMILY,
                                fontSize: "12px",
                                fontWeight: 600,
                            }}
                        >
                            Installment Plan
                        </p>

                        <h3
                            className="mt-2 text-white"
                            style={{
                                fontFamily: FONT_FAMILY,
                                fontSize: "24px",
                                fontWeight: 600,
                            }}
                        >
                            Pay in {installmentCount || 2} Monthly Installments
                        </h3>
                    </div>

                    <div
                        className="overflow-hidden rounded-[8px] border border-white/10 bg-white/5"
                        style={{ fontFamily: FONT_FAMILY }}
                    >
                        <div className="divide-y divide-white/10">
                            {[
                                {
                                    label: "Course Price",
                                    value: formatCurrency(
                                        Number(
                                            activeInstallmentSummary?.total_amount ??
                                                checkout.amount ??
                                                0,
                                        ),
                                        activeCurrencyCode,
                                    ),
                                },
                                {
                                    label: "Number of Installments",
                                    value: `${installmentCount || 2}`,
                                },
                                {
                                    label: usesMonthlyInstallmentSchedule
                                        ? "Monthly Installment"
                                        : "Recurring Installment",
                                    value: formatCurrency(
                                        recurringAmount || amountDueToday,
                                        activeCurrencyCode,
                                    ),
                                },
                                {
                                    label: "Last Installment",
                                    value: formatCurrency(
                                        lastInstallmentAmount,
                                        activeCurrencyCode,
                                    ),
                                },
                            ].map((row) => (
                                <div
                                    key={row.label}
                                    className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-4 px-5 py-4"
                                >
                                    <p className="text-[15px] font-medium text-white">
                                        {row.label}
                                    </p>
                                    <p className="text-[15px] font-semibold text-white">
                                        {row.value}
                                    </p>
                                </div>
                            ))}

                            <div className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-4 bg-[#DB202C]/18 px-5 py-4">
                                <p className="text-[16px] font-semibold text-white">
                                    First Installment Due Today
                                </p>
                                <p className="text-[16px] font-bold text-white">
                                    {formatCurrency(
                                        amountDueToday,
                                        activeCurrencyCode,
                                    )}
                                </p>
                            </div>

                            <div className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-4 px-5 py-4">
                                <p className="text-[15px] font-medium text-white">
                                    Next Installment
                                </p>
                                <p className="text-right text-[15px] font-semibold text-white">
                                    {formatInstallmentDateAmount(
                                        nextInstallment?.due_at,
                                        nextInstallmentAmount,
                                        activeCurrencyCode,
                                    )}
                                </p>
                            </div>

                            <div className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-4 px-5 py-4">
                                <p className="text-[15px] font-medium text-white">
                                    Last Installment
                                </p>
                                <p className="text-right text-[15px] font-semibold text-white">
                                    {formatInstallmentDateAmount(
                                        finalDueAt ?? lastInstallment?.due_at,
                                        lastInstallmentAmount,
                                        activeCurrencyCode,
                                    )}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            <div>
                <label
                    className="flex cursor-pointer items-start gap-4 text-sm font-normal text-white/80"
                    style={{
                        fontFamily: FONT_FAMILY,
                        fontSize: "14px",
                        fontWeight: 400,
                    }}
                >
                    <input
                        type="checkbox"
                        checked={formData.terms_accepted}
                        onChange={(event) =>
                            setFieldValue(
                                "terms_accepted",
                                event.target.checked,
                            )
                        }
                        className="mt-1 h-5 w-5 flex-shrink-0 rounded border-white/20 bg-black/20 text-[#DB202C] transition-colors focus:ring-[#DB202C] focus:ring-offset-gray-900"
                    />
                    <span className="flex-1 leading-relaxed">
                        I agree to continue with YogaFX payment processing and
                        understand that sensitive card data is handled directly
                        by PayPal-hosted secure components.
                    </span>
                    {fieldErrors.terms_accepted && (
                        <AlertCircle className="mt-1 h-5 w-5 flex-shrink-0 text-rose-400" />
                    )}
                </label>
                <InputError
                    className="mt-2 text-sm font-medium text-rose-400"
                    style={{ fontFamily: FONT_FAMILY }}
                    message={fieldErrors.terms_accepted}
                />
            </div>

            <div className="space-y-5">
                {isInstallmentSelected ? (
                    <div
                        className={`transition-opacity duration-300 ${
                            !formData.terms_accepted
                                ? "opacity-60"
                                : "opacity-100"
                        }`}
                    >
                        {installmentApprovalMessage && !isConfirmingPayment && (
                            <div
                                className="mb-5 rounded-[5px] border border-emerald-400/30 bg-emerald-500/10 px-4 py-4 text-sm text-emerald-100"
                                style={{ fontFamily: FONT_FAMILY }}
                            >
                                {installmentApprovalMessage}
                            </div>
                        )}

                        {installmentSession?.provider_subscription_id && !isConfirmingPayment ? (
                            <div
                                className="rounded-[5px] border border-white/10 bg-white/5 px-4 py-4 text-sm text-white/70"
                                style={{ fontFamily: FONT_FAMILY }}
                            >
                                {installmentApprovalMessage ||
                                    "PayPal approval has been received. We are confirming your first payment now."}
                            </div>
                        ) : (
                            <div className="space-y-4">
                                <div
                                    className={`relative transition-opacity duration-300 ${
                                        paymentIsLocked
                                            ? "opacity-70 grayscale-[35%]"
                                            : "opacity-100 grayscale-0"
                                    }`}
                                >
                                    <div
                                        className="rounded-[5px] p-3"
                                        style={{
                                            backgroundColor:
                                                "rgba(255, 255, 255, 0.97)",
                                        }}
                                    >
                                        <div
                                            ref={installmentButtonsRef}
                                            className="min-h-[48px]"
                                        />
                                    </div>

                                    {lockedOverlay}
                                </div>

                                {!installmentSdkReady && (
                                    <div
                                        className="flex items-center gap-3 text-sm font-medium text-white/60"
                                        style={{ fontFamily: FONT_FAMILY }}
                                    >
                                        <LoaderCircle className="h-5 w-5 animate-spin text-[#DB202C]" />
                                        <span>
                                            Loading PayPal approval methods...
                                        </span>
                                    </div>
                                )}

                                {(isSubmitting || isPreparingCheckout) && (
                                    <div
                                        className="flex items-center gap-3 text-sm font-medium text-white/60"
                                        style={{ fontFamily: FONT_FAMILY }}
                                    >
                                        <LoaderCircle className="h-5 w-5 animate-spin text-[#DB202C]" />
                                        <span>
                                            Preparing secure payment options...
                                        </span>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                ) : (
                    <div
                        className={`relative transition-opacity duration-300 ${
                            paymentIsLocked || !formData.terms_accepted
                                ? "opacity-70 grayscale-[35%]"
                                : "opacity-100 grayscale-0"
                        }`}
                    >
                        <div
                            className="rounded-[5px] p-3"
                            style={{
                                backgroundColor: "rgba(255, 255, 255, 0.97)",
                            }}
                        >
                            <div
                                ref={payFullButtonsRef}
                                className="min-h-[48px]"
                            />
                        </div>

                        {lockedOverlay}
                    </div>
                )}

                {!isInstallmentSelected && !payFullSdkReady && (
                    <div
                        className="flex items-center gap-3 text-sm font-medium text-white/60"
                        style={{ fontFamily: FONT_FAMILY }}
                    >
                        <LoaderCircle className="h-5 w-5 animate-spin text-[#DB202C]" />
                        <span>Loading secure payment methods...</span>
                    </div>
                )}

                {(isSubmitting || isPreparingCheckout) &&
                    !isInstallmentSelected && (
                        <div
                            className="flex items-center gap-3 text-sm font-medium text-white/60"
                            style={{ fontFamily: FONT_FAMILY }}
                        >
                            <LoaderCircle className="h-5 w-5 animate-spin text-[#DB202C]" />
                            <span>Preparing secure payment options...</span>
                        </div>
                    )}
            </div>

            {debugInfo && (
                <div
                    className="rounded-xl border border-white/10 bg-black/20 px-5 py-4 text-sm leading-6 text-white/70"
                    style={{ fontFamily: FONT_FAMILY }}
                >
                    <p className="font-semibold text-white/90">
                        Checkout debug
                    </p>
                    <p className="mt-1">
                        Stage: {debugInfo.stage}
                        {debugInfo.http_status
                            ? ` (${debugInfo.http_status})`
                            : ""}
                    </p>
                    {debugInfo.payload?.message && (
                        <p className="mt-1">
                            Message: {debugInfo.payload.message}
                        </p>
                    )}
                    {paypalConfig.environment && (
                        <p className="mt-1">
                            PayPal Environment: {paypalConfig.environment}
                        </p>
                    )}
                </div>
            )}

            {canUseMock && (
                <div className="rounded-[5px] border border-dashed border-white/20 bg-black/15 p-6 backdrop-blur-sm">
                    <div className="flex flex-wrap items-center justify-between gap-5">
                        <div>
                            <p
                                className="text-sm font-semibold text-white"
                                style={{
                                    fontFamily: FONT_FAMILY,
                                    fontSize: "14px",
                                    fontWeight: 600,
                                }}
                            >
                                Local testing
                            </p>
                            <p
                                className="mt-1 text-sm font-normal text-white/60"
                                style={{
                                    fontFamily: FONT_FAMILY,
                                    fontSize: "14px",
                                    fontWeight: 400,
                                }}
                            >
                                Mock checkout stays available only outside
                                production.
                            </p>
                        </div>

                        <Button
                            type="button"
                            variant="outline"
                            onClick={runMockCheckout}
                            disabled={isSubmitting}
                            style={{
                                fontFamily: FONT_FAMILY,
                                fontSize: "14px",
                                fontWeight: 500,
                            }}
                            className="rounded-[5px] border-white/20 bg-transparent px-2.5 py-2 text-sm font-medium text-white hover:bg-white/10"
                        >
                            Run Mock Payment
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}
