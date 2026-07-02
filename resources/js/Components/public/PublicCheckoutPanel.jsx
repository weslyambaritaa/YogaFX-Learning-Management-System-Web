import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import { Button } from "@/Components/ui/button";
import { formatCurrency } from "@/lib/currency";
import { AlertCircle, LoaderCircle } from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";

const INITIAL_ERRORS = {
    billing_day: "",
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
        return normalizedCount === 1 ? "daily" : `every ${normalizedCount} days`;
    }

    if (normalizedUnit === "WEEK") {
        return normalizedCount === 1 ? "weekly" : `every ${normalizedCount} weeks`;
    }

    if (normalizedUnit === "YEAR") {
        return normalizedCount === 1 ? "yearly" : `every ${normalizedCount} years`;
    }

    return normalizedCount === 1 ? "monthly" : `every ${normalizedCount} months`;
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
        checkout.installment_billing_interval_unit ?? "",
    ).toUpperCase();
    const installmentIntervalCount = Number(
        checkout.installment_billing_interval_count ?? 1,
    );

    const [paymentType, setPaymentType] = useState(
        paymentOptions[0]?.type ?? "pay_full",
    );
    const [billingDay, setBillingDay] = useState(
        checkout.installment_selected_billing_day ??
            installmentAllowedBillingDays[0] ??
            15,
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
    const activeInstallmentSummary =
        installmentSummaries[String(billingDay)] ??
        selectedPaymentOption?.summary ??
        checkout.installment_summary ??
        null;
    const amountDueToday = Number(
        selectedPaymentOption?.amount_due_today ?? checkout.amount ?? 0,
    );
    const activeCurrencyCode =
        selectedPaymentOption?.currency_code ?? checkout.currency_code;
    const recurringAmount = Number(
        selectedPaymentOption?.recurring_amount ??
            activeInstallmentSummary?.recurring_payment_amount ??
            0,
    );
    const installmentCount = Number(
        selectedPaymentOption?.installment_count ??
            activeInstallmentSummary?.installment_count ??
            0,
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
        installmentScheduleBreakdown[0] ??
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

    useEffect(() => {
        if (
            isInstallmentSelected &&
            installmentSession &&
            !installmentSession.provider_subscription_id
        ) {
            setInstallmentSession(null);
            installmentSessionRef.current = null;
            setInstallmentStatus(null);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [billingDay]);

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
    }, [
        isInstallmentSelected,
        payFullSdkReady,
        checkout.create_order_url,
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
                        ? billingDay
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
        // Closing the PayPal card sheet should keep buyers on the current
        // YogaFX package page so they can switch funding choices without
        // losing the prepared checkout form or being pushed to invoice status.
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
                    <div>
                        <InputLabel
                            value="Payment Type"
                            className="text-sm font-medium text-white/90"
                            style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 500 }}
                        />
                        <p
                            className="mt-2 text-sm leading-6 text-white/60"
                            style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 400 }}
                        >
                            Choose the payment path prepared by the backend checkout contract for this YogaFX package.
                        </p>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        {paymentOptions.map((option) => {
                            const optionIsActive = paymentType === option.type;
                            const optionIsInstallment =
                                option.type === "installment";
                            const optionBillingDay =
                                optionIsInstallment && optionIsActive
                                    ? billingDay
                                    : option.billing_day;
                            const optionFinalDueAt =
                                optionIsInstallment && optionIsActive
                                    ? activeInstallmentSummary?.final_due_at ??
                                      option.final_due_at
                                    : option.final_due_at;

                            return (
                                <button
                                    key={option.type}
                                    type="button"
                                    onClick={() => setPaymentType(option.type)}
                                    className={`rounded-[5px] border px-5 py-5 text-left transition-all duration-200 ${
                                        optionIsActive
                                            ? "border-[#DB202C] bg-[#DB202C]/10 shadow-[0_0_0_1px_rgba(219,32,44,0.25)]"
                                            : "border-white/10 bg-black/15 hover:border-white/25"
                                    }`}
                                    style={{ fontFamily: FONT_FAMILY }}
                                >
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="space-y-1">
                                            <p
                                                className="text-base text-white"
                                                style={{ fontFamily: FONT_FAMILY, fontSize: "16px", fontWeight: 600 }}
                                            >
                                                {option.label}
                                            </p>
                                            <p
                                                className="text-sm text-white/70"
                                                style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 400 }}
                                            >
                                                Due today:{" "}
                                                {formatCurrency(
                                                    option.amount_due_today,
                                                    option.currency_code,
                                                )}
                                            </p>
                                        </div>
                                        <span
                                            className={`rounded-full px-3 py-1 text-xs uppercase tracking-[0.18em] ${
                                                optionIsActive
                                                    ? "bg-[#DB202C] text-white"
                                                    : "bg-white/10 text-white/70"
                                            }`}
                                            style={{ fontFamily: FONT_FAMILY, fontWeight: 600 }}
                                        >
                                            {optionIsInstallment
                                                ? "Installment"
                                                : "Full"}
                                        </span>
                                    </div>

                                    {optionIsInstallment ? (
                                        <div className="mt-4 grid gap-2 text-sm text-white/65 sm:grid-cols-2">
                                            <p>
                                                Recurring amount:{" "}
                                                {formatCurrency(
                                                    option.recurring_amount,
                                                    option.currency_code,
                                                )}
                                            </p>
                                            <p>
                                                Total cycles:{" "}
                                                {option.installment_count}
                                            </p>
                                            <p>
                                                Recurring schedule:{" "}
                                                {usesMonthlyInstallmentSchedule
                                                    ? `every ${formatBillingDayLabel(optionBillingDay)}`
                                                    : formatIntervalLabel(
                                                          installmentIntervalUnit,
                                                          installmentIntervalCount,
                                                      )}
                                            </p>
                                            <p>
                                                Final due:{" "}
                                                {formatScheduleDate(
                                                    optionFinalDueAt,
                                                )}
                                            </p>
                                        </div>
                                    ) : (
                                        <p
                                            className="mt-4 text-sm leading-6 text-white/60"
                                            style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 400 }}
                                        >
                                            Pay the full package amount now and continue to onboarding after payment succeeds.
                                        </p>
                                    )}
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
                                {installmentAllowedBillingDays.map((allowedDay) => (
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
                                            checked={billingDay === allowedDay}
                                            onChange={() => setBillingDay(allowedDay)}
                                            className="h-4 w-4 border-white/20 bg-black/30 text-[#DB202C] focus:ring-[#DB202C]"
                                        />
                                        <span>Every {formatBillingDayLabel(allowedDay)} of the month</span>
                                    </label>
                                ))}
                            </div>
                            <InputError
                                className="mt-2 text-sm font-medium text-rose-400"
                                style={{ fontFamily: FONT_FAMILY }}
                                message={fieldErrors.billing_day}
                            />
                        </div>
                    )}

                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p
                                className="text-sm uppercase tracking-[0.18em] text-[#ffb8bf]"
                                style={{ fontFamily: FONT_FAMILY, fontSize: "12px", fontWeight: 600 }}
                            >
                                Installment Plan
                            </p>
                            <h3
                                className="mt-2 text-white"
                                style={{ fontFamily: FONT_FAMILY, fontSize: "22px", fontWeight: 500 }}
                            >
                                Pay{" "}
                                {formatCurrency(
                                    amountDueToday,
                                    activeCurrencyCode,
                                )}{" "}
                                {usesMonthlyInstallmentSchedule
                                    ? showBillingDaySelector
                                        ? `today, then continue monthly on the ${formatBillingDayLabel(activeBillingDay)}`
                                        : "today, then continue on this monthly installment schedule"
                                    : `today, then continue on the ${formatIntervalLabel(installmentIntervalUnit, installmentIntervalCount)} recurring schedule`}
                            </h3>
                            <p
                                className="mt-3 text-sm leading-6 text-white/65"
                                style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 400 }}
                            >
                                Clear breakdown of your YogaFX installment schedule before you continue to PayPal approval.
                            </p>
                        </div>


                    </div>

                    <div
                        className="overflow-hidden rounded-[5px] border border-white/10 bg-black/20"
                        style={{ fontFamily: FONT_FAMILY }}
                    >
                        <div className="hidden grid-cols-[minmax(0,1.1fr)_minmax(0,1.4fr)] border-b border-white/10 bg-white/[0.03] px-5 py-3 text-xs uppercase tracking-[0.16em] text-white/45 md:grid">
                            <span>Detail</span>
                            <span>Value</span>
                        </div>

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
                                    helper: "Total package price for this checkout.",
                                },
                                {
                                    label: "Number of Installments",
                                    value: `${installmentCount} total payments`,
                                    helper: "Includes the first payment due at checkout.",
                                },
                                {
                                    label: "Monthly Installment",
                                    value: formatCurrency(
                                        recurringAmount,
                                        activeCurrencyCode,
                                    ),
                                    helper: usesMonthlyInstallmentSchedule
                                        ? showBillingDaySelector
                                            ? `Auto-billed every month on the ${formatBillingDayLabel(activeBillingDay)}.`
                                            : "Auto-billed on the package's monthly recurring schedule."
                                        : `Auto-billed on the ${formatIntervalLabel(installmentIntervalUnit, installmentIntervalCount)} recurring schedule.`,
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
                                    helper: lastInstallment?.due_at
                                        ? `Final scheduled charge on ${formatScheduleDate(lastInstallment.due_at)}.`
                                        : "Final scheduled charge in this installment plan.",
                                },
                                {
                                    label: "First Installment Due Today",
                                    value: formatCurrency(
                                        amountDueToday,
                                        activeCurrencyCode,
                                    ),
                                    helper: "Charged immediately when PayPal approval succeeds.",
                                },
                                {
                                    label: "Next Installment",
                                    value: nextInstallment
                                        ? `${formatCurrency(Number(nextInstallment.amount ?? recurringAmount), activeCurrencyCode)} on ${formatScheduleDate(nextInstallment.due_at)}`
                                        : "Will be scheduled after the first payment.",
                                    helper: "Your next recurring installment after checkout.",
                                },
                                {
                                    label: "Last Installment Date",
                                    value: formatScheduleDate(finalDueAt),
                                    helper: "End date of this current installment schedule.",
                                },
                                installmentAcceptsBillingDay
                                    ? {
                                          label: "Preferred Monthly Billing Day",
                                          value: `Every ${formatBillingDayLabel(activeBillingDay)} of the month`,
                                          helper: showBillingDaySelector
                                              ? "You can change this before continuing to PayPal."
                                              : "Set by the package's installment billing configuration.",
                                      }
                                    : null,
                            ]
                                .filter(Boolean)
                                .map((row) => (
                                    <div
                                        key={row.label}
                                        className="grid gap-2 px-5 py-4 md:grid-cols-[minmax(0,1.1fr)_minmax(0,1.4fr)] md:gap-6"
                                    >
                                        <div className="min-w-0">
                                            <p className="text-[11px] uppercase tracking-[0.16em] text-white/45 md:hidden">
                                                {row.label}
                                            </p>
                                            <p className="hidden text-sm font-medium text-white/72 md:block">
                                                {row.label}
                                            </p>
                                        </div>

                                        <div className="min-w-0">
                                            <p className="text-base font-semibold text-white md:text-[15px]">
                                                {row.value}
                                            </p>
                                            <p className="mt-1 text-sm leading-6 text-white/55">
                                                {row.helper}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                        </div>
                    </div>
                </div>
            )}

            <div>
                <label
                    className={`flex items-start gap-4 rounded-[5px] border px-5 py-5 text-sm font-normal text-white/80 transition-all duration-200 cursor-pointer ${
                        fieldErrors.terms_accepted
                            ? "border-rose-500 bg-rose-500/10 ring-1 ring-rose-500/20"
                            : "border-white/10 bg-white/5 shadow-lg backdrop-blur-sm hover:border-white/30"
                    }`}
                    style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 400 }}
                >
                    <input
                        type="checkbox"
                        checked={formData.terms_accepted}
                        onChange={(event) =>
                            setFieldValue("terms_accepted", event.target.checked)
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

            <div className="rounded-[5px] border border-[#DB202C]/30 bg-white/5 p-6 shadow-xl backdrop-blur-sm">
                <h2
                    className="text-white"
                    style={{ fontFamily: FONT_FAMILY, fontSize: "22px", fontWeight: 500 }}
                >
                    {isInstallmentSelected
                        ? "PayPal Installment Approval"
                        : "Payment Method"}
                </h2>
                <p
                    className="mt-2 text-sm font-normal leading-6 text-white/70"
                    style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 400 }}
                >
                    {isInstallmentSelected
                        ? ""
                        : "Pay with PayPal or your debit or credit card safely below."}
                </p>
                {paypalConfig.environment && (
                    <p
                        className="mt-3 text-xs uppercase tracking-[0.18em] text-white/45"
                        style={{ fontFamily: FONT_FAMILY, fontWeight: 600 }}
                    >
                        PayPal Environment: {paypalConfig.environment}
                    </p>
                )}

                {isInstallmentSelected ? (
                    <div
                        className={`mt-6 rounded-[5px] border border-white/10 bg-black/20 p-5 transition-opacity duration-300 ${
                            !formData.terms_accepted ? "opacity-60" : "opacity-100"
                        }`}
                    >
                        <p
                            className="text-sm leading-6 text-white/65"
                            style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 400 }}
                        >
                            You are approving the first payment of{" "}
                            <span className="font-semibold text-white">
                                {formatCurrency(
                                    amountDueToday,
                                    activeCurrencyCode,
                                )}
                            </span>
                            . The remaining recurring payments follow{" "}
                            {usesMonthlyInstallmentSchedule
                                ? showBillingDaySelector
                                    ? `the ${formatBillingDayLabel(activeBillingDay)} monthly schedule`
                                    : "the package's monthly schedule"
                                : `the ${formatIntervalLabel(installmentIntervalUnit, installmentIntervalCount)} schedule`}{" "}
                            until{" "}
                            <span className="font-semibold text-white">
                                {formatScheduleDate(finalDueAt)}
                            </span>
                            .
                        </p>
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
                                style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 500 }}
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
                                <p
                                    className="text-sm text-white/65"
                                    style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 400 }}
                                >
                                    Continue in the PayPal sandbox popup. You should stay on the YogaFX checkout page while PayPal opens a separate approval window.
                                </p>
                                <div
                                    className="rounded-[5px] border border-white/10 p-5 shadow-inner"
                                    style={{ backgroundColor: "rgba(255, 255, 255, 0.97)" }}
                                >
                                    <div ref={installmentButtonsRef} className="min-h-[48px]" />
                                </div>
                                {!installmentSdkReady && (
                                    <div
                                        className="flex items-center gap-3 text-sm font-medium text-white/60"
                                        style={{ fontFamily: FONT_FAMILY }}
                                    >
                                        <LoaderCircle className="h-5 w-5 animate-spin text-[#DB202C]" />
                                        <span>Loading PayPal sandbox approval popup...</span>
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
                            style={{ backgroundColor: "rgba(255, 255, 255, 0.97)" }}
                        >
                            <div ref={payFullButtonsRef} className="min-h-[48px]" />
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
                                style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 600 }}
                            >
                                Local testing
                            </p>
                            <p
                                className="mt-1 text-sm font-normal text-white/60"
                                style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 400 }}
                            >
                                Mock checkout stays available only outside production.
                            </p>
                        </div>

                        <Button
                            type="button"
                            variant="outline"
                            onClick={runMockCheckout}
                            disabled={isSubmitting}
                            style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 500 }}
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
