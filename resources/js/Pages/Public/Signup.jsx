import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import PasswordField from "@/Components/PasswordField";
import PasswordRequirementsCard from "@/Components/PasswordRequirementsCard";
import { Button } from "@/Components/ui/button";
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
            heading="Create your final YogaFX password to activate your account"
            description="Enrollment is complete. This last step activates your YogaFX account so you can sign in with your new password."
        >
            <form onSubmit={submit} className="space-y-6">
                <div className="grid gap-5">
                    {/* Name — disabled, style sama dengan Checkout */}
                    <div>
                        <InputLabel
                            htmlFor="name"
                            value="Name"
                            className="text-gray-700"
                        />
                        <input
                            id="name"
                            disabled
                            value={student.name}
                            className="mt-2 block w-full rounded-md border border-gray-700 bg-black px-3 py-2 text-white"
                        />
                    </div>

                    {/* Email — disabled, style sama dengan Checkout */}
                    <div>
                        <InputLabel
                            htmlFor="email"
                            value="Email"
                            className="text-gray-700"
                        />
                        <input
                            id="email"
                            disabled
                            value={student.email}
                            className="mt-2 block w-full rounded-md border border-gray-700 bg-black px-3 py-2 text-white"
                        />
                    </div>

                    {/* Password */}
                    <div>
                        <InputLabel
                            htmlFor="password"
                            value="Create Password"
                            className="text-white/80"
                        />
                        <PasswordField
                            id="password"
                            value={data.password}
                            className="mt-2 block w-full"
                            inputClassName="border-white/20 bg-white/10 text-white placeholder:text-white/30"
                            onChange={(event) =>
                                setData("password", event.target.value)
                            }
                            buttonClassName="text-white/60 hover:text-white"
                            autoComplete="new-password"
                            required
                        />
                        <InputError
                            className="mt-2 text-red-400"
                            message={errors.password}
                        />
                    </div>

                    <PasswordRequirementsCard password={data.password} />

                    {/* Confirm Password */}
                    <div>
                        <InputLabel
                            htmlFor="password_confirmation"
                            value="Confirm Password"
                            className="text-white/80"
                        />
                        <PasswordField
                            id="password_confirmation"
                            value={data.password_confirmation}
                            className="mt-2 block w-full"
                            inputClassName="border-white/20 bg-white/10 text-white placeholder:text-white/30"
                            onChange={(event) =>
                                setData(
                                    "password_confirmation",
                                    event.target.value,
                                )
                            }
                            buttonClassName="text-white/60 hover:text-white"
                            autoComplete="new-password"
                            required
                        />
                        <InputError
                            className="mt-2 text-red-400"
                            message={errors.password_confirmation}
                        />
                    </div>
                </div>

                {/* Footer + tombol */}
                <div className="flex justify-end">
                    <Button
                        type="submit"
                        disabled={processing}
                        className="rounded-md bg-[#DB202C] px-6 text-white hover:bg-[#c01a25]"
                    >
                        Create Password and Activate Account
                    </Button>
                </div>
            </form>
        </PublicFlowLayout>
    );
}
