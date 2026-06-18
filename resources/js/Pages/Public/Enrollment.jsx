import StudentProfileForm from '@/Components/StudentProfileForm';
import PublicFlowLayout from '@/Layouts/PublicFlowLayout';
import { useForm } from '@inertiajs/react';

export default function Enrollment({ onboarding, student }) {
    const { data, setData, post, errors, processing } = useForm({
        first_name: student.first_name ?? '',
        last_name: student.last_name ?? '',
        email: student.email ?? '',
        whatsapp_country_code: student.whatsapp_country_code ?? '+62',
        whatsapp_number: student.whatsapp_number ?? '',
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
            heading="Complete your YogaFX enrollment before creating your final password"
            description="Your payment has already succeeded, your base account already exists, and this step now fills the full profile required before sign up and LMS access are allowed."
            aside={
                <div className="space-y-5">
                    {/* Current state card */}
                    <div className="rounded-[10px] border border-gray-200 bg-gray-900 p-5">
                        <div className="flex justify-end">
                            <p className="inline-block rounded-full border border-white/30 bg-white/10 px-4 py-1 text-sm font-semibold text-white">
                                Current state
                            </p>
                        </div>
                        <div className="mt-7 space-y-2 text-sm text-white">
                            <p>Payment status: success</p>
                            <p>Onboarding status: {onboarding.status}</p>
                            <p>Tier access: {onboarding.access_tier.name}</p>
                        </div>
                    </div>

                    {/* Why this step card */}
                    <div className="rounded-[10px] border border-gray-200 bg-gray-900 p-5">
                        <div className="flex justify-end">
                            <p className="inline-block rounded-full border border-white/30 bg-white/10 px-4 py-1 text-sm font-semibold text-white">
                                Why this step comes first
                            </p>
                        </div>
                        <div className="mt-7 space-y-3 text-sm leading-6 text-white/80">
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
                variant="light"
                currentProfilePhotoUrl={student.profile_photo_url}
            />
        </PublicFlowLayout>
    );
}