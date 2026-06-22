import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatCurrency } from '@/lib/currency';
import StudentProfileForm from '@/Components/StudentProfileForm';
import { Button } from '@/Components/ui/button';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

export default function Edit({ status, upgradeOptions = [] }) {
    const user = usePage().props.auth.user;
    const studentName = user.first_name ?? user.name ?? 'Student';
    const accessTierName = user.access_tier?.name ?? 'Not assigned yet';
    const profileComplete = Boolean(user.profile_is_complete);
    const missingProfileFields = user.missing_profile_fields ?? [];
    const missingFieldLabels = {
        first_name: 'First Name',
        last_name: 'Last Name',
        email: 'Email',
        whatsapp: 'WhatsApp',
        country: 'Country',
        birth_date: 'Birth Date',
        gender: 'Gender',
        practicing_yoga_for: 'Current Yoga Experience',
        yoga_sequence_experience: 'Yoga Sequence Experience',
        hours_per_week: 'How Many Hours Per Week',
        current_fitness_level: 'Current Fitness Level',
        flexibility_rating: 'Flexibility Rating',
        motivation: 'Motivation',
        why_yogafx: 'Why YogaFX',
        how_did_you_find_us: 'How Did You Find Us',
    };
    const { data, setData, post, transform, errors, processing } = useForm({
        _method: 'patch',
        first_name: user.first_name ?? '',
        last_name: user.last_name ?? '',
        email: user.email ?? '',
        whatsapp_country_code: user.whatsapp_country_code ?? '+62',
        whatsapp_number: user.whatsapp_number ?? '',
        profile_photo: null,
        instagram: user.instagram ?? '',
        country: user.country ?? '',
        birth_date: user.birth_date ?? '',
        gender: user.gender ?? '',
        practicing_yoga_for: user.practicing_yoga_for ?? '',
        yoga_sequence_experience: user.yoga_sequence_experience ?? [],
        hours_per_week: user.hours_per_week ?? '',
        current_fitness_level: user.current_fitness_level ?? '',
        flexibility_rating: user.flexibility_rating ?? '',
        motivation: user.motivation ?? '',
        why_yogafx: user.why_yogafx ?? '',
        how_did_you_find_us: user.how_did_you_find_us ?? [],
    });

    const submit = (e) => {
        e.preventDefault();

        transform((currentData) => ({
            ...currentData,
            _method: 'patch',
        })).post(route('profile.update'), {
            forceFormData: true,
        });
    };

    const requestPasswordChange = () => {
        router.post(route('profile.password.request'), {}, {
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout studentVariant="immersive" studentContentClassName="pb-16">
            <Head title="Student Profile" />

            <div className="mx-auto flex max-w-[1400px] flex-col gap-8 px-4 pt-6 sm:px-6 lg:px-10">
                <section className="rounded-[16px] border border-white/10 bg-[#15110f] px-6 py-8 shadow-[0_30px_120px_rgba(0,0,0,0.45)] sm:px-8">
                    <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                        <div className="space-y-3">
                            <p className="text-xs font-medium uppercase tracking-[0.28em] text-[#f2d9c8]">
                                Hi {studentName}
                            </p>
                            <h1 className="text-4xl font-semibold tracking-[-0.03em] text-white sm:text-5xl">
                                Profile
                            </h1>
                        </div>

                        <div className="flex flex-wrap items-center gap-3">
                            <div className="rounded-lg border border-white/12 bg-white/5 px-4 py-2 text-sm text-white/80">
                                {profileComplete ? 'Complete' : 'Needs completion'}
                            </div>
                            <div className="rounded-lg border border-white/12 bg-white/5 px-4 py-2 text-sm text-white/80">
                                {accessTierName}
                            </div>
                        </div>
                    </div>
                </section>

                {!profileComplete && (
                    <div className="rounded-[12px] border border-amber-300/15 bg-[linear-gradient(160deg,rgba(217,119,6,0.16),rgba(255,255,255,0.03))] px-5 py-4 text-sm text-amber-50/90">
                        Complete the required profile fields before entering the dashboard.
                        {missingProfileFields.length ? (
                            <div className="mt-2 text-amber-50/80">
                                Missing: {missingProfileFields.map((field) => missingFieldLabels[field] ?? field).join(', ')}.
                            </div>
                        ) : null}
                    </div>
                )}

                {status === 'profile-updated' && (
                    <div className="rounded-[12px] border border-emerald-300/15 bg-[linear-gradient(160deg,rgba(16,185,129,0.16),rgba(255,255,255,0.03))] px-5 py-4 text-sm text-emerald-50/90">
                        Profile updated.
                    </div>
                )}

                {status === 'student-password-change-email-sent' && (
                    <div className="rounded-[12px] border border-emerald-300/15 bg-[linear-gradient(160deg,rgba(16,185,129,0.16),rgba(255,255,255,0.03))] px-5 py-4 text-sm text-emerald-50/90">
                        Password change email sent.
                    </div>
                )}

                <section className="rounded-[16px] border border-white/10 bg-white/[0.04] p-5 sm:p-6 lg:p-8">
                    <div className="mb-6 flex items-center justify-between gap-4">
                        <div>
                            <p className="text-xs uppercase tracking-[0.22em] text-white/45">
                                Student Profile
                            </p>
                            <h2 className="mt-2 text-3xl font-semibold tracking-tight text-white">
                                Edit details
                            </h2>
                        </div>
                    </div>

                    <StudentProfileForm
                        data={data}
                        setData={setData}
                        errors={errors}
                        processing={processing}
                        onSubmit={submit}
                        submitLabel="Save Profile"
                        variant="immersive"
                        mode="profile"
                        currentProfilePhotoUrl={user.profile_photo}
                    />
                </section>

                <section className="rounded-[16px] border border-white/10 bg-white/[0.04] p-5 sm:p-6 lg:p-8">
                    <div className="mb-6 flex items-center justify-between gap-4">
                        <div>
                            <p className="text-xs uppercase tracking-[0.22em] text-white/45">
                                Security
                            </p>
                            <h2 className="mt-2 text-3xl font-semibold tracking-tight text-white">
                                Change password
                            </h2>
                        </div>
                    </div>

                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-sm text-white/60">
                            Request a password change by email.
                        </p>

                        <Button
                            type="button"
                            onClick={requestPasswordChange}
                            className="rounded-lg bg-[#d5462f] px-6 text-white hover:bg-[#e2553d]"
                        >
                            Change Password
                        </Button>
                    </div>
                </section>

                <section className="rounded-[16px] border border-white/10 bg-white/[0.04] p-5 sm:p-6 lg:p-8">
                    <div className="mb-6 flex items-center justify-between gap-4">
                        <div>
                            <p className="text-xs uppercase tracking-[0.22em] text-white/45">
                                Upgrade Access
                            </p>
                            <h2 className="mt-2 text-3xl font-semibold tracking-tight text-white">
                                Upgrade tier
                            </h2>
                        </div>
                    </div>

                    {upgradeOptions.length > 0 ? (
                        <div className="grid gap-4 lg:grid-cols-2">
                            {upgradeOptions.map((tier) => (
                                <div
                                    key={tier.id}
                                    className="rounded-[14px] border border-white/10 bg-[#100d0c] p-5 sm:p-6"
                                >
                                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <h3 className="text-xl font-semibold text-white">
                                                {tier.name}
                                            </h3>
                                            <p className="mt-2 text-sm text-white/58">
                                                {formatCurrency(tier.price, tier.currency_code)}
                                            </p>
                                        </div>

                                        <Button
                                            asChild
                                            className="rounded-lg bg-[#d5462f] px-6 text-white hover:bg-[#e2553d]"
                                        >
                                            <Link href={tier.upgrade_url}>Upgrade</Link>
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="rounded-[14px] border border-white/10 bg-[#100d0c] p-5 text-sm text-white/60 sm:p-6">
                            No upgrade is available right now.
                        </div>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
