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
const LOADING_COUNTDOWN_SECONDS = 5;

function SignupLoadingOverlay({ secondsRemaining, isSubmitting = false }) {
    return (
        <div
            className="fixed inset-0 z-[9999] flex min-h-[100dvh] w-full items-center justify-center overflow-y-auto bg-black px-4 py-8 text-white sm:px-6"
            style={{ fontFamily: FONT_FAMILY }}
            role="status"
            aria-live="polite"
            aria-label={
                isSubmitting
                    ? "Opening your dashboard"
                    : `Signup page will open in ${secondsRemaining} seconds`
            }
        >
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-0"
                style={{
                    backgroundImage:
                        "radial-gradient(circle at 50% 45%, rgba(219,32,44,0.16), transparent 34%), radial-gradient(circle at 85% 90%, rgba(219,32,44,0.10), transparent 28%)",
                }}
            />

            <div className="relative z-10 flex min-h-[410px] w-full max-w-lg flex-col items-center justify-center rounded-[18px] border border-white/15 bg-[#111111] px-6 py-10 text-center shadow-[0_24px_80px_rgba(0,0,0,0.55)] sm:min-h-[450px] sm:px-10">
                <img
                    src="https://yogafx.b-cdn.net/content/Logo%20YogAFX.png"
                    alt="YogaFX"
                    className="mb-8 h-16 w-auto object-contain sm:h-20"
                />

                <div className="relative h-24 w-24 shrink-0">
                    <LoaderCircle
                        className="absolute inset-0 h-24 w-24 animate-spin text-[#DB202C] drop-shadow-[0_0_10px_rgba(219,32,44,0.85)] motion-reduce:animate-none"
                        strokeWidth={2.4}
                        aria-hidden="true"
                    />

                    {!isSubmitting ? (
                        <div className="absolute inset-0 flex items-center justify-center">
                            <span
                                key={secondsRemaining}
                                className="text-3xl font-bold leading-none text-white"
                            >
                                {secondsRemaining}
                            </span>
                        </div>
                    ) : null}
                </div>

                <p className="mt-6 text-sm font-bold text-white">
                    {isSubmitting ? "Opening Dashboard..." : "Loading..."}
                </p>
            </div>
        </div>
    );
}

export default function Signup({ onboarding, student }) {
    /*
     * Flow:
     *
     * initial-loading
     *      ↓ 5 seconds
     * form
     *      ↓ click Access Your Dashboard Now
     * final-loading
     *      ↓ 5 seconds
     * submitting
     *      ↓
     * Student Dashboard
     */
    const [flowPhase, setFlowPhase] = useState("initial-loading");

    const [secondsRemaining, setSecondsRemaining] = useState(
        LOADING_COUNTDOWN_SECONDS,
    );

    const { data, setData, post, processing, errors } = useForm({
        password: "",
        password_confirmation: "",
        remember: false,
    });

    const studentName = String(
        student?.name ?? onboarding?.student?.name ?? "Student",
    ).trim();

    const packageTitle =
        onboarding?.package?.title ?? onboarding?.access_tier?.name ?? "Course";

    const isInitialLoading = flowPhase === "initial-loading";

    const isFinalLoading = flowPhase === "final-loading";

    const isSubmitting = flowPhase === "submitting";

    const isCountingDown = isInitialLoading || isFinalLoading;

    const showLoadingOverlay = isCountingDown || isSubmitting;

    const showForm = flowPhase === "form";

    const passwordFilled = data.password.trim().length > 0;

    const passwordConfirmationFilled =
        data.password_confirmation.trim().length > 0;

    const passwordMatches =
        passwordFilled &&
        passwordConfirmationFilled &&
        data.password === data.password_confirmation;

    const passwordInputClassName = [
        "bg-white/10 font-bold text-white placeholder:font-medium placeholder:text-white/30 transition-colors duration-200",
        errors.password
            ? "!border-red-500 focus:!border-red-500 focus-visible:!border-red-500 focus-visible:ring-red-500/30"
            : passwordFilled
              ? "!border-emerald-500 focus:!border-emerald-500 focus-visible:!border-emerald-500 focus-visible:ring-emerald-500/30"
              : "!border-white/20 focus:!border-white/50",
    ].join(" ");

    const passwordConfirmationInputClassName = [
        "bg-white/10 font-bold text-white placeholder:font-medium placeholder:text-white/30 transition-colors duration-200",
        errors.password_confirmation ||
        (passwordConfirmationFilled && !passwordMatches)
            ? "!border-red-500 focus:!border-red-500 focus-visible:!border-red-500 focus-visible:ring-red-500/30"
            : passwordMatches
              ? "!border-emerald-500 focus:!border-emerald-500 focus-visible:!border-emerald-500 focus-visible:ring-emerald-500/30"
              : "!border-white/20 focus:!border-white/50",
    ].join(" ");

    /*
     * Countdown pertama:
     * Page dibuka -> 5, 4, 3, 2, 1 -> form.
     *
     * Countdown kedua:
     * Button ditekan -> 5, 4, 3, 2, 1 ->
     * submit -> dashboard.
     */
    useEffect(() => {
        if (!isCountingDown) {
            return undefined;
        }

        const timeoutId = window.setTimeout(() => {
            if (secondsRemaining <= 1) {
                if (isInitialLoading) {
                    setSecondsRemaining(0);
                    setFlowPhase("form");
                    return;
                }

                if (isFinalLoading) {
                    /*
                     * Ubah phase terlebih dahulu supaya POST
                     * tidak dapat dipanggil dua kali oleh effect.
                     */
                    setSecondsRemaining(0);
                    setFlowPhase("submitting");

                    post(onboarding.submit_url, {
                        preserveScroll: true,

                        onError: () => {
                            /*
                             * Jika password ditolak backend,
                             * kembali ke form dan tampilkan error.
                             */
                            setFlowPhase("form");
                            setSecondsRemaining(0);
                        },
                    });
                }

                return;
            }

            setSecondsRemaining((current) => Math.max(current - 1, 1));
        }, 1000);

        return () => {
            window.clearTimeout(timeoutId);
        };
    }, [
        isCountingDown,
        isInitialLoading,
        isFinalLoading,
        secondsRemaining,
        onboarding.submit_url,
        post,
    ]);

    /*
     * Lock body selama fullscreen loading.
     */
    useEffect(() => {
        if (!showLoadingOverlay) {
            return undefined;
        }

        const previousBodyOverflow = document.body.style.overflow;

        const previousHtmlOverflow = document.documentElement.style.overflow;

        document.body.style.overflow = "hidden";
        document.documentElement.style.overflow = "hidden";

        return () => {
            document.body.style.overflow = previousBodyOverflow;

            document.documentElement.style.overflow = previousHtmlOverflow;
        };
    }, [showLoadingOverlay]);

    /*
     * Jangan langsung POST ketika button ditekan.
     *
     * Jalankan countdown kedua selama 5 detik,
     * kemudian POST dilakukan oleh effect di atas.
     */
    const submit = (event) => {
        event.preventDefault();

        if (processing || flowPhase !== "form") {
            return;
        }

        setSecondsRemaining(LOADING_COUNTDOWN_SECONDS);

        setFlowPhase("final-loading");
    };

    return (
        <>
            <PublicFlowLayout
                title="Create Password"
                progressStep={3}
                heading={
                    showForm ? (
                        <>
                            Welcome {studentName} to Your {packageTitle}{" "}
                            Pre-Course Preparation
                        </>
                    ) : null
                }
                description={
                    showForm ? (
                        <span
                            className="block font-medium text-white"
                            style={{
                                fontFamily: FONT_FAMILY,
                            }}
                        >
                            <span className="block">
                                Your Final Step Activates Your Yoga
                                <span className="text-[#DB202C]">FX</span>{" "}
                                Dashboard Access
                            </span>

                            <span className="mt-1 block italic">
                                Please Sign In And Set Your Password
                            </span>
                        </span>
                    ) : null
                }
            >
                {showForm ? (
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
                                    className="mt-2 block w-full rounded-[5px] border border-emerald-500 bg-white/10 px-3 py-2 font-bold text-white opacity-100 transition-colors duration-200 focus:border-emerald-500 focus:ring-emerald-500/30"
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
                                    className="mt-2 block w-full rounded-[5px] border border-emerald-500 bg-white/10 px-3 py-2 font-bold text-white opacity-100 transition-colors duration-200 focus:border-emerald-500 focus:ring-emerald-500/30"
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
                                    inputClassName={passwordInputClassName}
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
                                    inputClassName={
                                        passwordConfirmationInputClassName
                                    }
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

                                {/* Remember Me */}
                                <label
                                    htmlFor="remember"
                                    className="mt-4 flex w-fit cursor-pointer items-center gap-3 text-sm font-medium text-white"
                                    style={{
                                        fontFamily: FONT_FAMILY,
                                    }}
                                >
                                    <input
                                        id="remember"
                                        type="checkbox"
                                        checked={Boolean(data.remember)}
                                        onChange={(event) =>
                                            setData(
                                                "remember",
                                                event.target.checked,
                                            )
                                        }
                                        className="size-5 cursor-pointer rounded border-2 border-white/70 bg-transparent accent-emerald-500 focus:ring-2 focus:ring-emerald-500/40"
                                    />

                                    <span>Remember Me</span>
                                </label>
                            </div>
                        </div>

                        <div className="flex flex-col items-center gap-3 pt-2 text-center">
                            <Button
                                type="submit"
                                disabled={processing}
                                className="min-h-[64px] w-full max-w-[360px] rounded-[8px] bg-[#DB202C] px-8 py-5 text-xl font-bold italic text-white shadow-[0_12px_35px_rgba(219,32,44,0.3)] transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#c01a25] focus:outline-none focus:ring-4 focus:ring-[#DB202C]/35 disabled:cursor-not-allowed disabled:opacity-60"
                                style={{
                                    fontFamily: FONT_FAMILY,
                                }}
                            >
                                Access Your Dashboard Now
                            </Button>
                        </div>
                    </form>
                ) : null}
            </PublicFlowLayout>

            {showLoadingOverlay ? (
                <SignupLoadingOverlay
                    secondsRemaining={secondsRemaining}
                    isSubmitting={isSubmitting}
                />
            ) : null}
        </>
    );
}
