import PublicCheckoutPanel from "@/Components/public/PublicCheckoutPanel";
import FlagOptionSelect from "@/Components/FlagOptionSelect";
import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import TextInput from "@/Components/TextInput";
import { Button } from "@/Components/ui/button";
import PublicFlowLayout from "@/Layouts/PublicFlowLayout";
import {
    enrichCountryOptions,
    findCountryOptionByDialCode,
} from "@/lib/countryFlags";
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

// Single source of truth for the new font so it can't be silently
// overridden by an older font-family declared elsewhere in the tree.
const FONT_FAMILY = "'Montserrat', sans-serif";

function normalizePhoneNumberInput(value) {
    return String(value ?? "")
        .replace(/^\s+/, "")
        .replace(/^0+/, "");
}

export default function Scoreboard({
    packages,
    submit_url,
    selected_package_id,
    is_package_locked = false,
}) {
    const { directory = {} } = usePage().props;
    const countryOptions = enrichCountryOptions(directory.countries ?? []);
    const phoneCountryCodeOptions = enrichCountryOptions(
        directory.phone_country_codes ?? [],
    );
    const [data, setData] = useState({
        first_name: "",
        last_name: "",
        email: "",
        phone_country_code: "+62",
        phone_number: "",
        country: "",
        package_id: selected_package_id ?? packages[0]?.id ?? "",
    });
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);
    const [checkout, setCheckout] = useState(null);
    const [autoPreparePaused, setAutoPreparePaused] = useState(false);
    const checkoutRef = useRef(null);
    const lastPreparedSignatureRef = useRef(null);

    const selectedPackage =
        packages.find(
            (pkg) => String(pkg.id) === String(data.package_id),
        ) ?? null;
    const selectedCountryOption =
        countryOptions.find((option) => option.value === data.country) ?? null;
    const selectedPhoneCountryOption = findCountryOptionByDialCode(
        phoneCountryCodeOptions,
        data.phone_country_code,
        data.country,
    );
    const selectedPackageHasPrice = Number(selectedPackage?.price ?? 0) > 0;
    const isIdentityLocked = checkout !== null;
    const packageTitle = selectedPackage?.title ?? "YogaFX Package";
    const packagePrice = selectedPackageHasPrice
        ? formatCurrency(
              selectedPackage.price,
              selectedPackage.currency_code,
          )
        : "Price not set yet";
    const normalizedEmail = (data.email ?? "").trim().toLowerCase();
    const formSignature = [
        data.first_name.trim(),
        data.last_name.trim(),
        normalizedEmail,
        data.phone_country_code.trim(),
        data.phone_number.trim(),
        data.country.trim(),
        String(data.package_id ?? ""),
    ].join("|");
    const canPrepareCheckout =
        packages.length > 0 &&
        selectedPackageHasPrice &&
        data.first_name.trim() !== "" &&
        data.last_name.trim() !== "" &&
        normalizedEmail !== "" &&
        data.phone_country_code.trim() !== "" &&
        data.phone_number.trim() !== "" &&
        data.country.trim() !== "" &&
        String(data.package_id ?? "") !== "";

    useEffect(() => {
        if (!checkoutRef.current) {
            return;
        }

        checkoutRef.current.scrollIntoView({
            behavior: "smooth",
            block: "start",
        });
    }, [checkout]);

    useEffect(() => {
        if (
            !canPrepareCheckout ||
            checkout ||
            processing ||
            autoPreparePaused ||
            lastPreparedSignatureRef.current === formSignature
        ) {
            return undefined;
        }

        const timeoutId = window.setTimeout(() => {
            prepareCheckout();
        }, 350);

        return () => {
            window.clearTimeout(timeoutId);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        autoPreparePaused,
        canPrepareCheckout,
        checkout,
        formSignature,
        processing,
    ]);

    const setFieldValue = (field, value) => {
        setData((current) => ({
            ...current,
            [field]: value,
        }));

        setErrors((current) => ({
            ...current,
            [field]: "",
        }));
        setAutoPreparePaused(false);
    };

    const prepareCheckout = async () => {
        if (!canPrepareCheckout || processing) {
            return;
        }

        setProcessing(true);
        setErrors({});
        lastPreparedSignatureRef.current = formSignature;

        try {
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
                if (response.status === 422 && payload.errors) {
                    setErrors(
                        Object.fromEntries(
                            Object.entries(payload.errors).map(
                                ([key, value]) => [
                                    key,
                                    Array.isArray(value) ? value[0] : value,
                                ],
                            ),
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

            if (!payload.checkout) {
                setErrors({
                    general:
                        "The checkout flow was prepared, but the payment panel could not be opened. Please try again.",
                });
                return;
            }

            setCheckout(payload.checkout);
        } catch {
            setErrors({
                general:
                    "The checkout flow could not be reached right now. Please check your connection and try again.",
            });
        } finally {
            setProcessing(false);
        }
    };

    const submit = async (event) => {
        event.preventDefault();
        await prepareCheckout();
    };

    const resetCheckoutPreparation = () => {
        setCheckout(null);
        setAutoPreparePaused(true);
        setErrors({});
    };

    return (
        <PublicFlowLayout
            title="Scoreboard"
            showBackButton={!is_package_locked}
            heading={
                // Heading is rendered by PublicFlowLayout, so we pass a
                // styled node to preserve the existing premium hero
                // hierarchy while letting package data stay dynamic.
                <span
                    className="block text-balance"
                    style={{
                        fontFamily: FONT_FAMILY,
                        fontSize: "clamp(26px, 3.4vw, 34px)",
                        fontWeight: 700,
                        lineHeight: 1.2,
                    }}
                >
                    {`We Are Thrilled That You Will Be Joining Our ${packageTitle}`}
                </span>
            }
            description={
                <span
                    className="block text-balance"
                    style={{
                        fontFamily: FONT_FAMILY,
                        fontSize: "18px",
                        fontWeight: 500,
                        lineHeight: 1.6,
                    }}
                >
                    {`Please Continue Your ${packagePrice} Transfer Below.`}
                </span>
            }
            aside={
                <div className="space-y-6">
                </div>
            }
        >
            {/* Root font-family applied here so any element below that
                doesn't set its own font-family inherits Montserrat,
                preventing an old global/legacy font from leaking in. */}
            <div className="space-y-10" style={{ fontFamily: FONT_FAMILY }}>
                <form id="scoreboard-form" onSubmit={submit} className="space-y-8" style={{ fontFamily: FONT_FAMILY }}>
                    {errors.general && (
                        <div
                            className="rounded-xl border border-rose-400/30 bg-rose-500/10 px-5 py-4 text-sm font-medium text-rose-100"
                            style={{ fontFamily: FONT_FAMILY }}
                        >
                            {errors.general}
                        </div>
                    )}

                    {checkout ? (
                        <div className="flex justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={resetCheckoutPreparation}
                                className="rounded-[5px] border-white/20 bg-transparent px-2.5 py-2 text-sm font-medium text-white hover:bg-white/10"
                                style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 500 }}
                            >
                                Edit Details
                            </Button>
                        </div>
                    ) : null}

                    <div className="grid gap-6 md:grid-cols-2">
                        {/* First Name */}
                        <div>
                            <InputLabel
                                htmlFor="first_name"
                                value="First Name"
                                className="text-sm font-medium text-white/90"
                                style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 500 }}
                            />
                            <TextInput
                                id="first_name"
                                value={data.first_name}
                                disabled={isIdentityLocked}
                                className="mt-2 block w-full min-h-[52px] rounded-[5px] border border-white/20 bg-black/20 px-4 py-3.5 text-sm font-normal text-white placeholder:text-white/30 shadow-sm transition-all duration-200 focus:border-white/40 focus:ring-2 focus:ring-white/20 disabled:opacity-60"
                                style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 400 }}
                                onChange={(event) =>
                                    setFieldValue("first_name", event.target.value)
                                }
                                required
                            />
                            <InputError
                                className="mt-2 text-sm font-medium text-rose-400"
                                style={{ fontFamily: FONT_FAMILY }}
                                message={errors.first_name}
                            />
                        </div>

                        {/* Last Name */}
                        <div>
                            <InputLabel
                                htmlFor="last_name"
                                value="Last Name"
                                className="text-sm font-medium text-white/90"
                                style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 500 }}
                            />
                            <TextInput
                                id="last_name"
                                value={data.last_name}
                                disabled={isIdentityLocked}
                                className="mt-2 block w-full min-h-[52px] rounded-[5px] border border-white/20 bg-black/20 px-4 py-3.5 text-sm font-normal text-white placeholder:text-white/30 shadow-sm transition-all duration-200 focus:border-white/40 focus:ring-2 focus:ring-white/20 disabled:opacity-60"
                                style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 400 }}
                                onChange={(event) =>
                                    setFieldValue("last_name", event.target.value)
                                }
                                required
                            />
                            <InputError
                                className="mt-2 text-sm font-medium text-rose-400"
                                style={{ fontFamily: FONT_FAMILY }}
                                message={errors.last_name}
                            />
                        </div>

                        {/* Email */}
                        <div className="md:col-span-2">
                            <InputLabel
                                htmlFor="email"
                                value="Email"
                                className="text-sm font-medium text-white/90"
                                style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 500 }}
                            />
                            <TextInput
                                id="email"
                                type="email"
                                value={data.email}
                                disabled={isIdentityLocked}
                                className="mt-2 block w-full min-h-[52px] rounded-[5px] border border-white/20 bg-black/20 px-4 py-3.5 text-sm font-normal text-white placeholder:text-white/30 shadow-sm transition-all duration-200 focus:border-white/40 focus:ring-2 focus:ring-white/20 disabled:opacity-60"
                                style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 400 }}
                                onChange={(event) =>
                                    setFieldValue("email", event.target.value)
                                }
                                required
                            />
                            <InputError
                                className="mt-2 text-sm font-medium text-rose-400"
                                style={{ fontFamily: FONT_FAMILY }}
                                message={errors.email}
                            />
                        </div>

                        {/* Mobile Phone */}
                        <div className="md:col-span-2">
                            <InputLabel
                                htmlFor="phone_number"
                                value="Mobile Phone"
                                className="text-sm font-medium text-white/90"
                                style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 500 }}
                            />
                                    <div className="mt-2 grid grid-cols-[128px_minmax(0,1fr)] items-start gap-4 sm:grid-cols-[160px_minmax(0,1fr)] md:grid-cols-[180px_minmax(0,1fr)]">
                                        <FlagOptionSelect
                                            id="phone_country_code"
                                            value={data.phone_country_code}
                                    selectedOption={selectedPhoneCountryOption}
                                    options={phoneCountryCodeOptions}
                                    onChange={(option) =>
                                        setFieldValue(
                                            "phone_country_code",
                                            option.value,
                                        )
                                    }
                                    displayMode="phone-code"
                                    searchPlaceholder="Search phone code or country"
                                    disabled={isIdentityLocked}
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
                                    value={data.phone_number}
                                    disabled={isIdentityLocked}
                                    className="block w-full min-h-[52px] rounded-[5px] border border-white/20 bg-black/20 px-4 py-3.5 text-sm font-normal text-white placeholder:text-white/30 shadow-sm transition-all duration-200 focus:border-white/40 focus:ring-2 focus:ring-white/20 disabled:opacity-60"
                                    style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 400 }}
                                    onChange={(event) =>
                                        setFieldValue(
                                            "phone_number",
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
                                style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 500 }}
                            />
                            <div className="mt-2">
                                <FlagOptionSelect
                                    id="country"
                                    value={data.country}
                                    selectedOption={selectedCountryOption}
                                    options={countryOptions}
                                    onChange={(option) => {
                                        setFieldValue("country", option.value);
                                        const matchedDialCode =
                                            phoneCountryCodeOptions.find((entry) =>
                                                entry.label.startsWith(`${option.label} (`),
                                            );
                                        if (matchedDialCode && !data.phone_number) {
                                            setFieldValue(
                                                "phone_country_code",
                                                matchedDialCode.value,
                                            );
                                        }
                                    }}
                                    placeholder="Select a country"
                                    disabled={isIdentityLocked}
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
                                message={errors.country}
                            />
                        </div>

                        {/* Program / Tier */}
                        <div className="md:col-span-2">
                            <InputLabel
                                htmlFor="package_id"
                                value="Package"
                                className="text-sm font-medium text-white/90"
                                style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 500 }}
                            />
                            {is_package_locked && selectedPackage ? (
                                <div
                                    className="mt-2 min-h-[52px] rounded-[5px] bg-[#ffffff] px-5 py-4 text-black shadow-sm"
                                    style={{ fontFamily: FONT_FAMILY }}
                                >
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="min-w-0">
                                            <div
                                                className="text-sm font-medium text-black"
                                                style={{
                                                    fontFamily: FONT_FAMILY,
                                                    fontSize: "14px",
                                                    fontWeight: 500,
                                                }}
                                            >
                                                {selectedPackage.title}
                                            </div>
                                        </div>
                                        <div
                                            className="shrink-0 text-right text-sm font-medium text-black"
                                            style={{
                                                fontFamily: FONT_FAMILY,
                                                fontSize: "14px",
                                                fontWeight: 500,
                                            }}
                                        >
                                            {Number(selectedPackage.price) > 0
                                                ? formatCurrency(
                                                      selectedPackage.price,
                                                      selectedPackage.currency_code,
                                                  )
                                                : "Price not set yet"}
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <select
                                    id="package_id"
                                    value={data.package_id}
                                    disabled={isIdentityLocked}
                                    onChange={(event) =>
                                        setFieldValue(
                                            "package_id",
                                            event.target.value,
                                        )
                                    }
                                    className="mt-2 block w-full min-h-[52px] rounded-[5px] border border-white/20 bg-black/20 px-4 py-3.5 text-sm font-normal text-white shadow-sm transition-all duration-200 focus:border-white/40 focus:ring-2 focus:ring-white/20 disabled:opacity-60"
                                    style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 400 }}
                                    required
                                >
                                    {packages.map((pkg) => (
                                        <option
                                            key={pkg.id}
                                            value={pkg.id}
                                            className="bg-gray-900 text-white"
                                            style={{ fontFamily: FONT_FAMILY }}
                                        >
                                            {pkg.title}
                                            {pkg.access_tier?.name ? ` (${pkg.access_tier.name})` : ''} -{" "}
                                            {Number(pkg.price) > 0
                                                ? formatCurrency(
                                                      pkg.price,
                                                      pkg.currency_code,
                                                  )
                                                : "Price not set yet"}
                                        </option>
                                    ))}
                                </select>
                            )}
                            <InputError
                                className="mt-2 text-sm font-medium text-rose-400"
                                style={{ fontFamily: FONT_FAMILY }}
                                message={errors.package_id}
                            />
                        </div>
                    </div>

                    {!checkout ? (
                        <p
                            className="text-sm text-white/65"
                            style={{ fontFamily: FONT_FAMILY, fontSize: "14px", fontWeight: 400 }}
                        >
                            {processing
                                ? "Preparing payment options..."
                                : selectedPackageHasPrice
                                  ? "Complete the identity form above to unlock payment options automatically."
                                  : "Set package price first."}
                        </p>
                    ) : null}
                </form>

                {checkout ? (
                    <section ref={checkoutRef} className="space-y-6">
                       <p
    className="text-[#DB202C]"
    style={{ fontFamily: FONT_FAMILY, fontSize: "22px", fontWeight: 500 }}
>
    Payment
</p>

                        <PublicCheckoutPanel checkout={checkout} />
                    </section>
                ) : null}
            </div>
        </PublicFlowLayout>
    );
}
