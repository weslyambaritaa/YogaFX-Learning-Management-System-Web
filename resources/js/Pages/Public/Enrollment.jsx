import StudentProfileForm from "@/Components/StudentProfileForm";
import PublicFlowLayout from "@/Layouts/PublicFlowLayout";
import { useForm } from "@inertiajs/react";

// Single source of truth for the font so it can't be silently
// overridden by an older font-family declared elsewhere in the tree.
const FONT_FAMILY = "'Montserrat', sans-serif";

export default function Enrollment({ onboarding, student }) {
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

    const submit = (event) => {
        event.preventDefault();
        post(onboarding.submit_url, {
            forceFormData: true,
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
                    Complete your YogaFX enrollment before creating your
                    final password
                </span>
            }
            description={
                <div
                    className="rounded-[5px] px-[10px] py-[8px]"
                    style={{
                        fontFamily: FONT_FAMILY,
                        backgroundColor: "#00B14F",
                    }}
                >
                    <span
                        className="text-white"
                        style={{
                            fontFamily: FONT_FAMILY,
                            fontSize: "14px",
                            fontWeight: 400,
                        }}
                    >
                        Your payment is complete. Finish your profile before
                        creating the final password.
                    </span>
                </div>
            }
        >
            <StudentProfileForm
                data={data}
                setData={setData}
                errors={errors}
                processing={processing}
                onSubmit={submit}
                submitLabel="Save Enrollment and Continue"
                variant="scoreboard"
                mode="enrollment"
                currentProfilePhotoUrl={student.profile_photo_url}
            />
        </PublicFlowLayout>
    );
}
