import StudentProfileForm from "@/Components/StudentProfileForm";
import PublicFlowLayout from "@/Layouts/PublicFlowLayout";
import { useForm } from "@inertiajs/react";

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
            heading="Complete your YogaFX enrollment before creating your final password"
            description="Your payment is complete. Finish your profile before creating the final password."
            aside={
                <div className="space-y-6">
                    <div className="rounded-[10px] border border-white/10 bg-white/5 p-5">
                        <p className="text-sm font-semibold text-white">
                            Enrollment status
                        </p>
                        <div className="mt-5 space-y-3 text-sm leading-6 text-white/70">
                            <p>Payment status: success</p>
                            <p>Onboarding status: {onboarding.status}</p>
                            <p>Tier access: {onboarding.access_tier.name}</p>
                        </div>
                    </div>
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
