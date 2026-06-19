import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import StudentProfileForm from '@/Components/StudentProfileForm';
import { Button } from '@/Components/ui/button';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, ChevronRight, KeyRound, ShieldCheck } from 'lucide-react';

export default function Edit({ status }) {
    const user = usePage().props.auth.user;
    const studentName = user.first_name ?? user.name ?? 'Student';
    const accessTierName = user.access_tier?.name ?? 'Not assigned yet';
    const profileComplete = Boolean(user.profile_is_complete);
    const { data, setData, patch, errors, processing } = useForm({
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

        patch(route('profile.update'), {
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
                <section className="relative overflow-hidden rounded-[16px] border border-white/10 bg-[#15110f] shadow-[0_30px_120px_rgba(0,0,0,0.45)]">
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_18%_20%,_rgba(173,76,38,0.42),_transparent_30%),radial-gradient(circle_at_84%_18%,_rgba(245,158,11,0.14),_transparent_24%),linear-gradient(180deg,_rgba(255,255,255,0.04)_0%,_rgba(0,0,0,0.62)_78%,_rgba(0,0,0,0.82)_100%)]" />

                    <div className="relative grid gap-8 px-6 py-8 sm:px-8 lg:grid-cols-[minmax(0,1fr)_320px] lg:px-12 lg:py-12">
                        <div className="space-y-7">
                            <div className="flex flex-wrap items-center gap-3 text-xs uppercase tracking-[0.24em] text-white/62">
                                <span>YogaFX Profile</span>
                                <span className="h-1 w-1 rounded-full bg-white/30" />
                                <span>Student Identity</span>
                                <span className="h-1 w-1 rounded-full bg-white/30" />
                                <span>Premium Wellness Setup</span>
                            </div>

                            <div className="space-y-4">
                                <p className="text-xs font-medium uppercase tracking-[0.28em] text-[#f2d9c8]">
                                    Hi {studentName}, shape your YogaFX identity
                                </p>
                                <h1 className="max-w-3xl text-4xl font-semibold tracking-[-0.03em] text-white sm:text-5xl xl:text-6xl">
                                    Keep your student profile complete so your
                                    learning journey stays personal, calm, and
                                    ready to continue.
                                </h1>
                                <p className="max-w-2xl text-sm leading-7 text-white/68 sm:text-base">
                                    Keep your profile accurate so your student experience stays aligned.
                                </p>
                            </div>

                            <div className="flex flex-wrap items-center gap-3">
                                <Button
                                    type="button"
                                    disabled
                                    className="rounded-lg bg-[#d5462f] px-6 text-white opacity-100 hover:bg-[#e2553d]"
                                >
                                    {profileComplete ? 'Profile Complete' : 'Complete Your Profile'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled
                                    className="rounded-lg border-white/20 bg-white/5 px-6 text-white opacity-100 hover:bg-white/10 hover:text-white"
                                >
                                    Current Tier: {accessTierName}
                                </Button>
                            </div>
                        </div>

                        <div className="grid gap-4">
                            <div className="rounded-[14px] border border-white/10 bg-black/30 p-5 backdrop-blur-md">
                                <p className="text-xs uppercase tracking-[0.22em] text-white/50">
                                    Profile Status
                                </p>
                                <div className="mt-5 flex items-start gap-3">
                                    <div className="rounded-full border border-white/10 bg-white/5 p-2">
                                        <CheckCircle2 className="size-5 text-[#ffd7cf]" />
                                    </div>
                                    <div>
                                        <p className="text-lg font-semibold text-white">
                                            {profileComplete
                                                ? 'Ready for your dashboard'
                                                : 'Needs completion before dashboard access'}
                                        </p>
                                        <p className="mt-2 text-sm leading-6 text-white/58">
                                            {profileComplete
                                                ? 'Your student identity is complete and ready to support the rest of your YogaFX learning experience.'
                                                : 'Finish all required fields here so Home and the rest of the student area can safely personalize your next learning steps.'}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div className="rounded-[14px] border border-white/10 bg-black/30 p-5 backdrop-blur-md">
                                <p className="text-xs uppercase tracking-[0.22em] text-white/50">
                                    Access Tier
                                </p>
                                <div className="mt-5 flex items-start gap-3">
                                    <div className="rounded-full border border-white/10 bg-white/5 p-2">
                                        <ShieldCheck className="size-5 text-[#ffd7cf]" />
                                    </div>
                                    <div>
                                        <p className="text-lg font-semibold text-white">
                                            {accessTierName}
                                        </p>
                                        <p className="mt-2 text-sm leading-6 text-white/58">
                                            Your tier determines which modules,
                                            lessons, ebooks, and courses can
                                            appear across the student side.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {!profileComplete && (
                    <div className="rounded-[12px] border border-amber-300/15 bg-[linear-gradient(160deg,rgba(217,119,6,0.16),rgba(255,255,255,0.03))] px-5 py-4 text-sm text-amber-50/90">
                        Complete every required profile field before continuing
                        to the student dashboard. This keeps your Home experience
                        relevant and your learning flow stable.
                    </div>
                )}

                {status === 'profile-updated' && (
                    <div className="rounded-[12px] border border-emerald-300/15 bg-[linear-gradient(160deg,rgba(16,185,129,0.16),rgba(255,255,255,0.03))] px-5 py-4 text-sm text-emerald-50/90">
                        Your profile has been updated and saved successfully.
                    </div>
                )}

                {status === 'student-password-change-email-sent' && (
                    <div className="rounded-[12px] border border-emerald-300/15 bg-[linear-gradient(160deg,rgba(16,185,129,0.16),rgba(255,255,255,0.03))] px-5 py-4 text-sm text-emerald-50/90">
                        We sent a password change email to your student inbox. Open it to get the OTP code and secure link for your next step.
                    </div>
                )}

                <section className="rounded-[16px] border border-white/10 bg-white/[0.04] p-5 backdrop-blur-sm sm:p-6 lg:p-8">
                    <div className="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div className="space-y-2">
                            <p className="text-xs uppercase tracking-[0.22em] text-white/45">
                                Student Profile Form
                            </p>
                            <h2 className="text-3xl font-semibold tracking-tight text-white">
                                Keep your personal and learning context aligned
                            </h2>
                            <p className="max-w-2xl text-sm leading-7 text-white/60">
                                Update the details used across your learning journey.
                            </p>
                        </div>

                        <div className="rounded-lg border border-white/12 bg-black/20 px-4 py-2 text-sm text-white/62">
                            Used across your student area
                        </div>
                    </div>

                    <div className="rounded-[14px] border border-white/10 bg-[#100d0c] p-4 sm:p-5 lg:p-6">
                        <StudentProfileForm
                            data={data}
                            setData={setData}
                            errors={errors}
                            processing={processing}
                            onSubmit={submit}
                            submitLabel="Save Profile"
                            variant="immersive"
                            currentProfilePhotoUrl={user.profile_photo}
                        />
                    </div>
                </section>

                <section className="rounded-[16px] border border-white/10 bg-white/[0.04] p-5 backdrop-blur-sm sm:p-6 lg:p-8">
                    <div className="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div className="space-y-2">
                            <p className="text-xs uppercase tracking-[0.22em] text-white/45">
                                Security
                            </p>
                            <h2 className="text-3xl font-semibold tracking-tight text-white">
                                Change your password safely
                            </h2>
                            <p className="max-w-2xl text-sm leading-7 text-white/60">
                                Request a password change by email.
                            </p>
                        </div>

                        <div className="rounded-lg border border-white/12 bg-black/20 px-4 py-2 text-sm text-white/62">
                            Secure email flow
                        </div>
                    </div>

                    <div className="rounded-[14px] border border-white/10 bg-[#100d0c] p-5 sm:p-6">
                        <div className="mb-6 flex items-start gap-4">
                            <div className="rounded-full border border-white/10 bg-white/5 p-3">
                                <KeyRound className="size-5 text-[#ffd7cf]" />
                            </div>
                            <div className="space-y-2">
                                <h3 className="text-xl font-semibold text-white">
                                    Request password change
                                </h3>
                                <p className="max-w-2xl text-sm leading-7 text-white/60">
                                    We will send an OTP and secure link to your email.
                                </p>
                            </div>
                        </div>

                        <div className="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                            <p className="max-w-2xl text-sm leading-7 text-white/48">
                                Use this when you want to change your password from inside your account.
                            </p>

                            <Button
                                type="button"
                                onClick={requestPasswordChange}
                                className="rounded-lg bg-[#d5462f] px-6 text-white hover:bg-[#e2553d]"
                            >
                                Change Password
                            </Button>
                        </div>
                    </div>
                </section>

                <div className="flex justify-end">
                    <div className="rounded-lg border border-white/10 bg-black/20 px-4 py-2 text-xs uppercase tracking-[0.22em] text-white/45">
                        Next student stage stays paused until you are ready
                        <ChevronRight className="ml-2 inline size-3.5" />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
