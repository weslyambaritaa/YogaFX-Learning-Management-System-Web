import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import PasswordField from "@/Components/PasswordField";
import PasswordRequirementsCard from "@/Components/PasswordRequirementsCard";
import { Button } from "@/Components/ui/button";
import { PUBLIC_FORM_FIELD_CLASS } from "@/lib/publicFormStyles";
import PublicFlowLayout from "@/Layouts/PublicFlowLayout";
import { useForm } from "@inertiajs/react";
import { LoaderCircle } from "lucide-react";
import { useEffect, useState } from "react";

const FONT_FAMILY = "'Montserrat', sans-serif";
const LOADING_COUNTDOWN_SECONDS = 3;

export default function Signup({ onboarding, student }) {
    const [secondsRemaining, setSecondsRemaining] = useState(
        LOADING_COUNTDOWN_SECONDS,
    );

    const { data, setData, post, processing, errors } = useForm({
        password: "",
        password_confirmation: "",
    });

    const isLoading = secondsRemaining > 0;

    useEffect(() => {
        if (!isLoading) {
            return undefined;
        }

        const timeoutId = window.setTimeout(() => {
            setSecondsRemaining((current) =>
                Math.max(current - 1, 0),
            );
        }, 1000);

        return () => {
            window.clearTimeout(timeoutId);
        };
    }, [isLoading, secondsRemaining]);

    const submit = (event) => {
        event.preventDefault();

        post(onboarding.submit_url, {
            onFinish: () => {
                setData("password", "");
            },
        });
    };

    return (
        <PublicFlowLayout
            title="Create Password"
            heading={
                isLoading
                    ? null
                    : "Create your final YogaFX password to activate your account"
            }
            description={
                isLoading
                    ? null
                    : "Enrollment is complete. This last step activates your YogaFX account so you can sign in with your new password."
            }
        >
            {isLoading ? (
                <div
                    className="flex justify-center py-6"
                    style={{ fontFamily: FONT_FAMILY }}
                >
                    <div
                        className="w-full max-w-lg rounded-[18px] border border-white/15 bg-[#111111] px-6 py-10 text-center shadow-[0_24px_80px_rgba(0,0,0,0.45)] sm:px-8"
                        role="status"
                        aria-live="polite"
                    >
                        <div className="relative mx-auto h-24 w-24">
                            <LoaderCircle
                                className="absolute inset-0 h-24 w-24 animate-spin text-[#DB202C] motion-reduce:animate-none"
                                strokeWidth={1.8}
                                aria-hidden="true"
                            />

                            <div className="absolute inset-0 flex items-center justify-center">
                                <span
                                    key={secondsRemaining}
                                    className="text-3xl font-bold text-white"
                                >
                                    {secondsRemaining}
                                </span>
                            </div>
                        </div>

                        <p className="mt-6 text-sm font-semibold text-white">
                            Loading...
                        </p>
                    </div>
                </div>
            ) : (
                <form onSubmit={submit} className="space-y-6">
                    <div className="grid gap-5">
                        <div>
                            <InputLabel
                                htmlFor="name"
                                value="Name"
                                className="text-gray-700"
                            />

                            <input
                                id="name"
                                type="text"
                                disabled
                                value={student?.name ?? ""}
                                className="mt-2 block w-full rounded-md border border-gray-700 bg-black px-3 py-2 text-white"
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="email"
                                value="Email"
                                className="text-gray-700"
                            />

                            <input
                                id="email"
                                type="email"
                                disabled
                                value={student?.email ?? ""}
                                className="mt-2 block w-full rounded-md border border-gray-700 bg-black px-3 py-2 text-white"
                            />
                        </div>

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
                                    setData(
                                        "password",
                                        event.target.value,
                                    )
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

                        <PasswordRequirementsCard
                            password={data.password}
                        />

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
                            className="rounded-md bg-[#DB202C] px-6 text-white hover:bg-[#c01a25]"
                        >
                            Create Password and Activate Account
                        </Button>
                    </div>
                </form>
            )}
        </PublicFlowLayout>
    );
}