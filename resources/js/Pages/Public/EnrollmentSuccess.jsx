import { Button } from "@/Components/ui/button";
import PublicFlowLayout from "@/Layouts/PublicFlowLayout";
import { Link } from "@inertiajs/react";
import { Check, LoaderCircle } from "lucide-react";
import { useEffect, useMemo, useState } from "react";

const FONT_FAMILY = "'Montserrat', sans-serif";
const COUNTDOWN_SECONDS = 5;

export default function EnrollmentSuccess({ onboarding }) {
    const [phase, setPhase] = useState("loading");
    const [secondsRemaining, setSecondsRemaining] = useState(
        COUNTDOWN_SECONDS,
    );

    useEffect(() => {
        if (phase !== "loading") {
            return undefined;
        }

        const timeoutId = window.setTimeout(() => {
            if (secondsRemaining <= 1) {
                setPhase("success");
                return;
            }

            setSecondsRemaining((current) => current - 1);
        }, 1000);

        return () => {
            window.clearTimeout(timeoutId);
        };
    }, [phase, secondsRemaining]);

    const progressPercentage = useMemo(() => {
        if (COUNTDOWN_SECONDS <= 1) {
            return 100;
        }

        return (
            ((COUNTDOWN_SECONDS - secondsRemaining) /
                (COUNTDOWN_SECONDS - 1)) *
            100
        );
    }, [secondsRemaining]);

    const isLoading = phase === "loading";

    return (
        <PublicFlowLayout
            title={
                isLoading
                    ? "Enrollment Submitted"
                    : "Enrollment Success"
            }
            eyebrow={
                isLoading
                    ? "Enrollment Submitted"
                    : "Application Successful"
            }
            heading={
                isLoading
                    ? "Preparing your next step"
                    : "Congratulations on Your Enrollment Application Success"
            }
        >
            <div
                className="flex justify-center pb-6 pt-0"
                style={{ fontFamily: FONT_FAMILY }}
            >
                <div className="w-full max-w-xl text-center">
                    {isLoading ? (
                        <div
                            className="rounded-[18px] border border-white/15 bg-[#111111] px-6 py-8 shadow-[0_24px_80px_rgba(0,0,0,0.45)] sm:px-8"
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

                            <p className="mt-6 text-xs font-semibold uppercase tracking-[0.2em] text-[#ffb8bf]">
                                Enrollment successfully submitted
                            </p>

                            <h2 className="mt-3 text-2xl font-semibold text-white">
                                Preparing your account setup
                            </h2>

                            <p className="mx-auto mt-3 max-w-md text-sm leading-6 text-white/70">
                                Your enrollment information has been saved.
                                We are preparing the final account setup
                                step.
                            </p>

                            <p className="mt-5 text-sm font-semibold text-white">
                                Continuing in{" "}
                                <span className="text-[#DB202C]">
                                    {secondsRemaining}
                                </span>{" "}
                                {secondsRemaining === 1
                                    ? "second"
                                    : "seconds"}
                            </p>

                            <div className="mt-6 overflow-hidden rounded-full bg-white/10">
                                <div
                                    className="h-1.5 rounded-full bg-[#DB202C] transition-[width] duration-1000 ease-linear motion-reduce:transition-none"
                                    style={{
                                        width: `${progressPercentage}%`,
                                    }}
                                />
                            </div>
                        </div>
                    ) : (
                        <div className="rounded-[18px] border border-white/15 bg-[#111111] px-6 py-8 shadow-[0_24px_80px_rgba(0,0,0,0.45)] sm:px-8">
                            <div className="mx-auto flex h-24 w-24 items-center justify-center rounded-full bg-emerald-500">
                                <Check
                                    className="h-14 w-14 text-white"
                                    strokeWidth={3.2}
                                    aria-hidden="true"
                                />
                            </div>

                            <p className="mt-7 text-xs font-semibold uppercase tracking-[0.2em] text-[#ffb8bf]">
                                Enrollment application successful
                            </p>

                            <h2 className="mx-auto mt-3 max-w-lg text-2xl font-bold leading-tight text-white sm:text-3xl">
                                Congratulations on Your Enrollment
                                Application Success
                            </h2>

                            <p className="mx-auto mt-4 max-w-md text-sm leading-6 text-white/70">
                                Your enrollment details have been
                                successfully recorded. Continue to create
                                your password and access your next
                                preparation step.
                            </p>

                            <Button
                                asChild
                                className="mt-7 h-auto w-full max-w-full whitespace-normal rounded-[5px] bg-[#DB202C] px-6 py-4 text-center text-sm font-bold leading-5 text-white hover:bg-[#c31c28] sm:w-auto sm:min-w-[320px]"
                            >
                                <Link
                                    href={onboarding.continue_url}
                                    className="inline-flex items-center justify-center"
                                >
                                    Click here to access your masterclass
                                    pre course preparation
                                </Link>
                            </Button>
                        </div>
                    )}
                </div>
            </div>
        </PublicFlowLayout>
    );
}