import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
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

function formatIntervalLabel(unit, count = 1) {
    const normalizedUnit = String(unit ?? "").toUpperCase();
    const normalizedCount = Number(count) > 0 ? Number(count) : 1;

    if (normalizedUnit === "DAY") {
        return normalizedCount === 1
            ? "daily"
            : `every ${normalizedCount} days`;
    }

    if (normalizedUnit === "WEEK") {
        return normalizedCount === 1
            ? "weekly"
            : `every ${normalizedCount} weeks`;
    }

    if (normalizedUnit === "YEAR") {
        return normalizedCount === 1
            ? "yearly"
            : `every ${normalizedCount} years`;
    }

    return normalizedCount === 1
        ? "monthly"
        : `every ${normalizedCount} months`;
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

function buildInstallmentCountOptions(summary) {
    const maximumInstallmentCount = Number(
        summary?.maximum_installment_count ?? summary?.installment_count ?? 0,
    );

    if (
        !Number.isFinite(maximumInstallmentCount) ||
        maximumInstallmentCount < 2
    ) {
        return [];
    }

    return Array.from(
        { length: maximumInstallmentCount - 1 },
        (_, index) => index + 2,
    );
}

function buildInstallmentSummaryForCount(summary, selectedInstallmentCount) {
    if (!summary) {
        return null;
    }

    const maximumInstallmentCount = Number(
        summary.maximum_installment_count ?? summary.installment_count ?? 0,
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

    const availableRecurringDueDates = Array.isArray(
        summary.available_recurring_due_dates,
    )
        ? summary.available_recurring_due_dates
        : Array.isArray(summary.recurring_due_dates)
          ? summary.recurring_due_dates
          : [];

    const selectedRecurringDueDates = availableRecurringDueDates.slice(
        0,
        normalizedInstallmentCount - 1,
    );

    const finalDueAt =
        selectedRecurringDueDates[selectedRecurringDueDates.length - 1] ??
        summary.final_due_at ??
        null;

    const scheduleBreakdown = [
        {
            cycle_number: 1,
            type: "first_payment",
            amount: firstPaymentAmount.toFixed(2),
            due_at: summary.first_payment_date ?? null,
            grace_deadline: null,
        },
        ...selectedRecurringDueDates.map((dueDate, index) => ({
            cycle_number: index + 2,
            type: "recurring",
            amount: recurringAmount.toFixed(2),
            due_at: dueDate,
            grace_deadline: null,
        })),
    ];

    return {
        ...summary,
        installment_count: normalizedInstallmentCount,
        maximum_installment_count: maximumInstallmentCount,
        first_payment_amount: firstPaymentAmount.toFixed(2),
        monthly_base_amount: recurringAmount.toFixed(2),
        recurring_payment_amount: recurringAmount.toFixed(2),
        recurring_due_dates: selectedRecurringDueDates,
        final_due_at: finalDueAt,
        schedule_breakdown: scheduleBreakdown,
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

export default function PublicCheckoutPanel({ checkout }) {
    const paymentOptions = Array.isArray(checkout.payment_options)
        ? checkout.payment_options
        : [];

    const mockAvailable = (checkout.payment_method_options ?? []).some(
        (option) => option.value === "mock",
    );

    const paypalConfig = checkout.paypal ?? {};
    const installmentSummaries = checkout.installment_summaries ?? {};

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

    const installmentIntervalCount = Number(
        checkout.installment_billing_interval_count ?? 1,
    );

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
    );

    const [paymentType, setPaymentType] = useState(
        paymentOptions[0]?.type ?? "pay_full",
    );

    const [billingDay, setBillingDay] = useState(initialBillingDay);

    /*
    |--------------------------------------------------------------------------
    | Important change
    |--------------------------------------------------------------------------
    |
    | Jangan langsung kunci ke maximum installment.
    | Student boleh memilih dari 2 sampai maximum.
    | Default dibuat 2 supaya jelas bahwa pilihan memang bisa diganti.
    |
    */
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
    const [debugInfo, setDebugInfo] = useState(null);
    const [installmentSession, setInstallmentSession] = useState(null);
    const [installmentStatus, setInstallmentStatus] = useState(null);
    const [installmentApprovalMessage, setInstallmentApprovalMessage] =
        useState("");

    const payFullButtonsRef = useRef(null);
    const installmentButtonsRef = useRef(null);
    const activeOrderRef = useRef(null);
    const installmentSessionRef = useRef(installmentSession);

    const selectedPaymentOption =
        paymentOptions.find((option) => option.type === paymentType) ??
        paymentOptions[0] ??
        null;

    const isInstallmentSelected = paymentType === "installment";

    const baseInstallmentSummary =
        installmentSummaries[String(billingDay)] ??
        selectedPaymentOption?.summary ??
        checkout.installment_summary ??
        null;

    const availableInstallmentCounts = buildInstallmentCountOptions(
        baseInstallmentSummary,
    );

    const maximumInstallmentCount =
        availableInstallmentCounts[availableInstallmentCounts.length - 1] ?? 0;

    const activeInstallmentSummary = isInstallmentSelected
        ? buildInstallmentSummaryForCount(
              baseInstallmentSummary,
              selectedInstallmentCount,
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

    const activeBillingDay =
        activeInstallmentSummary?.billing_day ?? billingDay;

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
        installmentScheduleBreakdown[installmentScheduleBreakdown.length - 1] ??
        null;

    const showBillingDaySelector =
        isInstallmentSelected &&
        installmentAcceptsBillingDay &&
        installmentRequiresBillingDayChoice &&
        installmentAllowedBillingDays.length > 1;

    const usesMonthlyInstallmentSchedule = installmentIntervalUnit === "MONTH";
    const canUseMock = mockAvailable && !isInstallmentSelected;

    const payFullScriptUrl = useMemo(() => {
        const params = new URLSearchParams({
            "client-id": paypalConfig.client_id ?? "",
            components: "buttons",
            currency: paypalConfig.currency_code ?? checkout.currency_code,
            intent: paypalConfig.intent ?? "capture",
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

    const shouldRenderInstallmentButtons =
        isInstallmentSelected &&
        installmentSession &&
        !installmentSession.provider_subscription_id;

    const visibleSdkError = isInstallmentSelected
        ? shouldRenderInstallmentButtons
            ? installmentSdkError
            : ""
        : payFullSdkError;

    useEffect(() => {
        installmentSessionRef.current = installmentSession;
    }, [installmentSession]);

    useEffect(() => {
        if (!isInstallmentSelected) {
            setInstallmentApprovalMessage("");
            setInstallmentStatus(null);
        }
    }, [isInstallmentSelected]);

    /*
    |--------------------------------------------------------------------------
    | Keep installment count inside the valid range
    |--------------------------------------------------------------------------
    |
    | Jika billing day berubah, maximum bisa berubah juga.
    | Di sini nilai pilihan student dipertahankan jika masih valid.
    | Jika tidak valid, fallback ke pilihan paling kecil yang tersedia.
    |
    */
    useEffect(() => {
        if (!isInstallmentSelected) {
            return;
        }

        if (availableInstallmentCounts.length === 0) {
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

    /*
    |--------------------------------------------------------------------------
    | Reset prepared PayPal subscription when student changes the plan
    |--------------------------------------------------------------------------
    */
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
        if (!payFullSdkReady || isInstallmentSelected) {
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
                label: "paypal",
                height: 48,
            },
            onClick: (_data, actions) => {
                setGeneralError("");
                setDebugInfo(null);

                if (!validateCheckoutFields()) {
                    return actions.reject();
                }

                return actions.resolve();
            },
            createOrder: async () => {
                const createdOrder = await createOrderSession("paypal");
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
    }, [isInstallmentSelected, payFullSdkReady, checkout.create_order_url]);

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
            onClick: (_data, actions) => {
                setGeneralError("");
                setInstallmentApprovalMessage("");
                setDebugInfo(null);

                if (!validateCheckoutFields()) {
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
                setIsSubmitting(false);
            },
            onError: () => {
                setGeneralError(
                    "PayPal could not start the subscription approval popup. Please try again.",
                );
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
    ]);

    useEffect(() => {
        if (isInstallmentSelected) {
            clearPayPalContainer(payFullButtonsRef);
            return;
        }

        clearPayPalContainer(installmentButtonsRef);
    }, [isInstallmentSelected]);

    useEffect(() => {
        if (
            !isInstallmentSelected ||
            !installmentSession?.provider_subscription_id ||
            !checkout.installment_status_url
        ) {
            return undefined;
        }

        let cancelled = false;

        const pollInstallmentStatus = async () => {
            try {
                const response = await fetch(checkout.installment_status_url, {
                    method: "GET",
                    credentials: "same-origin",
                    headers: {
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                    },
                });

                const payload = await parseJsonSafely(response);

                if (cancelled || !response.ok) {
                    return;
                }

                setInstallmentStatus(payload);

                if (payload.onboarding_ready && payload.onboarding_url) {
                    window.location.assign(payload.onboarding_url);
                    return;
                }

                if (payload.message) {
                    setInstallmentApprovalMessage(payload.message);
                }
            } catch {
                if (!cancelled) {
                    setInstallmentApprovalMessage(
                        "PayPal approval received. We are confirming your first payment now.",
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
    }, [
        checkout.installment_status_url,
        installmentSession,
        isInstallmentSelected,
    ]);

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

        if (isInstallmentSelected) {
            if (installmentAcceptsBillingDay && !billingDay) {
                nextErrors.billing_day =
                    "Please select your preferred billing day.";
            }

            const normalizedInstallmentCount = normalizeInstallmentCount(
                selectedInstallmentCount,
            );

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

    const createOrderSession = async (paymentMethod) => {
        if (!validateCheckoutFields()) {
            setIsSubmitting(false);
            throw new Error("Checkout form is incomplete.");
        }

        setIsSubmitting(true);
        setGeneralError("");
        setDebugInfo(null);

        const currentData = formDataRef.current;

        const response = await fetch(checkout.create_order_url, {
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
                installment_count: isInstallmentSelected
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
            setIsSubmitting(false);
            throw new Error("create-order");
        }

        if (payload.redirect_url && payload.status === "success") {
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

        if (
            !activeSubscription?.payment_subscription_id ||
            !checkout.installment_approve_url
        ) {
            setGeneralError(
                "The installment approval route was not prepared correctly.",
            );
            setIsSubmitting(false);
            return;
        }

        setIsSubmitting(true);
        setGeneralError("");
        setInstallmentApprovalMessage("");

        const response = await fetch(checkout.installment_approve_url, {
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
        });

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
        setIsSubmitting(false);
    };

    const captureApprovedOrder = async (orderId) => {
        const activeOrder = activeOrderRef.current;

        if (!activeOrder?.capture_url) {
            setGeneralError(
                "The payment capture route was not prepared correctly.",
            );
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
        setIsSubmitting(false);
    };

    const handleDismissedOrder = () => {
        activeOrderRef.current = null;
        setDebugInfo(null);
        setGeneralError("");
        setIsSubmitting(false);
    };

    const runMockCheckout = async () => {
        try {
            await createOrderSession("mock");
        } catch {
            // Validation and server errors are already surfaced in state.
        }
    };

    const runInstallmentCheckout = async () => {
        try {
            await createOrderSession("paypal");
        } catch {
            // Validation and server errors are already surfaced in state.
        }
    };

    return (
        <div className="w-full space-y-8" style={{ fontFamily: FONT_FAMILY }}>
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

            <div className="rounded-[5px] border border-white/10 bg-white/5 p-6 shadow-lg backdrop-blur-sm">
                <div className="space-y-4">
                    <div className="grid gap-3 sm:grid-cols-2">
                        {paymentOptions.map((option) => {
                            const optionIsActive = paymentType === option.type;
                            const optionIsInstallment =
                                option.type === "installment";

                            return (
                                <button
                                    key={option.type}
                                    type="button"
                                    onClick={() => setPaymentType(option.type)}
                                    className={`rounded-[5px] border px-5 py-4 text-center text-sm font-semibold transition-all duration-200 ${
                                        optionIsActive
                                            ? "border-[#DB202C] bg-[#DB202C] text-white shadow-[0_0_0_1px_rgba(219,32,44,0.25)]"
                                            : "border-white/10 bg-black/20 text-white/75 hover:border-white/25 hover:bg-white/10"
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
                            );
                        })}
                    </div>
                </div>
            </div>

            {isInstallmentSelected && activeInstallmentSummary && (
                <div className="space-y-5 rounded-[5px] border border-[#DB202C]/25 bg-[#DB202C]/8 p-6 shadow-lg backdrop-blur-sm">
                    {showBillingDaySelector && (
                        <div className="rounded-[5px] border border-white/10 bg-black/20 px-4 py-4">
                            <p className="text-xs uppercase tracking-[0.16em] text-white/45">
                                Monthly billing date
                            </p>

                            <div className="mt-3 grid gap-3 md:grid-cols-2">
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
                                                className="h-4 w-4 border-white/20 bg-black/30 text-[#DB202C] focus:ring-[#DB202C]"
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

                    <div className="rounded-[5px] border border-white/10 bg-black/20 px-4 py-4">
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p className="text-xs uppercase tracking-[0.16em] text-white/45">
                                    Number of installments
                                </p>
                            </div>
                        </div>

                        <select
                            value={selectedInstallmentCount || ""}
                            onChange={(event) => {
                                setSelectedInstallmentCount(
                                    Number(event.target.value),
                                );
                                setFieldErrors((current) => ({
                                    ...current,
                                    installment_count: "",
                                }));
                            }}
                            className="mt-4 block w-full rounded-[5px] border border-white/10 bg-black/40 px-4 py-3 text-sm text-white shadow-sm focus:border-[#DB202C] focus:ring-[#DB202C]"
                        >
                            {availableInstallmentCounts.length === 0 ? (
                                <option value="">
                                    No installment option available
                                </option>
                            ) : (
                                availableInstallmentCounts.map((count) => (
                                    <option key={count} value={count}>
                                        {count} total payments
                                    </option>
                                ))
                            )}
                        </select>

                        <InputError
                            className="mt-2 text-sm font-medium text-rose-400"
                            style={{ fontFamily: FONT_FAMILY }}
                            message={fieldErrors.installment_count}
                        />
                    </div>

                    <div className="flex flex-wrap items-start justify-between gap-4">
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
                                Pay in {installmentCount} Monthly Installments
                            </h3>

                        </div>
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
                                    value: `${installmentCount}`,
                                },
                                {
                                    label: usesMonthlyInstallmentSchedule
                                        ? "Monthly Installment"
                                        : "Recurring Installment",
                                    value: formatCurrency(
                                        recurringAmount,
                                        activeCurrencyCode,
                                    ),
                                },
                                {
                                    label: "Last Installment",
                                    value: formatCurrency(
                                        Number(
                                            lastInstallment?.amount ??
                                                recurringAmount,
                                        ),
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
                                <p className="text-[15px] font-semibold text-white">
                                    {nextInstallment?.due_at
                                        ? formatScheduleDate(
                                              nextInstallment.due_at,
                                          )
                                        : "-"}
                                </p>
                            </div>

                            <div className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-4 px-5 py-4">
                                <p className="text-[15px] font-medium text-white">
                                    Last Installment
                                </p>
                                <p className="text-[15px] font-semibold text-white">
                                    {formatScheduleDate(finalDueAt)}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            <div>
                <label
                    className={`flex cursor-pointer items-start gap-4 rounded-[5px] border px-5 py-5 text-sm font-normal text-white/80 transition-all duration-200 ${
                        fieldErrors.terms_accepted
                            ? "border-rose-500 bg-rose-500/10 ring-1 ring-rose-500/20"
                            : "border-white/10 bg-white/5 shadow-lg backdrop-blur-sm hover:border-white/30"
                    }`}
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

            <div className="rounded-[5px] border border-[#DB202C]/30 bg-white/5 p-6 shadow-xl backdrop-blur-sm">
                <h2
                    className="text-white"
                    style={{
                        fontFamily: FONT_FAMILY,
                        fontSize: "22px",
                        fontWeight: 500,
                    }}
                >
                    {isInstallmentSelected
                        ? "PayPal Installment Approval"
                        : "Payment Method"}
                </h2>


                {isInstallmentSelected ? (
                    <div
                        className={`mt-6 rounded-[5px] border border-white/10 bg-black/20 p-5 transition-opacity duration-300 ${
                            !formData.terms_accepted
                                ? "opacity-60"
                                : "opacity-100"
                        }`}
                    >

                        {installmentApprovalMessage && (
                            <div
                                className="mt-5 rounded-[5px] border border-emerald-400/30 bg-emerald-500/10 px-4 py-4 text-sm text-emerald-100"
                                style={{ fontFamily: FONT_FAMILY }}
                            >
                                {installmentApprovalMessage}
                            </div>
                        )}

                        {!installmentSession ? (
                            <Button
                                type="button"
                                onClick={runInstallmentCheckout}
                                disabled={isSubmitting}
                                style={{
                                    fontFamily: FONT_FAMILY,
                                    fontSize: "14px",
                                    fontWeight: 500,
                                }}
                                className="mt-5 min-h-[52px] w-full rounded-[5px] bg-[#DB202C] px-4 py-3 text-sm font-medium text-white hover:bg-[#c01a25] disabled:pointer-events-none disabled:opacity-60"
                            >
                                {isSubmitting
                                    ? "Preparing PayPal installment approval..."
                                    : `Continue with PayPal - ${formatCurrency(amountDueToday, activeCurrencyCode)} today`}
                            </Button>
                        ) : installmentSession.provider_subscription_id ? (
                            <div
                                className="mt-5 rounded-[5px] border border-white/10 bg-white/5 px-4 py-4 text-sm text-white/70"
                                style={{ fontFamily: FONT_FAMILY }}
                            >
                                {installmentApprovalMessage ||
                                    "PayPal approval has been received. We are confirming your first payment now."}
                            </div>
                        ) : (
                            <div className="mt-5 space-y-4">

                                <div
                                    className="rounded-[5px] border border-white/10 p-5 shadow-inner"
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

                                {!installmentSdkReady && (
                                    <div
                                        className="flex items-center gap-3 text-sm font-medium text-white/60"
                                        style={{ fontFamily: FONT_FAMILY }}
                                    >
                                        <LoaderCircle className="h-5 w-5 animate-spin text-[#DB202C]" />
                                        <span>
                                            Loading PayPal sandbox approval
                                            popup...
                                        </span>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                ) : (
                    <div
                        className={`mt-6 transition-opacity duration-300 ${
                            !formData.terms_accepted
                                ? "opacity-50 grayscale-[30%]"
                                : "opacity-100 grayscale-0"
                        }`}
                    >
                        <div
                            className="rounded-[5px] border border-white/10 p-5 shadow-inner"
                            style={{
                                backgroundColor: "rgba(255, 255, 255, 0.97)",
                            }}
                        >
                            <div
                                ref={payFullButtonsRef}
                                className="min-h-[48px]"
                            />
                        </div>
                    </div>
                )}

                {!isInstallmentSelected && !payFullSdkReady && (
                    <div
                        className="mt-5 flex items-center gap-3 text-sm font-medium text-white/60"
                        style={{ fontFamily: FONT_FAMILY }}
                    >
                        <LoaderCircle className="h-5 w-5 animate-spin text-[#DB202C]" />
                        <span>Loading secure payment methods...</span>
                    </div>
                )}
            </div>

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
