import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import { Button } from "@/Components/ui/button";
import PublicFlowLayout from "@/Layouts/PublicFlowLayout";
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

export default function Checkout({ checkout }) {
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
    
    // STATE UNTUK FORM
    const [formData, setFormData] = useState({
        first_name: checkout.first_name ?? "",
        last_name: checkout.last_name ?? "",
        billing_postcode: "",
        billing_country: checkout.country ?? "",
        billing_address_line_1: "",
        billing_address_line_2: "",
        terms_accepted: false,
    });
    
    // REF UNTUK MENCEGAH STALE CLOSURE PADA TOMBOL PAYPAL
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

    // Memuat Script SDK PayPal
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

    // Merender Tombol Standard PayPal
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

                // Memvalidasi seluruh field menggunakan REF data terbaru
                if (!validateCheckoutFields()) {
                    console.warn("click_initiate_payment_reject", {
                        reason: "frontend_validation_failed",
                    });
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
    }, [sdkReady, paymentType]);

    // FUNGSI UPDATE STATE DAN REF SECARA BERSAMAAN
    const setFieldValue = (field, value) => {
        setFormData((current) => {
            const updated = { ...current, [field]: value };
            formDataRef.current = updated; // Selalu perbarui Ref agar PayPal membaca data terbaru
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

    // VALIDASI MENGGUNAKAN REF (DATA TERBARU)
    const validateCheckoutFields = () => {
        const nextErrors = { ...INITIAL_ERRORS };
        const currentData = formDataRef.current; // INI KUNCI UTAMANYA

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

    // CREATE ORDER MENGGUNAKAN REF (DATA TERBARU)
    const createOrderSession = async (paymentMethod) => {
        if (!validateCheckoutFields()) {
            setIsSubmitting(false);
            throw new Error("Checkout form is incomplete.");
        }

        setIsSubmitting(true);
        setGeneralError("");
        setDebugInfo(null);

        const currentData = formDataRef.current; // INI KUNCI UTAMANYA

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

            console.error("create_order_error", nextDebugInfo);
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

        console.debug("create_order_success", {
            checkout_mode: "paypal",
            payment_method: paymentMethod,
            invoice_id: payload.invoice_id ?? null,
            order_id: payload.order_id ?? null,
        });

        activeOrderRef.current = payload;
        return payload;
    };

    const captureApprovedOrder = async (orderId) => {
        const activeOrder = activeOrderRef.current;

        if (!activeOrder?.capture_url) {
            setGeneralError("The payment capture route was not prepared correctly.");
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
                "The payment was processed, but the final status page could not be opened automatically.",
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
        <PublicFlowLayout
            title="Checkout"
            eyebrow="Secure Checkout"
            heading="Complete YogaFX checkout without leaving our system."
            description="PayPal still handles payment processing behind the scenes, while this page keeps the checkout experience calm, onsite, and tied to the correct product tier."
            aside={
                <div className="space-y-6">
                    <div className="rounded-[18px] border border-white/10 bg-white/5 p-5">
                        <div className="flex justify-between gap-3">
                            <p className="text-sm font-semibold text-white">
                                Selected product
                            </p>
                            <span className="rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs uppercase tracking-[0.2em] text-white/70">
                                {checkout.access_tier.slug.replaceAll("_", " ")}
                            </span>
                        </div>
                        <div className="mt-5 space-y-2">
                            <p className="text-2xl font-semibold text-white">
                                {checkout.access_tier.name}
                            </p>
                            <p className="inline-flex rounded-full border border-white/15 bg-black/20 px-4 py-2 text-sm text-white">
                                {formatCurrency(
                                    checkout.amount,
                                    checkout.currency_code,
                                )}
                            </p>
                        </div>
                    </div>

                    <div className="rounded-[18px] border border-white/10 bg-white/5 p-5 text-sm leading-6 text-white/72">
                        <p>
                            Card number, expiry, and CVV are rendered inside
                            PayPal-hosted secure fields and are never stored in
                            our database.
                        </p>
                        <p className="mt-3">
                            PayPal login, wallet details, and provider-side
                            authentication stay managed by PayPal through the
                            official in-context flow.
                        </p>
                    </div>

                    <div className="rounded-[18px] border border-white/10 bg-white/5 p-5 text-sm leading-6 text-white/72">
                        <p>
                            {paymentType === "installment"
                                ? `The first successful payment records ${formatCurrency(installmentAmount, checkout.currency_code)} now and leaves the remaining balance on the invoice.`
                                : "A successful capture immediately marks the transaction paid and opens the next onboarding step."}
                        </p>
                    </div>
                </div>
            }
        >
            <div className="space-y-6">
                {(generalError || sdkError) && (
                    <div className="rounded-[18px] border border-rose-400/30 bg-rose-500/10 px-4 py-4 text-sm text-rose-100">
                        <div className="flex items-start gap-3">
                            <AlertCircle className="mt-0.5 h-5 w-5 flex-shrink-0" />
                            <p>{generalError || sdkError}</p>
                        </div>
                    </div>
                )}

                <div className="space-y-5 rounded-[20px] border border-white/10 bg-white/[0.03] p-5 shadow-[0_18px_48px_rgba(0,0,0,0.18)] backdrop-blur-sm">
                    <div className="grid gap-5 md:grid-cols-2">
                        <div className="rounded-[20px] border border-white/8 bg-black/15 p-4 md:col-span-2">
                            <p className="text-xs font-semibold uppercase tracking-[0.24em] text-white/45">
                                Billing identity
                            </p>
                            <div className="mt-4 grid gap-5 md:grid-cols-2">
                                <div>
                                    <InputLabel value="First Name" className="text-white/84" />
                                    <div className="relative mt-2">
                                        <input
                                            value={formData.first_name}
                                            onChange={(event) =>
                                                setFieldValue("first_name", event.target.value)
                                            }
                                            className={`block w-full rounded-2xl border bg-black/30 px-4 py-3 text-white transition-all duration-200 placeholder:text-white/28 focus:outline-none focus:ring-2 focus:ring-[#d7a686]/30 ${
                                                fieldErrors.first_name
                                                    ? "border-rose-500 pr-11 focus:border-rose-400"
                                                    : "border-white/10 focus:border-[#d7a686]"
                                            }`}
                                        />
                                        {fieldErrors.first_name && (
                                            <AlertCircle className="pointer-events-none absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 text-rose-300" />
                                        )}
                                    </div>
                                    <InputError className="mt-2" message={fieldErrors.first_name} />
                                </div>

                                <div>
                                    <InputLabel value="Last Name" className="text-white/84" />
                                    <div className="relative mt-2">
                                        <input
                                            value={formData.last_name}
                                            onChange={(event) =>
                                                setFieldValue("last_name", event.target.value)
                                            }
                                            className={`block w-full rounded-2xl border bg-black/30 px-4 py-3 text-white transition-all duration-200 placeholder:text-white/28 focus:outline-none focus:ring-2 focus:ring-[#d7a686]/30 ${
                                                fieldErrors.last_name
                                                    ? "border-rose-500 pr-11 focus:border-rose-400"
                                                    : "border-white/10 focus:border-[#d7a686]"
                                            }`}
                                        />
                                        {fieldErrors.last_name && (
                                            <AlertCircle className="pointer-events-none absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 text-rose-300" />
                                        )}
                                    </div>
                                    <InputError className="mt-2" message={fieldErrors.last_name} />
                                </div>
                            </div>
                        </div>

                        <div>
                            <InputLabel value="Email" className="text-white/84" />
                            <input
                                value={checkout.email}
                                disabled
                                className="mt-2 block w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white/75 transition-all duration-200"
                            />
                        </div>

                        <div>
                            <InputLabel value="Mobile Phone" className="text-white/84" />
                            <input
                                value={checkout.phone}
                                disabled
                                className="mt-2 block w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white/75 transition-all duration-200"
                            />
                        </div>

                        <div>
                            <InputLabel value="Billing Postcode" className="text-white/84" />
                            <div className="relative mt-2">
                                <input
                                    value={formData.billing_postcode}
                                    onChange={(event) =>
                                        setFieldValue("billing_postcode", event.target.value)
                                    }
                                    className={`block w-full rounded-2xl border bg-black/30 px-4 py-3 text-white transition-all duration-200 placeholder:text-white/28 focus:outline-none focus:ring-2 focus:ring-[#d7a686]/30 ${
                                        fieldErrors.billing_postcode
                                            ? "border-rose-500 pr-11 focus:border-rose-400"
                                            : "border-white/10 focus:border-[#d7a686]"
                                    }`}
                                />
                                {fieldErrors.billing_postcode && (
                                    <AlertCircle className="pointer-events-none absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 text-rose-300" />
                                )}
                            </div>
                            <InputError className="mt-2" message={fieldErrors.billing_postcode} />
                        </div>

                        <div>
                            <InputLabel value="Billing Country" className="text-white/84" />
                            <div className="relative mt-2">
                                <input
                                    value={formData.billing_country}
                                    onChange={(event) =>
                                        setFieldValue("billing_country", event.target.value)
                                    }
                                    className={`block w-full rounded-2xl border bg-black/30 px-4 py-3 text-white transition-all duration-200 placeholder:text-white/28 focus:outline-none focus:ring-2 focus:ring-[#d7a686]/30 ${
                                        fieldErrors.billing_country
                                            ? "border-rose-500 pr-11 focus:border-rose-400"
                                            : "border-white/10 focus:border-[#d7a686]"
                                    }`}
                                />
                                {fieldErrors.billing_country && (
                                    <AlertCircle className="pointer-events-none absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 text-rose-300" />
                                )}
                            </div>
                            <InputError className="mt-2" message={fieldErrors.billing_country} />
                        </div>
                    </div>

                    <div className="rounded-[20px] border border-white/8 bg-white/[0.045] p-4">
                        <div className="flex items-center justify-between gap-3">
                            <p className="text-xs font-semibold uppercase tracking-[0.24em] text-white/45">
                                Billing address
                            </p>
                            <span className="text-xs text-white/38">
                                Optional second line supported
                            </span>
                        </div>

                        <div className="mt-4 grid gap-5 md:grid-cols-2">
                            <div>
                                <InputLabel value="Billing Address Line 1" className="text-white/84" />
                                <input
                                    value={formData.billing_address_line_1}
                                    onChange={(event) =>
                                        setFieldValue(
                                            "billing_address_line_1",
                                            event.target.value,
                                        )
                                    }
                                    className="mt-2 block w-full rounded-2xl border border-white/10 bg-black/30 px-4 py-3 text-white transition-all duration-200 placeholder:text-white/28 focus:border-[#d7a686] focus:outline-none focus:ring-2 focus:ring-[#d7a686]/30"
                                />
                            </div>

                            <div>
                                <InputLabel value="Billing Address Line 2" className="text-white/84" />
                                <input
                                    value={formData.billing_address_line_2}
                                    onChange={(event) =>
                                        setFieldValue(
                                            "billing_address_line_2",
                                            event.target.value,
                                        )
                                    }
                                    className="mt-2 block w-full rounded-2xl border border-white/10 bg-black/30 px-4 py-3 text-white transition-all duration-200 placeholder:text-white/28 focus:border-[#d7a686] focus:outline-none focus:ring-2 focus:ring-[#d7a686]/30"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div className="rounded-[20px] border border-white/10 bg-white/[0.04] p-5 shadow-[0_18px_48px_rgba(0,0,0,0.16)] backdrop-blur-sm">
                    <div className="w-full">
                        <InputLabel value="Payment Type" className="text-white/84" />
                        <select
                            value={paymentType}
                            onChange={(event) => setPaymentType(event.target.value)}
                            className="mt-2 block w-full rounded-2xl border border-white/10 bg-black/30 px-4 py-3 text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.04)] transition-all duration-200 focus:border-[#d7a686] focus:outline-none focus:ring-2 focus:ring-[#d7a686]/30"
                        >
                            <option value="pay_full">
                                Pay in Full -{" "}
                                {formatCurrency(
                                    checkout.amount,
                                    checkout.currency_code,
                                )}
                            </option>
                            <option value="installment">
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

                <label
                    className={`flex items-start gap-3 rounded-[20px] border px-4 py-4 text-sm text-white/78 transition-all duration-200 ${
                        fieldErrors.terms_accepted
                            ? "border-rose-500 bg-rose-500/10 ring-1 ring-rose-500/20"
                            : "border-white/10 bg-white/[0.04] backdrop-blur-sm"
                    }`}
                >
                    <input
                        type="checkbox"
                        checked={formData.terms_accepted}
                        onChange={(event) =>
                            setFieldValue("terms_accepted", event.target.checked)
                        }
                        className="mt-1 h-4 w-4 rounded border-white/20 bg-black/20 text-[#d7a686] focus:ring-[#d7a686]"
                    />
                    <span className="flex-1">
                        I agree to continue with YogaFX payment processing and
                        understand that sensitive card data is handled directly
                        by PayPal-hosted secure components.
                    </span>
                    {fieldErrors.terms_accepted && (
                        <AlertCircle className="mt-0.5 h-4 w-4 flex-shrink-0 text-rose-300" />
                    )}
                </label>
                <InputError className="-mt-3" message={fieldErrors.terms_accepted} />

                {debugInfo && (
                    <div className="rounded-[18px] border border-white/10 bg-black/20 px-4 py-4 text-xs leading-6 text-white/68">
                        <p className="font-semibold text-white/82">
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

                <div className="rounded-[20px] border border-white/10 bg-white/[0.05] p-5 shadow-[0_24px_60px_rgba(0,0,0,0.2)] backdrop-blur-sm">
                    <h2 className="text-xl font-semibold text-white">
                        Payment Method
                    </h2>
                    <p className="mt-1 text-sm leading-6 text-white/64">
                        Please complete your billing information above. The payment buttons will become active once you accept the terms and conditions statement.
                    </p>

                    <div 
                        className={`mt-6 transition-opacity duration-200 ${
                            !formData.terms_accepted ? "opacity-50" : "opacity-100"
                        }`}
                    >
                        <div className="rounded-[20px] border border-white/10 bg-black/20 p-4 shadow-[inset_0_1px_0_rgba(255,255,255,0.04)]">
                            <div ref={paypalButtonsRef} />
                        </div>
                    </div>

                    {!sdkReady && (
                        <div className="mt-4 flex items-center gap-2 text-sm text-white/60">
                            <LoaderCircle className="h-4 w-4 animate-spin" />
                            <span>Loading payment methods...</span>
                        </div>
                    )}
                </div>

                {mockAvailable && (
                    <div className="rounded-[20px] border border-dashed border-white/20 bg-black/15 p-5 backdrop-blur-sm">
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <p className="text-sm font-semibold text-white">
                                    Local testing
                                </p>
                                <p className="mt-1 text-sm text-white/58">
                                    Mock checkout stays available only outside production.
                                </p>
                            </div>

                            <Button
                                type="button"
                                variant="outline"
                                onClick={runMockCheckout}
                                disabled={isSubmitting}
                                className="border-white/15 bg-transparent text-white hover:bg-white/10"
                            >
                                Run Mock Payment
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </PublicFlowLayout>
    );
}
