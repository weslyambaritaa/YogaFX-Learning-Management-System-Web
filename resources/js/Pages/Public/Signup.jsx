import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import PasswordField from "@/Components/PasswordField";
import PasswordRequirementsCard from "@/Components/PasswordRequirementsCard";
import { Button } from "@/Components/ui/button";
import PublicFlowLayout from "@/Layouts/PublicFlowLayout";
import { useForm } from "@inertiajs/react";
import { LoaderCircle } from "lucide-react";
import { useEffect, useState } from "react";

const FONT_FAMILY = "'Montserrat', sans-serif";
const LOADING_COUNTDOWN_SECONDS = 3;

function SignupLoadingOverlay({ secondsRemaining }) {
    return (
        <div
            className="
                fixed
                inset-0
                z-[9999]
                flex
                min-h-[100dvh]
                w-full
                items-center
                justify-center
                overflow-y-auto
                bg-black
                px-4
                py-6
                text-white
                sm:px-6
            "
            style={{ fontFamily: FONT_FAMILY }}
            role="status"
            aria-live="polite"
            aria-label={`Signup form will open in ${secondsRemaining} seconds`}
        >
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-0"
                style={{
                    backgroundImage:
                        "radial-gradient(circle at 50% 45%, rgba(219,32,44,0.16), transparent 34%), radial-gradient(circle at 85% 90%, rgba(219,32,44,0.10), transparent 28%)",
                }}
            />

            <div
                className="
                    relative
                    z-10
                    flex
                    min-h-[410px]
                    w-full
                    max-w-lg
                    flex-col
                    items-center
                    justify-center
                    rounded-[18px]
                    border
                    border-white/15
                    bg-[#111111]
                    px-6
                    py-8
                    text-center
                    shadow-[0_24px_80px_rgba(0,0,0,0.55)]
                    sm:min-h-[450px]
                    sm:px-10
                    sm:py-10
                "
            >
                <div className="relative h-24 w-24 shrink-0">
                    <LoaderCircle
                        className="
                            absolute
                            inset-0
                            h-24
                            w-24
                            animate-spin
                            text-[#DB202C]
                            drop-shadow-[0_0_10px_rgba(219,32,44,0.85)]
                            motion-reduce:animate-none
                        "
                        strokeWidth={2.4}
                        aria-hidden="true"
                    />

                    <div className="absolute inset-0 flex items-center justify-center">
                        <span
                            key={secondsRemaining}
                            className="text-3xl font-bold leading-none text-white"
                        >
                            {secondsRemaining}
                        </span>
                    </div>
                </div>

                <p className="mt-6 text-sm font-bold text-white">
                    Loading...
                </p>
            </div>
        </div>
    );
}

export default function Signup({ onboarding, student }) {
    const [secondsRemaining, setSecondsRemaining] = useState(
        LOADING_COUNTDOWN_SECONDS,
    );

    const { data, setData, post, processing, errors } = useForm({
        password: "",
        password_confirmation: "",
    });

    const studentName = String(
        student?.name ?? onboarding?.student?.name ?? "Student",
    ).trim();

    const packageTitle =
        onboarding?.package?.title ??
        onboarding?.access_tier?.name ??
        "Course";

    const isLoading = secondsRemaining > 0;

    useEffect(() => {
        if (!isLoading) {
            return undefined;
        }

        const timeoutId = window.setTimeout(() => {
            setSecondsRemaining((current) => Math.max(current - 1, 0));
        }, 1000);

        return () => {
            window.clearTimeout(timeoutId);
        };
    }, [isLoading, secondsRemaining]);

    useEffect(() => {
        if (!isLoading) {
            return undefined;
        }

        const previousBodyOverflow = document.body.style.overflow;
        const previousHtmlOverflow =
            document.documentElement.style.overflow;

        document.body.style.overflow = "hidden";
        document.documentElement.style.overflow = "hidden";

        return () => {
            document.body.style.overflow = previousBodyOverflow;
            document.documentElement.style.overflow =
                previousHtmlOverflow;
        };
    }, [isLoading]);

    const submit = (event) => {
        event.preventDefault();

        post(onboarding.submit_url, {
            onFinish: () => {
                setData("password", "");
            },
        });
    };

    return (
        <>
            <PublicFlowLayout
                title="Create Password"
                progressStep={3}
                heading={
                    isLoading
                        ? null
                        : `Welcome ${studentName} to Your ${packageTitle} Course Preparation`
                }
                description={
                    isLoading
                        ? null
                        : "This last step activates your YogaFX account so you can sign in with your new password."
                }
            >
                {!isLoading ? (
                    <form onSubmit={submit} className="space-y-6">
                        <div className="grid gap-5">
                            <div>
                                <InputLabel
                                    htmlFor="name"
                                    value="Name"
                                    className="text-white/80"
                                />

                                <input
                                    id="name"
                                    type="text"
                                    readOnly
                                    value={student?.name ?? ""}
                                    className="mt-2 block w-full rounded-[5px] border border-white/20 bg-white/10 px-3 py-2 text-white opacity-100"
                                />
                            </div>

                            <div>
                                <InputLabel
                                    htmlFor="email"
                                    value="Email"
                                    className="text-white/80"
                                />

                                <input
                                    id="email"
                                    type="email"
                                    readOnly
                                    value={student?.email ?? ""}
                                    className="mt-2 block w-full rounded-[5px] border border-white/20 bg-white/10 px-3 py-2 text-white opacity-100"
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

                        <div className="flex flex-col items-center gap-3 text-center">
                            <p
                                className="text-center text-sm font-medium italic leading-relaxed text-white/80"
                                style={{
                                    fontFamily: FONT_FAMILY,
                                }}
                            >
                                For Easy Access To Your Course Materials
                            </p>

                            <Button
                                type="submit"
                                disabled={processing}
                                className="min-h-[56px] rounded-none bg-[#DB202C] px-8 py-4 text-base font-bold italic text-white hover:bg-[#c01a25]"
                            >
                                Access Dashboard Now
                            </Button>
                        </div>
                    </form>
                ) : null}
            </PublicFlowLayout>

            {isLoading ? (
                <SignupLoadingOverlay
                    secondsRemaining={secondsRemaining}
                />
            ) : null}
        </>
    );
}