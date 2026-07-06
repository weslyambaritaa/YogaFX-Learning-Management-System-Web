import InputError from "@/Components/InputError";
import { Button } from "@/Components/ui/button";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { formatCurrency } from "@/lib/currency";
import { Head, router, useForm } from "@inertiajs/react";
import { LoaderCircle } from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";

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

function toCents(value) {
    return Math.round(Number(value ?? 0) * 100);
}

function centsToAmount(value) {
    return Number((Number(value ?? 0) / 100).toFixed(2));
}

function formatAmount(value) {
    return Number(value ?? 0).toFixed(2);
}

function buildInstallmentCountOptions(summary) {
    const maximumInstallmentCount = Number(
        summary?.maximum_installment_count ??
            summary?.installment_count ??
            0,
    );

    if (!Number.isFinite(maximumInstallmentCount) || maximumInstallmentCount < 2) {
        return [];
    }

    return Array.from(
        { length: maximumInstallmentCount - 1 },
        (_, index) => index + 2,
    );
}

function buildSelectedInstallmentSummary(summary, selectedInstallmentCount, totalAmount) {
    if (!summary || !selectedInstallmentCount) {
        return summary ?? null;
    }

    const installmentCount = Number(selectedInstallmentCount);
    const maximumInstallmentCount = Number(
        summary.maximum_installment_count ??
            summary.installment_count ??
            installmentCount,
    );

    if (
        !Number.isFinite(installmentCount) ||
        installmentCount < 2 ||
        installmentCount > maximumInstallmentCount
    ) {
        return summary;
    }

    const totalAmountCents = toCents(summary.total_amount ?? totalAmount ?? 0);
    const recurringAmountCents = Math.floor(totalAmountCents / installmentCount);
    const firstPaymentAmountCents =
        totalAmountCents - recurringAmountCents * (installmentCount - 1);

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
        installmentCount - 1,
    );

    const finalDueAt =
        selectedRecurringDueDates[selectedRecurringDueDates.length - 1] ??
        summary.final_due_at ??
        null;

    const scheduleBreakdown = [
        {
            cycle_number: 1,
            type: "first_payment",
            amount: formatAmount(firstPaymentAmount),
            due_at: summary.first_payment_date ?? null,
            grace_deadline: null,
        },
        ...selectedRecurringDueDates.map((dueDate, index) => ({
            cycle_number: index + 2,
            type: "recurring",
            amount: formatAmount(recurringAmount),
            due_at: dueDate,
            grace_deadline: null,
        })),
    ];

    return {
        ...summary,
        installment_count: installmentCount,
        maximum_installment_count: maximumInstallmentCount,
        first_payment_amount: formatAmount(firstPaymentAmount),
        monthly_base_amount: formatAmount(recurringAmount),
        recurring_payment_amount: formatAmount(recurringAmount),
        recurring_due_dates: selectedRecurringDueDates,
        final_due_at: finalDueAt,
        schedule_breakdown: scheduleBreakdown,
    };
}

export default function UpgradeCheckout({ upgrade }) {
    const paymentOptions = Array.isArray(upgrade.payment_options)
        ? upgrade.payment_options
        : [];

    const installmentSummaries = upgrade.installment_summaries ?? {};

    const installmentBillingDayOptions = Array.isArray(
        upgrade.installment_billing_day_options,
    )
        ? upgrade.installment_billing_day_options
        : [];

    const paymentMethodOptions = Array.isArray(upgrade.payment_method_options)
        ? upgrade.payment_method_options
        : [];

    const defaultPaymentType = paymentOptions[0]?.type ?? "pay_full";
    const defaultPaymentMethod = paymentMethodOptions[0]?.value ?? "paypal";

    const defaultBillingDay =
        upgrade.installment_selected_billing_day ??
        installmentBillingDayOptions[0] ??
        15;

    const defaultInstallmentSummary =
        installmentSummaries[String(defaultBillingDay)] ??
        upgrade.installment_summary ??
        null;

    const defaultInstallmentCount =
        Number(
            defaultInstallmentSummary?.installment_count ??
                defaultInstallmentSummary?.maximum_installment_count ??
                upgrade.installment_maximum_count ??
                2,
        ) || 2;

    const { data, setData, processing, errors, clearErrors, setError } =
        useForm({
            payment_type: defaultPaymentType,
            payment_method: defaultPaymentMethod,
            billing_day: defaultBillingDay,
            installment_count: defaultInstallmentCount,
            terms_accepted: false,
        });

    const [generalError, setGeneralError] = useState("");
    const [sdkReady, setSdkReady] = useState(false);
    const [sdkError, setSdkError] = useState("");
    const [isPreparingInstallment, setIsPreparingInstallment] = useState(false);
    const [installmentSession, setInstallmentSession] = useState(null);
    const [installmentMessage, setInstallmentMessage] = useState("");
    const [installmentStatus, setInstallmentStatus] = useState(null);

    const paypalButtonsRef = useRef(null);
    const installmentSessionRef = useRef(null);

    const selectedPaymentOption =
        paymentOptions.find((option) => option.type === data.payment_type) ??
        paymentOptions[0] ??
        null;

    const isInstallmentSelected = data.payment_type === "installment";

    const baseInstallmentSummary =
        installmentSummaries[String(data.billing_day)] ??
        selectedPaymentOption?.summary ??
        upgrade.installment_summary ??
        null;

    const installmentCountOptions = buildInstallmentCountOptions(
        baseInstallmentSummary,
    );

    const activeInstallmentSummary = buildSelectedInstallmentSummary(
        baseInstallmentSummary,
        data.installment_count,
        upgrade.amount_due,
    );

    const showBillingDaySelector =
        isInstallmentSelected &&
        upgrade.installment_accepts_billing_day === true &&
        upgrade.installment_requires_billing_day_choice === true &&
        installmentBillingDayOptions.length > 1;

    const showInstallmentCountSelector =
        isInstallmentSelected && installmentCountOptions.length > 0;

    const amountDueToday = Number(
        isInstallmentSelected
            ? activeInstallmentSummary?.first_payment_amount
            : selectedPaymentOption?.amount_due_today ?? upgrade.amount_due ?? 0,
    );

    const recurringAmount = Number(
        activeInstallmentSummary?.recurring_payment_amount ?? 0,
    );

    const finalDueAt = activeInstallmentSummary?.final_due_at ?? null;

    const nextInstallment = Array.isArray(
        activeInstallmentSummary?.schedule_breakdown,
    )
        ? activeInstallmentSummary.schedule_breakdown.find(
              (item) => item.type === "recurring",
          ) ?? null
        : null;

    const paypalScriptUrl = useMemo(() => {
        const clientId = upgrade.paypal?.client_id ?? "";

        if (!clientId || !isInstallmentSelected) {
            return null;
        }

        const params = new URLSearchParams({
            "client-id": clientId,
            components: "buttons",
            vault: "true",
            intent: "subscription",
        });

        return `https://www.paypal.com/sdk/js?${params.toString()}`;
    }, [isInstallmentSelected, upgrade.paypal?.client_id]);

    useEffect(() => {
        installmentSessionRef.current = installmentSession;
    }, [installmentSession]);

    useEffect(() => {
        if (!isInstallmentSelected || !baseInstallmentSummary) {
            return;
        }

        const options = buildInstallmentCountOptions(baseInstallmentSummary);

        if (options.length === 0) {
            return;
        }

        const currentInstallmentCount = Number(data.installment_count);

        if (!options.includes(currentInstallmentCount)) {
            setData("installment_count", options[options.length - 1]);
        }
    }, [
        baseInstallmentSummary,
        data.installment_count,
        isInstallmentSelected,
        setData,
    ]);

    useEffect(() => {
        if (!isInstallmentSelected) {
            return;
        }

        setInstallmentSession(null);
        installmentSessionRef.current = null;
        setInstallmentStatus(null);
        setInstallmentMessage("");
    }, [data.billing_day, data.installment_count, isInstallmentSelected]);

    useEffect(() => {
        if (!isInstallmentSelected) {
            setInstallmentSession(null);
            installmentSessionRef.current = null;
            setInstallmentStatus(null);
            setInstallmentMessage("");
            setSdkError("");
            setGeneralError("");
        }
    }, [isInstallmentSelected]);

    useEffect(() => {
        if (!isInstallmentSelected) {
            return;
        }

        setData("payment_method", "paypal");
    }, [isInstallmentSelected, setData]);

    useEffect(() => {
        if (!paypalScriptUrl) {
            return undefined;
        }

        let cancelled = false;
        let existingScript = document.querySelector(
            'script[data-paypal-upgrade-sdk="true"]',
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
                    "PayPal could not be loaded right now. Please refresh and try again.",
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
        script.dataset.paypalUpgradeSdk = "true";
        script.addEventListener("load", handleReady);
        script.addEventListener("error", handleError);
        document.body.appendChild(script);

        return () => {
            cancelled = true;
            script.removeEventListener("load", handleReady);
            script.removeEventListener("error", handleError);
        };
    }, [paypalScriptUrl]);

    useEffect(() => {
        if (
            !isInstallmentSelected ||
            !sdkReady ||
            !window.paypal ||
            !installmentSession ||
            installmentSession.provider_subscription_id
        ) {
            return undefined;
        }

        const { paypal } = window;

        if (paypalButtonsRef.current) {
            paypalButtonsRef.current.innerHTML = "";
        }

        if (typeof paypal.Buttons !== "function") {
            setGeneralError(
                "The PayPal subscription button is not available right now.",
            );

            return undefined;
        }

        const buttons = paypal.Buttons({
            style: {
                layout: "vertical",
                shape: "rect",
                color: "gold",
                label: "paypal",
                height: 46,
            },
            onClick: (_data, actions) => {
                setGeneralError("");
                setInstallmentMessage("");

                if (!data.terms_accepted) {
                    setError("terms_accepted", "Please confirm before continuing.");
                    return actions.reject();
                }

                return actions.resolve();
            },
            createSubscription: async (_data, actions) => {
                const session = installmentSessionRef.current;

                if (!session?.provider_plan_id) {
                    throw new Error("subscription-plan-missing");
                }

                return actions.subscription.create({
                    plan_id: session.provider_plan_id,
                });
            },
            onApprove: async (approvalData) => {
                await attachApprovedSubscription(approvalData.subscriptionID);
            },
            onCancel: () => {
                setGeneralError("The PayPal installment approval was cancelled.");
            },
            onError: () => {
                setGeneralError(
                    "PayPal could not start the installment approval.",
                );
            },
        });

        if (buttons.isEligible() && paypalButtonsRef.current) {
            buttons.render(paypalButtonsRef.current).catch(() => {
                setGeneralError(
                    "The PayPal installment button could not be rendered.",
                );
            });
        }

        return () => {
            if (paypalButtonsRef.current) {
                paypalButtonsRef.current.innerHTML = "";
            }
        };
    }, [
        data.terms_accepted,
        installmentSession,
        isInstallmentSelected,
        sdkReady,
        setError,
    ]);

    useEffect(() => {
        if (
            !isInstallmentSelected ||
            !installmentSession?.provider_subscription_id ||
            !upgrade.installment_status_url
        ) {
            return undefined;
        }

        let cancelled = false;

        const pollStatus = async () => {
            try {
                const response = await fetch(upgrade.installment_status_url, {
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

                if (payload.upgrade_ready && payload.redirect_url) {
                    window.location.assign(payload.redirect_url);
                    return;
                }

                if (payload.message) {
                    setInstallmentMessage(payload.message);
                }
            } catch {
                if (!cancelled) {
                    setInstallmentMessage(
                        "Waiting for the first payment confirmation.",
                    );
                }
            }
        };

        pollStatus();
        const intervalId = window.setInterval(pollStatus, 4000);

        return () => {
            cancelled = true;
            window.clearInterval(intervalId);
        };
    }, [
        installmentSession,
        isInstallmentSelected,
        upgrade.installment_status_url,
    ]);

    const submitFullPayment = (event) => {
        event.preventDefault();
        clearErrors();
        setGeneralError("");

        if (!data.terms_accepted) {
            setError("terms_accepted", "Please confirm before continuing.");
            return;
        }

        router.post(
            upgrade.submit_url,
            {
                payment_type: data.payment_type,
                payment_method: data.payment_method,
                billing_day: null,
                installment_count: null,
                terms_accepted: data.terms_accepted,
            },
            {
                preserveScroll: true,
            },
        );
    };

    const prepareInstallmentCheckout = async () => {
        clearErrors();
        setGeneralError("");

        if (!data.terms_accepted) {
            setError("terms_accepted", "Please confirm before continuing.");
            return;
        }

        if (!data.installment_count) {
            setError(
                "installment_count",
                "Please select the number of installments.",
            );
            return;
        }

        setIsPreparingInstallment(true);

        const response = await fetch(upgrade.submit_url, {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-CSRF-TOKEN": getCsrfToken() ?? "",
                "X-Requested-With": "XMLHttpRequest",
            },
            body: JSON.stringify({
                payment_type: "installment",
                payment_method: "paypal",
                checkout_mode: "paypal",
                billing_day:
                    upgrade.installment_accepts_billing_day === true
                        ? data.billing_day
                        : null,
                installment_count: Number(data.installment_count),
                terms_accepted: data.terms_accepted,
            }),
        });

        const payload = await parseJsonSafely(response);

        if (!response.ok) {
            if (payload.errors) {
                Object.entries(payload.errors).forEach(([field, messages]) => {
                    setError(
                        field,
                        Array.isArray(messages) ? messages[0] : messages,
                    );
                });
            }

            setGeneralError(
                payload.message ??
                    "The installment checkout could not be prepared.",
            );
            setIsPreparingInstallment(false);
            return;
        }

        setInstallmentSession(payload);
        installmentSessionRef.current = payload;
        setInstallmentMessage("");
        setInstallmentStatus(null);
        setIsPreparingInstallment(false);
    };

    const attachApprovedSubscription = async (providerSubscriptionId) => {
        const activeSession = installmentSessionRef.current;

        if (
            !activeSession?.payment_subscription_id ||
            !upgrade.installment_approve_url
        ) {
            setGeneralError("The installment approval route is not ready.");
            return;
        }

        const response = await fetch(upgrade.installment_approve_url, {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-CSRF-TOKEN": getCsrfToken() ?? "",
                "X-Requested-With": "XMLHttpRequest",
            },
            body: JSON.stringify({
                payment_subscription_id: activeSession.payment_subscription_id,
                provider_subscription_id: providerSubscriptionId,
            }),
        });

        const payload = await parseJsonSafely(response);

        if (!response.ok) {
            setGeneralError(
                payload.message ??
                    "The PayPal approval could not be attached to this upgrade.",
            );
            return;
        }

        const nextSession = {
            ...activeSession,
            provider_subscription_id:
                payload.provider_subscription_id ?? providerSubscriptionId,
        };

        installmentSessionRef.current = nextSession;
        setInstallmentSession(nextSession);
        setInstallmentStatus(payload);
        setInstallmentMessage(
            payload.message ?? "Waiting for the first payment confirmation.",
        );
    };

    return (
        <AuthenticatedLayout
            studentVariant="immersive"
            studentContentClassName="pb-16"
        >
            <Head title="Upgrade Program" />

            <div className="mx-auto flex max-w-[960px] flex-col gap-5 px-4 pt-6 sm:px-6 lg:px-8">
                <section className="rounded-[20px] border border-white/10 bg-[#15110f] p-5 shadow-[0_24px_80px_rgba(0,0,0,0.35)] sm:p-6">
                    <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_280px]">
                        <div className="space-y-5">
                            <div className="space-y-2">
                                <p className="text-xs uppercase tracking-[0.24em] text-[#f2d9c8]">
                                    Upgrade Class
                                </p>
                                <h1 className="text-3xl font-semibold tracking-[-0.03em] text-white">
                                    {upgrade.current_tier?.name ?? "Current Tier"} to{" "}
                                    {upgrade.target_tier?.name}
                                </h1>
                            </div>

                            <form
                                onSubmit={
                                    isInstallmentSelected
                                        ? (event) => {
                                              event.preventDefault();
                                          }
                                        : submitFullPayment
                                }
                                className="space-y-4 rounded-[16px] border border-white/10 bg-white/[0.04] p-5"
                            >
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label className="text-xs uppercase tracking-[0.16em] text-white/58">
                                            Payment Type
                                        </label>
                                        <select
                                            value={data.payment_type}
                                            onChange={(event) =>
                                                setData(
                                                    "payment_type",
                                                    event.target.value,
                                                )
                                            }
                                            className="mt-2 block w-full rounded-[5px] border border-white/12 bg-[#171311] px-4 py-3 text-sm text-white focus:border-[#d5462f] focus:ring-[#d5462f]"
                                        >
                                            {paymentOptions.map((option) => (
                                                <option
                                                    key={option.type}
                                                    value={option.type}
                                                >
                                                    {option.label} -{" "}
                                                    {formatCurrency(
                                                        Number(
                                                            option.amount_due_today ??
                                                                0,
                                                        ),
                                                        option.currency_code,
                                                    )}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={errors.payment_type}
                                            className="mt-2 text-sm text-[#ffb4a8]"
                                        />
                                    </div>

                                    {!isInstallmentSelected ? (
                                        <div>
                                            <label className="text-xs uppercase tracking-[0.16em] text-white/58">
                                                Payment Method
                                            </label>
                                            <select
                                                value={data.payment_method}
                                                onChange={(event) =>
                                                    setData(
                                                        "payment_method",
                                                        event.target.value,
                                                    )
                                                }
                                                className="mt-2 block w-full rounded-[5px] border border-white/12 bg-[#171311] px-4 py-3 text-sm text-white focus:border-[#d5462f] focus:ring-[#d5462f]"
                                            >
                                                {paymentMethodOptions.map(
                                                    (option) => (
                                                        <option
                                                            key={option.value}
                                                            value={option.value}
                                                        >
                                                            {option.label}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                            <InputError
                                                message={errors.payment_method}
                                                className="mt-2 text-sm text-[#ffb4a8]"
                                            />
                                        </div>
                                    ) : showBillingDaySelector ? (
                                        <div>
                                            <label className="text-xs uppercase tracking-[0.16em] text-white/58">
                                                Billing Day
                                            </label>
                                            <select
                                                value={data.billing_day}
                                                onChange={(event) =>
                                                    setData(
                                                        "billing_day",
                                                        Number(event.target.value),
                                                    )
                                                }
                                                className="mt-2 block w-full rounded-[5px] border border-white/12 bg-[#171311] px-4 py-3 text-sm text-white focus:border-[#d5462f] focus:ring-[#d5462f]"
                                            >
                                                {installmentBillingDayOptions.map(
                                                    (day) => (
                                                        <option
                                                            key={day}
                                                            value={day}
                                                        >
                                                            {formatBillingDayLabel(
                                                                day,
                                                            )}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                            <InputError
                                                message={errors.billing_day}
                                                className="mt-2 text-sm text-[#ffb4a8]"
                                            />
                                        </div>
                                    ) : isInstallmentSelected ? (
                                        <div>
                                            <label className="text-xs uppercase tracking-[0.16em] text-white/58">
                                                Billing Day
                                            </label>
                                            <div className="mt-2 rounded-[5px] border border-white/12 bg-[#171311] px-4 py-3 text-sm text-white/70">
                                                {formatBillingDayLabel(
                                                    data.billing_day,
                                                )}
                                            </div>
                                            <InputError
                                                message={errors.billing_day}
                                                className="mt-2 text-sm text-[#ffb4a8]"
                                            />
                                        </div>
                                    ) : null}

                                    {showInstallmentCountSelector ? (
                                        <div>
                                            <label className="text-xs uppercase tracking-[0.16em] text-white/58">
                                                Number of Installments
                                            </label>
                                            <select
                                                value={data.installment_count}
                                                onChange={(event) =>
                                                    setData(
                                                        "installment_count",
                                                        Number(event.target.value),
                                                    )
                                                }
                                                className="mt-2 block w-full rounded-[5px] border border-white/12 bg-[#171311] px-4 py-3 text-sm text-white focus:border-[#d5462f] focus:ring-[#d5462f]"
                                            >
                                                {installmentCountOptions.map(
                                                    (count) => (
                                                        <option
                                                            key={count}
                                                            value={count}
                                                        >
                                                            {count} installments
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                            <p className="mt-2 text-xs text-white/45">
                                                Maximum available:{" "}
                                                {
                                                    installmentCountOptions[
                                                        installmentCountOptions.length -
                                                            1
                                                    ]
                                                }{" "}
                                                installments.
                                            </p>
                                            <InputError
                                                message={errors.installment_count}
                                                className="mt-2 text-sm text-[#ffb4a8]"
                                            />
                                        </div>
                                    ) : null}
                                </div>

                                <label className="flex items-start gap-3 rounded-[5px] border border-white/10 bg-black/20 px-4 py-4 text-sm text-white/75">
                                    <input
                                        type="checkbox"
                                        checked={data.terms_accepted}
                                        onChange={(event) =>
                                            setData(
                                                "terms_accepted",
                                                event.target.checked,
                                            )
                                        }
                                        className="mt-1 size-4 rounded border-white/20 bg-transparent accent-[#DB202C]"
                                    />
                                    <span>I confirm this upgrade payment.</span>
                                </label>
                                <InputError
                                    message={errors.terms_accepted}
                                    className="text-sm text-[#ffb4a8]"
                                />

                                {generalError ? (
                                    <div className="rounded-[5px] border border-[#ffb4a8]/30 bg-[#ffb4a8]/10 px-4 py-3 text-sm text-[#ffcfbf]">
                                        {generalError}
                                    </div>
                                ) : null}

                                {isInstallmentSelected ? (
                                    <div className="space-y-4">
                                        {!installmentSession ? (
                                            <Button
                                                type="button"
                                                onClick={
                                                    prepareInstallmentCheckout
                                                }
                                                disabled={
                                                    isPreparingInstallment ||
                                                    !data.installment_count
                                                }
                                                className="rounded-[5px] bg-[#DB202C] px-6 text-white hover:bg-[#c31c28]"
                                            >
                                                {isPreparingInstallment
                                                    ? "Preparing..."
                                                    : `Continue with PayPal - ${formatCurrency(
                                                          amountDueToday,
                                                          upgrade.target_tier
                                                              .currency_code,
                                                      )}`}
                                            </Button>
                                        ) : installmentSession.provider_subscription_id ? (
                                            <div className="rounded-[5px] border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
                                                {installmentMessage ||
                                                    "Waiting for the first payment confirmation."}
                                            </div>
                                        ) : (
                                            <div className="space-y-3">
                                                <div className="rounded-[5px] border border-white/10 bg-white p-4">
                                                    <div
                                                        ref={paypalButtonsRef}
                                                        className="min-h-[46px]"
                                                    />
                                                </div>

                                                {!sdkReady && !sdkError ? (
                                                    <div className="flex items-center gap-2 text-sm text-white/60">
                                                        <LoaderCircle className="size-4 animate-spin text-[#DB202C]" />
                                                        <span>
                                                            Loading PayPal...
                                                        </span>
                                                    </div>
                                                ) : null}

                                                {sdkError ? (
                                                    <div className="rounded-[5px] border border-[#ffb4a8]/30 bg-[#ffb4a8]/10 px-4 py-3 text-sm text-[#ffcfbf]">
                                                        {sdkError}
                                                    </div>
                                                ) : null}
                                            </div>
                                        )}
                                    </div>
                                ) : (
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="rounded-[5px] bg-[#DB202C] px-6 text-white hover:bg-[#c31c28]"
                                    >
                                        {processing
                                            ? "Processing..."
                                            : "Pay Upgrade Now"}
                                    </Button>
                                )}
                            </form>
                        </div>

                        <aside className="space-y-3">
                            <div className="rounded-[16px] border border-white/10 bg-black/25 p-4">
                                <p className="text-xs uppercase tracking-[0.18em] text-white/45">
                                    Upgrade Path
                                </p>
                                <div className="mt-3 space-y-2 text-sm text-white/70">
                                    <p>
                                        Current:{" "}
                                        {upgrade.current_tier?.name ?? "-"}
                                    </p>
                                    <p>
                                        Target:{" "}
                                        {upgrade.target_tier?.name ?? "-"}
                                    </p>
                                    <p>
                                        Paid:{" "}
                                        {formatCurrency(
                                            Number(upgrade.total_paid ?? 0),
                                            upgrade.target_tier.currency_code,
                                        )}
                                    </p>
                                    <p>
                                        Due:{" "}
                                        {formatCurrency(
                                            Number(upgrade.amount_due ?? 0),
                                            upgrade.target_tier.currency_code,
                                        )}
                                    </p>
                                </div>
                            </div>

                            {isInstallmentSelected &&
                            activeInstallmentSummary ? (
                                <div className="rounded-[16px] border border-white/10 bg-black/25 p-4">
                                    <p className="text-xs uppercase tracking-[0.18em] text-white/45">
                                        Installment
                                    </p>
                                    <div className="mt-3 space-y-2 text-sm text-white/70">
                                        <p>
                                            Total Installments:{" "}
                                            {
                                                activeInstallmentSummary.installment_count
                                            }
                                        </p>
                                        <p>
                                            Today:{" "}
                                            {formatCurrency(
                                                amountDueToday,
                                                upgrade.target_tier
                                                    .currency_code,
                                            )}
                                        </p>
                                        <p>
                                            Next:{" "}
                                            {nextInstallment
                                                ? `${formatCurrency(
                                                      Number(
                                                          nextInstallment.amount ??
                                                              recurringAmount,
                                                      ),
                                                      upgrade.target_tier
                                                          .currency_code,
                                                  )} on ${formatScheduleDate(
                                                      nextInstallment.due_at,
                                                  )}`
                                                : "-"}
                                        </p>
                                        <p>
                                            Final Due:{" "}
                                            {formatScheduleDate(finalDueAt)}
                                        </p>
                                        {activeInstallmentSummary.billing_day ? (
                                            <p>
                                                Billing Day:{" "}
                                                {formatBillingDayLabel(
                                                    activeInstallmentSummary.billing_day,
                                                )}
                                            </p>
                                        ) : null}
                                    </div>
                                </div>
                            ) : null}

                            {installmentStatus?.message &&
                            isInstallmentSelected ? (
                                <div className="rounded-[16px] border border-white/10 bg-black/25 p-4 text-sm text-white/65">
                                    {installmentStatus.message}
                                </div>
                            ) : null}
                        </aside>
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}