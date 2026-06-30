import StudentProfileForm from "@/Components/StudentProfileForm";
import TransientStatusBanner from "@/Components/TransientStatusBanner";
import { Button } from "@/Components/ui/button";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, router, useForm, usePage } from "@inertiajs/react";
import { useState } from "react";

function buildProfileFormData(user) {
    return {
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
    };
}

export default function Edit({ status }) {
    const { auth } = usePage().props;
    const user = auth.user;
    const [submitNotice, setSubmitNotice] = useState(null);
    const { data, setData, post, errors, processing, transform } = useForm(
        buildProfileFormData(user),
    );

    const submit = (event) => {
        event.preventDefault();

        transform((current) => ({
            ...current,
            _method: "patch",
        }));

        post(route("profile.update"), {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onSuccess: (page) => {
                setSubmitNotice(null);
                setData(buildProfileFormData(page.props.auth.user));
            },
            onError: () => {
                setSubmitNotice({
                    id: Date.now(),
                    tone: "error",
                    message:
                        "Profile could not be updated. Please review the highlighted fields.",
                });
            },
            onFinish: () => transform((current) => current),
        });
    };

    return (
        <AuthenticatedLayout
            studentVariant="immersive"
            studentContentClassName="pb-16"
        >
            <Head title="Profile" />

            <div className="mx-auto flex max-w-[1100px] flex-col gap-6 px-4 py-8 sm:px-6 lg:px-8">
                <TransientStatusBanner
                    message={submitNotice?.message}
                    tone={submitNotice?.tone}
                    noticeKey={submitNotice?.id}
                    onDismiss={() => setSubmitNotice(null)}
                    className="shadow-[0_10px_30px_rgba(244,63,94,0.12)]"
                />

                {status === "student-password-change-email-sent" ? (
                    <div className="rounded-[5px] border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
                        Password change instructions have been sent to your
                        email.
                    </div>
                ) : null}

                <div className="rounded-[5px] border border-white/10 bg-white/[0.04] p-5">
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h1 className="font-['Montserrat'] text-[22px] font-semibold text-white">
                                Profile
                            </h1>
                            <p className="mt-2 font-['Montserrat'] text-sm leading-7 text-white/70">
                                Keep your student profile complete so YogaFX can
                                personalize your learning path correctly.
                            </p>
                        </div>

                        <Button
                            type="button"
                            onClick={() => router.post(route("profile.password.request"))}
                            className="rounded-[5px] bg-[#DB202C] text-white hover:bg-[#c31c28]"
                        >
                            Change Password
                        </Button>
                    </div>
                </div>

                <div className="rounded-[5px] border border-white/10 bg-white/[0.04] p-6 sm:p-8">
                    <StudentProfileForm
                        data={data}
                        setData={setData}
                        errors={errors}
                        processing={processing}
                        onSubmit={submit}
                        submitLabel="Save Profile"
                        mode="profile"
                        currentProfilePhotoUrl={user.profile_photo}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
