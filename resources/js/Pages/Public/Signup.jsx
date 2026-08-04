import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import YogaFXText from "@/Components/YogaFXText";
import PasswordField from "@/Components/PasswordField";
import PasswordRequirementsCard from "@/Components/PasswordRequirementsCard";
import { Button } from "@/Components/ui/button";
import { PUBLIC_FORM_FIELD_CLASS } from "@/lib/publicFormStyles";
import PublicFlowLayout from "@/Layouts/PublicFlowLayout";
import { useForm } from "@inertiajs/react";

export default function Signup({ onboarding, student }) {
    const { data, setData, post, processing, errors } = useForm({
        password: "",
        password_confirmation: "",
    });

    const submit = (event) => {
        event.preventDefault();
        post(onboarding.submit_url, {
            onFinish: () => setData("password", ""),
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
            <form onSubmit={submit} className="space-y-6 text-[#FFFFFF]">
                <div className="grid gap-5">
                    {/* Name — disabled, style sama dengan Checkout */}
                    <div>
                        <InputLabel
                            htmlFor="name"
                            value="Name"
                            className="!text-[#FFFFFF]"
                        />
                        <input
                            id="name"
                            disabled
                            value={student.name}
                            className={`mt-2 block w-full ${PUBLIC_FORM_FIELD_CLASS} cursor-not-allowed !text-[#FFFFFF] disabled:opacity-100`}
                        />
                    </div>

                    {/* Email — disabled, style sama dengan Checkout */}
                    <div>
                        <InputLabel
                            htmlFor="email"
                            value="Email"
                            className="!text-[#FFFFFF]"
                        />
                        <input
                            id="email"
                            disabled
                            value={student.email}
                            className={`mt-2 block w-full ${PUBLIC_FORM_FIELD_CLASS} cursor-not-allowed !text-[#FFFFFF] disabled:opacity-100`}
                        />
                    </div>

                    {/* Password */}
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
                                setData("password", event.target.value)
                            }
                            buttonClassName="text-white/70 hover:text-white"
                            autoComplete="new-password"
                            required
                        />
                        <InputError
                            className="mt-2 !text-[#FFFFFF]"
                            message={errors.password}
                        />
                    </div>

                    <div className="text-[#FFFFFF] [&_div]:!text-[#FFFFFF] [&_p]:!text-[#FFFFFF] [&_li]:!text-[#FFFFFF] [&_span]:!text-[#FFFFFF] [&_h2]:!text-[#FFFFFF] [&_h3]:!text-[#FFFFFF]">
    <PasswordRequirementsCard password={data.password} />
</div>

                    {/* Confirm Password */}
                    <div>
                        <InputLabel
                            htmlFor="password_confirmation"
                            value="Confirm Password"
                            className="!text-[#FFFFFF]"
                        />
                        <PasswordField
                            id="password"
                            value={data.password}
                            className="mt-2 block w-full"
                            inputClassName={`${PUBLIC_FORM_FIELD_CLASS} !text-[#FFFFFF]`}
                            onChange={(event) =>
                                setData("password", event.target.value)
                            }
                            buttonClassName="text-white/70 hover:text-white"
                            autoComplete="new-password"
                            required
                        />
                        <InputError
                            className="mt-2 !text-[#FFFFFF]"
                            message={errors.password_confirmation}
                        />
                    </div>
                </div>

                {/* Footer + tombol */}
                <div className="flex justify-end">
                    <Button
                        type="submit"
                        disabled={processing}
                        className="rounded-md bg-[#DB202C] px-6 !text-[#FFFFFF] hover:bg-[#c01a25]"
                    >
                        Create Password and Activate Account
                    </Button>
                </div>
            </form>
        </PublicFlowLayout>
    );
}
