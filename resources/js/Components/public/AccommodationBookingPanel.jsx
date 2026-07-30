import FlagOptionSelect from "@/Components/FlagOptionSelect";
import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import TextInput from "@/Components/TextInput";
import {
    enrichCountryOptions,
    findCountryOptionByDialCode,
} from "@/lib/countryFlags";
import { formatCurrency } from "@/lib/currency";
import { usePage } from "@inertiajs/react";
import { useEffect, useRef, useState } from "react";

const FIELD_CLASS =
    "min-h-[52px] w-full rounded-[5px] border-2 border-white/50 bg-black/45 px-4 py-3.5 text-sm font-normal text-white placeholder:text-white/55 shadow-[0_0_0_1px_rgba(255,255,255,0.14)] transition-all duration-200 focus:border-white focus:bg-black/60 focus:ring-2 focus:ring-white/35 [&::-webkit-calendar-picker-indicator]:invert";
const LABEL_CLASS = "block text-sm font-medium text-white/80";
const PAYPAL_FULL_NAMESPACE = "paypalAccommodationCheckout";
const PAYPAL_INSTALLMENT_NAMESPACE = "paypalAccommodationInstallmentCheckout";
const INSTALLMENT_STATUS_POLL_MS = 4000;
const INSTALLMENT_STATUS_MAX_POLLS = 45; // ~3 minutes

// Copied verbatim from resources/js/Pages/Public/Scoreboard.jsx so the
// identity fields below render byte-identical to Package's checkout.
const FONT_FAMILY = "'Montserrat', sans-serif";

function getCsrfToken() {
    return document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content");
}

function todayDateString() {
    const now = new Date();

    return [
        now.getFullYear(),
        String(now.getMonth() + 1).padStart(2, "0"),
        String(now.getDate()).padStart(2, "0"),
    ].join("-");
}

function addDaysToDateString(dateString, days) {
    const parsed = new Date(`${dateString}T00:00:00`);

    if (Number.isNaN(parsed.getTime())) {
        return "";
    }

    parsed.setDate(parsed.getDate() + days);

    return [
        parsed.getFullYear(),
        String(parsed.getMonth() + 1).padStart(2, "0"),
        String(parsed.getDate()).padStart(2, "0"),
    ].join("-");
}

// Copied verbatim from Scoreboard.jsx.
function normalizePhoneNumberInput(value) {
    return String(value ?? "")
        .replace(/^\s+/, "")
        .replace(/^0+/, "");
}

// Copied verbatim from Scoreboard.jsx.
function isValidEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value ?? "").trim());
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

function getPayPalNamespace(namespace) {
    if (typeof window === "undefined") {
        return null;
    }

    return window[namespace] ?? null;
}

function clearContainer(ref) {
    if (ref?.current) {
        ref.current.innerHTML = "";
    }
}

export default function AccommodationBookingPanel({
    accommodation,
    roomTypes,
    prefill,
    availabilityUrl,
    ordersUrl,
    installmentsUrl,
    paypal,
}) {
    const { directory = {} } = usePage().props;
    const countryOptions = enrichCountryOptions(directory.countries ?? []);
    const phoneCountryCodeOptions = enrichCountryOptions(
        directory.phone_country_codes ?? [],
    );

    const [selectedRoomTypeId, setSelectedRoomTypeId] = useState(
        roomTypes[0]?.id ?? null,
    );
    const [checkInDate, setCheckInDate] = useState("");
    const [checkOutDate, setCheckOutDate] = useState("");

    // Identity fields — field names/shape match Scoreboard.jsx exactly.
    const [firstName, setFirstName] = useState(prefill?.first_name ?? "");
    const [lastName, setLastName] = useState(prefill?.last_name ?? "");
    const [email, setEmail] = useState(prefill?.email ?? "");
    const [phoneCountryCode, setPhoneCountryCode] = useState(
        prefill?.phone_country_code ?? "+62",
    );
    const [phoneNumber, setPhoneNumber] = useState(
        prefill?.phone_number ?? "",
    );
    const [country, setCountry] = useState(prefill?.country ?? "");
    const [termsAccepted, setTermsAccepted] = useState(false);

    const [availability, setAvailability] = useState(null);
    const [checkingAvailability, setCheckingAvailability] = useState(false);
    const [availabilityError, setAvailabilityError] = useState("");

    const [paymentMode, setPaymentMode] = useState("full"); // "full" | "installment"
    const [installmentCount, setInstallmentCount] = useState("");

    const [generalError, setGeneralError] = useState("");
    const [fieldErrors, setFieldErrors] = useState({});
    const [bookingCancelled, setBookingCancelled] = useState(false);
    const [payFullSdkReady, setPayFullSdkReady] = useState(false);
    const [payFullSdkError, setPayFullSdkError] = useState("");

    const [installmentSdkReady, setInstallmentSdkReady] = useState(false);
    const [installmentSdkError, setInstallmentSdkError] = useState("");
    const [installmentSession, setInstallmentSession] = useState(null);
    const [installmentApproved, setInstallmentApproved] = useState(false);
    const [installmentStatusMessage, setInstallmentStatusMessage] =
        useState("");

    const buttonsContainerRef = useRef(null);
    const installmentButtonsContainerRef = useRef(null);
    const orderSessionRef = useRef(null);
    const installmentSessionRef = useRef(null);
    const selectedRoomTypeIdRef = useRef(selectedRoomTypeId);
    const checkInDateRef = useRef(checkInDate);
    const checkOutDateRef = useRef(checkOutDate);
    const firstNameRef = useRef(firstName);
    const lastNameRef = useRef(lastName);
    const emailRef = useRef(email);
    const phoneCountryCodeRef = useRef(phoneCountryCode);
    const phoneNumberRef = useRef(phoneNumber);
    const countryRef = useRef(country);
    const termsAcceptedRef = useRef(termsAccepted);
    const availabilityRef = useRef(availability);
    const installmentCountRef = useRef(installmentCount);

    const minCheckOutDate = checkInDate
        ? addDaysToDateString(checkInDate, 1)
        : "";

    const normalizedEmail = email.trim().toLowerCase();
    const identityIsComplete =
        firstName.trim() !== "" &&
        lastName.trim() !== "" &&
        normalizedEmail !== "" &&
        isValidEmail(normalizedEmail) &&
        phoneCountryCode.trim() !== "" &&
        phoneNumber.trim() !== "" &&
        country.trim() !== "";

    const canPay =
        Boolean(availability?.available) && identityIsComplete && termsAccepted;
    const installmentOffer = availability?.installment ?? null;
    const canOfferInstallment = Boolean(
        accommodation.installment_enabled && installmentOffer,
    );
    const canPayInstallment =
        Boolean(installmentOffer) && identityIsComplete && termsAccepted;

    const selectedCountryOption =
        countryOptions.find((option) => option.value === country) ?? null;
    const selectedPhoneCountryOption = findCountryOptionByDialCode(
        phoneCountryCodeOptions,
        phoneCountryCode,
        country,
    );

    useEffect(() => {
        selectedRoomTypeIdRef.current = selectedRoomTypeId;
    }, [selectedRoomTypeId]);

    useEffect(() => {
        checkInDateRef.current = checkInDate;
    }, [checkInDate]);

    useEffect(() => {
        checkOutDateRef.current = checkOutDate;
    }, [checkOutDate]);

    useEffect(() => {
        firstNameRef.current = firstName;
    }, [firstName]);

    useEffect(() => {
        lastNameRef.current = lastName;
    }, [lastName]);

    useEffect(() => {
        emailRef.current = email;
    }, [email]);

    useEffect(() => {
        phoneCountryCodeRef.current = phoneCountryCode;
    }, [phoneCountryCode]);

    useEffect(() => {
        phoneNumberRef.current = phoneNumber;
    }, [phoneNumber]);

    useEffect(() => {
        countryRef.current = country;
    }, [country]);

    useEffect(() => {
        termsAcceptedRef.current = termsAccepted;
    }, [termsAccepted]);

    useEffect(() => {
        availabilityRef.current = availability;
    }, [availability]);

    useEffect(() => {
        installmentCountRef.current = installmentCount;
    }, [installmentCount]);

    useEffect(() => {
        installmentSessionRef.current = installmentSession;
    }, [installmentSession]);

    useEffect(() => {
        // A stale session must never be reused once the selection changes.
        orderSessionRef.current = null;
        setInstallmentSession(null);
        setInstallmentApproved(false);
        setInstallmentStatusMessage("");
        setInstallmentCount("");
        setBookingCancelled(false);
        setGeneralError("");
    }, [selectedRoomTypeId, checkInDate, checkOutDate]);

    // Fall back to "full" if installment stops being offered (e.g. dates
    // changed to a window that no longer fits enough installments).
    useEffect(() => {
        if (paymentMode === "installment" && !canOfferInstallment) {
            setPaymentMode("full");
        }
    }, [paymentMode, canOfferInstallment]);

    useEffect(() => {
        if (
            !selectedRoomTypeId ||
            !checkInDate ||
            !checkOutDate ||
            checkOutDate <= checkInDate
        ) {
            setAvailability(null);
            setAvailabilityError("");
            return undefined;
        }

        let cancelled = false;
        setCheckingAvailability(true);
        setAvailabilityError("");

        const body = {
            room_type_id: selectedRoomTypeId,
            check_in_date: checkInDate,
            check_out_date: checkOutDate,
        };

        if (paymentMode === "installment" && installmentCount) {
            body.installment_count = installmentCount;
        }

        fetch(availabilityUrl, {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-CSRF-TOKEN": getCsrfToken() ?? "",
                "X-Requested-With": "XMLHttpRequest",
            },
            body: JSON.stringify(body),
        })
            .then(async (response) => {
                const payload = await parseJsonSafely(response);

                if (cancelled) {
                    return;
                }

                if (!response.ok) {
                    setAvailability(null);
                    setAvailabilityError(
                        payload.message ??
                            "Unable to check availability for the selected dates.",
                    );
                    return;
                }

                setAvailability(payload);

                if (
                    payload.installment &&
                    (installmentCount === "" ||
                        installmentCount >
                            payload.installment.maximum_installment_count)
                ) {
                    setInstallmentCount(payload.installment.installment_count);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setAvailability(null);
                    setAvailabilityError(
                        "Unable to check availability right now. Please try again.",
                    );
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setCheckingAvailability(false);
                }
            });

        return () => {
            cancelled = true;
        };
        // installmentCount is intentionally excluded here — the flex-count
        // selector re-triggers this itself via its own effect below so we
        // don't refetch on every keystroke of an unrelated change.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selectedRoomTypeId, checkInDate, checkOutDate, availabilityUrl]);

    // Re-check availability (with the new count) whenever the guest changes
    // their selected installment count in flex mode.
    useEffect(() => {
        if (
            paymentMode !== "installment" ||
            !installmentOffer ||
            installmentOffer.count_mode !== "flex" ||
            !installmentCount
        ) {
            return undefined;
        }

        let cancelled = false;

        fetch(availabilityUrl, {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-CSRF-TOKEN": getCsrfToken() ?? "",
                "X-Requested-With": "XMLHttpRequest",
            },
            body: JSON.stringify({
                room_type_id: selectedRoomTypeId,
                check_in_date: checkInDate,
                check_out_date: checkOutDate,
                installment_count: installmentCount,
            }),
        })
            .then(async (response) => {
                if (cancelled || !response.ok) {
                    return;
                }

                const payload = await parseJsonSafely(response);
                setAvailability(payload);
            })
            .catch(() => {});

        return () => {
            cancelled = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [installmentCount]);

    // Load the pay-full PayPal JS SDK once client_id/currency are known.
    useEffect(() => {
        if (!paypal?.client_id) {
            setPayFullSdkError("PayPal is not configured for this hotel yet.");
            return undefined;
        }

        let cancelled = false;
        const scriptUrl = `https://www.paypal.com/sdk/js?${new URLSearchParams(
            {
                "client-id": paypal.client_id,
                components: "buttons",
                currency: paypal.currency_code,
                intent: paypal.intent ?? "capture",
            },
        ).toString()}`;

        let script = document.querySelector(
            "script[data-paypal-accommodation-sdk]",
        );

        if (
            script &&
            script.getAttribute("src") === scriptUrl &&
            getPayPalNamespace(PAYPAL_FULL_NAMESPACE)
        ) {
            setPayFullSdkReady(true);
            return undefined;
        }

        if (script && script.getAttribute("src") !== scriptUrl) {
            script.remove();
            script = null;
            delete window[PAYPAL_FULL_NAMESPACE];
        }

        const handleLoad = () => {
            if (!cancelled) {
                setPayFullSdkReady(true);
            }
        };
        const handleError = () => {
            if (!cancelled) {
                setPayFullSdkError(
                    "PayPal could not be loaded right now. Please refresh and try again.",
                );
            }
        };

        if (!script) {
            script = document.createElement("script");
            script.src = scriptUrl;
            script.async = true;
            script.dataset.paypalAccommodationSdk = "true";
            script.dataset.namespace = PAYPAL_FULL_NAMESPACE;
            script.setAttribute("data-namespace", PAYPAL_FULL_NAMESPACE);
            document.body.appendChild(script);
        }

        script.addEventListener("load", handleLoad);
        script.addEventListener("error", handleError);

        return () => {
            cancelled = true;
            script.removeEventListener("load", handleLoad);
            script.removeEventListener("error", handleError);
        };
    }, [paypal?.client_id, paypal?.currency_code, paypal?.intent]);

    // Load the installment (subscription-intent) PayPal JS SDK — a separate
    // script/namespace, only while installment mode is actually selected.
    // A capture-intent and a subscription-intent SDK cannot share one script.
    useEffect(() => {
        if (!paypal?.client_id || paymentMode !== "installment") {
            return undefined;
        }

        let cancelled = false;
        const scriptUrl = `https://www.paypal.com/sdk/js?${new URLSearchParams(
            {
                "client-id": paypal.client_id,
                components: "buttons",
                vault: "true",
                intent: "subscription",
            },
        ).toString()}`;

        let script = document.querySelector(
            "script[data-paypal-accommodation-installment-sdk]",
        );

        if (
            script &&
            script.getAttribute("src") === scriptUrl &&
            getPayPalNamespace(PAYPAL_INSTALLMENT_NAMESPACE)
        ) {
            setInstallmentSdkReady(true);
            return undefined;
        }

        if (script && script.getAttribute("src") !== scriptUrl) {
            script.remove();
            script = null;
            delete window[PAYPAL_INSTALLMENT_NAMESPACE];
        }

        const handleLoad = () => {
            if (!cancelled) {
                setInstallmentSdkReady(true);
            }
        };
        const handleError = () => {
            if (!cancelled) {
                setInstallmentSdkError(
                    "PayPal installment approval could not be loaded right now. Please refresh and try again.",
                );
            }
        };

        if (!script) {
            script = document.createElement("script");
            script.src = scriptUrl;
            script.async = true;
            script.dataset.paypalAccommodationInstallmentSdk = "true";
            script.dataset.namespace = PAYPAL_INSTALLMENT_NAMESPACE;
            script.setAttribute("data-namespace", PAYPAL_INSTALLMENT_NAMESPACE);
            document.body.appendChild(script);
        }

        script.addEventListener("load", handleLoad);
        script.addEventListener("error", handleError);

        return () => {
            cancelled = true;
            script.removeEventListener("load", handleLoad);
            script.removeEventListener("error", handleError);
        };
    }, [paypal?.client_id, paymentMode]);

    const validateBookingFields = () => {
        if (!selectedRoomTypeIdRef.current) {
            setGeneralError("Please select a room type.");
            return false;
        }

        if (!checkInDateRef.current || !checkOutDateRef.current) {
            setGeneralError("Please select both check-in and check-out dates.");
            return false;
        }

        if (!availabilityRef.current?.available) {
            setGeneralError(
                "Please choose dates with at least one room available.",
            );
            return false;
        }

        const nextFieldErrors = {};

        if (!firstNameRef.current.trim()) {
            nextFieldErrors.first_name = "First name is required.";
        }

        if (!lastNameRef.current.trim()) {
            nextFieldErrors.last_name = "Last name is required.";
        }

        const emailValue = emailRef.current.trim().toLowerCase();

        if (!emailValue) {
            nextFieldErrors.email = "Email is required.";
        } else if (!isValidEmail(emailValue)) {
            nextFieldErrors.email = "Enter a valid email address.";
        }

        if (!phoneCountryCodeRef.current.trim()) {
            nextFieldErrors.phone_country_code = "Phone country code is required.";
        }

        if (!phoneNumberRef.current.trim()) {
            nextFieldErrors.phone_number = "Mobile phone is required.";
        }

        if (!countryRef.current.trim()) {
            nextFieldErrors.country = "Country is required.";
        }

        if (Object.keys(nextFieldErrors).length > 0) {
            setFieldErrors(nextFieldErrors);
            setGeneralError("Please complete your details above.");
            return false;
        }

        if (!termsAcceptedRef.current) {
            setGeneralError("Please agree to continue before paying.");
            return false;
        }

        setFieldErrors({});
        setGeneralError("");
        return true;
    };

    const guestPayload = () => ({
        room_type_id: selectedRoomTypeIdRef.current,
        check_in_date: checkInDateRef.current,
        check_out_date: checkOutDateRef.current,
        first_name: firstNameRef.current,
        last_name: lastNameRef.current,
        guest_email: emailRef.current,
        phone_country_code: phoneCountryCodeRef.current,
        phone_number: phoneNumberRef.current,
        country: countryRef.current,
    });

    const createBookingOrder = async () => {
        setGeneralError("");
        setBookingCancelled(false);

        const response = await fetch(ordersUrl, {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-CSRF-TOKEN": getCsrfToken() ?? "",
                "X-Requested-With": "XMLHttpRequest",
            },
            body: JSON.stringify(guestPayload()),
        });

        const payload = await parseJsonSafely(response);

        if (!response.ok || !payload.order_id) {
            setGeneralError(
                payload.message ?? "Unable to start this booking. Please try again.",
            );
            throw new Error("order-creation-failed");
        }

        orderSessionRef.current = {
            bookingId: payload.booking_id,
            captureUrl: payload.capture_url,
            cancelUrl: payload.cancel_url,
        };

        return payload.order_id;
    };

    const captureBookingOrder = async (orderId) => {
        const session = orderSessionRef.current;

        if (!session?.captureUrl) {
            setGeneralError("Your booking session was lost. Please try again.");
            return;
        }

        const response = await fetch(session.captureUrl, {
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

        if (!response.ok || !payload.redirect_url) {
            setGeneralError(
                payload.message ?? "This payment did not complete. Please try again.",
            );
            return;
        }

        window.location.assign(payload.redirect_url);
    };

    const releaseBookingHold = async () => {
        const session = orderSessionRef.current;

        if (!session?.cancelUrl) {
            return;
        }

        try {
            await fetch(session.cancelUrl, {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    Accept: "application/json",
                    "X-CSRF-TOKEN": getCsrfToken() ?? "",
                    "X-Requested-With": "XMLHttpRequest",
                },
            });
        } catch {
            // Best effort — the hold expires on its own either way.
        }
    };

    const createInstallmentCheckout = async () => {
        setGeneralError("");
        setBookingCancelled(false);

        const body = guestPayload();

        if (installmentCountRef.current) {
            body.installment_count = installmentCountRef.current;
        }

        const response = await fetch(installmentsUrl, {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-CSRF-TOKEN": getCsrfToken() ?? "",
                "X-Requested-With": "XMLHttpRequest",
            },
            body: JSON.stringify(body),
        });

        const payload = await parseJsonSafely(response);

        if (!response.ok || !payload.provider_plan_id) {
            setGeneralError(
                payload.message ??
                    "Unable to start this installment checkout. Please try again.",
            );
            throw new Error("installment-plan-creation-failed");
        }

        const session = {
            bookingId: payload.booking_id,
            providerPlanId: payload.provider_plan_id,
            approveUrl: payload.approve_url,
            cancelUrl: payload.cancel_url,
            statusUrl: payload.status_url,
            paypalSubscriptionStartTime: payload.paypal_subscription_start_time,
        };

        installmentSessionRef.current = session;
        setInstallmentSession(session);

        return session;
    };

    const attachApprovedInstallmentSubscription = async (subscriptionId) => {
        const session = installmentSessionRef.current;

        if (!session?.approveUrl) {
            setGeneralError("Your installment session was lost. Please try again.");
            return;
        }

        const response = await fetch(session.approveUrl, {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-CSRF-TOKEN": getCsrfToken() ?? "",
                "X-Requested-With": "XMLHttpRequest",
            },
            body: JSON.stringify({ provider_subscription_id: subscriptionId }),
        });

        if (!response.ok) {
            const payload = await parseJsonSafely(response);
            setGeneralError(
                payload.message ??
                    "We could not confirm your PayPal approval. Please try again.",
            );
            return;
        }

        setInstallmentApproved(true);
        setInstallmentStatusMessage(
            "Your first payment is being confirmed by PayPal. This usually takes a few moments — please keep this page open.",
        );
    };

    const releaseInstallmentHold = async () => {
        const session = installmentSessionRef.current;

        if (!session?.cancelUrl) {
            return;
        }

        try {
            await fetch(session.cancelUrl, {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    Accept: "application/json",
                    "X-CSRF-TOKEN": getCsrfToken() ?? "",
                    "X-Requested-With": "XMLHttpRequest",
                },
            });
        } catch {
            // Best effort — the hold expires on its own either way.
        }
    };

    // Poll for the webhook-driven confirmation once the guest has approved
    // the subscription in the PayPal popup.
    useEffect(() => {
        if (!installmentApproved || !installmentSession?.statusUrl) {
            return undefined;
        }

        let cancelled = false;
        let attempts = 0;

        const poll = async () => {
            attempts += 1;

            try {
                const response = await fetch(installmentSession.statusUrl, {
                    method: "GET",
                    credentials: "same-origin",
                    headers: { Accept: "application/json" },
                });
                const payload = await parseJsonSafely(response);

                if (cancelled) {
                    return;
                }

                if (payload.status === "confirmed" && payload.redirect_url) {
                    window.location.assign(payload.redirect_url);
                    return;
                }

                if (payload.status === "past_due") {
                    setInstallmentStatusMessage(
                        "PayPal reported a problem confirming your first payment. Please check your email or contact support.",
                    );
                    return;
                }

                if (payload.status === "cancelled" || payload.status === "failed") {
                    setInstallmentStatusMessage(
                        "This installment subscription was not completed. Please start a new booking.",
                    );
                    return;
                }
            } catch {
                // Keep polling — a transient network error shouldn't stop it.
            }

            if (!cancelled && attempts < INSTALLMENT_STATUS_MAX_POLLS) {
                setTimeout(poll, INSTALLMENT_STATUS_POLL_MS);
            } else if (!cancelled) {
                setInstallmentStatusMessage(
                    "Confirmation is taking longer than expected. You will receive an email once your first payment is verified — you can safely leave this page.",
                );
            }
        };

        poll();

        return () => {
            cancelled = true;
        };
    }, [installmentApproved, installmentSession?.statusUrl]);

    // Render the pay-full PayPal buttons.
    useEffect(() => {
        if (paymentMode !== "full" || !payFullSdkReady) {
            return undefined;
        }

        const paypalNamespace = getPayPalNamespace(PAYPAL_FULL_NAMESPACE);

        if (!paypalNamespace || typeof paypalNamespace.Buttons !== "function") {
            setPayFullSdkError("The PayPal button API is not available right now.");
            return undefined;
        }

        clearContainer(buttonsContainerRef);

        const buttons = paypalNamespace.Buttons({
            style: {
                layout: "vertical",
                shape: "rect",
                color: "gold",
                label: "buynow",
                height: 48,
            },
            onClick: (_data, actions) => {
                if (!validateBookingFields()) {
                    return actions.reject();
                }

                return actions.resolve();
            },
            createOrder: () => createBookingOrder(),
            onApprove: async (data) => {
                await captureBookingOrder(data.orderID);
            },
            onCancel: async () => {
                setBookingCancelled(true);
                await releaseBookingHold();
            },
            onError: () => {
                setGeneralError(
                    "PayPal could not complete this booking. Please try again.",
                );
            },
        });

        if (buttons.isEligible() && buttonsContainerRef.current) {
            buttons.render(buttonsContainerRef.current).catch(() => {
                setPayFullSdkError(
                    "The PayPal button could not be rendered. Please refresh and try again.",
                );
            });
        }

        return () => {
            clearContainer(buttonsContainerRef);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [paymentMode, payFullSdkReady]);

    // Render the installment (subscription) PayPal buttons.
    useEffect(() => {
        if (
            paymentMode !== "installment" ||
            !installmentSdkReady ||
            installmentApproved
        ) {
            return undefined;
        }

        const paypalNamespace = getPayPalNamespace(PAYPAL_INSTALLMENT_NAMESPACE);

        if (!paypalNamespace || typeof paypalNamespace.Buttons !== "function") {
            setInstallmentSdkError(
                "The PayPal button API is not available right now.",
            );
            return undefined;
        }

        clearContainer(installmentButtonsContainerRef);

        const buttons = paypalNamespace.Buttons({
            style: {
                layout: "vertical",
                shape: "rect",
                color: "gold",
                label: "subscribe",
                height: 48,
            },
            onClick: (_data, actions) => {
                if (!validateBookingFields()) {
                    return actions.reject();
                }

                return actions.resolve();
            },
            createSubscription: async (_data, actions) => {
                const session = await createInstallmentCheckout();

                const subscriptionPayload = {
                    plan_id: session.providerPlanId,
                };

                if (session.paypalSubscriptionStartTime) {
                    subscriptionPayload.start_time =
                        session.paypalSubscriptionStartTime;
                }

                return actions.subscription.create(subscriptionPayload);
            },
            onApprove: async (data) => {
                await attachApprovedInstallmentSubscription(data.subscriptionID);
            },
            onCancel: async () => {
                setBookingCancelled(true);
                await releaseInstallmentHold();
            },
            onError: () => {
                setGeneralError(
                    "PayPal could not start the installment approval. Please try again.",
                );
            },
        });

        if (buttons.isEligible() && installmentButtonsContainerRef.current) {
            buttons.render(installmentButtonsContainerRef.current).catch(() => {
                setInstallmentSdkError(
                    "The PayPal button could not be rendered. Please refresh and try again.",
                );
            });
        }

        return () => {
            clearContainer(installmentButtonsContainerRef);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [paymentMode, installmentSdkReady, installmentApproved]);

    return (
        <div className="space-y-6">
            {accommodation.image_url && (
                <img
                    src={accommodation.image_url}
                    alt={accommodation.title}
                    className="h-48 w-full rounded-xl object-cover"
                />
            )}

            <div>
                <span className={LABEL_CLASS}>Room Type</span>
                <div className="mt-2 space-y-2">
                    {roomTypes.map((roomType) => (
                        <label
                            key={roomType.id}
                            className={[
                                "flex cursor-pointer items-center justify-between rounded-[5px] border-2 px-4 py-3 text-sm transition-colors",
                                selectedRoomTypeId === roomType.id
                                    ? "border-white bg-white/10"
                                    : "border-white/25 bg-black/25",
                            ].join(" ")}
                        >
                            <span className="flex items-center gap-3 text-white">
                                <input
                                    type="radio"
                                    name="room_type_id"
                                    checked={selectedRoomTypeId === roomType.id}
                                    onChange={() =>
                                        setSelectedRoomTypeId(roomType.id)
                                    }
                                    className="accent-white"
                                />
                                {roomType.title}
                            </span>
                            <span className="text-white/70">
                                {formatCurrency(
                                    roomType.price,
                                    accommodation.currency_code,
                                )}{" "}
                                / night
                            </span>
                        </label>
                    ))}

                    {roomTypes.length === 0 && (
                        <p className="text-sm text-white/60">
                            No room types are available for booking right now.
                        </p>
                    )}
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div>
                    <label className={LABEL_CLASS} htmlFor="check_in_date">
                        Check-in
                    </label>
                    <input
                        id="check_in_date"
                        type="date"
                        min={todayDateString()}
                        className={`mt-1 ${FIELD_CLASS}`}
                        value={checkInDate}
                        onChange={(event) => {
                            const nextCheckIn = event.target.value;
                            setCheckInDate(nextCheckIn);

                            if (checkOutDate && checkOutDate <= nextCheckIn) {
                                setCheckOutDate("");
                            }
                        }}
                    />
                </div>

                <div>
                    <label className={LABEL_CLASS} htmlFor="check_out_date">
                        Check-out
                    </label>
                    <input
                        id="check_out_date"
                        type="date"
                        min={minCheckOutDate || undefined}
                        disabled={!checkInDate}
                        className={`mt-1 ${FIELD_CLASS} disabled:cursor-not-allowed disabled:opacity-50`}
                        value={checkOutDate}
                        onChange={(event) =>
                            setCheckOutDate(event.target.value)
                        }
                    />
                </div>
            </div>

            <div className="rounded-[5px] border-2 border-white/25 bg-black/25 p-4 text-sm">
                {checkingAvailability && (
                    <p className="text-white/70">Checking availability…</p>
                )}

                {!checkingAvailability && availabilityError && (
                    <p className="text-rose-300">{availabilityError}</p>
                )}

                {!checkingAvailability &&
                    !availabilityError &&
                    availability && (
                        <div className="space-y-2">
                            <p
                                className={
                                    availability.available
                                        ? "text-emerald-300"
                                        : "text-rose-300"
                                }
                            >
                                {availability.available
                                    ? `${availability.available_rooms} room(s) available for the selected dates.`
                                    : "No rooms are available for the selected dates."}
                            </p>

                            {availability.available && (
                                <div className="flex flex-wrap items-center justify-between gap-2 text-white/80">
                                    <span>
                                        {formatCurrency(
                                            availability.price_per_night,
                                            availability.currency_code,
                                        )}{" "}
                                        × {availability.nights} night
                                        {availability.nights === 1 ? "" : "s"}
                                    </span>
                                    <span className="text-base font-semibold text-white">
                                        {formatCurrency(
                                            availability.total_amount,
                                            availability.currency_code,
                                        )}
                                    </span>
                                </div>
                            )}
                        </div>
                    )}

                {!checkingAvailability &&
                    !availabilityError &&
                    !availability && (
                        <p className="text-white/60">
                            Select a room type and both dates to see
                            availability and price.
                        </p>
                    )}
            </div>

            {canOfferInstallment && (
                <div>
                    <span className={LABEL_CLASS}>How would you like to pay?</span>
                    <div className="mt-2 grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            onClick={() => setPaymentMode("full")}
                            className={`w-full rounded-[5px] border-2 px-5 py-4 text-center text-sm font-semibold transition-all duration-200 ${
                                paymentMode === "full"
                                    ? "border-[#DB202C] bg-[#DB202C] text-white shadow-[0_0_0_2px_rgba(219,32,44,0.28)]"
                                    : "border-white/35 bg-black/25 text-white/85 hover:border-white/55 hover:bg-white/10"
                            }`}
                            style={{
                                fontFamily: FONT_FAMILY,
                                fontSize: "14px",
                                fontWeight: 600,
                            }}
                        >
                            Pay in full
                        </button>
                        <button
                            type="button"
                            onClick={() => setPaymentMode("installment")}
                            className={`w-full rounded-[5px] border-2 px-5 py-4 text-center text-sm font-semibold transition-all duration-200 ${
                                paymentMode === "installment"
                                    ? "border-[#DB202C] bg-[#DB202C] text-white shadow-[0_0_0_2px_rgba(219,32,44,0.28)]"
                                    : "border-white/35 bg-black/25 text-white/85 hover:border-white/55 hover:bg-white/10"
                            }`}
                            style={{
                                fontFamily: FONT_FAMILY,
                                fontSize: "14px",
                                fontWeight: 600,
                            }}
                        >
                            Pay in installment
                        </button>
                    </div>
                </div>
            )}

            {paymentMode === "installment" && installmentOffer && (
                <div className="space-y-4 rounded-[5px] border-2 border-white/25 bg-black/25 p-4 text-sm">
                    {installmentOffer.count_mode === "flex" && (
                        <div>
                            <label
                                className={LABEL_CLASS}
                                htmlFor="installment_count"
                            >
                                Number of installments
                            </label>
                            <select
                                id="installment_count"
                                value={installmentCount || ""}
                                onChange={(event) =>
                                    setInstallmentCount(
                                        Number(event.target.value),
                                    )
                                }
                                className={`mt-1 ${FIELD_CLASS}`}
                            >
                                {Array.from(
                                    {
                                        length:
                                            installmentOffer.maximum_installment_count -
                                            installmentOffer.minimum_installment_count +
                                            1,
                                    },
                                    (_, index) =>
                                        installmentOffer.minimum_installment_count +
                                        index,
                                ).map((count) => (
                                    <option key={count} value={count}>
                                        {count}x installments
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}

                    <div className="space-y-1 text-white/80">
                        <div className="flex items-center justify-between">
                            <span>Due today (first installment)</span>
                            <span className="font-semibold text-white">
                                {formatCurrency(
                                    installmentOffer.first_payment_amount,
                                    installmentOffer.currency_code,
                                )}
                            </span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span>
                                Then {installmentOffer.installment_count - 1}{" "}
                                monthly payment
                                {installmentOffer.installment_count - 1 === 1
                                    ? ""
                                    : "s"}{" "}
                                of
                            </span>
                            <span className="font-semibold text-white">
                                {formatCurrency(
                                    installmentOffer.monthly_base_amount,
                                    installmentOffer.currency_code,
                                )}
                            </span>
                        </div>
                        <p className="pt-1 text-xs text-white/50">
                            Final payment due {installmentOffer.final_due_at}.
                            PayPal will bill each installment automatically.
                        </p>
                    </div>

                    {installmentOffer.schedule_breakdown?.length > 0 && (
                        <details className="text-white/70">
                            <summary className="cursor-pointer text-xs text-white/60">
                                View full payment schedule
                            </summary>
                            <ul className="mt-2 space-y-1 text-xs">
                                {installmentOffer.schedule_breakdown.map(
                                    (cycle) => (
                                        <li
                                            key={cycle.cycle_number}
                                            className="flex items-center justify-between"
                                        >
                                            <span>
                                                #{cycle.cycle_number} —{" "}
                                                {cycle.due_at}
                                            </span>
                                            <span>
                                                {formatCurrency(
                                                    cycle.amount,
                                                    installmentOffer.currency_code,
                                                )}
                                            </span>
                                        </li>
                                    ),
                                )}
                            </ul>
                        </details>
                    )}
                </div>
            )}

            {/*
                Identity fields below — structure, order, className, and
                style are copied from Scoreboard.jsx's First Name/Last Name/
                Email/Mobile Phone/Country block, not reimplemented from
                scratch.
            */}
            <div className="grid gap-6 md:grid-cols-2">
                <div>
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
                        value={firstName}
                        className="mt-2 block w-full min-h-[52px] rounded-[5px] border border-white/20 bg-black/20 px-4 py-3.5 text-sm font-normal text-white placeholder:text-white/30 shadow-sm transition-all duration-200 focus:border-white/40 focus:ring-2 focus:ring-white/20 disabled:opacity-60"
                        style={{
                            fontFamily: FONT_FAMILY,
                            fontSize: "14px",
                            fontWeight: 400,
                        }}
                        onChange={(event) => setFirstName(event.target.value)}
                        required
                    />
                    <InputError
                        className="mt-2 text-sm font-medium text-rose-400"
                        style={{ fontFamily: FONT_FAMILY }}
                        message={fieldErrors.first_name}
                    />
                </div>

                <div>
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
                        value={lastName}
                        className="mt-2 block w-full min-h-[52px] rounded-[5px] border border-white/20 bg-black/20 px-4 py-3.5 text-sm font-normal text-white placeholder:text-white/30 shadow-sm transition-all duration-200 focus:border-white/40 focus:ring-2 focus:ring-white/20 disabled:opacity-60"
                        style={{
                            fontFamily: FONT_FAMILY,
                            fontSize: "14px",
                            fontWeight: 400,
                        }}
                        onChange={(event) => setLastName(event.target.value)}
                        required
                    />
                    <InputError
                        className="mt-2 text-sm font-medium text-rose-400"
                        style={{ fontFamily: FONT_FAMILY }}
                        message={fieldErrors.last_name}
                    />
                </div>

                <div className="md:col-span-2">
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
                        value={email}
                        className="mt-2 block w-full min-h-[52px] rounded-[5px] border border-white/20 bg-black/20 px-4 py-3.5 text-sm font-normal text-white placeholder:text-white/30 shadow-sm transition-all duration-200 focus:border-white/40 focus:ring-2 focus:ring-white/20 disabled:opacity-60"
                        style={{
                            fontFamily: FONT_FAMILY,
                            fontSize: "14px",
                            fontWeight: 400,
                        }}
                        onChange={(event) => setEmail(event.target.value)}
                        required
                    />
                    <InputError
                        className="mt-2 text-sm font-medium text-rose-400"
                        style={{ fontFamily: FONT_FAMILY }}
                        message={fieldErrors.email}
                    />
                </div>

                <div className="md:col-span-2">
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
                            value={phoneCountryCode}
                            selectedOption={selectedPhoneCountryOption}
                            options={phoneCountryCodeOptions}
                            onChange={(option) =>
                                setPhoneCountryCode(option.value)
                            }
                            displayMode="phone-code"
                            searchPlaceholder="Search phone code or country"
                            buttonClassName="block w-full min-h-[52px] rounded-[5px] border border-white/20 bg-black/20 px-4 py-3.5 text-sm font-normal text-white shadow-sm transition-all duration-200 focus:border-white/40 focus:ring-2 focus:ring-white/20 disabled:opacity-60"
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
                            value={phoneNumber}
                            className="block w-full min-h-[52px] rounded-[5px] border border-white/20 bg-black/20 px-4 py-3.5 text-sm font-normal text-white placeholder:text-white/30 shadow-sm transition-all duration-200 focus:border-white/40 focus:ring-2 focus:ring-white/20 disabled:opacity-60"
                            style={{
                                fontFamily: FONT_FAMILY,
                                fontSize: "14px",
                                fontWeight: 400,
                            }}
                            onChange={(event) =>
                                setPhoneNumber(
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
                            fieldErrors.phone_number ??
                            fieldErrors.phone_country_code
                        }
                    />
                </div>

                <div className="md:col-span-2">
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
                            value={country}
                            selectedOption={selectedCountryOption}
                            options={countryOptions}
                            onChange={(option) => setCountry(option.value)}
                            placeholder="Select a country"
                            buttonClassName="block w-full min-h-[52px] rounded-[5px] border border-white/20 bg-black/20 px-4 py-3.5 text-sm font-normal text-white shadow-sm transition-all duration-200 focus:border-white/40 focus:ring-2 focus:ring-white/20 disabled:opacity-60"
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
                        message={fieldErrors.country}
                    />
                </div>
            </div>

            {/*
                Consent checkbox — className/style/text copied from
                PublicCheckoutPanel.jsx. Client-side validation only, no
                server-side terms_accepted rule (same as Package).
            */}
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
                        checked={termsAccepted}
                        onChange={(event) =>
                            setTermsAccepted(event.target.checked)
                        }
                        className="mt-1 h-5 w-5 flex-shrink-0 rounded border-white/20 bg-black/20 text-[#DB202C] transition-colors focus:ring-[#DB202C] focus:ring-offset-gray-900"
                    />
                    <span className="flex-1 leading-relaxed">
                        I agree to continue with YogaFX payment processing and
                        understand that sensitive card data is handled
                        directly by PayPal-hosted secure components.
                    </span>
                </label>
            </div>

            <div className="space-y-3 border-t border-white/15 pt-6">
                {generalError && (
                    <p className="text-sm text-rose-300">{generalError}</p>
                )}

                {bookingCancelled && !generalError && (
                    <p className="text-sm text-white/60">
                        The PayPal payment was cancelled. Your room hold has
                        been released.
                    </p>
                )}

                {paymentMode === "full" && (
                    <>
                        {payFullSdkError && (
                            <p className="text-sm text-rose-300">
                                {payFullSdkError}
                            </p>
                        )}

                        <div
                            className={
                                canPay
                                    ? undefined
                                    : "pointer-events-none opacity-40 grayscale"
                            }
                        >
                            <div ref={buttonsContainerRef} />
                        </div>

                        {!canPay && !payFullSdkError && (
                            <p className="text-xs text-white/50">
                                Complete your details above and accept the
                                terms to enable payment.
                            </p>
                        )}
                    </>
                )}

                {paymentMode === "installment" && (
                    <>
                        {installmentSdkError && (
                            <p className="text-sm text-rose-300">
                                {installmentSdkError}
                            </p>
                        )}

                        {installmentApproved ? (
                            <p className="text-sm text-white/70">
                                {installmentStatusMessage}
                            </p>
                        ) : (
                            <>
                                <div
                                    className={
                                        canPayInstallment
                                            ? undefined
                                            : "pointer-events-none opacity-40 grayscale"
                                    }
                                >
                                    <div ref={installmentButtonsContainerRef} />
                                </div>

                                {!canPayInstallment && !installmentSdkError && (
                                    <p className="text-xs text-white/50">
                                        Complete your details above and accept
                                        the terms to enable payment.
                                    </p>
                                )}
                            </>
                        )}
                    </>
                )}
            </div>
        </div>
    );
}
