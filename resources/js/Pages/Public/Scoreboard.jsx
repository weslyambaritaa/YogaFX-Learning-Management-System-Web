import PublicCheckoutPanel from "@/Components/public/PublicCheckoutPanel";
import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import TextInput from "@/Components/TextInput";
import { Button } from "@/Components/ui/button";
import PublicFlowLayout from "@/Layouts/PublicFlowLayout";
import { formatCurrency } from "@/lib/currency";
import { usePage } from "@inertiajs/react";
import { useEffect, useRef, useState } from "react";

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

export default function Scoreboard({
    accessTiers,
    submit_url,
    selected_access_tier_id,
    is_access_tier_locked = false,
}) {
    const { directory = {} } = usePage().props;
    const countryOptions = directory.countries ?? [];
    const phoneCountryCodeOptions = directory.phone_country_codes ?? [];
    const [data, setData] = useState({
        first_name: "",
        last_name: "",
        email: "",
        phone_country_code: "+62",
        phone_number: "",
        country: "",
        access_tier_id: selected_access_tier_id ?? accessTiers[0]?.id ?? "",
    });
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);
    const [checkout, setCheckout] = useState(null);
    const checkoutRef = useRef(null);

    const selectedTier =
        accessTiers.find(
            (tier) => String(tier.id) === String(data.access_tier_id),
        ) ?? null;
    const selectedTierHasPrice = Number(selectedTier?.price ?? 0) > 0;
    const isIdentityLocked = checkout !== null;

    useEffect(() => {
        if (!checkoutRef.current) {
            return;
        }

        checkoutRef.current.scrollIntoView({
            behavior: "smooth",
            block: "start",
        });
    }, [checkout]);

    const setFieldValue = (field, value) => {
        setData((current) => ({
            ...current,
            [field]: value,
        }));

        setErrors((current) => ({
            ...current,
            [field]: "",
        }));
    };

    const submit = async (event) => {
        event.preventDefault();
        setProcessing(true);
        setErrors({});

        const response = await fetch(submit_url, {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-CSRF-TOKEN": getCsrfToken() ?? "",
                "X-Requested-With": "XMLHttpRequest",
            },
            body: JSON.stringify(data),
        });

        const payload = await parseJsonSafely(response);

        if (!response.ok) {
            setProcessing(false);

            if (response.status === 422 && payload.errors) {
                setErrors(
                    Object.fromEntries(
                        Object.entries(payload.errors).map(([key, value]) => [
                            key,
                            Array.isArray(value) ? value[0] : value,
                        ]),
                    ),
                );
                return;
            }

            setErrors({
                general:
                    payload.message ??
                    "The checkout flow could not be prepared. Please try again.",
            });
            return;
        }

        setCheckout(payload.checkout ?? null);
        setProcessing(false);
    };

    return (
        <PublicFlowLayout
            title="Scoreboard"
            heading="Start your YogaFX Journey!"
            aside={
                <div className="space-y-6">
                </div>
            }
        >
            <div className="space-y-10">
                <form id="scoreboard-form" onSubmit={submit} className="space-y-8">
                    {errors.general && (
                        <div className="rounded-xl border border-rose-400/30 bg-rose-500/10 px-5 py-4 text-sm font-medium text-rose-100">
                            {errors.general}
                        </div>
                    )}

                    <div className="grid gap-6 md:grid-cols-2">
                        {/* First Name */}
                        <div>
                            <InputLabel
                                htmlFor="first_name"
                                value="First Name"
                                className="text-sm font-medium text-white/90"
                            />
                            <TextInput
                                id="first_name"
                                value={data.first_name}
                                disabled={isIdentityLocked}
                                className="mt-2 block w-full min-h-[52px] rounded-xl border border-[#DB202C] bg-black/20 px-4 py-3.5 text-base text-white placeholder:text-white/30 shadow-sm transition-all duration-200 focus:border-[#DB202C] focus:ring-2 focus:ring-[#DB202C]/30 disabled:opacity-60"
                                onChange={(event) =>
                                    setFieldValue("first_name", event.target.value)
                                }
                                required
                            />
                            <InputError
                                className="mt-2 text-sm font-medium text-rose-400"
                                message={errors.first_name}
                            />
                        </div>

                        {/* Last Name */}
                        <div>
                            <InputLabel
                                htmlFor="last_name"
                                value="Last Name"
                                className="text-sm font-medium text-white/90"
                            />
                            <TextInput
                                id="last_name"
                                value={data.last_name}
                                disabled={isIdentityLocked}
                                className="mt-2 block w-full min-h-[52px] rounded-xl border border-[#DB202C] bg-black/20 px-4 py-3.5 text-base text-white placeholder:text-white/30 shadow-sm transition-all duration-200 focus:border-[#DB202C] focus:ring-2 focus:ring-[#DB202C]/30 disabled:opacity-60"
                                onChange={(event) =>
                                    setFieldValue("last_name", event.target.value)
                                }
                                required
                            />
                            <InputError
                                className="mt-2 text-sm font-medium text-rose-400"
                                message={errors.last_name}
                            />
                        </div>

                        {/* Email */}
                        <div className="md:col-span-2">
                            <InputLabel
                                htmlFor="email"
                                value="Email"
                                className="text-sm font-medium text-white/90"
                            />
                            <TextInput
                                id="email"
                                type="email"
                                value={data.email}
                                disabled={isIdentityLocked}
                                className="mt-2 block w-full min-h-[52px] rounded-xl border border-[#DB202C] bg-black/20 px-4 py-3.5 text-base text-white placeholder:text-white/30 shadow-sm transition-all duration-200 focus:border-[#DB202C] focus:ring-2 focus:ring-[#DB202C]/30 disabled:opacity-60"
                                onChange={(event) =>
                                    setFieldValue("email", event.target.value)
                                }
                                required
                            />
                            <InputError
                                className="mt-2 text-sm font-medium text-rose-400"
                                message={errors.email}
                            />
                        </div>

                        {/* Mobile Phone */}
                        <div className="md:col-span-2">
                            <InputLabel
                                htmlFor="phone_number"
                                value="Mobile Phone"
                                className="text-sm font-medium text-white/90"
                            />
                            <div className="mt-2 grid gap-4 sm:grid-cols-[160px_minmax(0,1fr)]">
                                <select
                                    id="phone_country_code"
                                    value={data.phone_country_code}
                                    disabled={isIdentityLocked}
                                    onChange={(event) =>
                                        setFieldValue(
                                            "phone_country_code",
                                            event.target.value,
                                        )
                                    }
                                    className="block w-full min-h-[52px] rounded-xl border border-[#DB202C] bg-black/20 px-4 py-3.5 text-base text-white shadow-sm transition-all duration-200 focus:border-[#DB202C] focus:ring-2 focus:ring-[#DB202C]/30 disabled:opacity-60"
                                    required
                                >
                                    {phoneCountryCodeOptions.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                            className="bg-gray-900 text-white"
                                        >
                                            {option.flag ? `${option.flag} ` : ""}
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                                <TextInput
                                    id="phone_number"
                                    value={data.phone_number}
                                    disabled={isIdentityLocked}
                                    className="block w-full min-h-[52px] rounded-xl border border-[#DB202C] bg-black/20 px-4 py-3.5 text-base text-white placeholder:text-white/30 shadow-sm transition-all duration-200 focus:border-[#DB202C] focus:ring-2 focus:ring-[#DB202C]/30 disabled:opacity-60"
                                    onChange={(event) =>
                                        setFieldValue("phone_number", event.target.value)
                                    }
                                    placeholder="81234567890"
                                    required
                                />
                            </div>
                            <InputError
                                className="mt-2 text-sm font-medium text-rose-400"
                                message={
                                    errors.phone_number ??
                                    errors.phone_country_code ??
                                    errors.phone
                                }
                            />
                        </div>

                        {/* Country */}
                        <div className="md:col-span-2">
                            <InputLabel
                                htmlFor="country"
                                value="Country"
                                className="text-sm font-medium text-white/90"
                            />
                            <select
                                id="country"
                                value={data.country}
                                disabled={isIdentityLocked}
                                className="mt-2 block w-full min-h-[52px] rounded-xl border border-[#DB202C] bg-black/20 px-4 py-3.5 text-base text-white shadow-sm transition-all duration-200 focus:border-[#DB202C] focus:ring-2 focus:ring-[#DB202C]/30 disabled:opacity-60"
                                onChange={(event) => {
                                    const value = event.target.value;
                                    setFieldValue("country", value);
                                    const matchedDialCode =
                                        phoneCountryCodeOptions.find((option) =>
                                            option.label.startsWith(`${value} (`),
                                        );
                                    if (matchedDialCode && !data.phone_number) {
                                        setFieldValue(
                                            "phone_country_code",
                                            matchedDialCode.value,
                                        );
                                    }
                                }}
                                required
                            >
                                <option value="" className="bg-gray-900 text-white">
                                    Select a country
                                </option>
                                {countryOptions.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                        className="bg-gray-900 text-white"
                                    >
                                        {option.flag ? `${option.flag} ` : ""}
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                            <InputError
                                className="mt-2 text-sm font-medium text-rose-400"
                                message={errors.country}
                            />
                        </div>

                        {/* Program / Tier */}
                        <div className="md:col-span-2">
                            <InputLabel
                                htmlFor="access_tier_id"
                                value="Program / Tier"
                                className="text-sm font-medium text-white/90"
                            />
                            {is_access_tier_locked && selectedTier ? (
                                <div className="mt-2 min-h-[52px] rounded-xl border border-[#DB202C]/50 bg-black/20 px-5 py-4 text-white shadow-sm">
                                    <div className="text-base font-medium">
                                        {selectedTier.name}
                                    </div>
                                    <div className="mt-1 text-sm text-white/70">
                                        {Number(selectedTier.price) > 0
                                            ? formatCurrency(
                                                  selectedTier.price,
                                                  selectedTier.currency_code,
                                              )
                                            : "Price not set yet"}
                                    </div>
                                </div>
                            ) : (
                                <select
                                    id="access_tier_id"
                                    value={data.access_tier_id}
                                    disabled={isIdentityLocked}
                                    onChange={(event) =>
                                        setFieldValue(
                                            "access_tier_id",
                                            event.target.value,
                                        )
                                    }
                                    className="mt-2 block w-full min-h-[52px] rounded-xl border border-[#DB202C] bg-black/20 px-4 py-3.5 text-base text-white shadow-sm transition-all duration-200 focus:border-[#DB202C] focus:ring-2 focus:ring-[#DB202C]/30 disabled:opacity-60"
                                    required
                                >
                                    {accessTiers.map((tier) => (
                                        <option
                                            key={tier.id}
                                            value={tier.id}
                                            className="bg-gray-900 text-white"
                                        >
                                            {tier.name} -{" "}
                                            {Number(tier.price) > 0
                                                ? formatCurrency(
                                                      tier.price,
                                                      tier.currency_code,
                                                  )
                                                : "Price not set yet"}
                                        </option>
                                    ))}
                                </select>
                            )}
                            <InputError
                                className="mt-2 text-sm font-medium text-rose-400"
                                message={errors.access_tier_id}
                            />
                        </div>
                    </div>

                    {!checkout && (
                        <div className="mt-8 flex justify-start">
                            <Button
                                type="submit"
                                disabled={
                                    processing ||
                                    accessTiers.length === 0 ||
                                    !selectedTierHasPrice
                                }
                                className="min-h-[52px] rounded-xl bg-[#DB202C] px-8 py-4 text-base font-semibold text-white shadow-lg transition-all duration-200 hover:bg-[#c01a25] hover:shadow-xl disabled:pointer-events-none disabled:opacity-60"
                            >
                                {processing
                                    ? "Preparing Payment..."
                                    : selectedTierHasPrice
                                      ? "Continue to Payment"
                                      : "Set Tier Price First"}
                            </Button>
                        </div>
                    )}
                </form>

                {checkout ? (
                    <section ref={checkoutRef} className="space-y-6">
                        <div className="border-t border-white/10 pt-10">
                            <p className="text-sm font-bold uppercase tracking-[0.2em] text-[#DB202C]">
                                Payment
                            </p>
                        </div>

                        <PublicCheckoutPanel checkout={checkout} />
                    </section>
                ) : null}
            </div>
        </PublicFlowLayout>
    );
}