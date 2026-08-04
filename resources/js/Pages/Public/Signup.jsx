import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import PasswordField from "@/Components/PasswordField";
import PasswordRequirementsCard from "@/Components/PasswordRequirementsCard";
import YogaFXText from "@/Components/YogaFXText";
import { Button } from "@/Components/ui/button";
import PublicFlowLayout from "@/Layouts/PublicFlowLayout";
import { PUBLIC_FORM_FIELD_CLASS } from "@/lib/publicFormStyles";
import { useForm } from "@inertiajs/react";

export default function Signup({
    onboarding,
    student,
}) {
    const {
        data,
        setData,
        post,
        processing,
        errors,
    } = useForm({
        password: "",
        password_confirmation: "",
    });

    const submit = (event) => {
        event.preventDefault();

        post(onboarding.submit_url, {
            preserveScroll: true,
        });
    };

    return (
        <PublicFlowLayout
            title="Create Password"
            heading={
                <span className="text-[#FFFFFF]">
                    <YogaFXText
                        text="Create your final YogaFX password to activate your account"
                        fxClassName="!text-[#DB202C]"
                    />
                </span>
            }
            description={
                <span className="text-[#FFFFFF]">
                    <YogaFXText
                        text="Enrollment is complete. This last step activates your YogaFX account so you can sign in with your new password."
                        fxClassName="!text-[#DB202C]"
                    />
                </span>
            }
        >
            <form
                onSubmit={submit}
                className="space-y-6 text-[#FFFFFF]"
            >
                <div className="grid gap-5">
                    {/* Name */}
                    <div>
                        <InputLabel
                            htmlFor="name"
                            value="Name"
                            className="!text-[#FFFFFF]"
                        />

                        <input
                            id="name"
                            type="text"
                            disabled
                            value={student.name ?? ""}
                            className={`mt-2 block w-full ${PUBLIC_FORM_FIELD_CLASS} cursor-not-allowed !text-[#FFFFFF] disabled:opacity-100`}
                        />
                    </div>

                    {/* Email */}
                    <div>
                        <InputLabel
                            htmlFor="email"
                            value="Email"
                            className="!text-[#FFFFFF]"
                        />

                        <input
                            id="email"
                            type="email"
                            disabled
                            value={student.email ?? ""}
                            className={`mt-2 block w-full ${PUBLIC_FORM_FIELD_CLASS} cursor-not-allowed !text-[#FFFFFF] disabled:opacity-100`}
                        />
                    </div>

                    {/* Create Password */}
                    <div>
                        <InputLabel
                            htmlFor="password"
                            value="Create Password"
                            className="!text-[#FFFFFF]"
                        />

                        <PasswordField
                            id="password"
                            value={data.password}
                            className="mt-2 block w-full"
                            inputClassName={`${PUBLIC_FORM_FIELD_CLASS} !text-[#FFFFFF]`}
                            onChange={(event) =>
                                setData(
                                    "password",
                                    event.target.value,
                                )
                            }
                            buttonClassName="text-white/70 hover:text-white"
                            autoComplete="new-password"
                            required
                        />

                        <InputError
                            className="mt-2 !text-[#ffb4a8]"
                            message={errors.password}
                        />
                    </div>

                    {/* Password Requirements */}
                    <div className="text-[#000000] [&_div]:!text-[#000000] [&_p]:!text-[#000000] [&_li]:!text-[#000000] [&_span]:!text-[#000000] [&_h2]:!text-[#000000] [&_h3]:!text-[#000000]">
                        <PasswordRequirementsCard
                            password={data.password}
                        />
                    </div>

                    {/* Confirm Password */}
                    <div>
                        <InputLabel
                            htmlFor="password_confirmation"
                            value="Confirm Password"
                            className="!text-[#FFFFFF]"
                        />

                        <PasswordField
                            id="password_confirmation"
                            value={
                                data.password_confirmation
                            }
                            className="mt-2 block w-full"
                            inputClassName={`${PUBLIC_FORM_FIELD_CLASS} !text-[#FFFFFF]`}
                            onChange={(event) =>
                                setData(
                                    "password_confirmation",
                                    event.target.value,
                                )
                            }
                            buttonClassName="text-white/70 hover:text-white"
                            autoComplete="new-password"
                            required
                        />

                        <InputError
                            className="mt-2 !text-[#ffb4a8]"
                            message={
                                errors.password_confirmation
                            }
                        />
                    </div>
                </div>

                <div className="flex justify-end">
                    <Button
                        type="submit"
                        disabled={processing}
                        className="rounded-md bg-[#DB202C] px-6 !text-[#FFFFFF] hover:bg-[#c01a25]"
                    >
                        {processing
                            ? "Activating Account..."
                            : "Create Password and Activate Account"}
                    </Button>
                </div>
            </form>
        </PublicFlowLayout>
    );
}