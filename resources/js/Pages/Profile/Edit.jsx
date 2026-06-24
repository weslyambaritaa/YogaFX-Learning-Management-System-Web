import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatCurrency } from '@/lib/currency';
import StudentProfileForm from '@/Components/StudentProfileForm';
import { Button } from '@/Components/ui/button';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

const FONT = { fontFamily: "'Montserrat', sans-serif" };

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

    const { data, setData, post, errors, processing } = useForm({
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
        post(route('profile.update'), { forceFormData: true });
    };

    const requestPasswordChange = () => {
        router.post(route('profile.password.request'), {}, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout studentVariant="immersive" studentContentClassName="pb-16">
            <Head title="Student Profile" />

            <div
                className="mx-auto flex max-w-[1400px] flex-col gap-8 px-4 pt-6 sm:px-6 lg:px-10"
                style={FONT}
            >
                {/* ── Hero ── */}
                <section>
                    <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                        <div className="space-y-2">
                            <p className="text-[14px] font-medium text-[#f2d9c8]">
                                Hi {studentName}
                            </p>
                            <h1 className="text-[28px] font-semibold tracking-[-0.03em] text-white">
                                Profile
                            </h1>
                        </div>

                        <div className="flex flex-wrap items-center gap-3">
                            <div className="rounded-[5px] border border-white/12 bg-white/5 px-[10px] py-[8px] text-[14px] font-medium text-white/80">
                                {profileComplete ? 'Complete' : 'Needs completion'}
                            </div>
                            <div className="rounded-[5px] border border-white/12 bg-white/5 px-[10px] py-[8px] text-[14px] font-medium text-white/80">
                                {accessTierName}
                            </div>
                        </div>
                    </div>
                </section>

                {/* ── Alerts ── */}
                {!profileComplete && (
                    <div className="rounded-[5px] border border-amber-300/15 bg-[linear-gradient(160deg,rgba(217,119,6,0.16),rgba(255,255,255,0.03))] px-[10px] py-[8px] text-[14px] font-medium text-amber-50/90">
                        Complete the required profile fields before entering the dashboard.
                        {missingProfileFields.length ? (
                            <div className="mt-2 text-amber-50/80">
                                Missing: {missingProfileFields.map((f) => missingFieldLabels[f] ?? f).join(', ')}.
                            </div>
                        ) : null}
                    </div>
                )}

                {status === 'profile-updated' && (
                    <div className="rounded-[5px] border border-emerald-300/15 bg-[linear-gradient(160deg,rgba(16,185,129,0.16),rgba(255,255,255,0.03))] px-[10px] py-[8px] text-[14px] font-medium text-emerald-50/90">
                        Profile updated.
                    </div>
                )}

                {status === 'student-password-change-email-sent' && (
                    <div className="rounded-[5px] border border-emerald-300/15 bg-[linear-gradient(160deg,rgba(16,185,129,0.16),rgba(255,255,255,0.03))] px-[10px] py-[8px] text-[14px] font-medium text-emerald-50/90">
                        Password change email sent.
                    </div>
                )}

                {/* ── Student Profile ── */}
                <section>
                    <div className="mb-6">
                        <p className="text-[14px] font-medium text-white/45">
                            Student Profile
                        </p>
                        <h2 className="mt-1 text-[22px] font-medium tracking-tight text-white">
                            Edit details
                        </h2>
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

                {/* ── Security ── */}
                <section className="border-t border-white/10 pt-8">
                    <div className="mb-6">
                        <p className="text-[14px] font-medium text-white/45">
                            Security
                        </p>
                        <h2 className="mt-1 text-[22px] font-medium tracking-tight text-white">
                            Change password
                        </h2>
                    </div>

                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-[14px] font-normal text-white/60">
                            Request a password change by email.
                        </p>

                        <Button
                            type="button"
                            onClick={requestPasswordChange}
                            style={FONT}
                            className="h-auto rounded-[5px] bg-[#db202c] px-[10px] py-[8px] text-[14px] font-medium text-white hover:bg-[#db202c]"
                        >
                            Change Password
                        </Button>
                    </div>
                </section>

                {/* ── Upgrade Access ── */}
                <section className="border-t border-white/10 pt-8">
                    <div className="mb-6">
                        <p className="text-[14px] font-medium text-white/45">
                            Upgrade Access
                        </p>
                        <h2 className="mt-1 text-[22px] font-medium tracking-tight text-white">
                            Upgrade tier
                        </h2>
                    </div>

                    {upgradeOptions.length > 0 ? (
                        <div className="grid gap-4 lg:grid-cols-2">
                            {upgradeOptions.map((tier) => (
                                <div
                                    key={tier.id}
                                    className="rounded-[5px] border border-white/10 bg-white/[0.04] px-[10px] py-[8px]"
                                >
                                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <h3 className="text-[14px] font-semibold text-white">
                                                {tier.name}
                                            </h3>
                                            <p className="mt-1 text-[14px] font-normal text-white/58">
                                                {formatCurrency(tier.price, tier.currency_code)}
                                            </p>
                                        </div>

                                        <Button
                                            asChild
                                            style={FONT}
                                            className="h-auto rounded-[5px] bg-[#d5462f] px-[10px] py-[8px] text-[14px] font-medium text-white hover:bg-[#db202c]"
                                        >
                                            <Link href={tier.upgrade_url}>Upgrade</Link>
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="text-[14px] font-normal text-white/60">
                            No upgrade is available right now.
                        </p>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}