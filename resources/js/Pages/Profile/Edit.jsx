import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import StudentProfileForm from '@/Components/StudentProfileForm';
import { Head, useForm, usePage } from '@inertiajs/react';

export default function Edit({ status }) {
    const user = usePage().props.auth.user;
    const { data, setData, patch, errors, processing } = useForm({
        first_name: user.first_name ?? '',
        last_name: user.last_name ?? '',
        email: user.email ?? '',
        whatsapp: user.whatsapp ?? '',
        preferred_certificate_picture: user.preferred_certificate_picture ?? '',
        instagram: user.instagram ?? '',
        country: user.country ?? '',
        birth_date: user.birth_date ?? '',
        gender: user.gender ?? '',
        practicing_yoga_for: user.practicing_yoga_for ?? '',
        yoga_sequence_experience: user.yoga_sequence_experience ?? '',
        hours_per_week: user.hours_per_week ?? '',
        current_fitness_level: user.current_fitness_level ?? '',
        flexibility_rating: user.flexibility_rating ?? '',
        motivation: user.motivation ?? '',
        why_yogafx: user.why_yogafx ?? '',
        how_did_you_find_us: user.how_did_you_find_us ?? '',
    });

    const submit = (e) => {
        e.preventDefault();

        patch(route('profile.update'));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Student Profile
                </h2>
            }
        >
            <Head title="Student Profile" />

            <div className="py-12">
                <div className="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                    {!user.profile_is_complete && (
                        <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            Complete your profile before continuing to the
                            student dashboard.
                        </div>
                    )}

                    {status === 'profile-updated' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Your profile has been updated.
                        </div>
                    )}

                    <div className="rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                        Current access tier:{' '}
                        <span className="font-medium">
                            {user.access_tier?.name ?? 'Not assigned yet'}
                        </span>
                    </div>

                    <div className="bg-white p-6 shadow sm:rounded-lg sm:p-8">
                        <StudentProfileForm
                            data={data}
                            setData={setData}
                            errors={errors}
                            processing={processing}
                            onSubmit={submit}
                            submitLabel="Save Profile"
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
