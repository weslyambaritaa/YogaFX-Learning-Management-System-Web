import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import { Button } from "@/Components/ui/button";
import { formatCurrency } from "@/lib/currency";
import { AlertCircle, LoaderCircle } from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";

const INITIAL_ERRORS = {
    first_name: "",
    last_name: "",
    billing_postcode: "",
    billing_country: "",
    billing_day: "",
    terms_accepted: "",
    payment_method: "",
};

const FONT_FAMILY = "'Montserrat', sans-serif";

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
        checkout.installment_allowed_billing_days,
    )
        ? checkout.installment_allowed_billing_days
        : [];

    const [paymentType, setPaymentType] = useState(
        paymentOptions[0]?.type ?? "pay_full",
    );
    const [billingDay, setBillingDay] = useState(
        checkout.installment_selected_billing_day ??
            installmentAllowedBillingDays[0] ??
            15,
    );
    const [formData, setFormData] = useState({
        first_name: checkout.first_name ?? "",
        last_name: checkout.last_name ?? "",
        billing_postcode: "",
        billing_country: checkout.country ?? "",
        billing_address_line_1: "",
        billing_address_line_2: "",
        terms_accepted: false,
    });
    const formDataRef = useRef(formData);
    const [fieldErrors, setFieldErrors] = useState(INITIAL_ERRORS);
    const [generalError, setGeneralError] = useState("");
    const [sdkReady, setSdkReady] = useState(false);
    const [sdkError, setSdkError] = useState("");
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [debugInfo, setDebugInfo] = useState(null);
    const [installmentSession, setInstallmentSession] = useState(null);
    const [installmentStatus, setInstallmentStatus] = useState(null);
    const [installmentApprovalMessage, setInstallmentApprovalMessage] =
        useState("");

    const paypalButtonsRef = useRef(null);
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
    const canUseMock = mockAvailable && !isInstallmentSelected;

    const paypalScriptUrl = useMemo(() => {
        const params = new URLSearchParams(
            isInstallmentSelected
                ? {
                      "client-id": paypalConfig.client_id ?? "",
                      components: "buttons",
                      vault: "true",
                      intent: "subscription",
                  }
                : {
                      "client-id": paypalConfig.client_id ?? "",
                      components: "buttons",
                      currency:
                          paypalConfig.currency_code ?? checkout.currency_code,
                      intent: paypalConfig.intent ?? "capture",
                  },
        );

        return `https://www.paypal.com/sdk/js?${params.toString()}`;
    }, [
        checkout.currency_code,
        isInstallmentSelected,
        paypalConfig.client_id,
        paypalConfig.currency_code,
        paypalConfig.intent,
    ]);

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
            setSdkError("PayPal client configuration is missing.");
            return undefined;
        }

        let cancelled = false;
        let existingScript = document.querySelector(
            'script[data-paypal-checkout-sdk="true"]',
        );
        const existingSrc = existingScript?.getAttribute("src") ?? "";

        if (existingScript && existingSrc !== paypalScriptUrl) {
            existingScript.remove();
            existingScript = null;
            delete window.paypal;
            setSdkReady(false);
        }

        if (window.paypal && existingScript) {
            setSdkReady(true);
            setSdkError("");
            return undefined;
        }

        const handleReady = () => {
            if (!cancelled) {
                setSdkReady(true);
                setSdkError("");
            }
        };

        const handleError = () => {
            if (!cancelled) {
                setSdkError(
                    "PayPal checkout could not be loaded right now. Please refresh and try again.",
                );
            }
        };

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
        script.src = paypalScriptUrl;
        script.async = true;
        script.dataset.paypalCheckoutSdk = "true";
        script.addEventListener("load", handleReady);
        script.addEventListener("error", handleError);
        document.body.appendChild(script);

        return () => {
            cancelled = true;
            script.removeEventListener("load", handleReady);
            script.removeEventListener("error", handleError);
        };
    }, [paypalConfig.client_id, paypalScriptUrl]);

    useEffect(() => {
        if (!sdkReady || !window.paypal) {
            return undefined;
        }

        if (isInstallmentSelected) {
            return undefined;
        }

        const { paypal } = window;

        if (paypalButtonsRef.current) {
            paypalButtonsRef.current.innerHTML = "";
        }

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
            onCancel: async (data) => {
                await cancelActiveOrder(data.orderID);
            },
            onError: () => {
                setGeneralError(
                    "PayPal could not start the embedded checkout flow. Please try again.",
                );
                setIsSubmitting(false);
            },
        });

        if (buttons.isEligible() && paypalButtonsRef.current) {
            paypalButtonsRef.current.innerHTML = "";
            buttons.render(paypalButtonsRef.current).catch(() => {
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
            if (paypalButtonsRef.current) {
                paypalButtonsRef.current.innerHTML = "";
            }
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [isInstallmentSelected, sdkReady, paymentType, checkout.create_order_url]);

    useEffect(() => {
        if (!isInstallmentSelected) {
            return undefined;
        }

        if (!sdkReady || !window.paypal || !installmentSession) {
            return undefined;
        }

        if (installmentSession.provider_subscription_id) {
            return undefined;
        }

        const { paypal } = window;

        if (paypalButtonsRef.current) {
            paypalButtonsRef.current.innerHTML = "";
        }

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

        if (buttons.isEligible() && paypalButtonsRef.current) {
            paypalButtonsRef.current.innerHTML = "";
            buttons.render(paypalButtonsRef.current).catch(() => {
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
            if (paypalButtonsRef.current) {
                paypalButtonsRef.current.innerHTML = "";
            }
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        installmentSession,
        isInstallmentSelected,
        sdkReady,
        checkout.create_order_url,
    ]);

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
                        "PayPal approval is attached. YogaFX is still waiting for the first payment confirmation.",
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

        if (!(currentData.first_name || "").trim()) {
            nextErrors.first_name = "First name is required.";
        }

        if (!(currentData.last_name || "").trim()) {
            nextErrors.last_name = "Last name is required.";
        }

        if (!(currentData.billing_postcode || "").trim()) {
            nextErrors.billing_postcode = "Postcode is required.";
        }

        if (!(currentData.billing_country || "").trim()) {
            nextErrors.billing_country = "Billing country is required.";
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
                billing_day: isInstallmentSelected ? billingDay : null,
                first_name: currentData.first_name,
                last_name: currentData.last_name,
                billing_postcode: currentData.billing_postcode,
                billing_country: currentData.billing_country,
                billing_address_line_1: currentData.billing_address_line_1,
                billing_address_line_2: currentData.billing_address_line_2,
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
                "PayPal approval received. YogaFX is now waiting for the first payment confirmation from the sandbox webhook.",
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

    const cancelActiveOrder = async (orderId) => {
        const activeOrder = activeOrderRef.current;

        if (!activeOrder?.cancel_url) {
            setGeneralError("The PayPal checkout was cancelled.");
            setIsSubmitting(false);
            return;
        }

        const response = await fetch(activeOrder.cancel_url, {
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

        if (payload.redirect_url) {
            window.location.assign(payload.redirect_url);
            return;
        }

        setGeneralError("The PayPal checkout was cancelled.");
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
            {(generalError || sdkError) && (
                <div
                    className="rounded-xl border border-rose-400/30 bg-rose-500/10 px-5 py-4 text-sm font-medium text-rose-100"
                    style={{ fontFamily: FONT_FAMILY }}
                >
                    <div className="flex items-start gap-3">
                        <AlertCircle className="mt-0.5 h-5 w-5 flex-shrink-0" />
                        <p>{generalError || sdkError}</p>
                    </div>
                </div>
            )}

            <div className="space-y-6 rounded-[5px] border border-white/10 bg-white/5 p-6 shadow-lg backdrop-blur-sm">
                <div className="grid gap-6 md:grid-cols-2">
                    <div className="md:col-span-2">
                        <p
                            className="text-sm font-semibold text-[#DB202C]"
                            style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 600 }}
                        >
                            Billing identity
                        </p>
                    </div>

                    <div>
                        <InputLabel
                            value="First Name"
                            className="text-sm font-medium text-white/90"
                            style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 500 }}
                        />
                        <div className="relative mt-2">
                            <input
                                value={formData.first_name}
                                onChange={(event) =>
                                    setFieldValue("first_name", event.target.value)
                                }
                                style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 400 }}
                                className={`block w-full min-h-[52px] rounded-[5px] border bg-black/20 px-4 py-3.5 text-sm font-normal text-white shadow-sm transition-all duration-200 placeholder:text-white/30 focus:outline-none focus:ring-2 focus:ring-white/20 ${
                                    fieldErrors.first_name
                                        ? "border-rose-500 pr-11 focus:border-rose-500"
                                        : "border-white/20 focus:border-white/40"
                                }`}
                            />
                            {fieldErrors.first_name && (
                                <AlertCircle className="pointer-events-none absolute right-4 top-1/2 h-5 w-5 -translate-y-1/2 text-rose-400" />
                            )}
                        </div>
                        <InputError
                            className="mt-2 text-sm font-medium text-rose-400"
                            style={{ fontFamily: FONT_FAMILY }}
                            message={fieldErrors.first_name}
                        />
                    </div>

                    <div>
                        <InputLabel
                            value="Last Name"
                            className="text-sm font-medium text-white/90"
                            style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 500 }}
                        />
                        <div className="relative mt-2">
                            <input
                                value={formData.last_name}
                                onChange={(event) =>
                                    setFieldValue("last_name", event.target.value)
                                }
                                style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 400 }}
                                className={`block w-full min-h-[52px] rounded-[5px] border bg-black/20 px-4 py-3.5 text-sm font-normal text-white shadow-sm transition-all duration-200 placeholder:text-white/30 focus:outline-none focus:ring-2 focus:ring-white/20 ${
                                    fieldErrors.last_name
                                        ? "border-rose-500 pr-11 focus:border-rose-500"
                                        : "border-white/20 focus:border-white/40"
                                }`}
                            />
                            {fieldErrors.last_name && (
                                <AlertCircle className="pointer-events-none absolute right-4 top-1/2 h-5 w-5 -translate-y-1/2 text-rose-400" />
                            )}
                        </div>
                        <InputError
                            className="mt-2 text-sm font-medium text-rose-400"
                            style={{ fontFamily: FONT_FAMILY }}
                            message={fieldErrors.last_name}
                        />
                    </div>

                    <div className="md:col-span-2">
                        <InputLabel
                            value="Email"
                            className="text-sm font-medium text-white/90"
                            style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 500 }}
                        />
                        <input
                            value={checkout.email}
                            disabled
                            style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 400 }}
                            className="mt-2 block w-full min-h-[52px] rounded-[5px] border border-white/20 bg-black/20 px-4 py-3.5 text-sm font-normal text-white/60 opacity-70 transition-all duration-200"
                        />
                    </div>

                    <div className="md:col-span-2">
                        <InputLabel
                            value="Mobile Phone"
                            className="text-sm font-medium text-white/90"
                            style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 500 }}
                        />
                        <input
                            value={checkout.phone}
                            disabled
                            style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 400 }}
                            className="mt-2 block w-full min-h-[52px] rounded-[5px] border border-white/20 bg-black/20 px-4 py-3.5 text-sm font-normal text-white/60 opacity-70 transition-all duration-200"
                        />
                    </div>

                    <div>
                        <InputLabel
                            value="Billing Postcode"
                            className="text-sm font-medium text-white/90"
                            style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 500 }}
                        />
                        <div className="relative mt-2">
                            <input
                                value={formData.billing_postcode}
                                onChange={(event) =>
                                    setFieldValue("billing_postcode", event.target.value)
                                }
                                style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 400 }}
                                className={`block w-full min-h-[52px] rounded-[5px] border bg-black/20 px-4 py-3.5 text-sm font-normal text-white shadow-sm transition-all duration-200 placeholder:text-white/30 focus:outline-none focus:ring-2 focus:ring-white/20 ${
                                    fieldErrors.billing_postcode
                                        ? "border-rose-500 pr-11 focus:border-rose-500"
                                        : "border-white/20 focus:border-white/40"
                                }`}
                            />
                            {fieldErrors.billing_postcode && (
                                <AlertCircle className="pointer-events-none absolute right-4 top-1/2 h-5 w-5 -translate-y-1/2 text-rose-400" />
                            )}
                        </div>
                        <InputError
                            className="mt-2 text-sm font-medium text-rose-400"
                            style={{ fontFamily: FONT_FAMILY }}
                            message={fieldErrors.billing_postcode}
                        />
                    </div>

                    <div>
                        <InputLabel
                            value="Billing Country"
                            className="text-sm font-medium text-white/90"
                            style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 500 }}
                        />
                        <div className="relative mt-2">
                            <input
                                value={formData.billing_country}
                                onChange={(event) =>
                                    setFieldValue("billing_country", event.target.value)
                                }
                                style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 400 }}
                                className={`block w-full min-h-[52px] rounded-[5px] border bg-black/20 px-4 py-3.5 text-sm font-normal text-white shadow-sm transition-all duration-200 placeholder:text-white/30 focus:outline-none focus:ring-2 focus:ring-white/20 ${
                                    fieldErrors.billing_country
                                        ? "border-rose-500 pr-11 focus:border-rose-500"
                                        : "border-white/20 focus:border-white/40"
                                }`}
                            />
                            {fieldErrors.billing_country && (
                                <AlertCircle className="pointer-events-none absolute right-4 top-1/2 h-5 w-5 -translate-y-1/2 text-rose-400" />
                            )}
                        </div>
                        <InputError
                            className="mt-2 text-sm font-medium text-rose-400"
                            style={{ fontFamily: FONT_FAMILY }}
                            message={fieldErrors.billing_country}
                        />
                    </div>
                </div>

                <div className="my-6 border-t border-white/10" />

                <div className="space-y-4">
                    <div className="flex items-center justify-between gap-3">
                        <p
                            className="text-sm font-semibold text-[#DB202C]"
                            style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 600 }}
                        >
                            Billing address
                        </p>
                        <span
                            className="text-xs text-white/40"
                            style={{ fontFamily: FONT_FAMILY }}
                        >
                            Optional second line supported
                        </span>
                    </div>

                    <div className="grid gap-6 md:grid-cols-2">
                        <div className="md:col-span-2">
                            <InputLabel
                                value="Billing Address Line 1"
                                className="text-sm font-medium text-white/90"
                                style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 500 }}
                            />
                            <input
                                value={formData.billing_address_line_1}
                                onChange={(event) =>
                                    setFieldValue("billing_address_line_1", event.target.value)
                                }
                                style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 400 }}
                                className="mt-2 block w-full min-h-[52px] rounded-[5px] border border-white/20 bg-black/20 px-4 py-3.5 text-sm font-normal text-white shadow-sm transition-all duration-200 placeholder:text-white/30 focus:border-white/40 focus:outline-none focus:ring-2 focus:ring-white/20"
                            />
                        </div>

                        <div className="md:col-span-2">
                            <InputLabel
                                value="Billing Address Line 2"
                                className="text-sm font-medium text-white/90"
                                style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 500 }}
                            />
                            <input
                                value={formData.billing_address_line_2}
                                onChange={(event) =>
                                    setFieldValue("billing_address_line_2", event.target.value)
                                }
                                style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 400 }}
                                className="mt-2 block w-full min-h-[52px] rounded-[5px] border border-white/20 bg-black/20 px-4 py-3.5 text-sm font-normal text-white shadow-sm transition-all duration-200 placeholder:text-white/30 focus:border-white/40 focus:outline-none focus:ring-2 focus:ring-white/20"
                            />
                        </div>
                    </div>
                </div>
            </div>

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
                                                Recurring day: every{" "}
                                                {formatBillingDayLabel(
                                                    optionBillingDay,
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
                                today, then continue monthly on the{" "}
                                {formatBillingDayLabel(activeBillingDay)}
                            </h3>
                        </div>

                        <div
                            className="rounded-[5px] border border-white/10 bg-black/20 px-4 py-3 text-right"
                            style={{ fontFamily: FONT_FAMILY }}
                        >
                            <p className="text-xs uppercase tracking-[0.16em] text-white/45">
                                Final due date
                            </p>
                            <p className="mt-1 text-sm font-medium text-white">
                                {formatScheduleDate(finalDueAt)}
                            </p>
                        </div>
                    </div>

                    <div className="mt-5 grid gap-4 md:grid-cols-3">
                        <div className="rounded-[5px] border border-white/10 bg-black/20 px-4 py-4">
                            <p className="text-xs uppercase tracking-[0.16em] text-white/45">
                                First payment
                            </p>
                            <p className="mt-2 text-lg font-semibold text-white">
                                {formatCurrency(
                                    amountDueToday,
                                    activeCurrencyCode,
                                )}
                            </p>
                            <p className="mt-1 text-sm text-white/55">
                                Charged immediately when PayPal approval succeeds.
                            </p>
                        </div>

                        <div className="rounded-[5px] border border-white/10 bg-black/20 px-4 py-4">
                            <p className="text-xs uppercase tracking-[0.16em] text-white/45">
                                Recurring payment
                            </p>
                            <p className="mt-2 text-lg font-semibold text-white">
                                {formatCurrency(
                                    recurringAmount,
                                    activeCurrencyCode,
                                )}
                            </p>
                            <p className="mt-1 text-sm text-white/55">
                                Auto-billed every month on the{" "}
                                {formatBillingDayLabel(activeBillingDay)}.
                            </p>
                        </div>

                        <div className="rounded-[5px] border border-white/10 bg-black/20 px-4 py-4">
                            <p className="text-xs uppercase tracking-[0.16em] text-white/45">
                                Installment count
                            </p>
                            <p className="mt-2 text-lg font-semibold text-white">
                                {installmentCount} total payments
                            </p>
                            <p className="mt-1 text-sm text-white/55">
                                Includes the checkout payment and recurring cycles through January 15.
                            </p>
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
                        ? "Installment checkout continues through the PayPal subscription approval flow. YogaFX will wait for the payment confirmation webhook before opening onboarding."
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
                            . The remaining recurring payments follow the{" "}
                            {formatBillingDayLabel(activeBillingDay)} monthly schedule
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
                                    "PayPal approval is already attached to this checkout. YogaFX is waiting for the first payment confirmation webhook before opening onboarding."}
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
                                    <div ref={paypalButtonsRef} className="min-h-[48px]" />
                                </div>
                                {!sdkReady && (
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
                            <div ref={paypalButtonsRef} className="min-h-[48px]" />
                        </div>
                    </div>
                )}

                {!isInstallmentSelected && !sdkReady && (
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
