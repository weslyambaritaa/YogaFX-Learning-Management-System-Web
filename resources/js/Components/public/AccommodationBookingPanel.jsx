import { formatCurrency } from "@/lib/currency";
import { useEffect, useRef, useState } from "react";

const FIELD_CLASS =
    "min-h-[52px] w-full rounded-[5px] border-2 border-white/50 bg-black/45 px-4 py-3.5 text-sm font-normal text-white placeholder:text-white/55 shadow-[0_0_0_1px_rgba(255,255,255,0.14)] transition-all duration-200 focus:border-white focus:bg-black/60 focus:ring-2 focus:ring-white/35 [&::-webkit-calendar-picker-indicator]:invert";
const LABEL_CLASS = "block text-sm font-medium text-white/80";
const PAYPAL_NAMESPACE = "paypalAccommodationCheckout";

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

function getPayPalNamespace() {
    if (typeof window === "undefined") {
        return null;
    }

    return window[PAYPAL_NAMESPACE] ?? null;
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
    paypal,
}) {
    const [selectedRoomTypeId, setSelectedRoomTypeId] = useState(
        roomTypes[0]?.id ?? null,
    );
    const [checkInDate, setCheckInDate] = useState("");
    const [checkOutDate, setCheckOutDate] = useState("");
    const [guestName, setGuestName] = useState(prefill?.name ?? "");
    const [guestEmail, setGuestEmail] = useState(prefill?.email ?? "");
    const [guestPhone, setGuestPhone] = useState(prefill?.phone ?? "");
    const [availability, setAvailability] = useState(null);
    const [checkingAvailability, setCheckingAvailability] = useState(false);
    const [availabilityError, setAvailabilityError] = useState("");

    const [generalError, setGeneralError] = useState("");
    const [bookingCancelled, setBookingCancelled] = useState(false);
    const [sdkReady, setSdkReady] = useState(false);
    const [sdkError, setSdkError] = useState("");

    const buttonsContainerRef = useRef(null);
    const orderSessionRef = useRef(null);
    const selectedRoomTypeIdRef = useRef(selectedRoomTypeId);
    const checkInDateRef = useRef(checkInDate);
    const checkOutDateRef = useRef(checkOutDate);
    const guestNameRef = useRef(guestName);
    const guestEmailRef = useRef(guestEmail);
    const guestPhoneRef = useRef(guestPhone);
    const availabilityRef = useRef(availability);

    const minCheckOutDate = checkInDate
        ? addDaysToDateString(checkInDate, 1)
        : "";
    const canPay =
        Boolean(availability?.available) &&
        guestName.trim() !== "" &&
        guestEmail.trim() !== "" &&
        guestPhone.trim() !== "";

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
        guestNameRef.current = guestName;
    }, [guestName]);

    useEffect(() => {
        guestEmailRef.current = guestEmail;
    }, [guestEmail]);

    useEffect(() => {
        guestPhoneRef.current = guestPhone;
    }, [guestPhone]);

    useEffect(() => {
        availabilityRef.current = availability;
    }, [availability]);

    useEffect(() => {
        // A stale order session must never be reused once the selection changes.
        orderSessionRef.current = null;
        setBookingCancelled(false);
        setGeneralError("");
    }, [selectedRoomTypeId, checkInDate, checkOutDate]);

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
            }),
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
    }, [selectedRoomTypeId, checkInDate, checkOutDate, availabilityUrl]);

    // Load the PayPal JS SDK once client_id/currency are known.
    useEffect(() => {
        if (!paypal?.client_id) {
            setSdkError("PayPal is not configured for this hotel yet.");
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
            getPayPalNamespace()
        ) {
            setSdkReady(true);
            return undefined;
        }

        if (script && script.getAttribute("src") !== scriptUrl) {
            script.remove();
            script = null;
            delete window[PAYPAL_NAMESPACE];
        }

        const handleLoad = () => {
            if (!cancelled) {
                setSdkReady(true);
            }
        };
        const handleError = () => {
            if (!cancelled) {
                setSdkError(
                    "PayPal could not be loaded right now. Please refresh and try again.",
                );
            }
        };

        if (!script) {
            script = document.createElement("script");
            script.src = scriptUrl;
            script.async = true;
            script.dataset.paypalAccommodationSdk = "true";
            script.dataset.namespace = PAYPAL_NAMESPACE;
            script.setAttribute("data-namespace", PAYPAL_NAMESPACE);
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

        if (
            !guestNameRef.current.trim() ||
            !guestEmailRef.current.trim() ||
            !guestPhoneRef.current.trim()
        ) {
            setGeneralError("Please fill in your name, email, and phone.");
            return false;
        }

        setGeneralError("");
        return true;
    };

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
            body: JSON.stringify({
                room_type_id: selectedRoomTypeIdRef.current,
                check_in_date: checkInDateRef.current,
                check_out_date: checkOutDateRef.current,
                guest_name: guestNameRef.current,
                guest_email: guestEmailRef.current,
                guest_phone: guestPhoneRef.current,
            }),
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
            // Best effort — the 30-minute hold expires on its own either way.
        }
    };

    // Render the official PayPal buttons once the SDK script has loaded.
    useEffect(() => {
        if (!sdkReady) {
            return undefined;
        }

        const paypalNamespace = getPayPalNamespace();

        if (!paypalNamespace || typeof paypalNamespace.Buttons !== "function") {
            setSdkError("The PayPal button API is not available right now.");
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
                setSdkError(
                    "The PayPal button could not be rendered. Please refresh and try again.",
                );
            });
        }

        return () => {
            clearContainer(buttonsContainerRef);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [sdkReady]);

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

            <div className="grid gap-4 sm:grid-cols-3">
                <div>
                    <label className={LABEL_CLASS} htmlFor="guest_name">
                        Full Name
                    </label>
                    <input
                        id="guest_name"
                        type="text"
                        className={`mt-1 ${FIELD_CLASS}`}
                        value={guestName}
                        onChange={(event) => setGuestName(event.target.value)}
                    />
                </div>

                <div>
                    <label className={LABEL_CLASS} htmlFor="guest_email">
                        Email
                    </label>
                    <input
                        id="guest_email"
                        type="email"
                        className={`mt-1 ${FIELD_CLASS}`}
                        value={guestEmail}
                        onChange={(event) =>
                            setGuestEmail(event.target.value)
                        }
                    />
                </div>

                <div>
                    <label className={LABEL_CLASS} htmlFor="guest_phone">
                        Phone
                    </label>
                    <input
                        id="guest_phone"
                        type="text"
                        className={`mt-1 ${FIELD_CLASS}`}
                        value={guestPhone}
                        onChange={(event) =>
                            setGuestPhone(event.target.value)
                        }
                    />
                </div>
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

                {sdkError && <p className="text-sm text-rose-300">{sdkError}</p>}

                <div
                    className={
                        canPay
                            ? undefined
                            : "pointer-events-none opacity-40 grayscale"
                    }
                >
                    <div ref={buttonsContainerRef} />
                </div>

                {!canPay && !sdkError && (
                    <p className="text-xs text-white/50">
                        Complete the room, dates, and your details above to
                        enable payment.
                    </p>
                )}
            </div>
        </div>
    );
}
