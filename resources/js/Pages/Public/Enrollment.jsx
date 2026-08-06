import StudentProfileForm from "@/Components/StudentProfileForm";
import PublicFlowLayout from "@/Layouts/PublicFlowLayout";
import { useForm } from "@inertiajs/react";
import { Check, LoaderCircle } from "lucide-react";
import { useEffect, useRef, useState } from "react";

// Single source of truth for the font so it can't be silently
// overridden by an older font-family declared elsewhere in the tree.
const FONT_FAMILY = "'Montserrat', sans-serif";
const COUNTDOWN_SECONDS = 3;

function normalizeYesNoFormValue(value) {
    if (value === true || value === 1 || value === "1" || value === "yes") {
        return "yes";
    }

    if (value === false || value === 0 || value === "0" || value === "no") {
        return "no";
    }

    return "";
}

function isMasterClassSlug(value) {
    const normalized = String(value ?? "")
        .trim()
        .toLowerCase()
        .replace(/[-\s]+/g, "_");

    return normalized === "master_class" || normalized === "masterclass";
}

function buildCountdownStorageKey(onboarding) {
    const onboardingIdentifier =
        onboarding?.id ??
        onboarding?.uuid ??
        onboarding?.token ??
        onboarding?.submit_url ??
        "default";

    return `yogafx-enrollment-countdown:${onboardingIdentifier}`;
}

function shouldShowInitialCountdown(onboarding) {
    if (typeof window === "undefined") {
        return true;
    }

    try {
        return (
            window.sessionStorage.getItem(
                buildCountdownStorageKey(onboarding),
            ) !== "completed"
        );
    } catch {
        return true;
    }
}

function OnboardingProgressBar() {
    const steps = [
        {
            key: "payment",
            label: "Payment",
            status: "complete",
        },
        {
            key: "enrollment",
            label: "Enrollment",
            status: "current",
        },
        {
            key: "account",
            label: "Account Setup",
            status: "upcoming",
        },
    ];

    return (
        <div
            className="w-full pb-8"
            style={{ fontFamily: FONT_FAMILY }}
            aria-label="Onboarding progress"
        >
            <div className="relative mx-auto w-full max-w-xl">
                {/* Background connector */}
                <div
                    aria-hidden="true"
                    className="absolute left-[16.666%] right-[16.666%] top-5 h-[3px] rounded-full bg-white/15"
                />

                {/* Completed connector: Payment → Enrollment */}
                <div
                    aria-hidden="true"
                    className="absolute left-[16.666%] top-5 h-[3px] w-1/3 rounded-full bg-[#DB202C]"
                />

                <div className="relative z-10 grid grid-cols-3 gap-2">
                    {steps.map((step, index) => {
                        const isComplete = step.status === "complete";
                        const isCurrent = step.status === "current";

                        return (
                            <div
                                key={step.key}
                                className="flex min-w-0 flex-col items-center text-center"
                                aria-current={isCurrent ? "step" : undefined}
                            >
                                <div
                                    className={[
                                        "flex h-10 w-10 shrink-0 items-center justify-center rounded-full border-2 text-sm font-bold transition-all",
                                        isComplete
                                            ? "border-emerald-500 bg-emerald-500 text-white shadow-[0_0_24px_rgba(16,185,129,0.24)]"
                                            : "",
                                        isCurrent
                                            ? "border-[#DB202C] bg-[#DB202C] text-white shadow-[0_0_24px_rgba(219,32,44,0.28)]"
                                            : "",
                                        !isComplete && !isCurrent
                                            ? "border-white/25 bg-[#111111] text-white/55"
                                            : "",
                                    ].join(" ")}
                                >
                                    {isComplete ? (
                                        <Check
                                            className="h-5 w-5"
                                            strokeWidth={3}
                                            aria-hidden="true"
                                        />
                                    ) : (
                                        index + 1
                                    )}
                                </div>

                                <p
                                    className={[
                                        "mt-3 truncate text-xs font-semibold sm:text-sm",
                                        isComplete || isCurrent
                                            ? "text-white"
                                            : "text-white/50",
                                    ].join(" ")}
                                >
                                    {step.label}
                                </p>

                                {isCurrent ? (
                                    <p className="mt-1 text-[10px] font-bold uppercase tracking-[0.14em] text-[#ff9ca5] sm:text-xs">
                                        In Progress
                                    </p>
                                ) : null}
                            </div>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}

function EnrollmentCountdown({ secondsRemaining }) {
    return (
        <div
            className="flex w-full justify-center pb-6 pt-1"
            style={{ fontFamily: FONT_FAMILY }}
        >
            <div
                className="
                    flex
                    min-h-[410px]
                    w-full
                    max-w-lg
                    flex-col
                    items-center
                    justify-center
                    rounded-[18px]
                    border
                    border-white/15
                    bg-[#111111]
                    px-6
                    py-8
                    text-center
                    shadow-[0_24px_80px_rgba(0,0,0,0.55)]
                    sm:min-h-[450px]
                    sm:px-10
                    sm:py-10
                "
                role="status"
                aria-live="polite"
                aria-label={`Enrollment form will open in ${secondsRemaining} seconds`}
            >
                <div className="relative h-24 w-24 shrink-0">
                    <LoaderCircle
                        className="absolute inset-0 h-24 w-24 animate-spin text-[#DB202C] drop-shadow-[0_0_10px_rgba(219,32,44,0.85)] motion-reduce:animate-none"
                        strokeWidth={2.4}
                        aria-hidden="true"
                    />

                    <div className="absolute inset-0 flex items-center justify-center">
                        <span
                            key={secondsRemaining}
                            className="text-3xl font-bold leading-none text-white"
                        >
                            {secondsRemaining}
                        </span>
                    </div>
                </div>

                <p className="mt-6 text-sm font-bold text-white">Loading...</p>
            </div>
        </div>
    );
}

export default function Enrollment({ onboarding, student }) {
    const errorBannerRef = useRef(null);

    const [secondsRemaining, setSecondsRemaining] = useState(() =>
        shouldShowInitialCountdown(onboarding) ? COUNTDOWN_SECONDS : 0,
    );

    const isLoading = secondsRemaining > 0;

    const isMasterClass = isMasterClassSlug(onboarding.access_tier?.slug);

    const { data, setData, post, errors, processing } = useForm({
        first_name: student.first_name ?? "",
        last_name: student.last_name ?? "",
        email: student.email ?? "",

        whatsapp_country_code: student.whatsapp_country_code ?? "+62",

        whatsapp_number: student.whatsapp_number ?? "",

        profile_photo: null,
        instagram: student.instagram ?? "",
        country: student.country ?? "",
        birth_date: student.birth_date ?? "",
        gender: student.gender ?? "",

        /*
         * MasterClass-only fields.
         */
        tshirt_size: student.tshirt_size ?? "",

        favorite_song: student.favorite_song ?? "",

        emergency_contact_name: student.emergency_contact_name ?? "",

        emergency_contact_relationship:
            student.emergency_contact_relationship ?? "",

        emergency_contact_country_code:
            student.emergency_contact_country_code ?? "+62",

        emergency_contact_number: student.emergency_contact_number ?? "",

        has_medical_issues: normalizeYesNoFormValue(student.has_medical_issues),

        medical_issues_details: student.medical_issues_details ?? "",

        is_taking_medication: normalizeYesNoFormValue(
            student.is_taking_medication,
        ),

        medication_details: student.medication_details ?? "",

        /*
         * Existing yoga profile fields.
         */
        practicing_yoga_for: student.practicing_yoga_for ?? "",

        yoga_sequence_experience: student.yoga_sequence_experience ?? [],

        hours_per_week: student.hours_per_week ?? "",

        current_fitness_level: student.current_fitness_level ?? "",

        flexibility_rating: student.flexibility_rating ?? "",

        motivation: student.motivation ?? "",

        why_yogafx: student.why_yogafx ?? "",

        how_did_you_find_us: student.how_did_you_find_us ?? [],

        terms_accepted: false,
        recaptcha_confirmed: false,
    });

    useEffect(() => {
        if (!isLoading) {
            return undefined;
        }

        const timeoutId = window.setTimeout(() => {
            setSecondsRemaining((current) => Math.max(current - 1, 0));
        }, 1000);

        return () => {
            window.clearTimeout(timeoutId);
        };
    }, [isLoading, secondsRemaining]);

    useEffect(() => {
        if (secondsRemaining !== 0) {
            return;
        }

        try {
            window.sessionStorage.setItem(
                buildCountdownStorageKey(onboarding),
                "completed",
            );
        } catch {
            // The form still works if sessionStorage is unavailable.
        }
    }, [onboarding, secondsRemaining]);

    useEffect(() => {
        if (Object.keys(errors).length > 0 && errorBannerRef.current) {
            errorBannerRef.current.scrollIntoView({
                behavior: "smooth",
                block: "start",
            });
        }
    }, [errors]);

    const submit = (event) => {
        event.preventDefault();

        post(onboarding.submit_url, {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    return (
        <PublicFlowLayout
            title="Enrollment"
            heading={
                <span
                    className="block text-balance"
                    style={{
                        fontFamily: FONT_FAMILY,
                        fontSize: "clamp(26px, 3.4vw, 34px)",
                        fontWeight: 700,
                        lineHeight: 1.2,
                    }}
                >
                    Complete your YogaFX enrollment
                </span>
            }
        >
            {/* Progress bar is intentionally outside the countdown switch.
                It always remains visible above both countdown and form. */}
            <OnboardingProgressBar />

            {isLoading ? (
                <EnrollmentCountdown secondsRemaining={secondsRemaining} />
            ) : (
                <>
                    {Object.keys(errors).length > 0 ? (
                        <div
                            ref={errorBannerRef}
                            className="mb-6 scroll-mt-24 rounded-lg border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-600"
                            style={{
                                fontFamily: FONT_FAMILY,
                            }}
                        >
                            <p className="mb-2 font-bold">
                                Please complete the highlighted fields before
                                continuing:
                            </p>

                            <ul className="list-inside list-disc">
                                {Object.entries(errors).map(
                                    ([field, error]) => (
                                        <li key={field}>{error}</li>
                                    ),
                                )}
                            </ul>
                        </div>
                    ) : null}

                    <StudentProfileForm
                        data={data}
                        setData={setData}
                        errors={errors}
                        processing={processing}
                        onSubmit={submit}
                        submitLabel="Enroll Now"
                        variant="scoreboard"
                        mode="enrollment"
                        currentProfilePhotoUrl={student.profile_photo_url}
                        isMasterClass={isMasterClass}
                    />
                </>
            )}
        </PublicFlowLayout>
    );
}
