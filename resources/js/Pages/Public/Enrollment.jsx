import StudentProfileForm from "@/Components/StudentProfileForm";
import PublicFlowLayout from "@/Layouts/PublicFlowLayout";
import { useForm } from "@inertiajs/react";
import { useEffect, useRef } from "react";

// Single source of truth for the font so it can't be silently
// overridden by an older font-family declared elsewhere in the tree.
const FONT_FAMILY = "'Montserrat', sans-serif";

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

export default function Enrollment({ onboarding, student }) {
    const errorBannerRef = useRef(null);

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
            progressStep={2}
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
                        {Object.entries(errors).map(([field, error]) => (
                            <li key={field}>{error}</li>
                        ))}
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
        </PublicFlowLayout>
    );
}
