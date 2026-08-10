import StudentProfileForm from "@/Components/StudentProfileForm";
import PublicFlowLayout from "@/Layouts/PublicFlowLayout";
import { useForm } from "@inertiajs/react";
import { LoaderCircle } from "lucide-react";
import { useEffect, useRef, useState } from "react";

// Single source of truth for the font so it can't be silently
// overridden by an older font-family declared elsewhere in the tree.
const FONT_FAMILY = "'Montserrat', sans-serif";
const COUNTDOWN_SECONDS = 5;

function normalizeYesNoFormValue(value) {
    if (
        value === true ||
        value === 1 ||
        value === "1" ||
        value === "yes"
    ) {
        return "yes";
    }

    if (
        value === false ||
        value === 0 ||
        value === "0" ||
        value === "no"
    ) {
        return "no";
    }

    return "";
}

function isMasterClassSlug(value) {
    const normalized = String(value ?? "")
        .trim()
        .toLowerCase()
        .replace(/[-\s]+/g, "_");

    return (
        normalized === "master_class" ||
        normalized === "masterclass"
    );
}

function EnrollmentLoadingOverlay({ secondsRemaining }) {
    return (
        <div
            className="fixed inset-0 z-[9999] flex min-h-[100dvh] items-center justify-center overflow-y-auto bg-black px-4 py-6 text-white sm:px-6"
            style={{ fontFamily: FONT_FAMILY }}
            role="status"
            aria-live="polite"
            aria-label={`Enrollment form will open in ${secondsRemaining} seconds`}
        >
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-0"
                style={{
                    backgroundImage:
                        "radial-gradient(circle at 50% 45%, rgba(219,32,44,0.16), transparent 34%), radial-gradient(circle at 85% 90%, rgba(219,32,44,0.10), transparent 28%)",
                }}
            />

            <div
                className="
                    relative
                    z-10
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
            >
                <img
                    src="https://yogafx.b-cdn.net/content/Logo%20YogAFX.png"
                    alt="YogaFX"
                    className="mb-8 h-16 w-auto object-contain sm:h-20"
                />

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

                <p className="mt-6 text-sm font-bold text-white">
                    Loading...
                </p>
            </div>
        </div>
    );
}

export default function Enrollment({ onboarding, student }) {
    const errorBannerRef = useRef(null);

    const [secondsRemaining, setSecondsRemaining] = useState(
        COUNTDOWN_SECONDS,
    );

    const isLoading = secondsRemaining > 0;

    const isMasterClass = isMasterClassSlug(
        onboarding.access_tier?.slug,
    );

    const {
        data,
        setData,
        post,
        errors,
        processing,
    } = useForm({
        first_name: student.first_name ?? "",
        last_name: student.last_name ?? "",
        email: student.email ?? "",

        whatsapp_country_code:
            student.whatsapp_country_code ?? "+62",

        whatsapp_number:
            student.whatsapp_number ?? "",

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

        emergency_contact_name:
            student.emergency_contact_name ?? "",

        emergency_contact_relationship:
            student.emergency_contact_relationship ?? "",

        emergency_contact_country_code:
            student.emergency_contact_country_code ?? "+62",

        emergency_contact_number:
            student.emergency_contact_number ?? "",

        has_medical_issues:
            normalizeYesNoFormValue(
                student.has_medical_issues,
            ),

        medical_issues_details:
            student.medical_issues_details ?? "",

        is_taking_medication:
            normalizeYesNoFormValue(
                student.is_taking_medication,
            ),

        medication_details:
            student.medication_details ?? "",

        /*
         * Existing yoga profile fields.
         */
        practicing_yoga_for:
            student.practicing_yoga_for ?? "",

        yoga_sequence_experience:
            student.yoga_sequence_experience ?? [],

        hours_per_week:
            student.hours_per_week ?? "",

        current_fitness_level:
            student.current_fitness_level ?? "",

        flexibility_rating:
            student.flexibility_rating ?? "",

        motivation:
            student.motivation ?? "",

        why_yogafx:
            student.why_yogafx ?? "",

        how_did_you_find_us:
            student.how_did_you_find_us ?? [],

        terms_accepted: false,
        recaptcha_confirmed: false,
    });

    useEffect(() => {
        if (!isLoading) {
            return undefined;
        }

        const timeoutId = window.setTimeout(() => {
            setSecondsRemaining((current) =>
                Math.max(current - 1, 0),
            );
        }, 1000);

        return () => {
            window.clearTimeout(timeoutId);
        };
    }, [isLoading, secondsRemaining]);

    useEffect(() => {
        if (!isLoading) {
            return undefined;
        }

        const previousBodyOverflow =
            document.body.style.overflow;

        const previousHtmlOverflow =
            document.documentElement.style.overflow;

        document.body.style.overflow = "hidden";
        document.documentElement.style.overflow = "hidden";

        return () => {
            document.body.style.overflow =
                previousBodyOverflow;

            document.documentElement.style.overflow =
                previousHtmlOverflow;
        };
    }, [isLoading]);

    useEffect(() => {
        if (
            Object.keys(errors).length > 0 &&
            errorBannerRef.current
        ) {
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
        <>
            <PublicFlowLayout
                title="Enrollment"
                progressStep={2}
                heading={
                    <span
                        className="block text-balance"
                        style={{
                            fontFamily: FONT_FAMILY,
                            fontSize:
                                "clamp(26px, 3.4vw, 34px)",
                            fontWeight: 700,
                            lineHeight: 1.2,
                        }}
                    >
                        Welcome To Your Yoga
                        <span className="text-[#DB202C]">
                            FX
                        </span>{" "}
                        Enrollment Application Form
                    </span>
                }
                description={
                    <span
                        className="font-medium italic text-white/75"
                        style={{
                            fontFamily: FONT_FAMILY,
                        }}
                    >
                        Only 1 minute to complete
                    </span>
                }
            >
                {Object.keys(errors).length > 0 ? (
                    <div
                        ref={errorBannerRef}
                        className="mb-6 scroll-mt-24 rounded-lg border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-600"
                        style={{
                            fontFamily: FONT_FAMILY,
                        }}
                    >
                        <p className="mb-2 font-bold">
                            Please complete the highlighted
                            fields before continuing:
                        </p>

                        <ul className="list-inside list-disc">
                            {Object.entries(errors).map(
                                ([field, error]) => (
                                    <li key={field}>
                                        {error}
                                    </li>
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
                    currentProfilePhotoUrl={
                        student.profile_photo_url
                    }
                    isMasterClass={isMasterClass}
                />
            </PublicFlowLayout>

            {isLoading ? (
                <EnrollmentLoadingOverlay
                    secondsRemaining={
                        secondsRemaining
                    }
                />
            ) : null}
        </>
    );
}