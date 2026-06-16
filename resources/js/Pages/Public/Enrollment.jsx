import StudentProfileForm from '@/Components/StudentProfileForm';
import PublicFlowLayout from '@/Layouts/PublicFlowLayout';
import { useForm } from '@inertiajs/react';

export default function Enrollment({ onboarding, student }) {
    const { data, setData, post, errors, processing } = useForm({
        first_name: student.first_name ?? '',
        last_name: student.last_name ?? '',
        email: student.email ?? '',
        whatsapp: student.whatsapp ?? '',
        profile_photo: null,
        instagram: student.instagram ?? '',
        country: student.country ?? '',
        birth_date: student.birth_date ?? '',
        gender: student.gender ?? '',
        practicing_yoga_for: student.practicing_yoga_for ?? '',
        yoga_sequence_experience: student.yoga_sequence_experience ?? '',
        hours_per_week: student.hours_per_week ?? '',
        current_fitness_level: student.current_fitness_level ?? '',
        flexibility_rating: student.flexibility_rating ?? '',
        motivation: student.motivation ?? '',
        why_yogafx: student.why_yogafx ?? '',
        how_did_you_find_us: student.how_did_you_find_us ?? '',
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
            eyebrow="Onboarding Continuation"
            heading="Complete your YogaFX enrollment before creating your final password."
            description="Your payment has already succeeded, your base account already exists, and this step now fills the full profile required before sign up and LMS access are allowed."
            aside={
                <div className="space-y-5">
                    <div className="rounded-[24px] border border-white/10 bg-[#161210] p-5">
                        <p className="text-xs uppercase tracking-[0.22em] text-white/46">
                            Current state
                        </p>
                        <div className="mt-4 space-y-2 text-sm text-white/66">
                            <p>Payment status: success</p>
                            <p>Onboarding status: {onboarding.status}</p>
                            <p>Tier access: {onboarding.access_tier.name}</p>
                        </div>
                    </div>

                    <div className="rounded-[24px] border border-white/10 bg-[#161210] p-5">
                        <p className="text-xs uppercase tracking-[0.22em] text-white/46">
                            Why this step comes first
                        </p>
                        <div className="mt-4 space-y-3 text-sm leading-6 text-white/62">
                            <p>Enrollment captures the real profile data before final login credentials are created.</p>
                            <p>This prevents incomplete student accounts from entering LMS directly.</p>
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
                variant="immersive"
                currentProfilePhotoUrl={student.profile_photo_url}
            />
        </PublicFlowLayout>
    );
}
