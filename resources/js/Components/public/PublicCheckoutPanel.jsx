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
    terms_accepted: "",
    payment_method: "",
};

// Single source of truth for the font so it can't be silently
// overridden by an older font-family declared elsewhere in the tree.
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

export default function PublicCheckoutPanel({ checkout }) {
    const mockAvailable = (checkout.payment_method_options ?? []).some(
        (option) => option.value === "mock",
    );
    const paypalConfig = checkout.paypal ?? {};

    const paypalScriptUrl = useMemo(() => {
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

    const [paymentType, setPaymentType] = useState("pay_full");
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

    const paypalButtonsRef = useRef(null);
    const activeOrderRef = useRef(null);
    const buttonsInstanceRef = useRef(null);

    const installmentAmount = Number(checkout.amount || 0) / 4;

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

        buttonsInstanceRef.current = buttons;

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
            buttonsInstanceRef.current = null;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [sdkReady, paymentType, checkout.create_order_url]);

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

        if (payload.status === "success" && payload.redirect_url) {
            window.location.assign(payload.redirect_url);
            return payload;
        }

        activeOrderRef.current = payload;
        return payload;
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

            {/* Panel 1: Billing Identity & Address */}
            <div className="space-y-6 rounded-[16px] border border-white/10 bg-white/5 p-6 shadow-lg backdrop-blur-sm">

                <div className="grid gap-6 md:grid-cols-2">
                    {/* Billing Identity Header */}
                    <div className="md:col-span-2">
                        <p
                            className="text-sm font-semibold text-[#DB202C]"
                            style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 600 }}
                        >
                            Billing identity
                        </p>
                    </div>

                    {/* First Name */}
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
                        <InputError className="mt-2 text-sm font-medium text-rose-400" style={{ fontFamily: FONT_FAMILY }} message={fieldErrors.first_name} />
                    </div>

                    {/* Last Name */}
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
                        <InputError className="mt-2 text-sm font-medium text-rose-400" style={{ fontFamily: FONT_FAMILY }} message={fieldErrors.last_name} />
                    </div>

                    {/* Email (Full Width) */}
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

                    {/* Mobile Phone (Full Width) */}
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

                    {/* Billing Postcode */}
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
                        <InputError className="mt-2 text-sm font-medium text-rose-400" style={{ fontFamily: FONT_FAMILY }} message={fieldErrors.billing_postcode} />
                    </div>

                    {/* Billing Country */}
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
                        <InputError className="mt-2 text-sm font-medium text-rose-400" style={{ fontFamily: FONT_FAMILY }} message={fieldErrors.billing_country} />
                    </div>
                </div>

                {/* Divider */}
                <div className="my-6 border-t border-white/10" />

                {/* Billing Address */}
                <div className="space-y-4">
                    <div className="flex items-center justify-between gap-3">
                        <p
                            className="text-sm font-semibold text-[#DB202C]"
                            style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 600 }}
                        >
                            Billing address
                        </p>
                        <span className="text-xs text-white/40" style={{ fontFamily: FONT_FAMILY }}>
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
                                    setFieldValue(
                                        "billing_address_line_1",
                                        event.target.value,
                                    )
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
                                    setFieldValue(
                                        "billing_address_line_2",
                                        event.target.value,
                                    )
                                }
                                style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 400 }}
                                className="mt-2 block w-full min-h-[52px] rounded-[5px] border border-white/20 bg-black/20 px-4 py-3.5 text-sm font-normal text-white shadow-sm transition-all duration-200 placeholder:text-white/30 focus:border-white/40 focus:outline-none focus:ring-2 focus:ring-white/20"
                            />
                        </div>
                    </div>
                </div>
            </div>

            {/* Panel 2: Payment Type Selection */}
            <div className="rounded-[16px] border border-white/10 bg-white/5 p-6 shadow-lg backdrop-blur-sm">
                <div className="w-full">
                    <InputLabel
                        value="Payment Type"
                        className="text-sm font-medium text-white/90"
                        style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 500 }}
                    />
                    <select
                        value={paymentType}
                        onChange={(event) => setPaymentType(event.target.value)}
                        style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 400 }}
                        className="mt-2 block w-full min-h-[52px] rounded-[5px] border border-white/20 bg-black/20 px-4 py-3.5 text-sm font-normal text-white shadow-sm transition-all duration-200 focus:border-white/40 focus:outline-none focus:ring-2 focus:ring-white/20"
                    >
                        <option value="pay_full" className="bg-gray-900 text-white" style={{ fontFamily: FONT_FAMILY }}>
                            Pay in Full -{" "}
                            {formatCurrency(
                                checkout.amount,
                                checkout.currency_code,
                            )}
                        </option>
                        <option value="installment" className="bg-gray-900 text-white" style={{ fontFamily: FONT_FAMILY }}>
                            Pay in 4 Installments -{" "}
                            {formatCurrency(
                                installmentAmount,
                                checkout.currency_code,
                            )}{" "}
                            today
                        </option>
                    </select>
                </div>
            </div>

            {/* Panel 3: Terms Checkbox */}
            <div>
                <label
                    className={`flex items-start gap-4 rounded-[16px] border px-5 py-5 text-sm font-normal text-white/80 transition-all duration-200 cursor-pointer ${
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
                        I agree to continue with YogaFX payment processing
                        and understand that sensitive card data is handled
                        directly by PayPal-hosted secure components.
                    </span>
                    {fieldErrors.terms_accepted && (
                        <AlertCircle className="mt-1 h-5 w-5 flex-shrink-0 text-rose-400" />
                    )}
                </label>
                <InputError className="mt-2 text-sm font-medium text-rose-400" style={{ fontFamily: FONT_FAMILY }} message={fieldErrors.terms_accepted} />
            </div>

            {debugInfo && (
                <div className="rounded-xl border border-white/10 bg-black/20 px-5 py-4 text-sm leading-6 text-white/70" style={{ fontFamily: FONT_FAMILY }}>
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
                </div>
            )}

            {/* Panel 4: Payment Button Area */}
            <div className="rounded-[16px] border border-[#DB202C]/30 bg-white/5 p-6 shadow-xl backdrop-blur-sm">
                <h2
                    className="text-white"
                    style={{ fontFamily: FONT_FAMILY, fontSize: "22px", fontWeight: 500 }}
                >
                    Payment Method
                </h2>
                <p className="mt-2 text-sm font-normal leading-6 text-white/70" style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 400 }}>
                    Pay with PayPal or your debit or credit card safely below.
                </p>

                <div
                    className={`mt-6 transition-opacity duration-300 ${
                        !formData.terms_accepted
                            ? "opacity-50 grayscale-[30%]"
                            : "opacity-100 grayscale-0"
                    }`}
                >
                    <div className="rounded-[16px] border border-white/10 bg-black/20 p-5 shadow-inner">
                        <div ref={paypalButtonsRef} className="min-h-[48px]" />
                    </div>
                </div>

                {!sdkReady && (
                    <div className="mt-5 flex items-center gap-3 text-sm font-medium text-white/60" style={{ fontFamily: FONT_FAMILY }}>
                        <LoaderCircle className="h-5 w-5 animate-spin text-[#DB202C]" />
                        <span>Loading secure payment methods...</span>
                    </div>
                )}
            </div>

            {mockAvailable && (
                <div className="rounded-[16px] border border-dashed border-white/20 bg-black/15 p-6 backdrop-blur-sm">
                    <div className="flex flex-wrap items-center justify-between gap-5">
                        <div>
                            <p className="text-sm font-semibold text-white" style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 600 }}>
                                Local testing
                            </p>
                            <p className="mt-1 text-sm font-normal text-white/60" style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 400 }}>
                                Mock checkout stays available only
                                outside production.
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