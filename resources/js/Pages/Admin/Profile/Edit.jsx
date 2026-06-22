import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { formatCurrency } from "@/lib/currency";
import StudentProfileForm from "@/Components/StudentProfileForm";
import { Button } from "@/Components/ui/button";
import { Head, Link, router, useForm, usePage } from "@inertiajs/react";

export default function Edit({ status, upgradeOptions = [] }) {
    const user = usePage().props.auth.user;
    const studentName = user.first_name ?? user.name ?? "Student";
    const accessTierName = user.access_tier?.name ?? "Not assigned yet";
    const profileComplete = Boolean(user.profile_is_complete);
    const missingProfileFields = user.missing_profile_fields ?? [];
    const missingFieldLabels = {
        first_name: "First Name",
        last_name: "Last Name",
        email: "Email",
        whatsapp: "WhatsApp",
        country: "Country",
        birth_date: "Birth Date",
        gender: "Gender",
        practicing_yoga_for: "Current Yoga Experience",
        yoga_sequence_experience: "Yoga Sequence Experience",
        hours_per_week: "How Many Hours Per Week",
        current_fitness_level: "Current Fitness Level",
        flexibility_rating: "Flexibility Rating",
        motivation: "Motivation",
        why_yogafx: "Why YogaFX",
        how_did_you_find_us: "How Did You Find Us",
    };
    const { data, setData, post, transform, errors, processing } = useForm({
        _method: "patch",
        first_name: user.first_name ?? "",
        last_name: user.last_name ?? "",
        email: user.email ?? "",
        whatsapp_country_code: user.whatsapp_country_code ?? "+62",
        whatsapp_number: user.whatsapp_number ?? "",
        profile_photo: null,
        instagram: user.instagram ?? "",
        country: user.country ?? "",
        birth_date: user.birth_date ?? "",
        gender: user.gender ?? "",
        practicing_yoga_for: user.practicing_yoga_for ?? "",
        yoga_sequence_experience: user.yoga_sequence_experience ?? [],
        hours_per_week: user.hours_per_week ?? "",
        current_fitness_level: user.current_fitness_level ?? "",
        flexibility_rating: user.flexibility_rating ?? "",
        motivation: user.motivation ?? "",
        why_yogafx: user.why_yogafx ?? "",
        how_did_you_find_us: user.how_did_you_find_us ?? [],
    });

    const submit = (e) => {
        e.preventDefault();

        transform((currentData) => ({
            ...currentData,
            _method: "patch",
        })).post(route("profile.update"), {
            forceFormData: true,
        });
    };

    const requestPasswordChange = () => {
        router.post(
            route("profile.password.request"),
            {},
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <AuthenticatedLayout
            studentVariant="immersive"
            studentContentClassName="pb-16"
        >
            <Head title="Student Profile" />

            <div className="mx-auto flex max-w-[1400px] flex-col gap-10 px-4 pt-8 sm:px-6 lg:px-10">
                {/* Header (No Frame) */}
                <section className="px-2">
                    <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                        <div className="space-y-3">
                            <p className="text-xs font-semibold uppercase tracking-[0.28em] text-white">
                                Hi {studentName}
                            </p>
                            <h1 className="text-4xl font-bold tracking-[-0.03em] text-white sm:text-5xl">
                                Profile
                            </h1>
                        </div>

                        <div className="flex flex-wrap items-center gap-3">
                            <div className="rounded-full border border-white/20 bg-white/5 px-5 py-2.5 text-sm font-medium text-white backdrop-blur">
                                {profileComplete
                                    ? "Complete"
                                    : "Needs completion"}
                            </div>
                            <div className="rounded-full border border-white/20 bg-white/5 px-5 py-2.5 text-sm font-medium text-white backdrop-blur">
                                {accessTierName}
                            </div>
                        </div>
                    </div>
                </section>

                {!profileComplete && (
                    <div className="rounded-[12px] border border-amber-500/30 bg-amber-500/10 px-5 py-4 text-sm font-medium text-white">
                        Complete the required profile fields before entering the
                        dashboard.
                        {missingProfileFields.length ? (
                            <div className="mt-2 text-white/80 font-normal">
                                Missing:{" "}
                                {missingProfileFields
                                    .map(
                                        (field) =>
                                            missingFieldLabels[field] ?? field,
                                    )
                                    .join(", ")}
                                .
                            </div>
                        ) : null}
                    </div>
                )}

                {status === "profile-updated" && (
                    <div className="rounded-[12px] border border-emerald-500/30 bg-emerald-500/10 px-5 py-4 text-sm font-medium text-white">
                        Profile updated.
                    </div>
                )}

                {status === "student-password-change-email-sent" && (
                    <div className="rounded-[12px] border border-emerald-500/30 bg-emerald-500/10 px-5 py-4 text-sm font-medium text-white">
                        Password change email sent.
                    </div>
                )}

                {/* Form Section (No Frame) */}
                <section className="px-2">
                    <div className="mb-6">
                        <p className="text-xs font-semibold uppercase tracking-[0.22em] text-white/60">
                            Student Profile
                        </p>
                        <h2 className="mt-2 text-3xl font-bold tracking-tight text-white">
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

                {/* Security Section (No Frame) */}
                <section className="px-2 mt-8 border-t border-white/10 pt-10">
                    <div className="mb-6">
                        <p className="text-xs font-semibold uppercase tracking-[0.22em] text-white/60">
                            Security
                        </p>
                        <h2 className="mt-2 text-3xl font-bold tracking-tight text-white">
                            Change password
                        </h2>
                    </div>

                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-base font-medium text-white/80">
                            Request a password change by email.
                        </p>

                        <Button
                            type="button"
                            onClick={requestPasswordChange}
                            className="rounded-full bg-white px-8 py-3.5 text-base font-bold text-black hover:bg-white/80"
                        >
                            Change Password
                        </Button>
                    </div>
                </section>

                {/* Upgrade Section (No Frame) */}
                <section className="px-2 mt-8 border-t border-white/10 pt-10 pb-8">
                    <div className="mb-6">
                        <p className="text-xs font-semibold uppercase tracking-[0.22em] text-white/60">
                            Upgrade Access
                        </p>
                        <h2 className="mt-2 text-3xl font-bold tracking-tight text-white">
                            Upgrade tier
                        </h2>
                    </div>

                    {upgradeOptions.length > 0 ? (
                        <div className="grid gap-6 lg:grid-cols-2">
                            {upgradeOptions.map((tier) => (
                                <div
                                    key={tier.id}
                                    className="rounded-[16px] border border-white/10 bg-[#141110] p-6 shadow-lg"
                                >
                                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <h3 className="text-2xl font-bold text-white">
                                                {tier.name}
                                            </h3>
                                            <p className="mt-2 text-lg font-medium text-white/70">
                                                {formatCurrency(
                                                    tier.price,
                                                    tier.currency_code,
                                                )}
                                            </p>
                                        </div>

                                        <Button
                                            asChild
                                            className="rounded-full bg-[#DB202C] px-8 py-3.5 text-base font-bold text-white hover:bg-[#c31c28]"
                                        >
                                            <Link href={tier.upgrade_url}>
                                                Upgrade
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="rounded-[14px] border border-white/10 bg-white/5 p-6 text-base font-medium text-white/80 backdrop-blur">
                            No upgrade is available right now.
                        </div>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
